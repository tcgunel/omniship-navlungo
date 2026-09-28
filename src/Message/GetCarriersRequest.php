<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Message\ResponseInterface;

/**
 * Lists the cargo agreements. `mine = true` returns only the agreements the
 * merchant has connected (carrier/my-carriers); `false` lists every carrier
 * Navlungo works with (carrier/getAll).
 */
class GetCarriersRequest extends AbstractNavlungoRequest
{
    protected function getEndpoint(): string
    {
        return $this->isMine() ? 'carrier/my-carriers' : 'carrier/getAll';
    }

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    public function isMine(): bool
    {
        return (bool) ($this->getParameter('mine') ?? true);
    }

    public function setMine(bool $mine): static
    {
        return $this->setParameter('mine', $mine);
    }

    public function getLimit(): int
    {
        return (int) ($this->getParameter('limit') ?? 50);
    }

    public function setLimit(int $limit): static
    {
        return $this->setParameter('limit', $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        return [
            'limit' => $this->getLimit(),
        ];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new GetCarriersResponse($this, $data);
    }
}
