<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;

/**
 * Fetches the printable label. `pdf` is the format every carrier supports;
 * `zpl`, `zpl-10` and `zpl-pure` are carrier-specific (and zpl-pure needs an
 * account permission) — see the README.
 */
class GetBarcodeRequest extends AbstractNavlungoRequest
{
    /** @var string[] */
    public const BARCODE_TYPES = ['pdf', 'zpl', 'zpl-10', 'zpl-pure'];

    protected function getEndpoint(): string
    {
        return 'barcode/getBarcode';
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    public function getBarcodeType(): string
    {
        return (string) ($this->getParameter('barcodeType') ?? 'pdf');
    }

    public function setBarcodeType(string $barcodeType): static
    {
        return $this->setParameter('barcodeType', $barcodeType);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        return [
            'post_number' => $this->resolvePostNumber(),
            'barcode_type' => $this->getBarcodeType(),
        ];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new GetBarcodeResponse($this, $data, $this->resolvePostNumber());
    }

    private function resolvePostNumber(): string
    {
        $postNumber = $this->getParameter('postNumber')
            ?? $this->getShipmentId()
            ?? $this->getTrackingNumber();

        if (!is_string($postNumber) || trim($postNumber) === '') {
            throw new InvalidRequestException('The postNumber parameter is required');
        }

        return trim($postNumber);
    }
}
