<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Message\ResponseInterface;

class GetAddressesRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return 'address-book/getAll';
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    public function getAddressType(): string
    {
        return (string) ($this->getParameter('addressType') ?? 'sender');
    }

    public function setAddressType(string $addressType): static
    {
        return $this->setParameter('addressType', $addressType);
    }

    public function getLimit(): int
    {
        return (int) ($this->getParameter('limit') ?? 50);
    }

    public function setLimit(int $limit): static
    {
        return $this->setParameter('limit', $limit);
    }

    public function getPage(): int
    {
        return (int) ($this->getParameter('page') ?? 1);
    }

    public function setPage(int $page): static
    {
        return $this->setParameter('page', $page);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        return [
            'limit' => $this->getLimit(),
            'page' => $this->getPage(),
            'filters' => [
                'address_type' => $this->getAddressType(),
            ],
        ];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new GetAddressesResponse($this, $data);
    }
}
