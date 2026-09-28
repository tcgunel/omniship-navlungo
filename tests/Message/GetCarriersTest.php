<?php

declare(strict_types=1);

use Omniship\Navlungo\Message\GetCarriersRequest;
use Omniship\Navlungo\Message\GetCarriersResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoCarriersPayload(): array
{
    return [
        'body' => json_encode([
            'status' => true,
            'data' => [
                [
                    'id' => 9,
                    'carrier_name' => 'Sürat Kargo',
                    'tracking_url' => 'https://suratkargo.com.tr/KargoTakip/%s',
                    'short_name' => 'suratkargo',
                    'post_type' => [2, 3],
                    'cod' => 1,
                ],
                [
                    'id' => 11,
                    'carrier_name' => 'Kolay Gelsin',
                    'tracking_url' => 'https://esube.kolaygelsin.com/shipments?trackingId=%s&lang=TR',
                    'short_name' => 'kolaygelsin',
                    'post_type' => [1, 2],
                    'cod' => 1,
                ],
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

it('lists the merchant carriers from carrier/my-carriers', function () {
    $captured = [];
    $request = new GetCarriersRequest(
        createSequencedMockHttpClient([navlungoAuthOk(), navlungoCarriersPayload()], $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'mine' => true,
    ]);

    /** @var GetCarriersResponse $response */
    $response = $request->send();

    expect($captured[1]->getUri()->getPath())->toEndWith('/v2.1/carrier/my-carriers')
        ->and(json_decode((string) $captured[1]->getBody(), true))->toBe(['limit' => 50])
        ->and($response->getCarrierOptions())->toBe([
            9 => 'Sürat Kargo',
            11 => 'Kolay Gelsin',
        ])
        ->and($response->getCarriers()[0]['post_type'])->toBe([2, 3]);
});

it('lists every Navlungo carrier when mine is false', function () {
    $captured = [];
    $request = new GetCarriersRequest(
        createSequencedMockHttpClient([navlungoAuthOk(), navlungoCarriersPayload()], $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'mine' => false,
    ]);

    $request->send();

    expect($captured[1]->getUri()->getPath())->toEndWith('/v2.1/carrier/getAll');
});

it('reports carrier listing failures', function () {
    $request = new GetCarriersRequest(
        createSequencedMockHttpClient([
            navlungoAuthOk(),
            ['body' => json_encode(['status' => false, 'error' => 'Bu kaynağa erişim yetkiniz yoktur.'], JSON_THROW_ON_ERROR), 'status' => 422],
        ]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Bu kaynağa erişim yetkiniz yoktur.')
        ->and($response->getCarriers())->toBe([]);
});
