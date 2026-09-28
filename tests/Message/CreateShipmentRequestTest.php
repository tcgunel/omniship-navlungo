<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Package;
use Omniship\Navlungo\Message\CreateShipmentRequest;
use Omniship\Navlungo\Message\CreateShipmentResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoShipmentParams(array $extra = []): array
{
    return array_merge([
        'username' => 'api-user',
        'password' => 'api-pass',
        'testMode' => true,
        'platform' => 'Kolay Sipariş',
        'senderAddressId' => 56027,
        'carrierId' => 1,
        'postType' => 2,
        'barcodeFormat' => 'pdf-A5',
        'referenceId' => 'OMN-1234567890',
        'shipTo' => new Address(
            name: 'Ayşe Yılmaz',
            street1: 'Örnek Mah. 5. Sok. No:3',
            street2: 'Daire 7',
            city: 'İstanbul',
            district: 'Kadıköy',
            country: 'TR',
            phone: '0532 123 45 67',
            email: 'ayse@example.com',
            postalCode: '34710',
        ),
        'packages' => [new Package(weight: 1.2, desi: 2.5, quantity: 2)],
    ], $extra);
}

function navlungoSendShipmentRequest(array $responses, array $params, array &$captured = []): CreateShipmentResponse
{
    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize($params);

    /** @var CreateShipmentResponse $response */
    $response = $request->send();

    return $response;
}

it('builds the posts payload with sender address id, receiver details and desi', function () {
    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoShipmentParams());

    $data = $request->getData();
    $post = $data['posts'][0];

    expect($data['platform'])->toBe('Kolay Sipariş')
        ->and($post['reference_id'])->toBe('OMN-1234567890')
        ->and($post['carrier_id'])->toBe(1)
        ->and($post['post_type'])->toBe(2)
        ->and($post['cod_payment_type'])->toBe('')
        ->and($post['sender'])->toBe(['addressId' => 56027])
        ->and($post['barcode_format'])->toBe('pdf-A5')
        ->and($post['recipient']['name'])->toBe('Ayşe Yılmaz')
        ->and($post['recipient']['phone'])->toBe('+90 532 123 45 67')
        ->and($post['recipient']['email'])->toBe('ayse@example.com')
        ->and($post['recipient']['address'])->toBe('Örnek Mah. 5. Sok. No:3 Daire 7')
        ->and($post['recipient']['country'])->toBe('tr')
        ->and($post['recipient']['city'])->toBe('İstanbul')
        ->and($post['recipient']['district'])->toBe('Kadıköy')
        ->and($post['recipient']['post_code'])->toBe('34710')
        ->and($post['post']['desi'])->toBe(5.0)
        ->and($post['post']['package_count'])->toBe(2)
        ->and($post['post']['price'])->toBe('');
});

it('summarises multiple packages into total desi and piece count', function () {
    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoShipmentParams([
        'packages' => [
            new Package(weight: 5.0, desi: 2.5, quantity: 2),
            new Package(weight: 1.0, desi: 1.0, quantity: 1),
        ],
    ]));

    $post = $request->getData()['posts'][0]['post'];

    expect($post['desi'])->toBe(6.0)
        ->and($post['package_count'])->toBe(3);
});

it('maps cash-on-delivery to cod_payment_type 1 with the collection amount', function () {
    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoShipmentParams([
        'cashOnDelivery' => true,
        'codAmount' => 249.9,
        'codCollectionType' => '0',
    ]));

    $post = $request->getData()['posts'][0];

    expect($post['cod_payment_type'])->toBe(1)
        ->and($post['post']['price'])->toBe(249.9);
});

it('maps card-at-the-door to cod_payment_type 2', function () {
    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoShipmentParams([
        'cashOnDelivery' => true,
        'codAmount' => 100,
        'codCollectionType' => '1',
    ]));

    expect($request->getData()['posts'][0]['cod_payment_type'])->toBe(2);
});

it('sends the payload to post/create with a bearer token', function () {
    $captured = [];
    $response = navlungoSendShipmentRequest(
        [navlungoAuthOk('create-token'), navlungoCreateOk()],
        navlungoShipmentParams(),
        $captured,
    );

    $createRequest = $captured[1];

    expect($response->isSuccessful())->toBeTrue()
        ->and($createRequest->getMethod())->toBe('POST')
        ->and($createRequest->getUri()->getPath())->toEndWith('/v2.1/post/create')
        ->and($createRequest->getHeaderLine('Authorization'))->toBe('Bearer create-token')
        ->and($createRequest->getHeaderLine('Content-Type'))->toBe('application/json');

    $body = json_decode((string) $createRequest->getBody(), true);

    expect($body['posts'][0]['sender']['addressId'])->toBe(56027)
        ->and($body['posts'][0]['recipient']['city'])->toBe('İstanbul');
});

it('requires a sender address id', function () {
    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(navlungoShipmentParams(['senderAddressId' => 0]));

    $request->getData();
})->throws(InvalidRequestException::class);

it('requires a shipTo address', function () {
    $params = navlungoShipmentParams();
    unset($params['shipTo']);

    $request = new CreateShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize($params);

    $request->getData();
})->throws(InvalidRequestException::class);
