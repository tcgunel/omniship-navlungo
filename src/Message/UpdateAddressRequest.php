<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Exception\InvalidRequestException;

/**
 * Update an address book entry. Takes the same fields as create plus the
 * `addressId` in the path (the docs page omits the id, the Postman
 * collection and the live API use `address-book/update/{id}`).
 */
class UpdateAddressRequest extends CreateAddressRequest
{
    protected function getEndpoint(): string
    {
        return 'address-book/update/' . $this->resolveAddressId();
    }

    protected function getHttpMethod(): string
    {
        return 'PUT';
    }

    public function getAddressId(): int
    {
        return (int) ($this->getParameter('addressId') ?? 0);
    }

    public function setAddressId(int $addressId): static
    {
        return $this->setParameter('addressId', $addressId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        $this->resolveAddressId();

        return parent::getData();
    }

    private function resolveAddressId(): int
    {
        $addressId = $this->getAddressId();

        if ($addressId <= 0) {
            throw new InvalidRequestException('The addressId parameter is required');
        }

        return $addressId;
    }
}
