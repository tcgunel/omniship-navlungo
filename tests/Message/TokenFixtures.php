<?php

declare(strict_types=1);

/**
 * Shared request/response fixtures for the Navlungo test suite.
 * Function names are prefixed to avoid collisions in the single Pest process.
 */

function navlungoAuthOk(string $token = 'test-token', ?string $expiresAt = null): array
{
    return [
        'body' => json_encode([
            'status' => true,
            'message' => 'Oturum başarıyla açıldı.',
            'data' => [
                'token_type' => 'Bearer',
                'expires_in' => $expiresAt ?? date('Y-m-d H:i:s', time() + 8 * 3600),
                'access_token' => $token,
            ],
        ], JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}

function navlungoAuthInvalid(): array
{
    return [
        'body' => json_encode([
            'status' => false,
            'error' => 'Geçersiz kullanıcı bilgileri.',
        ], JSON_THROW_ON_ERROR),
        'status' => 422,
    ];
}

function navlungoCreateOk(array $overrides = []): array
{
    return [
        'body' => json_encode(array_replace_recursive([
            'status' => true,
            'message' => null,
            'post_number' => 'MFYS29970',
            'reference_id' => 'OMN-1234567890',
            'tracking_url' => 'https://domestic-track.navlungo.com/check/MFYS29970',
            'barcode_url' => 'https://domestic-qa-barcode.navlungo.com/MFYS29970.pdf',
            'post' => [
                'carrier_id' => 9,
                'carrier_name' => 'Sürat Kargo',
                'post_type' => 2,
                'post_type_name' => 'Standart Teslimat',
            ],
        ], $overrides), JSON_THROW_ON_ERROR),
        'status' => 201,
    ];
}

function navlungoTrackingOk(array $overrides = []): array
{
    return [
        'body' => json_encode(array_replace_recursive([
            'status' => true,
            'message' => null,
            'data' => [
                'post_number' => 'MFYS29970',
                'reference_id' => 'OMN-1234567890',
                'tracking_url' => 'https://domestic-track.navlungo.com/check/MFYS29970',
                'carrier_tracking_code' => '3547896654321',
                'carrier_tracking_url' => 'https://kargotakip.mngkargo.com.tr/?takipNo=XXX',
                'barcode_status' => 1,
                'post' => [
                    'carrier_id' => 9,
                    'carrier_name' => 'Sürat Kargo',
                    'carrier_status' => 1,
                    'post_type' => 2,
                    'post_type_name' => 'Standart Teslimat',
                ],
                'status' => [
                    'status_code' => 2,
                    'status_name' => 'Teslim Edildi',
                    'picked_up_date' => '2026-08-21 14:27:37',
                    'delivered_date' => '2026-08-22 09:47:41',
                    'delivered_person_name' => 'Ayşe Yılmaz',
                    'cancel_date' => null,
                ],
                'logs' => [
                    [
                        'status_code' => 2,
                        'action' => 'webhook_delivered',
                        'action_result' => 'Teslim Edildi',
                        'created_at' => '2026-08-22 12:47:41',
                    ],
                    [
                        'status_code' => 4,
                        'action' => 'webhook_out_for_delivery',
                        'action_result' => 'Yola Çıktı',
                        'created_at' => '2026-08-22 11:27:52',
                    ],
                    [
                        'status_code' => 16,
                        'action' => 'webhook_picked_up',
                        'action_result' => 'Teslim Alındı',
                        'created_at' => '2026-08-21 17:27:37',
                    ],
                ],
            ],
        ], $overrides), JSON_THROW_ON_ERROR),
        'status' => 200,
    ];
}
