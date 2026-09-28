<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Address;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Navlungo\Support\Packages;
use Omniship\Common\Package;

class CreateShipmentRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return 'post/create';
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    public function getReferenceId(): ?string
    {
        return $this->getParameter('referenceId');
    }

    public function setReferenceId(string $referenceId): static
    {
        return $this->setParameter('referenceId', $referenceId);
    }

    public function getCashOnDelivery(): bool
    {
        return (bool) ($this->getParameter('cashOnDelivery') ?? false);
    }

    public function setCashOnDelivery(bool $cashOnDelivery): static
    {
        return $this->setParameter('cashOnDelivery', $cashOnDelivery);
    }

    public function getCodAmount(): float
    {
        return (float) ($this->getParameter('codAmount') ?? 0.0);
    }

    public function setCodAmount(float|int|string $codAmount): static
    {
        return $this->setParameter('codAmount', (float) $codAmount);
    }

    /**
     * COD collection type as the host app sends it for every carrier:
     * 0 = cash at the door, 1 = credit card at the door.
     */
    public function getCodCollectionType(): string
    {
        return (string) ($this->getParameter('codCollectionType') ?? '0');
    }

    public function setCodCollectionType(string|int $codCollectionType): static
    {
        return $this->setParameter('codCollectionType', (string) $codCollectionType);
    }

    public function getNote(): ?string
    {
        return $this->getParameter('note');
    }

    public function setNote(string $note): static
    {
        return $this->setParameter('note', $note);
    }

    public function getCustomData1(): ?string
    {
        return $this->getParameter('customData1');
    }

    public function setCustomData1(string $value): static
    {
        return $this->setParameter('customData1', $value);
    }

    public function getCustomData2(): ?string
    {
        return $this->getParameter('customData2');
    }

    public function setCustomData2(string $value): static
    {
        return $this->setParameter('customData2', $value);
    }

    public function getCustomData3(): ?string
    {
        return $this->getParameter('customData3');
    }

    public function setCustomData3(string $value): static
    {
        return $this->setParameter('customData3', $value);
    }

    public function getCustomData4(): ?string
    {
        return $this->getParameter('customData4');
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
        $this->validate('username', 'password', 'shipTo');

        if ($this->getSenderAddressId() <= 0) {
            throw new InvalidRequestException('The senderAddressId parameter is required');
        }

        $shipTo = $this->getShipTo();

        if (!$shipTo instanceof Address) {
            throw new InvalidRequestException('The shipTo parameter must be an Omniship address');
        }

        $isCod = $this->getCashOnDelivery();
        [$desi, $packageCount] = Packages::summarize($this->getPackages() ?? []);

        $post = [
            'desi' => $desi > 0 ? $desi : 1.0,
            'package_count' => max(1, $packageCount),
            'price' => '',
            'note' => (string) ($this->getNote() ?? ''),
        ];

        // Navlungo's COD type: 1 = cash at the door, 2 = credit card at the
        // door. Collection is only available to accounts (and carriers) with
        // the COD permission; the API rejects unauthorised attempts itself.
        $codPaymentType = '';

        if ($isCod) {
            $codPaymentType = $this->getCodCollectionType() === '1' ? 2 : 1;
            $post['price'] = round($this->getCodAmount(), 2);
        }

        return [
            'platform' => $this->getPlatform(),
            'posts' => [
                [
                    'reference_id' => (string) ($this->getReferenceId() ?? ''),
                    'carrier_id' => $this->getCarrierId(),
                    'post_type' => $this->getPostType(),
                    'cod_payment_type' => $codPaymentType,
                    'sender' => [
                        'addressId' => $this->getSenderAddressId(),
                    ],
                    'recipient' => self::recipientFromAddress($shipTo),
                    'post' => $post,
                    'barcode_format' => $this->getBarcodeFormat(),
                    'custom_data_1' => (string) ($this->getCustomData1() ?? ''),
                    'custom_data_2' => (string) ($this->getCustomData2() ?? ''),
                    'custom_data_3' => (string) ($this->getCustomData3() ?? ''),
                    'custom_data_4' => (string) ($this->getCustomData4() ?? ''),
                ],
            ],
        ];
    }

    /**
     * Navlungo bills on the shipment-level desi and counts pieces, so fold
     * every package (with its quantity) into those two numbers. Packages
     * without dimensions fall back to their weight.
     *
     * @param Package[] $packages
     * @return array{0: float, 1: int}
     */
    protected static function summarizePackages(array $packages): array
    {
        return Packages::summarize($packages);
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CreateShipmentResponse($this, $data);
    }
}
