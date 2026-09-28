<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

class DeleteAddressResponse extends AbstractNavlungoResponse
{
    public function getMessage(): ?string
    {
        $payload = $this->payload();
        $message = $payload['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        $error = self::extractError($payload);

        return $error !== '' ? $error : null;
    }
}
