<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

use Omniship\Common\Address;
use Omniship\Common\Exception\HttpException;
use Omniship\Common\Message\AbstractHttpRequest;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Navlungo\Support\Phone;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

/**
 * Shared plumbing for every Navlungo Domestic request: Bearer token handling
 * (with optional PSR-16 caching) and JSON request/response transport.
 *
 * Navlungo always answers JSON: 2xx on success, 401/404/422/500 on failure,
 * with the business verdict carried in a `status` boolean plus `error` and/or
 * `message` fields. The HTTP status is passed to responses as `data.status`
 * so they can report it via getCode().
 */
abstract class AbstractNavlungoRequest extends AbstractHttpRequest
{
    private const TOKEN_PATH = '/auth/api';

    /**
     * Navlungo tokens are valid for 8 hours. Without an explicit expiry we
     * cache for 7h55m so in-flight requests never race the boundary.
     */
    private const TOKEN_CACHE_FALLBACK_TTL = 8 * 3600 - 300;

    private const TOKEN_CACHE_BUFFER_SECONDS = 300;

    abstract protected function getEndpoint(): string;

    abstract protected function getHttpMethod(): string;

    abstract protected function createResponse(mixed $data): ResponseInterface;

    public function getUsername(): string
    {
        return (string) $this->getParameter('username');
    }

    public function setUsername(string $username): static
    {
        return $this->setParameter('username', $username);
    }

    public function getPassword(): string
    {
        return (string) $this->getParameter('password');
    }

    public function setPassword(string $password): static
    {
        return $this->setParameter('password', $password);
    }

    public function getPlatform(): string
    {
        return (string) ($this->getParameter('platform') ?? '');
    }

    public function setPlatform(string $platform): static
    {
        return $this->setParameter('platform', $platform);
    }

    public function getSenderAddressId(): int
    {
        return (int) ($this->getParameter('senderAddressId') ?? 0);
    }

    public function setSenderAddressId(int $senderAddressId): static
    {
        return $this->setParameter('senderAddressId', $senderAddressId);
    }

    public function getCarrierId(): int
    {
        return (int) ($this->getParameter('carrierId') ?? 1);
    }

    public function setCarrierId(int $carrierId): static
    {
        return $this->setParameter('carrierId', $carrierId);
    }

    public function getPostType(): int
    {
        return (int) ($this->getParameter('postType') ?? 2);
    }

    public function setPostType(int $postType): static
    {
        return $this->setParameter('postType', $postType);
    }

    public function getBarcodeFormat(): string
    {
        return (string) ($this->getParameter('barcodeFormat') ?? 'pdf-A5');
    }

    public function setBarcodeFormat(string $barcodeFormat): static
    {
        return $this->setParameter('barcodeFormat', $barcodeFormat);
    }

    public function getTokenCache(): ?CacheInterface
    {
        $cache = $this->getParameter('tokenCache');

        return $cache instanceof CacheInterface ? $cache : null;
    }

    public function setTokenCache(?CacheInterface $cache): static
    {
        return $this->setParameter('tokenCache', $cache);
    }

    protected function getBaseUrl(): string
    {
        return $this->getTestMode()
            ? 'https://domestic-api-qa.navlungo.com/v2.1'
            : 'https://domestic-api.navlungo.com/v2.1';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $url = $this->getBaseUrl() . '/' . ltrim($this->getEndpoint(), '/');

        $headers = [
            'X-localization' => 'tr',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->fetchToken(),
        ];

        $body = null;

        if ($data !== []) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $response = $this->sendHttpRequest(
            method: $this->getHttpMethod(),
            url: $url,
            headers: $headers,
            body: $body,
        );

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();
        $decoded = $responseBody === '' ? null : json_decode($responseBody, true);

        if (!is_array($decoded)) {
            throw new HttpException(
                "Navlungo API request to {$url} failed with HTTP {$statusCode}: {$responseBody}",
                statusCode: $statusCode,
                responseBody: $responseBody,
            );
        }

        return $this->response = $this->createResponse([
            'status' => $statusCode,
            'body' => $decoded,
        ]);
    }

    /**
     * Mint (or reuse) the Bearer token every non-auth endpoint needs.
     */
    protected function fetchToken(): string
    {
        $cache = $this->getTokenCache();
        $cacheKey = $this->buildTokenCacheKey();

        if ($cache !== null) {
            try {
                $cached = $cache->get($cacheKey);
            } catch (CacheInvalidArgumentException) {
                $cached = null;
            }

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $url = $this->getBaseUrl() . self::TOKEN_PATH;

        $response = $this->sendHttpRequest(
            method: 'POST',
            url: $url,
            headers: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-localization' => 'tr',
            ],
            body: json_encode([
                'username' => $this->getUsername(),
                'password' => $this->getPassword(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();
        $decoded = $responseBody === '' ? null : json_decode($responseBody, true);

        $token = is_array($decoded) ? self::extractToken($decoded) : null;

        if ($token === null) {
            $error = is_array($decoded) ? self::extractError($decoded) : $responseBody;

            throw new HttpException(
                'Navlungo auth failed with HTTP ' . $statusCode . ($error !== '' ? ': ' . $error : ''),
                statusCode: $statusCode,
                responseBody: $responseBody,
            );
        }

        if ($cache !== null) {
            try {
                $cache->set($cacheKey, $token, self::resolveTokenTtl($decoded));
            } catch (CacheInvalidArgumentException) {
                // Cache write failures are non-fatal — the token is still valid.
            }
        }

        return $token;
    }

    /**
     * Scope the cache per environment and username so two shops (or the same
     * shop's QA + production accounts) can never see each other's tokens.
     */
    private function buildTokenCacheKey(): string
    {
        $env = $this->getTestMode() ? 'test' : 'prod';
        $hash = sha1($this->getUsername());

        return "omniship_navlungo_token_{$env}_{$hash}";
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private static function extractToken(array $decoded): ?string
    {
        $data = $decoded['data'] ?? null;

        if (!is_array($data)) {
            return null;
        }

        $token = $data['access_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * The API reports `expires_in` as an absolute datetime string
     * ("2026-09-29 02:55:54"), not a number of seconds.
     *
     * @param array<string, mixed> $decoded
     */
    private static function resolveTokenTtl(array $decoded): int
    {
        $data = $decoded['data'] ?? null;

        if (!is_array($data)) {
            return self::TOKEN_CACHE_FALLBACK_TTL;
        }

        $expiresAt = $data['expires_in'] ?? null;

        if (!is_string($expiresAt) || $expiresAt === '') {
            return self::TOKEN_CACHE_FALLBACK_TTL;
        }

        $timestamp = strtotime($expiresAt);

        if ($timestamp === false) {
            return self::TOKEN_CACHE_FALLBACK_TTL;
        }

        return max(60, min(
            self::TOKEN_CACHE_FALLBACK_TTL,
            $timestamp - time() - self::TOKEN_CACHE_BUFFER_SECONDS,
        ));
    }

    /**
     * Flatten Navlungo's `error` field (string or per-field validation map)
     * into something safe to show a merchant.
     *
     * @param array<string, mixed> $decoded
     */
    protected static function extractError(array $decoded): string
    {
        $error = $decoded['error'] ?? null;
        $message = $decoded['message'] ?? null;

        if ($error === null || $error === '') {
            return is_string($message) ? $message : '';
        }

        if (is_string($error)) {
            return $error;
        }

        if (is_array($error)) {
            $parts = [];

            foreach ($error as $field => $messages) {
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

            return implode(' ', $parts);
        }

        return '';
    }

    /**
     * Build Navlungo's recipient object from an Omniship address.
     *
     * @return array<string, mixed>
     */
    protected static function recipientFromAddress(Address $address): array
    {
        return [
            'name' => $address->name ?? '',
            'phone' => Phone::normalize($address->phone),
            'email' => $address->email ?? '',
            'address' => trim(implode(' ', array_filter([$address->street1, $address->street2]))),
            'country' => strtolower($address->country ?? 'tr') ?: 'tr',
            'city' => $address->city ?? '',
            'district' => $address->district ?? '',
            'post_code' => $address->postalCode ?? '',
        ];
    }
}
