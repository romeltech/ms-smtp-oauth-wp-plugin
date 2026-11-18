<?php

namespace Grandauto\MicrosoftSmtp;

use RuntimeException;
use Throwable;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    public const OPTION = 'gac_ms_smtp_settings';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_gac_ms_smtp_save', [self::class, 'save']);
        add_action('admin_post_gac_ms_smtp_test', [self::class, 'test']);
        add_filter('pre_wp_mail', [self::class, 'guard'], PHP_INT_MAX, 2);
        add_action('phpmailer_init', [self::class, 'configure'], PHP_INT_MAX);
    }

    public static function settings(): array
    {
        $saved = get_option(self::OPTION, []);

        return array_merge([
            'enabled' => false,
            'tenant_id' => '',
            'client_id' => '',
            'client_secret' => '',
            'mailbox' => '',
            'from_name' => '',
        ], is_array($saved) ? $saved : []);
    }

    public static function validate(array $settings): void
    {
        if (! preg_match('/\A(?:[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}|(?=.{1,253}\z)[a-z0-9]+(?:[.-][a-z0-9]+)*\.[a-z]{2,})\z/i', $settings['tenant_id'])) {
            throw new RuntimeException('Enter a tenant GUID or a tenant domain such as contoso.onmicrosoft.com.');
        }
        if (! preg_match('/\A[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}\z/i', $settings['client_id'])) {
            throw new RuntimeException('Enter the Application (client) ID as a GUID.');
        }
        if (! is_email($settings['mailbox']) || preg_match('/[\r\n\x01]/', $settings['mailbox'])) {
            throw new RuntimeException('Enter a valid Microsoft 365 mailbox address.');
        }
        if ($settings['client_secret'] === '' || Crypto::decrypt($settings['client_secret']) === '') {
            throw new RuntimeException('Enter the client secret Value from Microsoft Entra.');
        }
    }

    public static function forAdmin(): array
    {
        $settings = self::settings();
        $masks = [];
        foreach (['tenant_id', 'client_id', 'client_secret'] as $key) {
            $value = $settings[$key];
            try {
                if ($key === 'client_secret' && $value !== '') {
                    $value = Crypto::decrypt($value);
                }
                // Do not expose a whole credential if an unusually short value was saved.
                $masks[$key] = $value === '' ? '' : '•••••'.(strlen($value) > 5 ? substr($value, -5) : '');
            } catch (Throwable) {
                $masks[$key] = 'Saved value unreadable; enter a replacement';
            }
        }

        return [
            'enabled' => $settings['enabled'],
            'mailbox' => $settings['mailbox'],
            'from_name' => $settings['from_name'],
            'credential_masks' => $masks,
        ];
    }

    public static function guard($result, array $mail)
    {
        // Respect mail already handled by another pre_wp_mail integration.
        if ($result !== null || ! self::settings()['enabled']) {
            return $result;
        }
        try {
            self::validate(self::settings());
            Tokens::get(self::settings());
        } catch (Throwable $error) {
            do_action('wp_mail_failed', new WP_Error('gac_ms_smtp_configuration', $error->getMessage()));

            return false;
        }

        return null;
    }

    public static function configure($mailer): void
    {
        $settings = self::settings();
        if (! $settings['enabled']) {
            return;
        }

        require_once ABSPATH.WPINC.'/PHPMailer/OAuthTokenProvider.php';
        require_once __DIR__.'/TokenProvider.php';
        require_once __DIR__.'/OAuthSmtp.php';

        // Close a reused connection so changing credentials never reuses an old identity.
        $mailer->smtpClose();
        if (! $mailer->getSMTPInstance() instanceof OAuthSmtp) {
            $mailer->setSMTPInstance(new OAuthSmtp);
        }
        $mailer->isSMTP();
        $mailer->Host = 'smtp.office365.com';
        $mailer->Port = 587;
        $mailer->SMTPSecure = 'tls';
        $mailer->SMTPAutoTLS = true;
        $mailer->SMTPAuth = true;
        $mailer->AuthType = 'XOAUTH2';
        $mailer->Username = $settings['mailbox'];
        $mailer->Password = '';
        $mailer->Timeout = 20;
        $mailer->getSMTPInstance()->Timelimit = 30;
        $mailer->SMTPKeepAlive = false;
        $mailer->SMTPDebug = 0;
        $mailer->SMTPOptions = ['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0),
        ]];
        $mailer->setOAuth(new TokenProvider($settings));

        // Preserve a caller's From as Reply-To only when it supplied no Reply-To.
        if (strcasecmp($mailer->From, $settings['mailbox']) !== 0 && ! $mailer->getReplyToAddresses()) {
            $mailer->addReplyTo($mailer->From, $mailer->FromName);
        }
        $mailer->From = $settings['mailbox'];
        $mailer->FromName = $settings['from_name'] !== '' ? $settings['from_name'] : $mailer->FromName;
        $mailer->Sender = $settings['mailbox'];
    }

    public static function menu(): void
    {
        add_options_page('GAH Microsoft OAuth SMTP', 'GAH Microsoft OAuth SMTP', 'manage_options', 'gac-microsoft-smtp', [self::class, 'page']);
    }

    private static function authorize(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to manage Microsoft SMTP.', '', ['response' => 403]);
        }
        check_admin_referer($action);
    }

    private static function input(string $key): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    }

    public static function save(): void
    {
        self::authorize('gac_ms_smtp_save');
        try {
            $settings = self::settings();
            $settings['enabled'] = self::input('enabled') === '1';
            foreach (['mailbox', 'from_name'] as $key) {
                $settings[$key] = sanitize_text_field(self::input($key));
            }
            foreach (['tenant_id', 'client_id'] as $key) {
                $replacement = sanitize_text_field(self::input($key));
                if ($replacement !== '') {
                    $settings[$key] = $replacement;
                }
            }
            $secret = self::input('client_secret');
            if ($secret !== '') {
                $settings['client_secret'] = Crypto::encrypt($secret);
            }
            if ($settings['enabled']) {
                self::validate($settings);
            }
            update_option(self::OPTION, $settings, false);
            delete_transient(Tokens::CACHE);
            self::notice(true, 'Settings saved. Send a test email to verify the connection.');
        } catch (Throwable $error) {
            self::notice(false, $error->getMessage());
        }
    }

    public static function test(): void
    {
        self::authorize('gac_ms_smtp_test');
        $recipient = self::input('test_to');
        if (! is_email($recipient)) {
            self::notice(false, 'Enter a valid test recipient.');
        }
        if (! self::settings()['enabled']) {
            self::notice(false, 'Save settings with Microsoft SMTP enabled before sending a test.');
        }

        $failure = '';
        $capture = static function ($error) use (&$failure): void {
            $failure = $error->get_error_message();
        };
        add_action('wp_mail_failed', $capture);
        try {
            // Force a fresh token to verify credentials and consent, not just the cache.
            delete_transient(Tokens::CACHE);
            $sent = wp_mail($recipient, 'GAH Microsoft OAuth SMTP test', 'This test email was sent from WordPress through GAH Microsoft OAuth SMTP.');
        } catch (Throwable) {
            $sent = false;
        } finally {
            remove_action('wp_mail_failed', $capture);
        }

        // Only store our controlled messages, never arbitrary third-party mailer output.
        if (! $sent && (str_starts_with($failure, 'Microsoft sign-in failed') || str_starts_with($failure, 'Could not reach Microsoft') || str_starts_with($failure, 'Saved Microsoft SMTP'))) {
            self::notice(false, $failure);
        }
        self::notice((bool) $sent, $sent ? 'Test accepted by the mail transport. Check the inbox and spam folder; acceptance does not guarantee delivery.' : 'Test failed. Check the saved credentials, Exchange mailbox permissions, SMTP AUTH policy and outbound port 587.');
    }

    private static function notice(bool $success, string $message): void
    {
        set_transient('gac_ms_smtp_notice_'.get_current_user_id(), ['success' => $success, 'message' => $message], 120);
        wp_safe_redirect(admin_url('options-general.php?page=gac-microsoft-smtp'));
        exit;
    }

    public static function page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $settings = self::forAdmin();
        $noticeKey = 'gac_ms_smtp_notice_'.get_current_user_id();
        $notice = get_transient($noticeKey);
        delete_transient($noticeKey);
        require __DIR__.'/../views/settings.php';
    }

    public static function deactivate(): void
    {
        delete_transient(Tokens::CACHE);
        // Settings survive deactivation. Uninstall explicitly removes this site's data.
    }
}
