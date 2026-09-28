<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;

class GetAddressRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return 'address-book/get/' . $this->resolveAddressId();
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

        $this->resolveAddressId();

        return [];
    }

    private function resolveAddressId(): int
    {
        $addressId = (int) ($this->getParameter('addressId') ?? 0);

        if ($addressId <= 0) {
            throw new InvalidRequestException('The addressId parameter is required');
        }

        return $addressId;
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new GetAddressResponse($this, $data);
    }
}
