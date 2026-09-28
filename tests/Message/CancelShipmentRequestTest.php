<?php

declare(strict_types=1);

use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Navlungo\Message\CancelShipmentRequest;
use Omniship\Navlungo\Message\CancelShipmentResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoSendCancel(array $responsePayload, array $params = []): CancelShipmentResponse
{
    $request = new CancelShipmentRequest(
        createSequencedMockHttpClient([navlungoAuthOk(), $responsePayload]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize(array_merge([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'postNumber' => 'MFYS29970',
    ], $params));

    /** @var CancelShipmentResponse $response */
    $response = $request->send();

    return $response;
}

it('posts the post number to post/cancel', function () {
    $captured = [];
    $request = new CancelShipmentRequest(
        createSequencedMockHttpClient([
            navlungoAuthOk(),
            ['body' => json_encode(['status' => true, 'message' => 'Gönderi başarıyla iptal edilmiştir.'], JSON_THROW_ON_ERROR), 'status' => 200],
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

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue()
        ->and($response->getMessage())->toBe('Gönderi başarıyla iptal edilmiştir.')
        ->and($captured[1]->getMethod())->toBe('POST')
        ->and($captured[1]->getUri()->getPath())->toEndWith('/v2.1/post/cancel')
        ->and(json_decode((string) $captured[1]->getBody(), true))->toBe(['post_number' => 'MFYS29970']);
});

it('accepts the shipment id as the post number', function () {
    $request = new CancelShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'shipmentId' => 'AV7HSL5VNV',
    ]);

    expect($request->getData())->toBe(['post_number' => 'AV7HSL5VNV']);
});

it('reports failures with the API error', function () {
    $response = navlungoSendCancel([
        'body' => json_encode([
            'status' => false,
            'error' => 'Bu kaynağa erişim yetkiniz yoktur.',
        ], JSON_THROW_ON_ERROR),
        'status' => 422,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getMessage())->toBe('Bu kaynağa erişim yetkiniz yoktur.');
});

it('requires a post number', function () {
    $request = new CancelShipmentRequest(
        createSequencedMockHttpClient([]),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
    ]);

    $request->getData();
})->throws(InvalidRequestException::class);
