<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Label;
use Omniship\Common\Message\ShipmentResponse;

/**
 * Successful create answers carry the Navlungo post number — the identifier
 * every follow-up call (check, cancel, barcode) keys off — plus a tracking
 * URL. The printable barcode is fetched separately via getBarcode().
 */
class CreateShipmentResponse extends AbstractNavlungoResponse implements ShipmentResponse
{
    public function getShipmentId(): ?string
    {
        return $this->string('post_number');
    }

    public function getTrackingNumber(): ?string
    {
        return $this->string('post_number');
    }

    public function getBarcode(): ?string
    {
        // Navlungo does not return a scannable barcode value on create; the
        // PDF/HTML label comes from getBarcode().
        return null;
    }

    public function getLabel(): ?Label
    {
        return null;
    }

    public function getTotalCharge(): ?float
    {
        return null;
    }

    public function getCurrency(): ?string
    {
        return null;
    }

    /**
     * The merchant's own reference echoed back by the API.
     */
    public function getReferenceId(): ?string
    {
        return $this->string('reference_id');
    }

    /**
     * Navlungo's hosted tracking page for this post.
     */
    public function getTrackingLink(): ?string
    {
        return $this->string('tracking_url');
    }

    /**
     * The barcode PDF/HTML URL on Navlungo's domain. The file exists once
     * `barcode_status` turns 1; until then the URL may 404.
     */
    public function getBarcodeUrl(): ?string
    {
        return $this->string('barcode_url') ?? $this->string('barcode');
    }

    /**
     * The underlying carrier Navlungo booked the post with (empty when the
     * automatic agreement is still resolving).
     */
    public function getCarrierName(): ?string
    {
        $post = $this->source()['post'] ?? null;

        if (!is_array($post)) {
            return null;
        }

        $name = $post['carrier_name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Live create answers wrap the created post in a `data` array (and name
     * the label URL `barcode`); the documented example is a flat object with
     * `barcode_url`. Support both so a shape change upstream cannot silently
     * produce an empty shipment id.
     *
     * @return array<string, mixed>
     */
    private function source(): array
    {
        $payload = $this->payload();
        $data = $payload['data'] ?? null;

        if (!is_array($data)) {
            return $payload;
        }

        $first = $data[0] ?? null;

        return is_array($first) ? array_merge($payload, $first) : $payload;
    }

    private function string(string $key): ?string
    {
        $value = $this->source()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
