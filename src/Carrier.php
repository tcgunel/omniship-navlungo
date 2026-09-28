<?php

declare(strict_types=1);

namespace Omniship\Navlungo;

use Omniship\Common\AbstractHttpCarrier;
use Omniship\Common\Message\RequestInterface;
use Omniship\Navlungo\Message\CancelShipmentRequest;
use Omniship\Navlungo\Message\CreateAddressRequest;
use Omniship\Navlungo\Message\CreateReturnShipmentRequest;
use Omniship\Navlungo\Message\CreateShipmentRequest;
use Omniship\Navlungo\Message\GetAddressesRequest;
use Omniship\Navlungo\Message\GetBarcodeRequest;
use Omniship\Navlungo\Message\GetCarriersRequest;
use Omniship\Navlungo\Message\GetTrackingStatusRequest;

/**
 * Navlungo Domestic (domestic-api.navlungo.com) v2.1 carrier driver.
 *
 * Navlungo is a shipping aggregator: the merchant connects their own cargo
 * agreements (Sürat, HepsiJet, Kolay Gelsin, Aras, PTT, Yurtiçi, ...) in the
 * Navlungo panel and this driver books through whichever agreement the
 * `carrierId` points at. `carrierId = 1` means "automatic" — Navlungo picks
 * the agreement that covers the destination.
 *
 * Authentication is a username/password pair exchanged for an 8-hour Bearer
 * token at POST /auth/api. Pass a PSR-16 cache as `tokenCache` to reuse the
 * token across requests (see README).
 */
class Carrier extends AbstractHttpCarrier
{
    private const BASE_URL_TEST = 'https://domestic-api-qa.navlungo.com/v2.1';
    private const BASE_URL_PRODUCTION = 'https://domestic-api.navlungo.com/v2.1';

    public function getName(): string
    {
        return 'Navlungo';
    }

    public function getShortName(): string
    {
        return 'Navlungo';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'username' => '',
            'password' => '',
            // Free-form sender name echoed back on the waybill ("platform" in
            // the API payload). Merchants normally set it to their shop name.
            'platform' => '',
            // Address book entry the shipment is booked from (address_type=sender).
            'senderAddressId' => 0,
            // 1 = automatic (Navlungo picks the agreement), or a carrier id
            // from getMyCarriers().
            'carrierId' => 1,
            // 1 = same-day, 2 = standard.
            'postType' => 2,
            'barcodeFormat' => 'pdf-A5',
            'testMode' => false,
            'tokenCache' => null,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createReturnShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateReturnShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getTrackingStatus(array $options = []): RequestInterface
    {
        return $this->createRequest(GetTrackingStatusRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function cancelShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CancelShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getBarcode(array $options = []): RequestInterface
    {
        return $this->createRequest(GetBarcodeRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getMyCarriers(array $options = []): RequestInterface
    {
        return $this->createRequest(GetCarriersRequest::class, ['mine' => true] + $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getAllCarriers(array $options = []): RequestInterface
    {
        return $this->createRequest(GetCarriersRequest::class, ['mine' => false] + $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getAddresses(array $options = []): RequestInterface
    {
        return $this->createRequest(GetAddressesRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createAddress(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateAddressRequest::class, $options);
    }

    public function getBaseUrl(): string
    {
        return $this->getTestMode() ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
    }
}
