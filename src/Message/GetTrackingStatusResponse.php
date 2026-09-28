<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\Message\TrackingResponse;
use Omniship\Common\TrackingInfo;
use Omniship\Navlungo\Support\TrackingData;

class GetTrackingStatusResponse extends AbstractNavlungoResponse implements TrackingResponse
{
    public static function mapStatus(int|string|null $statusCode): ShipmentStatus
    {
        return TrackingData::mapStatus($statusCode);
    }

    public function getTrackingInfo(): TrackingInfo
    {
        return TrackingData::toTrackingInfo($this->data());
    }

    public function getPostNumber(): ?string
    {
        return TrackingData::string($this->data(), 'post_number');
    }

    public function getReferenceId(): ?string
    {
        return TrackingData::string($this->data(), 'reference_id');
    }

    /**
     * Navlungo's own tracking page.
     */
    public function getTrackingLink(): ?string
    {
        return TrackingData::string($this->data(), 'tracking_url');
    }

    /**
     * The underlying carrier's tracking number (empty until the carrier
     * issues one) and tracking page.
     */
    public function getCarrierTrackingCode(): ?string
    {
        return TrackingData::string($this->data(), 'carrier_tracking_code');
    }

    public function getCarrierTrackingUrl(): ?string
    {
        return TrackingData::string($this->data(), 'carrier_tracking_url');
    }

    public function getStatusName(): ?string
    {
        $status = $this->data()['status'] ?? null;

        return is_array($status) ? TrackingData::string($status, 'status_name') : null;
    }

    /**
     * Per-piece carrier barcodes; some carriers only return these once the
     * parcel has been accepted.
     *
     * @return string[]
     */
    public function getCarrierBarcodes(): array
    {
        $barcodes = $this->data()['carrierBarcodes'] ?? null;

        if (!is_array($barcodes)) {
            return [];
        }

        $values = [];

        foreach ($barcodes as $entry) {
            if (is_array($entry) && is_string($entry['barcode_number'] ?? null)) {
                $values[] = $entry['barcode_number'];
            }
        }

        return $values;
    }

    /**
     * Tracking payloads keep everything under `data`.
     *
     * @return array<string, mixed>
     */
    private function data(): array
    {
        $data = $this->payload()['data'] ?? null;

        return is_array($data) ? $data : [];
    }
}
