<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Address;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Navlungo\Support\Phone;

/**
 * Registers an address book entry. Sender addresses power `senderAddressId`
 * on standard posts; recipient entries are used for return pickups.
 *
 * Fields may be passed individually or via an `address` (Omniship Address)
 * plus `addressType` / `locationName`, which is the convenient shape for
 * seeding a sender from the shop's own address.
 */
class CreateAddressRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return 'address-book/create';
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    public function getAddressType(): string
    {
        return (string) ($this->getParameter('addressType') ?? 'sender');
    }

    public function setAddressType(string $addressType): static
    {
        return $this->setParameter('addressType', $addressType);
    }

    public function getLocationName(): ?string
    {
        $value = $this->getParameter('locationName');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setLocationName(string $locationName): static
    {
        return $this->setParameter('locationName', $locationName);
    }

    public function getAddressName(): ?string
    {
        return $this->stringParameter('addressName');
    }

    public function setAddressName(string $addressName): static
    {
        return $this->setParameter('addressName', $addressName);
    }

    public function getAddressEmail(): ?string
    {
        return $this->stringParameter('addressEmail');
    }

    public function setAddressEmail(string $addressEmail): static
    {
        return $this->setParameter('addressEmail', $addressEmail);
    }

    public function getAddressPhone(): ?string
    {
        return $this->stringParameter('addressPhone');
    }

    public function setAddressPhone(string $addressPhone): static
    {
        return $this->setParameter('addressPhone', $addressPhone);
    }

    public function getAddressLine(): ?string
    {
        return $this->stringParameter('addressLine');
    }

    public function setAddressLine(string $addressLine): static
    {
        return $this->setParameter('addressLine', $addressLine);
    }

    public function getAddressCountry(): string
    {
        return (string) ($this->getParameter('addressCountry') ?? 'tr');
    }

    public function setAddressCountry(string $addressCountry): static
    {
        return $this->setParameter('addressCountry', $addressCountry);
    }

    public function getAddressCity(): ?string
    {
        return $this->stringParameter('addressCity');
    }

    public function setAddressCity(string $addressCity): static
    {
        return $this->setParameter('addressCity', $addressCity);
    }

    public function getAddressDistrict(): ?string
    {
        return $this->stringParameter('addressDistrict');
    }

    public function setAddressDistrict(string $addressDistrict): static
    {
        return $this->setParameter('addressDistrict', $addressDistrict);
    }

    public function getAddressPostCode(): ?string
    {
        return $this->stringParameter('addressPostCode');
    }

    public function setAddressPostCode(string $addressPostCode): static
    {
        return $this->setParameter('addressPostCode', $addressPostCode);
    }

    public function isMainWarehouse(): bool
    {
        return (bool) ($this->getParameter('isMainWarehouse') ?? false);
    }

    public function setIsMainWarehouse(bool $isMainWarehouse): static
    {
        return $this->setParameter('isMainWarehouse', $isMainWarehouse);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        $type = $this->getAddressType();
        $address = $this->getParameter('address');
        $address = $address instanceof Address ? $address : null;

        $name = $this->getAddressName() ?? $address?->name;
        $phone = Phone::normalize($this->getAddressPhone() ?? $address?->phone) ?: null;
        $line = $this->getAddressLine()
            ?? ($address !== null ? trim(implode(' ', array_filter([$address->street1, $address->street2]))) : null);
        $city = $this->getAddressCity() ?? $address?->city;
        $district = $this->getAddressDistrict() ?? $address?->district;
        $email = $this->getAddressEmail() ?? $address?->email;
        $postCode = $this->getAddressPostCode() ?? $address?->postalCode;
        $country = strtolower($this->getAddressCountry()) ?: 'tr';

        if ($name === null || $name === '') {
            throw new InvalidRequestException('The addressName parameter is required');
        }

        if ($phone === null) {
            throw new InvalidRequestException('The addressPhone parameter is required');
        }

        if ($line === null || $line === '') {
            throw new InvalidRequestException('The addressLine parameter is required');
        }

        if ($city === null || $city === '') {
            throw new InvalidRequestException('The addressCity parameter is required');
        }

        if ($district === null || $district === '') {
            throw new InvalidRequestException('The addressDistrict parameter is required');
        }

        if ($type === 'sender' && $this->getLocationName() === null) {
            throw new InvalidRequestException('The locationName parameter is required for sender addresses');
        }

        $data = [
            'address_type' => $type,
            'location_name' => $this->getLocationName() ?? '',
            'address_name' => $name,
            'address_email' => $email ?? '',
            'address_phone' => $phone,
            'address_line' => $line,
            'address_country' => $country,
            'address_city' => $city,
            'address_district' => $district,
            'address_post_code' => $postCode ?? '',
        ];

        if ($type === 'sender') {
            $data['is_main_warehouse'] = $this->isMainWarehouse() ? 1 : 0;
        }

        return $data;
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CreateAddressResponse($this, $data);
    }

    private function stringParameter(string $key): ?string
    {
        $value = $this->getParameter($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
