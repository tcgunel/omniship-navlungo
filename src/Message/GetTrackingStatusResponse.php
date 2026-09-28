<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\Message\TrackingResponse;
use Omniship\Common\TrackingEvent;
use Omniship\Common\TrackingInfo;

class GetTrackingStatusResponse extends AbstractNavlungoResponse implements TrackingResponse
{
    /**
     * Navlungo service status codes (docs: "Servis Statü Kodları").
     */
    private const STATUS_MAP = [
        1 => ShipmentStatus::PRE_TRANSIT,       // Teslim Alınacak
        2 => ShipmentStatus::DELIVERED,         // Teslim Edildi
        3 => ShipmentStatus::OUT_FOR_DELIVERY,  // Teslim Edilecek
        4 => ShipmentStatus::OUT_FOR_DELIVERY,  // Dağıtıma Çıktı
        5 => ShipmentStatus::IN_TRANSIT,        // Tekrar Sevk
        6 => ShipmentStatus::IN_TRANSIT,        // Dağıtım Planlandı
        7 => ShipmentStatus::RETURNED,          // İade Edilecek
        9 => ShipmentStatus::RETURNED,          // İade Edildi
        10 => ShipmentStatus::CANCELLED,        // İptal
        14 => ShipmentStatus::PRE_TRANSIT,      // Hazırlanıyor
        16 => ShipmentStatus::PICKED_UP,        // Teslim Alındı
        17 => ShipmentStatus::IN_TRANSIT,       // Transfer Aşamasında
        18 => ShipmentStatus::IN_TRANSIT,       // Şubede Beklemede
        19 => ShipmentStatus::FAILURE,          // Tazmin Durumu Değerlendiriliyor
        20 => ShipmentStatus::FAILURE,          // Tazmin Süreci Tamamlandı
        21 => ShipmentStatus::RETURNED,         // Depoya İade Edildi
    ];

    public static function mapStatus(int|string|null $statusCode): ShipmentStatus
    {
        if ($statusCode === null || $statusCode === '') {
            return ShipmentStatus::UNKNOWN;
        }

        return self::STATUS_MAP[(int) $statusCode] ?? ShipmentStatus::UNKNOWN;
    }

    public function getTrackingInfo(): TrackingInfo
    {
        $payload = $this->data();

        $postNumber = $this->string($payload, 'post_number') ?? '';
        $carrierTrackingCode = $this->string($payload, 'carrier_tracking_code');

        $trackingNumber = $carrierTrackingCode ?? $postNumber;

        if ($trackingNumber === '') {
            return new TrackingInfo(
                trackingNumber: '',
                status: ShipmentStatus::UNKNOWN,
                events: [],
                carrier: 'Navlungo',
            );
        }

        $post = is_array($payload['post'] ?? null) ? $payload['post'] : [];
        $status = is_array($payload['status'] ?? null) ? $payload['status'] : [];
        $events = $this->parseEvents($payload);

        $statusValue = self::mapStatus($status['status_code'] ?? null);

        if ($events !== [] && $statusValue === ShipmentStatus::UNKNOWN) {
            $statusValue = $events[0]->status;
        }

        return new TrackingInfo(
            trackingNumber: $trackingNumber,
            status: $statusValue,
            events: $events,
            carrier: $this->string($post, 'carrier_name') ?? 'Navlungo',
            serviceName: $this->string($post, 'post_type_name'),
            signedBy: $this->string($status, 'delivered_person_name'),
        );
    }

    public function getPostNumber(): ?string
    {
        return $this->string($this->data(), 'post_number');
    }

    public function getReferenceId(): ?string
    {
        return $this->string($this->data(), 'reference_id');
    }

    /**
     * Navlungo's own tracking page.
     */
    public function getTrackingLink(): ?string
    {
        return $this->string($this->data(), 'tracking_url');
    }

    /**
     * The underlying carrier's tracking number (empty until the carrier
     * issues one) and tracking page.
     */
    public function getCarrierTrackingCode(): ?string
    {
        return $this->string($this->data(), 'carrier_tracking_code');
    }

    public function getCarrierTrackingUrl(): ?string
    {
        return $this->string($this->data(), 'carrier_tracking_url');
    }

    public function getStatusName(): ?string
    {
        $status = $this->data()['status'] ?? null;

        return is_array($status) ? $this->string($status, 'status_name') : null;
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
     * @param array<string, mixed> $payload
     * @return TrackingEvent[]
     */
    private function parseEvents(array $payload): array
    {
        $logs = $payload['logs'] ?? null;
        $events = [];

        if (is_array($logs)) {
            foreach ($logs as $log) {
                if (!is_array($log)) {
                    continue;
                }

                $occurredAt = $this->parseDate($log['created_at'] ?? null);

                if ($occurredAt === null) {
                    continue;
                }

                $events[] = new TrackingEvent(
                    status: self::mapStatus($log['status_code'] ?? null),
                    description: $this->string($log, 'action_result')
                        ?? $this->string($log, 'action')
                        ?? '',
                    occurredAt: $occurredAt,
                );
            }
        }

        // A freshly created post may have no log rows yet; synthesise the
        // headline status so the merchant still sees where it stands.
        if ($events === []) {
            $status = is_array($payload['status'] ?? null) ? $payload['status'] : [];
            $occurredAt = $this->parseDate(
                $status['picked_up_date']
                ?? $status['delivered_date']
                ?? $status['cancel_date']
                ?? $payload['post']['updated_at']
                ?? null,
            );

            $statusValue = self::mapStatus($status['status_code'] ?? null);

            if ($occurredAt !== null && $statusValue !== ShipmentStatus::UNKNOWN) {
                $events[] = new TrackingEvent(
                    status: $statusValue,
                    description: $this->string($status, 'status_name') ?? '',
                    occurredAt: $occurredAt,
                );
            }
        }

        return $events;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
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

    /**
     * @param array<string, mixed> $payload
     */
    private function string(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
