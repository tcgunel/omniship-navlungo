<?php

declare(strict_types=1);

use Omniship\Navlungo\Carrier;
use Omniship\Navlungo\Message\CancelShipmentRequest;
use Omniship\Navlungo\Message\CreateAddressRequest;
use Omniship\Navlungo\Message\CreateReturnShipmentRequest;
use Omniship\Navlungo\Message\CreateShipmentRequest;
use Omniship\Navlungo\Message\GetAddressesRequest;
use Omniship\Navlungo\Message\GetBarcodeRequest;
use Omniship\Navlungo\Message\GetCarriersRequest;
use Omniship\Navlungo\Message\GetTrackingStatusRequest;

use function Omniship\Navlungo\Tests\createMockHttpClient;
use function Omniship\Navlungo\Tests\createMockRequestFactory;
use function Omniship\Navlungo\Tests\createMockStreamFactory;

beforeEach(function () {
    $this->carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $this->carrier->initialize([
        'username' => 'user',
        'password' => 'pass',
        'testMode' => true,
    ]);
});

it('has the correct name', function () {
    expect($this->carrier->getName())->toBe('Navlungo')
        ->and($this->carrier->getShortName())->toBe('Navlungo');
});

it('uses the QA base URL in test mode and production otherwise', function () {
    expect($this->carrier->getBaseUrl())->toBe('https://domestic-api-qa.navlungo.com/v2.1');

    $this->carrier->setTestMode(false);

    expect($this->carrier->getBaseUrl())->toBe('https://domestic-api.navlungo.com/v2.1');
});

it('exposes sensible defaults', function () {
    $carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $carrier->initialize();

    $defaults = $carrier->getDefaultParameters();

    expect($defaults['username'])->toBe('')
        ->and($defaults['carrierId'])->toBe(1)
        ->and($defaults['postType'])->toBe(2)
        ->and($defaults['barcodeFormat'])->toBe('pdf-A5')
        ->and($defaults['testMode'])->toBeFalse()
        ->and($defaults['tokenCache'])->toBeNull();
});

it('supports the standard carrier methods', function () {
    expect($this->carrier->supports('createShipment'))->toBeTrue()
        ->and($this->carrier->supports('createReturnShipment'))->toBeTrue()
        ->and($this->carrier->supports('getTrackingStatus'))->toBeTrue()
        ->and($this->carrier->supports('cancelShipment'))->toBeTrue()
        ->and($this->carrier->supports('getBarcode'))->toBeTrue()
        ->and($this->carrier->supports('getMyCarriers'))->toBeTrue()
        ->and($this->carrier->supports('getAddresses'))->toBeTrue()
        ->and($this->carrier->supports('createAddress'))->toBeTrue();
});

it('returns the right request class per method', function () {
    expect($this->carrier->createShipment())->toBeInstanceOf(CreateShipmentRequest::class)
        ->and($this->carrier->createReturnShipment())->toBeInstanceOf(CreateReturnShipmentRequest::class)
        ->and($this->carrier->getTrackingStatus())->toBeInstanceOf(GetTrackingStatusRequest::class)
        ->and($this->carrier->cancelShipment())->toBeInstanceOf(CancelShipmentRequest::class)
        ->and($this->carrier->getBarcode())->toBeInstanceOf(GetBarcodeRequest::class)
        ->and($this->carrier->getMyCarriers())->toBeInstanceOf(GetCarriersRequest::class)
        ->and($this->carrier->getAllCarriers())->toBeInstanceOf(GetCarriersRequest::class)
        ->and($this->carrier->getAddresses())->toBeInstanceOf(GetAddressesRequest::class)
        ->and($this->carrier->createAddress())->toBeInstanceOf(CreateAddressRequest::class);
});

it('carries carrier defaults into created requests', function () {
    $this->carrier->initialize([
        'username' => 'user',
        'password' => 'pass',
        'senderAddressId' => 56027,
        'carrierId' => 9,
        'postType' => 1,
        'platform' => 'Test Shop',
    ]);

    /** @var CreateShipmentRequest $request */
    $request = $this->carrier->createShipment();

    expect($request->getSenderAddressId())->toBe(56027)
        ->and($request->getCarrierId())->toBe(9)
        ->and($request->getPostType())->toBe(1)
        ->and($request->getPlatform())->toBe('Test Shop');
});

it('builds return requests with the recipient address id', function () {
    /** @var CreateReturnShipmentRequest $request */
    $request = $this->carrier->createReturnShipment(['recipientAddressId' => 42]);

    expect($request->getRecipientAddressId())->toBe(42);
});
