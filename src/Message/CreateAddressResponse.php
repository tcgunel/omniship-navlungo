<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

class CreateAddressResponse extends AbstractNavlungoResponse
{
    public function getAddressId(): ?int
    {
        $id = $this->data()['id'] ?? null;

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    /**
     * The created address row as returned by the API.
     *
     * @return array<string, mixed>
     */
    public function getAddress(): array
    {
        return $this->data();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        $data = $this->payload()['data'] ?? null;

        return is_array($data) ? $data : [];
    }
}
