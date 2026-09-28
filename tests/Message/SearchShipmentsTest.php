<?php

declare(strict_types=1);

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Navlungo\Message\SearchShipmentsRequest;
use Omniship\Navlungo\Message\SearchShipmentsResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoBuildSearchRequest(array $responses, array &$captured = []): SearchShipmentsRequest
{
    return new SearchShipmentsRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('searches by any combination of filters', function () {
    $captured = [];
    $request = navlungoBuildSearchRequest(
        [
            navlungoAuthOk(),
            [
                'body' => json_encode([
                    'status' => true,
                    'data' => [
                        [
                            'post_number' => 'MFYS29970',
                            'reference_id' => 'OMN-1234567890',
                            'carrier_tracking_code' => '3547896654321',
                            'post' => ['carrier_name' => 'Sürat Kargo'],
                            'status' => ['status_code' => 2, 'status_name' => 'Teslim Edildi'],
                            'logs' => [],
                        ],
                        [
                            'post_number' => 'MFYS29971',
                            'reference_id' => 'OMN-1234567891',
                            'post' => ['carrier_name' => 'Aras Kargo'],
                            'status' => ['status_code' => 1],
                            'logs' => [],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'status' => 200,
            ],
        ],
        $captured,
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'recipientPhone' => '+90 532 123 45 67',
        'referenceId' => 'OMN-1234567890',
        'limit' => 99,
    ]);

    /** @var SearchShipmentsResponse $response */
    $response = $request->send();

    $searchRequest = $captured[1];

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getShipments())->toHaveCount(2)
        ->and($response->getTrackingInfos()[0]->trackingNumber)->toBe('3547896654321')
        ->and($response->getTrackingInfos()[0]->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($response->getTrackingInfos()[1]->carrier)->toBe('Aras Kargo')
        ->and($searchRequest->getMethod())->toBe('POST')
        ->and($searchRequest->getUri()->getPath())->toEndWith('/v2.1/post/check');

    expect(json_decode((string) $searchRequest->getBody(), true))->toBe([
        'post' => [
            'reference_id' => 'OMN-1234567890',
            'recipient_phone' => '+90 532 123 45 67',
        ],
        'limit' => 50,
    ]);
});

it('requires at least one filter', function () {
    $request = navlungoBuildSearchRequest([]);
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
    ]);

    $request->getData();
})->throws(InvalidRequestException::class);

it('reports search failures', function () {
    $request = navlungoBuildSearchRequest([
        navlungoAuthOk(),
        [
            'body' => json_encode(['status' => false, 'error' => 'Aradığınız kayıt bulunamadı.'], JSON_THROW_ON_ERROR),
            'status' => 422,
        ],
    ]);
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'postNumber' => 'MFYS29970',
    ]);

    $response = $request->send();

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Aradığınız kayıt bulunamadı.')
        ->and($response->getShipments())->toBe([]);
});
