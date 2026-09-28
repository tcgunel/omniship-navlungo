<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;

class CancelShipmentRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return 'post/cancel';
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        return [
            'post_number' => $this->resolvePostNumber(),
        ];
    }

    /**
     * Accepts the Navlungo post number under any of the names host apps use
     * for a shipment identifier.
     */
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

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new CancelShipmentResponse($this, $data);
    }
}
