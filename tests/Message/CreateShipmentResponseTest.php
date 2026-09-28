<?php

declare(strict_types=1);

use Omniship\Navlungo\Message\CreateShipmentRequest;
use Omniship\Navlungo\Message\CreateShipmentResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoSendCreateResponse(array $responsePayload): CreateShipmentResponse
{
    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient([navlungoAuthOk(), $responsePayload]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'senderAddressId' => 56027,
        'shipTo' => new Omniship\Common\Address(
            name: 'Test',
            street1: 'Street 1',
            city: 'Bursa',
            district: 'Nilüfer',
            phone: '5551234567',
        ),
        'packages' => [new Omniship\Common\Package(weight: 1.0)],
    ]);

    /** @var CreateShipmentResponse $response */
    $response = $request->send();

    return $response;
}

it('exposes the post number, tracking link and barcode url on success', function () {
    $response = navlungoSendCreateResponse(navlungoCreateOk());

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getShipmentId())->toBe('MFYS29970')
        ->and($response->getTrackingNumber())->toBe('MFYS29970')
        ->and($response->getBarcode())->toBeNull()
        ->and($response->getLabel())->toBeNull()
        ->and($response->getReferenceId())->toBe('OMN-1234567890')
        ->and($response->getTrackingLink())->toBe('https://domestic-track.navlungo.com/check/MFYS29970')
        ->and($response->getBarcodeUrl())->toBe('https://domestic-qa-barcode.navlungo.com/MFYS29970.pdf')
        ->and($response->getCarrierName())->toBe('Sürat Kargo')
        ->and($response->getCode())->toBe('201');
});

it('reads the live data-array create shape with the barcode url key', function () {
    // The QA API answers with `data: [ {...} ]` and names the label URL
    // `barcode`, while the docs show a flat object with `barcode_url`.
    $response = navlungoSendCreateResponse([
        'body' => json_encode([
            'status' => true,
            'message' => 'Gönderiniz, cüzdan bakiyenizin yeterli olması durumunda başarılı bir şekilde oluşturulacaktır.',
            'data' => [
                [
                    'post_number' => '5TV84M7G7DPO',
                    'reference_id' => 'OMN-1234567890',
                    'tracking_url' => 'https://domestic-track-qa.navlungo.com/check/5TV84M7G7DPO-dev',
                    'barcode' => 'https://domestic-qa-barcode.navlungo.com/5TV84M7G7DPO.pdf',
                    'post' => [
                        'carrier_id' => 9,
                        'carrier_name' => 'Sürat Kargo',
                        'post_type' => 2,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 201,
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getShipmentId())->toBe('5TV84M7G7DPO')
        ->and($response->getTrackingLink())->toBe('https://domestic-track-qa.navlungo.com/check/5TV84M7G7DPO-dev')
        ->and($response->getBarcodeUrl())->toBe('https://domestic-qa-barcode.navlungo.com/5TV84M7G7DPO.pdf')
        ->and($response->getCarrierName())->toBe('Sürat Kargo');
});

it('flattens validation errors into a merchant-readable message', function () {
    $response = navlungoSendCreateResponse([
        'body' => json_encode([
            'message' => 'Doğrulama Hatası',
            'status' => false,
            'error' => [
                'posts.0.reference_id' => ['Bu gönderi numarası zaten mevcuttur.'],
                'posts.0.sender.addressId' => ['Gönderici adres no alanı zorunludur.'],
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 422,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('422')
        ->and($response->getMessage())->toBe('Bu gönderi numarası zaten mevcuttur. Gönderici adres no alanı zorunludur.')
        ->and($response->getShipmentId())->toBeNull();
});

it('surfaces the API error string on auth-style failures', function () {
    $response = navlungoSendCreateResponse([
        'body' => json_encode([
            'status' => false,
            'error' => 'Bu kaynağa erişim yetkiniz yoktur.',
        ], JSON_THROW_ON_ERROR),
        'status' => 401,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getCode())->toBe('401')
        ->and($response->getMessage())->toBe('Bu kaynağa erişim yetkiniz yoktur.');
});
