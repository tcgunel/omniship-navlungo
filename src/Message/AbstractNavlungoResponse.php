<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Message\AbstractResponse;

/**
 * Navlungo answers every endpoint with `{status: bool, message, error, data}`.
 * The transport wrapper stores the HTTP status and the decoded body, so
 * responses can decide success from the envelope and surface the carrier's
 * own wording through getMessage().
 */
abstract class AbstractNavlungoResponse extends AbstractResponse
{
    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        if (!is_array($this->data)) {
            return [];
        }

        $body = $this->data['body'] ?? null;

        return is_array($body) ? $body : [];
    }

    protected function httpStatus(): int
    {
        if (!is_array($this->data)) {
            return 0;
        }

        return (int) ($this->data['status'] ?? 0);
    }

    public function getCode(): ?string
    {
        $status = $this->httpStatus();

        return $status > 0 ? (string) $status : null;
    }

    public function isSuccessful(): bool
    {
        return ($this->payload()['status'] ?? false) === true;
    }

    public function getMessage(): ?string
    {
        $message = self::extractError($this->payload());

        return $message !== '' ? $message : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function extractError(array $payload): string
    {
        $error = $payload['error'] ?? null;
        $message = $payload['message'] ?? null;

        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (is_array($error)) {
            $parts = [];

            foreach ($error as $messages) {
                if (is_string($messages)) {
                    $parts[] = $messages;
                } elseif (is_array($messages)) {
                    foreach ($messages as $item) {
                        if (is_string($item)) {
                            $parts[] = $item;
                        }
                    }
                }
            }

            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        return is_string($message) ? $message : '';
    }
}
