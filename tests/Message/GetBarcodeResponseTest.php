<?php

declare(strict_types=1);

use Omniship\Common\Enum\LabelFormat;
use Omniship\Navlungo\Message\GetBarcodeRequest;
use Omniship\Navlungo\Message\GetBarcodeResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoSendBarcode(array $responsePayload, array $params = []): GetBarcodeResponse
{
    $request = new GetBarcodeRequest(
        createSequencedMockHttpClient([navlungoAuthOk(), $responsePayload]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(array_merge([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'postNumber' => 'MFYS29970',
        'barcodeType' => 'pdf',
    ], $params));

    /** @var GetBarcodeResponse $response */
    $response = $request->send();

    return $response;
}

it('fetches the barcode and decodes the base64 pdf into a Label', function () {
    $binary = "%PDF-1.4 fake label bytes";
    $captured = [];
    $request = new GetBarcodeRequest(
        createSequencedMockHttpClient([
            navlungoAuthOk(),
            [
                'body' => json_encode([
                    'status' => true,
                    'message' => 'Barkod başarıyla oluşturulmuştur.',
                    'data' => [
                        'barcode_type' => 'pdf',
                        'barcode_url' => 'https://domestic-qa-barcode.navlungo.com/MFYS29970.pdf',
                        'barcode_pdf' => base64_encode($binary),
                        'barcode_html' => '',
                    ],
                ], JSON_THROW_ON_ERROR),
                'status' => 201,
            ],
        ], $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'postNumber' => 'MFYS29970',
    ]);

    $response = $request->send();
    $label = $response->getLabel();

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getBarcodeType())->toBe('pdf')
        ->and($response->getBarcodeUrl())->toBe('https://domestic-qa-barcode.navlungo.com/MFYS29970.pdf')
        ->and($label)->not->toBeNull()
        ->and($label->format)->toBe(LabelFormat::PDF)
        ->and($label->content)->toBe($binary)
        ->and($label->trackingNumber)->toBe('MFYS29970')
        ->and($captured[1]->getUri()->getPath())->toEndWith('/v2.1/barcode/getBarcode')
        ->and(json_decode((string) $captured[1]->getBody(), true))
        ->toBe(['post_number' => 'MFYS29970', 'barcode_type' => 'pdf']);
});

it('returns a null label when the API only provides a url', function () {
    $response = navlungoSendBarcode([
        'body' => json_encode([
            'status' => true,
            'data' => [
                'barcode_type' => 'zpl-pure',
                'barcode_url' => 'https://domestic-qa-barcode.navlungo.com/MFYS29970.zpl',
                'barcode_pdf' => '',
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 201,
    ]);

    expect($response->getLabel())->toBeNull()
        ->and($response->getBarcodeUrl())->toBe('https://domestic-qa-barcode.navlungo.com/MFYS29970.zpl');
});

it('reports barcode failures with the API error', function () {
    $response = navlungoSendBarcode([
        'body' => json_encode([
            'status' => false,
            'error' => 'Bu gönderi taşıyıcıda oluşmadığı için barkod alınamaz.',
        ], JSON_THROW_ON_ERROR),
        'status' => 404,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Bu gönderi taşıyıcıda oluşmadığı için barkod alınamaz.')
        ->and($response->getCode())->toBe('404');
});
