<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Message\CancelResponse;

class CancelShipmentResponse extends AbstractNavlungoResponse implements CancelResponse
{
    public function isCancelled(): bool
    {
        return $this->isSuccessful();
    }

    public function getMessage(): ?string
    {
        $payload = $this->payload();
        $error = self::extractError($payload);
        $message = $payload['message'] ?? null;

        if ($error !== '') {
            return $error;
        }

        return is_string($message) && $message !== '' ? $message : null;
    }
}
