<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Address;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Navlungo\Support\Packages;

/**
 * Update a post while it is being prepared or waiting for pickup
 * (`inProgress` must be 0). Every field except `post_number` is optional:
 * omitted fields keep their current value, so the request only carries what
 * the caller actually wants to change.
 */
class UpdateShipmentRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return 'post/update';
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    public function getPostNumber(): ?string
    {
        $value = $this->getParameter('postNumber');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setPostNumber(string $postNumber): static
    {
        return $this->setParameter('postNumber', $postNumber);
    }

    public function getRecipientAddressId(): int
    {
        return (int) ($this->getParameter('recipientAddressId') ?? 0);
    }

    public function setRecipientAddressId(int $recipientAddressId): static
    {
        return $this->setParameter('recipientAddressId', $recipientAddressId);
    }

    public function getNote(): ?string
    {
        $value = $this->getParameter('note');

        return is_string($value) ? $value : null;
    }

    public function setNote(string $note): static
    {
        return $this->setParameter('note', $note);
    }

    public function getCustomData1(): ?string
    {
        return $this->stringParameter('customData1');
    }

    public function setCustomData1(string $value): static
    {
        return $this->setParameter('customData1', $value);
    }

    public function getCustomData2(): ?string
    {
        return $this->stringParameter('customData2');
    }

    public function setCustomData2(string $value): static
    {
        return $this->setParameter('customData2', $value);
    }

    public function getCustomData3(): ?string
    {
        return $this->stringParameter('customData3');
    }

    public function setCustomData3(string $value): static
    {
        return $this->setParameter('customData3', $value);
    }

    public function getCustomData4(): ?string
    {
        return $this->stringParameter('customData4');
    }

    public function setCustomData4(string $value): static
    {
        return $this->setParameter('customData4', $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        $postNumber = $this->getPostNumber();

        if ($postNumber === null) {
            throw new InvalidRequestException('The postNumber parameter is required');
        }

        $data = ['post_number' => $postNumber];

        $shipFrom = $this->getParameter('shipFrom');
        $shipTo = $this->getShipTo();

        // The API wants either an address book id or the inline fields, not
        // both; an explicitly supplied address wins over the configured id.
        if ($shipFrom instanceof Address) {
            $data['sender'] = self::recipientFromAddress($shipFrom);
        } elseif ($this->getSenderAddressId() > 0) {
            $data['sender'] = ['addressId' => $this->getSenderAddressId()];
        }

        if ($shipTo instanceof Address) {
            $data['recipient'] = self::recipientFromAddress($shipTo);
        } elseif ($this->getRecipientAddressId() > 0) {
            $data['recipient'] = ['addressId' => $this->getRecipientAddressId()];
        }

        $packages = $this->getPackages();

        if (is_array($packages) && $packages !== []) {
            [$desi, $packageCount] = Packages::summarize($packages);

            $data['post'] = [
                'desi' => $desi,
                'package_count' => max(1, $packageCount),
            ];
        }

        if ($this->getNote() !== null) {
            $data['post']['note'] = $this->getNote();
        }

        if ($this->getParameter('barcodeFormat') !== null) {
            $data['barcode_format'] = $this->getBarcodeFormat();
        }

        foreach ([1, 2, 3, 4] as $index) {
            $value = $this->stringParameter('customData' . $index);

            if ($value !== null) {
                $data['custom_data_' . $index] = $value;
            }
        }

        return $data;
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new UpdateShipmentResponse($this, $data);
    }

    private function stringParameter(string $key): ?string
    {
        $value = $this->getParameter($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
