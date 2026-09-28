<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\TrackingInfo;
use Omniship\Navlungo\Support\TrackingData;

class SearchShipmentsResponse extends AbstractNavlungoResponse
{
    /**
     * The matching post rows, exactly as the API returned them.
     *
     * @return list<array<string, mixed>>
     */
    public function getShipments(): array
    {
        $rows = $this->payload()['data'] ?? null;

        if (!is_array($rows)) {
            return [];
        }

        $shipments = [];

        foreach ($rows as $row) {
            if (is_array($row) && isset($row['post_number'])) {
                $shipments[] = $row;
            }
        }

        return $shipments;
    }

    /**
     * @return TrackingInfo[]
     */
    public function getTrackingInfos(): array
    {
        $infos = [];

        foreach ($this->getShipments() as $shipment) {
            $infos[] = TrackingData::toTrackingInfo($shipment);
        }

        return $infos;
    }
}
