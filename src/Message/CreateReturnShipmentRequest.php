<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Address;
use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;

/**
 * Return pickup (post_type = 3) — the consumer is the shipper and the
 * merchant's return warehouse, which must exist in Navlungo's address book,
 * is the recipient. The sender address travels inline: the consumer's own
 * address is not in the address book.
 */
class CreateReturnShipmentRequest extends CreateShipmentRequest
{
    protected function getEndpoint(): string
    {
        return 'post/return';
    }

    public function getRecipientAddressId(): int
    {
        return (int) ($this->getParameter('recipientAddressId') ?? 0);
    }

    public function setRecipientAddressId(int $recipientAddressId): static
    {
        return $this->setParameter('recipientAddressId', $recipientAddressId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'shipFrom');

        if ($this->getRecipientAddressId() <= 0) {
            throw new InvalidRequestException('The recipientAddressId parameter is required');
        }

        $shipFrom = $this->getShipFrom();

        if (!$shipFrom instanceof Address) {
            throw new InvalidRequestException('The shipFrom parameter must be an Omniship address');
        }

        [$desi, $packageCount] = self::summarizePackages($this->getPackages() ?? []);

        $post = [
            'desi' => $desi > 0 ? $desi : 1.0,
            'package_count' => max(1, $packageCount),
            'price' => '',
            'note' => (string) ($this->getNote() ?? ''),
        ];

        // Returns have their own post type and cannot be COD.
        return [
            'platform' => $this->getPlatform(),
            'posts' => [
                [
                    'reference_id' => (string) ($this->getReferenceId() ?? ''),
                    'carrier_id' => $this->getCarrierId(),
                    'post_type' => 3,
                    'cod_payment_type' => '',
                    'sender' => self::recipientFromAddress($shipFrom),
                    'recipient' => [
                        'addressId' => $this->getRecipientAddressId(),
                    ],
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

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CreateShipmentResponse($this, $data);
    }
}
