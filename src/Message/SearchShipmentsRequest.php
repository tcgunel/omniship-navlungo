<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Exception\InvalidRequestException;
use Omniship\Common\Message\ResponseInterface;

/**
 * Detailed search (`POST /post/check`): up to 50 posts per call, newest
 * first, matched by any combination of identifiers. At least one filter is
 * required by the API.
 */
class SearchShipmentsRequest extends AbstractNavlungoRequest
{
    /** @var string[] */
    public const FILTERS = [
        'postNumber',
        'referenceId',
        'senderName',
        'senderPhone',
        'senderEmail',
        'recipientName',
        'recipientPhone',
        'recipientEmail',
    ];

    protected function getEndpoint(): string
    {
        return 'post/check';
    }

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    public function getLimit(): int
    {
        return (int) ($this->getParameter('limit') ?? 50);
    }

    public function setLimit(int $limit): static
    {
        return $this->setParameter('limit', min(50, max(1, $limit)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password');

        $filterMap = [
            'postNumber'      => 'post_number',
            'referenceId'     => 'reference_id',
            'senderName'      => 'sender_name',
            'senderPhone'     => 'sender_phone',
            'senderEmail'     => 'sender_email',
            'recipientName'   => 'recipient_name',
            'recipientPhone'  => 'recipient_phone',
            'recipientEmail'  => 'recipient_email',
        ];

        $post = [];

        foreach ($filterMap as $parameter => $field) {
            $value = $this->getParameter($parameter);

            if (is_string($value) && $value !== '') {
                $post[$field] = $value;
            }
        }

        if ($post === []) {
            throw new InvalidRequestException(
                'At least one search filter is required (postNumber, referenceId, sender*, recipient*).',
            );
        }

        return [
            'post'  => $post,
            'limit' => $this->getLimit(),
        ];
    }

    protected function createResponse(mixed $data): ResponseInterface
    {
        return new SearchShipmentsResponse($this, $data);
    }
}
