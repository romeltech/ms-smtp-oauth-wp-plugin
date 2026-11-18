<?php

namespace Grandauto\MicrosoftSmtp;

use RuntimeException;
use Throwable;

if (! defined('ABSPATH')) {
    exit;
}

final class Tokens
{
    public const CACHE = 'gac_ms_smtp_token';

    public const SCOPE = 'https://outlook.office365.com/.default';

    public static function get(array $settings): string
    {
        $secret = Crypto::decrypt($settings['client_secret']);
        $fingerprint = hash_hmac('sha256', $settings['tenant_id'].'|'.$settings['client_id'].'|'.$secret, wp_salt('auth'));
        $cached = get_transient(self::CACHE);
        if (is_array($cached) && ($cached['key'] ?? '') === $fingerprint && ($cached['expires'] ?? 0) > time() + 300) {
            try {
                return Crypto::decrypt($cached['token']);
            } catch (Throwable) {
                delete_transient(self::CACHE);
            }
        }

        $response = wp_remote_post('https://login.microsoftonline.com/'.rawurlencode($settings['tenant_id']).'/oauth2/v2.0/token', [
            'timeout' => 15,
            'redirection' => 0,
            'sslverify' => true,
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $settings['client_id'],
                'client_secret' => $secret,
                'scope' => self::SCOPE,
            ],
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException('Could not reach Microsoft sign-in. Check outbound HTTPS and DNS on the WordPress host.');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200 || ! is_array($body) || ! is_string($body['access_token'] ?? null) || $body['access_token'] === '' || preg_match('/[\x00-\x20\x7f]/', $body['access_token']) || strcasecmp((string) ($body['token_type'] ?? ''), 'Bearer') !== 0 || ! is_numeric($body['expires_in'] ?? null) || (int) $body['expires_in'] <= 0) {
            // Never echo Microsoft's response: it can contain identifiers or credentials.
            $codes = is_array($body) && is_array($body['error_codes'] ?? null) ? $body['error_codes'] : [];
            $code = isset($codes[0]) && is_numeric($codes[0]) ? ' AADSTS'.(int) $codes[0].'.' : '';
            throw new RuntimeException('Microsoft sign-in failed (HTTP '.(int) $status.').'.$code.' Check the tenant, client secret, SMTP.SendAsApp permission and admin consent.');
        }

        $lifetime = min((int) $body['expires_in'], 86400);
        if ($lifetime > 300) {
            set_transient(self::CACHE, [
                'key' => $fingerprint,
                'token' => Crypto::encrypt($body['access_token']),
                'expires' => time() + $lifetime,
            ], $lifetime - 300);
        }

        return $body['access_token'];
    }
}
