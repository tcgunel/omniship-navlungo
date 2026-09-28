<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Navlungo\Message\CreateAddressRequest;
use Omniship\Navlungo\Message\CreateAddressResponse;
use Omniship\Navlungo\Message\GetAddressesRequest;
use Omniship\Navlungo\Message\GetAddressesResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoAddressBookParams(array $extra = []): array
{
    return array_merge([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
    ], $extra);
}

it('lists sender addresses with filters and parses them into options', function () {
    $captured = [];
    $request = new GetAddressesRequest(
        createSequencedMockHttpClient([
            navlungoAuthOk(),
            [
                'body' => json_encode([
                    'status' => true,
                    'data' => [
                        [
                            'id' => 56027,
                            'address_type' => 'sender',
                            'location_name' => 'Teknokent',
                            'address_name' => 'kolay sipariş',
                            'address_city' => 'Bursa',
                        ],
                        [
                            'id' => 159,
                            'address_type' => 'sender',
                            'location_name' => null,
                            'address_name' => 'John Doe',
                            'address_city' => 'İstanbul',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'status' => 200,
            ],
        ], $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoAddressBookParams(['addressType' => 'sender']));

    /** @var GetAddressesResponse $response */
    $response = $request->send();

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getAddressOptions())->toBe([
            56027 => 'Teknokent',
            159 => 'John Doe - İstanbul',
        ])
        ->and($captured[1]->getMethod())->toBe('GET')
        ->and($captured[1]->getUri()->getPath())->toEndWith('/v2.1/address-book/getAll')
        ->and(json_decode((string) $captured[1]->getBody(), true))->toBe([
            'limit' => 50,
            'page' => 1,
            'filters' => ['address_type' => 'sender'],
        ]);
});

it('creates a sender address from explicit fields', function () {
    $request = new CreateAddressRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoAddressBookParams([
        'addressType' => 'sender',
        'locationName' => 'Merkez Depo',
        'addressName' => 'Kolay Sipariş',
        'addressPhone' => '0538 030 71 21',
        'addressLine' => 'Bursa Uludağ Üniversitesi Görükle Kampüsü',
        'addressCity' => 'Bursa',
        'addressDistrict' => 'Nilüfer',
        'isMainWarehouse' => true,
    ]));

    expect($request->getData())->toBe([
        'address_type' => 'sender',
        'location_name' => 'Merkez Depo',
        'address_name' => 'Kolay Sipariş',
        'address_email' => '',
        'address_phone' => '+90 538 030 71 21',
        'address_line' => 'Bursa Uludağ Üniversitesi Görükle Kampüsü',
        'address_country' => 'tr',
        'address_city' => 'Bursa',
        'address_district' => 'Nilüfer',
        'address_post_code' => '',
        'is_main_warehouse' => 1,
    ]);
});

it('creates an address from an Omniship address object', function () {
    $request = new CreateAddressRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoAddressBookParams([
        'addressType' => 'sender',
        'locationName' => 'Depo',
        'address' => new Address(
            name: 'Kolay Sipariş',
            street1: 'Test Mah. 1. Sok.',
            city: 'Bursa',
            district: 'Nilüfer',
            phone: '+905380307121',
            email: 'info@example.com',
        ),
    ]));

    $data = $request->getData();

    expect($data['address_name'])->toBe('Kolay Sipariş')
        ->and($data['address_phone'])->toBe('+90 538 030 71 21')
        ->and($data['address_email'])->toBe('info@example.com')
        ->and($data['address_city'])->toBe('Bursa')
        ->and($data['address_district'])->toBe('Nilüfer')
        ->and($data['is_main_warehouse'])->toBe(0);
});

it('requires a location name for sender addresses', function () {
    $request = new CreateAddressRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoAddressBookParams([
        'addressType' => 'sender',
        'addressName' => 'Kolay Sipariş',
        'addressPhone' => '5380307121',
        'addressLine' => 'Test Mah.',
        'addressCity' => 'Bursa',
        'addressDistrict' => 'Nilüfer',
    ]));

    $request->getData();
})->throws(InvalidRequestException::class);

it('returns the created address id', function () {
    $request = new CreateAddressRequest(
        createSequencedMockHttpClient([
            navlungoAuthOk(),
            [
                'body' => json_encode([
                    'status' => true,
                    'message' => 'Adres kaydınız başarıyla oluşturulmuştur.',
                    'data' => ['id' => 159, 'address_type' => 'sender'],
                ], JSON_THROW_ON_ERROR),
                'status' => 201,
            ],
        ]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoAddressBookParams([
        'addressType' => 'sender',
        'locationName' => 'Depo',
        'address' => new Address(
            name: 'Kolay Sipariş',
            street1: 'Test Mah.',
            city: 'Bursa',
            district: 'Nilüfer',
            phone: '5380307121',
        ),
    ]));

    /** @var CreateAddressResponse $response */
    $response = $request->send();

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getAddressId())->toBe(159)
        ->and($response->getAddress())->toMatchArray(['address_type' => 'sender']);
});
