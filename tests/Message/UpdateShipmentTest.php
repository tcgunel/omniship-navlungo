<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Package;
use Omniship\Navlungo\Message\UpdateShipmentRequest;
use Omniship\Navlungo\Message\UpdateShipmentResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoUpdateParams(array $extra = []): array
{
    return array_merge([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'postNumber' => 'MFYS29970',
        'senderAddressId' => 56027,
    ], $extra);
}

function navlungoBuildUpdateRequest(array $responses, array &$captured = []): UpdateShipmentRequest
{
    return new UpdateShipmentRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('sends only the fields being changed to post/update', function () {
    $request = navlungoBuildUpdateRequest([]);
    $request->initialize(navlungoUpdateParams([
        'shipTo' => new Address(
            name: 'Ayşe Yılmaz',
            street1: 'Yeni Adres Mah. 4. Sok. No:8',
            city: 'İstanbul',
            district: 'Kadıköy',
            phone: '0532 123 45 67',
        ),
        'packages' => [new Package(weight: 2.0, desi: 3.5, quantity: 1)],
        'note' => 'Kapıya bırakabilirsiniz',
        'customData1' => 'ONEMLI',
    ]));

    $data = $request->getData();

    expect($data['post_number'])->toBe('MFYS29970')
        ->and($data['sender'])->toBe(['addressId' => 56027])
        ->and($data['recipient']['name'])->toBe('Ayşe Yılmaz')
        ->and($data['recipient']['phone'])->toBe('+90 532 123 45 67')
        ->and($data['post']['desi'])->toBe(3.5)
        ->and($data['post']['package_count'])->toBe(1)
        ->and($data['post']['note'])->toBe('Kapıya bırakabilirsiniz')
        ->and($data['custom_data_1'])->toBe('ONEMLI')
        ->and($data)->not->toHaveKey('custom_data_2');
});

it('updates return posts with an inline sender and an address book recipient', function () {
    $request = navlungoBuildUpdateRequest([]);
    $request->initialize(navlungoUpdateParams([
        'shipFrom' => new Address(
            name: 'Müşteri',
            street1: 'İade Sok. No:1',
            city: 'Bursa',
            district: 'Nilüfer',
            phone: '5380307121',
        ),
        'recipientAddressId' => 42,
        'senderAddressId' => 0,
    ]));

    $data = $request->getData();

    expect($data['recipient'])->toBe(['addressId' => 42])
        ->and($data['sender']['name'])->toBe('Müşteri')
        ->and($data['sender']['city'])->toBe('Bursa')
        ->and($data)->not->toHaveKey('post');
});

it('posts to post/update and parses the nested data response', function () {
    $captured = [];
    $request = navlungoBuildUpdateRequest(
        [
            navlungoAuthOk(),
            [
                'body' => json_encode([
                    'status' => true,
                    'message' => 'Gönderi başarıyla güncellenmiştir.',
                    'data' => [
                        'post_number' => 'MFYS29970',
                        'reference_id' => 'OMN-1234567890',
                        'tracking_url' => 'https://domestic-track.navlungo.com/check/MFYS29970',
                        'barcode_url' => 'https://domestic-qa-barcode.navlungo.com/MFYS29970.pdf',
                        'post' => ['carrier_name' => 'Sürat Kargo'],
                    ],
                ], JSON_THROW_ON_ERROR),
                'status' => 200,
            ],
        ],
        $captured,
    );
    $request->initialize(navlungoUpdateParams());

    /** @var UpdateShipmentResponse $response */
    $response = $request->send();

    $updateRequest = $captured[1];

    expect($response)->toBeInstanceOf(UpdateShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->getShipmentId())->toBe('MFYS29970')
        ->and($response->getReferenceId())->toBe('OMN-1234567890')
        ->and($response->getBarcodeUrl())->toBe('https://domestic-qa-barcode.navlungo.com/MFYS29970.pdf')
        ->and($response->getCarrierName())->toBe('Sürat Kargo')
        ->and($updateRequest->getMethod())->toBe('POST')
        ->and($updateRequest->getUri()->getPath())->toEndWith('/v2.1/post/update')
        ->and(json_decode((string) $updateRequest->getBody(), true))->toBe([
            'post_number' => 'MFYS29970',
            'sender' => ['addressId' => 56027],
        ]);
});

it('requires a post number', function () {
    $params = navlungoUpdateParams();
    unset($params['postNumber']);

    $request = navlungoBuildUpdateRequest([]);
    $request->initialize($params);

    $request->getData();
})->throws(InvalidRequestException::class);
