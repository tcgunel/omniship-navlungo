<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Enum\LabelFormat;
use Omniship\Common\Label;
use Omniship\Common\Message\RequestInterface;

/**
 * The barcode endpoint answers with a hosted URL plus (for pdf/html) inline
 * content. `barcode_pdf` is base64-encoded; decode it into an Omniship Label
 * so the host app can store it exactly like any other carrier label.
 */
class GetBarcodeResponse extends AbstractNavlungoResponse
{
    public function __construct(
        RequestInterface $request,
        mixed $data,
        private readonly ?string $postNumber = null,
    ) {
        parent::__construct($request, $data);
    }

    public function getBarcodeType(): ?string
    {
        return $this->string('barcode_type');
    }

    public function getBarcodeUrl(): ?string
    {
        return $this->string('barcode_url');
    }

    /**
     * Raw base64 payload as returned by the API (may be an empty string for
     * formats that only come back as a URL).
     */
    public function getBarcodePdf(): ?string
    {
        return $this->string('barcode_pdf');
    }

    public function getBarcodeHtml(): ?string
    {
        return $this->string('barcode_html');
    }

    public function getPostNumber(): ?string
    {
        return $this->postNumber;
    }

    /**
     * Decoded label content when the API returned inline base64, otherwise
     * null (fall back to getBarcodeUrl()).
     */
    public function getLabel(): ?Label
    {
        $base64 = $this->getBarcodePdf();

        if ($base64 === null) {
            return null;
        }

        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            return null;
        }

        return new Label(
            trackingNumber: $this->postNumber ?? '',
            content: $binary,
            format: LabelFormat::PDF,
        );
    }

    private function string(string $key): ?string
    {
        $payload = $this->payload();

        // Barcode payloads live one level down under `data`.
        if (is_array($payload['data'] ?? null)) {
            $payload = $payload['data'];
        }

        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
