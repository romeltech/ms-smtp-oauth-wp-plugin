<?php

namespace Grandauto\MicrosoftSmtp;

use RuntimeException;

if (! defined('ABSPATH')) {
    exit;
}

final class Crypto
{
    private static function key(): string
    {
        if (! function_exists('openssl_encrypt')) {
            throw new RuntimeException('The PHP OpenSSL extension is required.');
        }

        return hash('sha256', wp_salt('auth').'|grandauto-microsoft-smtp-v1', true);
    }

    public static function encrypt(string $value): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false) {
            throw new RuntimeException('Could not encrypt Microsoft SMTP credentials.');
        }

        return base64_encode($iv.$tag.$encrypted);
    }

    public static function decrypt(string $value): string
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) < 29) {
            throw new RuntimeException('Saved Microsoft SMTP credentials are unreadable. Re-enter the client secret.');
        }

        $plain = openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($decoded, 0, 12), substr($decoded, 12, 16));
        if ($plain === false) {
            throw new RuntimeException('Saved Microsoft SMTP credentials are unreadable. Re-enter the client secret after changing WordPress salts.');
        }

        return $plain;
    }
}
