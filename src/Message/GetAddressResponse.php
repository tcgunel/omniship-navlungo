<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

class GetAddressResponse extends AbstractNavlungoResponse
{
    /**
     * The address row. The docs show it wrapped in a list, the live API
     * answers a single object; both are unwrapped here.
     *
     * @return array<string, mixed>
     */
    public function getAddress(): array
    {
        $data = $this->payload()['data'] ?? null;

        if (!is_array($data)) {
            return [];
        }

        if (isset($data['id'])) {
            return $data;
        }

        $first = $data[0] ?? null;

        return is_array($first) ? $first : [];
    }

    public function getAddressId(): ?int
    {
        $id = $this->getAddress()['id'] ?? null;

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }
}
