<?php

declare(strict_types=1);

use Omniship\Navlungo\Message\GetTrackingStatusRequest;
use Omniship\Navlungo\Message\GetTrackingStatusResponse;

use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoBuildTrackingRequest(array $responses, array &$captured = []): GetTrackingStatusRequest
{
    return new GetTrackingStatusRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

it('queries post/check with the tracking number', function () {
    $captured = [];
    $request = navlungoBuildTrackingRequest(
        [navlungoAuthOk(), navlungoTrackingOk()],
        $captured,
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'trackingNumber' => 'MFYS29970',
    ]);

    $response = $request->send();

    expect($response)->toBeInstanceOf(GetTrackingStatusResponse::class);

    $checkRequest = $captured[1];

    expect($checkRequest->getMethod())->toBe('GET')
        ->and($checkRequest->getUri()->getPath())->toEndWith('/v2.1/post/check/MFYS29970')
        ->and($checkRequest->getHeaderLine('Authorization'))->toBe('Bearer test-token')
        ->and($checkRequest->getHeaderLine('X-localization'))->toBe('tr')
        ->and((string) $checkRequest->getBody())->toBe('');
});

it('falls back to referenceId when no tracking number is given', function () {
    $captured = [];
    $request = navlungoBuildTrackingRequest(
        [navlungoAuthOk(), navlungoTrackingOk()],
        $captured,
    );
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
        'referenceId' => 'OMN-ABC123',
    ]);

    $request->send();

    expect($captured[1]->getUri()->getPath())->toEndWith('/v2.1/post/check/OMN-ABC123');
});

it('throws when no identifier is provided', function () {
    $request = navlungoBuildTrackingRequest([navlungoAuthOk()]);
    $request->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
