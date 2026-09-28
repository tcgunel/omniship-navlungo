<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

class GetAddressesResponse extends AbstractNavlungoResponse
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getAddresses(): array
    {
        $rows = $this->payload()['data'] ?? null;

        if (!is_array($rows)) {
            return [];
        }

        $addresses = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $addresses[] = $row;
            }
        }

        return $addresses;
    }

    /**
     * Address book entries shaped for a settings dropdown: id => label.
     * `location_name` is the warehouse name for sender addresses; fall back
     * to the contact name plus the city so the merchant can tell them apart.
     *
     * @return array<int, string>
     */
    public function getAddressOptions(): array
    {
        $options = [];

        foreach ($this->getAddresses() as $address) {
            $id = $address['id'] ?? null;

            if (!is_int($id)) {
                continue;
            }

            $location = $address['location_name'] ?? null;
            $name = $address['address_name'] ?? null;
            $city = $address['address_city'] ?? null;

            $label = is_string($location) && $location !== ''
                ? $location
                : trim(implode(' - ', array_filter([$name, $city], 'is_string')));

            $options[$id] = $label !== '' ? $label : (string) $id;
        }

        return $options;
    }
}
