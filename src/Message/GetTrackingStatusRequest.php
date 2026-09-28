<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;

/**
 * Track by Navlungo post number (preferred), your own reference, or the
 * carrier's tracking code — the API resolves whichever identifier is sent.
 */
class GetTrackingStatusRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return 'post/check/' . rawurlencode($this->resolveIdentifier());
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        // Surface a missing identifier at getData() time, not only when the
        // endpoint URL is built.
        $this->resolveIdentifier();

        return [];
    }

    private function resolveIdentifier(): string
    {
        $identifier = $this->getTrackingNumber()
            ?? $this->getShipmentId()
            ?? $this->getParameter('referenceId');

        if (!is_string($identifier) || trim($identifier) === '') {
            throw new InvalidRequestException(
                'One of trackingNumber, shipmentId or referenceId is required.',
            );
        }

        return trim($identifier);
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new GetTrackingStatusResponse($this, $data);
    }
}
