<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Support;

/**
 * Navlungo expects Turkish mobile numbers in the "+90 532 123 45 67" shape.
 * Anything we cannot confidently normalise is passed through untouched so the
 * API can reject it with its own (more specific) message.
 */
final class Phone
{
    public static function normalize(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return trim($phone);
        }

        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $national = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $national = substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $national = $digits;
        } else {
            return trim($phone);
        }

        return '+90 '
            . substr($national, 0, 3) . ' '
            . substr($national, 3, 3) . ' '
            . substr($national, 6, 2) . ' '
            . substr($national, 8, 2);
    }
}
