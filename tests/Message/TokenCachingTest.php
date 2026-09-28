<?php

declare(strict_types=1);

use Omniship\Common\Address;
use Omniship\Common\Exception\HttpException;
use Omniship\Common\Package;
use Omniship\Navlungo\Message\CreateShipmentRequest;

use function Omniship\Navlungo\Tests\createInMemoryCache;
use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;
use function Omniship\Navlungo\Tests\createSequencedMockHttpClient;

function navlungoBuildCreateRequest(array $responses, array &$captured = []): CreateShipmentRequest
{
    return new CreateShipmentRequest(
        createSequencedMockHttpClient($responses, $captured),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
}

function navlungoTokenRequestParams(array $extra = []): array
{
    return array_merge([
        'username' => 'api-user',
        'password' => 'api-pass',
        'testMode' => true,
        'senderAddressId' => 56027,
        'shipTo' => new Address(
            name: 'Test Alıcı',
            street1: 'Test Mah. 1. Sok. No:3',
            city: 'Bursa',
            district: 'Nilüfer',
            phone: '5380307121',
        ),
        'packages' => [new Package(weight: 1.0, desi: 1.0)],
    ], $extra);
}

it('mints the token once and reuses it from the cache on later requests', function () {
    $cache = createInMemoryCache();
    $captured = [];

    $first = navlungoBuildCreateRequest(
        [navlungoAuthOk('cached-token'), navlungoCreateOk()],
        $captured,
    );
    $first->initialize(navlungoTokenRequestParams(['tokenCache' => $cache, 'referenceId' => 'OMN-1']));
    $first->send();

    // Second request has no auth response queued: a token call would exhaust
    // the sequence and reuse the create response with HTTP 201, which the
    // assertion below would catch.
    $second = navlungoBuildCreateRequest(
        [navlungoCreateOk()],
        $captured,
    );
    $second->initialize(navlungoTokenRequestParams(['tokenCache' => $cache, 'referenceId' => 'OMN-2']));
    $second->send();

    $authCalls = array_filter(
        $captured,
        fn ($request) => str_ends_with($request->getUri()->getPath(), '/v2.1/auth/api'),
    );

    expect(count($authCalls))->toBe(1)
        ->and($captured[1]->getHeaderLine('Authorization'))->toBe('Bearer cached-token')
        ->and($captured[2]->getHeaderLine('Authorization'))->toBe('Bearer cached-token');
});

it('hits the auth endpoint before the first call when no cache is configured', function () {
    $captured = [];
    $request = navlungoBuildCreateRequest(
        [navlungoAuthOk(), navlungoCreateOk()],
        $captured,
    );
    $request->initialize(navlungoTokenRequestParams(['referenceId' => 'OMN-1']));

    $request->send();

    expect(count($captured))->toBe(2)
        ->and($captured[0]->getMethod())->toBe('POST')
        ->and($captured[0]->getUri()->getPath())->toEndWith('/v2.1/auth/api')
        ->and(json_decode((string) $captured[0]->getBody(), true))
        ->toBe(['username' => 'api-user', 'password' => 'api-pass']);
});

it('throws an HttpException carrying the API wording when credentials are rejected', function () {
    $request = navlungoBuildCreateRequest([navlungoAuthInvalid()]);
    $request->initialize(navlungoTokenRequestParams(['referenceId' => 'OMN-1']));

    $request->send();
})->throws(HttpException::class, 'Geçersiz kullanıcı bilgileri.');
