<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Support;

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\TrackingEvent;
use Omniship\Common\TrackingInfo;

/**
 * Shared mapping for the tracking payload returned by both
 * `GET /post/check/{id}` and the detailed search `POST /post/check`.
 */
final class TrackingData
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

    /**
     * Build a TrackingInfo from one post row (the elements of `data`).
     *
     * @param array<string, mixed> $post
     */
    public static function toTrackingInfo(array $post): TrackingInfo
    {
        $postNumber = self::string($post, 'post_number') ?? '';
        $carrierTrackingCode = self::string($post, 'carrier_tracking_code');

        $trackingNumber = $carrierTrackingCode ?? $postNumber;

        if ($trackingNumber === '') {
            return new TrackingInfo(
                trackingNumber: '',
                status: ShipmentStatus::UNKNOWN,
                events: [],
                carrier: 'Navlungo',
            );
        }

        $postDetails = is_array($post['post'] ?? null) ? $post['post'] : [];
        $status = is_array($post['status'] ?? null) ? $post['status'] : [];
        $events = self::parseEvents($post);

        $statusValue = self::mapStatus($status['status_code'] ?? null);

        if ($events !== [] && $statusValue === ShipmentStatus::UNKNOWN) {
            $statusValue = $events[0]->status;
        }

        return new TrackingInfo(
            trackingNumber: $trackingNumber,
            status: $statusValue,
            events: $events,
            carrier: self::string($postDetails, 'carrier_name') ?? 'Navlungo',
            serviceName: self::string($postDetails, 'post_type_name'),
            signedBy: self::string($status, 'delivered_person_name'),
        );
    }

    /**
     * @param array<string, mixed> $post
     * @return TrackingEvent[]
     */
    public static function parseEvents(array $post): array
    {
        $logs = $post['logs'] ?? null;
        $events = [];

        if (is_array($logs)) {
            foreach ($logs as $log) {
                if (!is_array($log)) {
                    continue;
                }

                $occurredAt = self::parseDate($log['created_at'] ?? null);

                if ($occurredAt === null) {
                    continue;
                }

                $events[] = new TrackingEvent(
                    status: self::mapStatus($log['status_code'] ?? null),
                    description: self::string($log, 'action_result')
                        ?? self::string($log, 'action')
                        ?? '',
                    occurredAt: $occurredAt,
                );
            }
        }

        // A freshly created post may have no log rows yet; synthesise the
        // headline status so the merchant still sees where it stands.
        if ($events === []) {
            $status = is_array($post['status'] ?? null) ? $post['status'] : [];
            $occurredAt = self::parseDate(
                $status['picked_up_date']
                ?? $status['delivered_date']
                ?? $status['cancel_date']
                ?? $post['post']['updated_at']
                ?? null,
            );

            $statusValue = self::mapStatus($status['status_code'] ?? null);

            if ($occurredAt !== null && $statusValue !== ShipmentStatus::UNKNOWN) {
                $events[] = new TrackingEvent(
                    status: $statusValue,
                    description: self::string($status, 'status_name') ?? '',
                    occurredAt: $occurredAt,
                );
            }
        }

        return $events;
    }

    public static function parseDate(mixed $value): ?\DateTimeImmutable
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
     * @param array<string, mixed> $payload
     */
    public static function string(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
