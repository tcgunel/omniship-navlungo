<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

/**
 * @phpstan-type CarrierRow array{id: int, carrier_name: string, short_name: string, tracking_url: string|null, post_type: int[], cod: int}
 */
class GetCarriersResponse extends AbstractNavlungoResponse
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getCarriers(): array
    {
        $rows = $this->payload()['data'] ?? null;

        if (!is_array($rows)) {
            return [];
        }

        $carriers = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $carriers[] = $row;
            }
        }

        return $carriers;
    }

    /**
     * Just the id/name pairs, ready for a settings dropdown.
     *
     * @return array<int, string>
     */
    public function getCarrierOptions(): array
    {
        $options = [];

        foreach ($this->getCarriers() as $carrier) {
            $id = $carrier['id'] ?? null;
            $name = $carrier['carrier_name'] ?? null;

            if (is_int($id) && is_string($name)) {
                $options[$id] = $name;
            }
        }

        return $options;
    }
}
