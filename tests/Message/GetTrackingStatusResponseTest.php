<?php

declare(strict_types=1);

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Navlungo\Message\GetTrackingStatusRequest;
use Omniship\Navlungo\Message\GetTrackingStatusResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoSendTracking(array $responsePayload): GetTrackingStatusResponse
{
    $request = new GetTrackingStatusRequest(
        createSequencedMockHttpClient([navlungoAuthOk(), $responsePayload]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'trackingNumber' => 'MFYS29970',
    ]);

    /** @var GetTrackingStatusResponse $response */
    $response = $request->send();

    return $response;
}

it('maps every documented Navlungo status code', function (int $code, ShipmentStatus $expected) {
    expect(GetTrackingStatusResponse::mapStatus($code))->toBe($expected);
})->with([
    [1, ShipmentStatus::PRE_TRANSIT],
    [2, ShipmentStatus::DELIVERED],
    [3, ShipmentStatus::OUT_FOR_DELIVERY],
    [4, ShipmentStatus::OUT_FOR_DELIVERY],
    [5, ShipmentStatus::IN_TRANSIT],
    [6, ShipmentStatus::IN_TRANSIT],
    [7, ShipmentStatus::RETURNED],
    [9, ShipmentStatus::RETURNED],
    [10, ShipmentStatus::CANCELLED],
    [14, ShipmentStatus::PRE_TRANSIT],
    [16, ShipmentStatus::PICKED_UP],
    [17, ShipmentStatus::IN_TRANSIT],
    [18, ShipmentStatus::IN_TRANSIT],
    [19, ShipmentStatus::FAILURE],
    [20, ShipmentStatus::FAILURE],
    [21, ShipmentStatus::RETURNED],
    [999, ShipmentStatus::UNKNOWN],
]);

it('parses the tracking payload into TrackingInfo with events', function () {
    $response = navlungoSendTracking(navlungoTrackingOk());
    $info = $response->getTrackingInfo();

    expect($response->isSuccessful())->toBeTrue()
        ->and($info->trackingNumber)->toBe('3547896654321')
        ->and($info->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->carrier)->toBe('Sürat Kargo')
        ->and($info->serviceName)->toBe('Standart Teslimat')
        ->and($info->signedBy)->toBe('Ayşe Yılmaz')
        ->and($info->events)->toHaveCount(3)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->events[0]->description)->toBe('Teslim Edildi')
        ->and($info->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-08-22 12:47:41')
        ->and($info->events[2]->status)->toBe(ShipmentStatus::PICKED_UP)
        ->and($response->getStatusName())->toBe('Teslim Edildi')
        ->and($response->getPostNumber())->toBe('MFYS29970')
        ->and($response->getReferenceId())->toBe('OMN-1234567890')
        ->and($response->getTrackingLink())->toBe('https://domestic-track.navlungo.com/check/MFYS29970')
        ->and($response->getCarrierTrackingCode())->toBe('3547896654321');
});

it('falls back to the Navlungo post number before a carrier code exists', function () {
    $response = navlungoSendTracking([
        'body' => json_encode([
            'status' => true,
            'data' => [
                'post_number' => 'MFYS29970',
                'reference_id' => 'OMN-1234567890',
                'carrier_tracking_code' => null,
                'post' => ['carrier_name' => 'Sürat Kargo'],
                'status' => [
                    'status_code' => 1,
                    'status_name' => 'Teslim Alınacak',
                    'picked_up_date' => '2026-09-28 09:00:00',
                ],
                'logs' => [],
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ]);

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('MFYS29970')
        ->and($info->status)->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and($info->events)->toHaveCount(1)
        ->and($info->events[0]->description)->toBe('Teslim Alınacak');
});

it('returns carrier barcodes when the carrier issued them', function () {
    $response = navlungoSendTracking(navlungoTrackingOk([
        'data' => [
            'carrierBarcodes' => [
                ['barcode_number' => '19246232634415'],
                ['barcode_number' => '93683061581758'],
            ],
        ],
    ]));

    expect($response->getCarrierBarcodes())->toBe(['19246232634415', '93683061581758']);
});

it('reports failures with the API message', function () {
    $response = navlungoSendTracking([
        'body' => json_encode([
            'status' => false,
            'error' => 'Aradığınız kayıt bulunamadı.',
        ], JSON_THROW_ON_ERROR),
        'status' => 422,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Aradığınız kayıt bulunamadı.')
        ->and($response->getCode())->toBe('422');
});
