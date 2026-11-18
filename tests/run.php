<?php

/**
 * Real WordPress wp_mail + PHPMailer integration checks, with no live services.
 * Usage: php tests/run.php <extracted WordPress directory>
 */
use Grandauto\MicrosoftSmtp\Crypto;
use Grandauto\MicrosoftSmtp\OAuthSmtp;
use Grandauto\MicrosoftSmtp\Plugin;
use Grandauto\MicrosoftSmtp\Tokens;

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$wp = realpath($argv[1] ?? __DIR__.'/../.test-runtime/wordpress');
if (! $wp || ! is_file($wp.'/wp-includes/pluggable.php')) {
    throw new RuntimeException('Pass the path to an extracted WordPress distribution.');
}
define('ABSPATH', $wp.'/');
define('WPINC', 'wp-includes');
$GLOBALS['hooks'] = [];
$GLOBALS['options'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['http_calls'] = [];
$GLOBALS['http_response'] = ['status' => 200, 'body' => json_encode(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600])];
$GLOBALS['salt'] = 'test-only-wordpress-salt';
$GLOBALS['allowed'] = true;
$GLOBALS['nonce_valid'] = true;

// Stubs only for WordPress's surrounding services; the mail pipeline is real.
function add_filter($name, $callback, $priority = 10, $accepted = 1): void
{
    $GLOBALS['hooks'][$name][$priority][] = [$callback, $accepted];
}
function add_action($name, $callback, $priority = 10, $accepted = 1): void
{
    add_filter($name, $callback, $priority, $accepted);
}
function apply_filters($name, $value, ...$args)
{
    $hooks = $GLOBALS['hooks'][$name] ?? [];
    ksort($hooks);
    foreach ($hooks as $callbacks) {
        foreach ($callbacks as [$callback, $accepted]) {
            $value = $callback(...array_slice([$value, ...$args], 0, $accepted));
        }
    }

    return $value;
}
function do_action($name, ...$args): void
{
    $hooks = $GLOBALS['hooks'][$name] ?? [];
    ksort($hooks);
    foreach ($hooks as $callbacks) {
        foreach ($callbacks as [$callback, $accepted]) {
            $callback(...array_slice($args, 0, $accepted));
        }
    }
}
function do_action_ref_array($name, $args): void
{
    do_action($name, ...$args);
}
function get_option($name, $default = false)
{
    return $GLOBALS['options'][$name] ?? $default;
}
function update_option($name, $value, $autoload = null): bool
{
    $GLOBALS['options'][$name] = $value;
    $GLOBALS['autoload'][$name] = $autoload;

    return true;
}
function delete_option($name): bool
{
    unset($GLOBALS['options'][$name]);

    return true;
}
function get_transient($name)
{
    $item = $GLOBALS['transients'][$name] ?? null;

    return $item && $item[1] > time() ? $item[0] : false;
}
function set_transient($name, $value, $ttl): bool
{
    $GLOBALS['transients'][$name] = [$value, time() + $ttl];

    return true;
}
function delete_transient($name): bool
{
    unset($GLOBALS['transients'][$name]);

    return true;
}
function wp_salt($scheme = 'auth'): string
{
    return $GLOBALS['salt'];
}
function wp_remote_post($url, $arguments)
{
    $GLOBALS['http_calls'][] = [$url, $arguments];

    return $GLOBALS['http_response'];
}
function wp_remote_retrieve_body($response): string
{
    return $response['body'];
}
function wp_remote_retrieve_response_code($response): int
{
    return $response['status'];
}
function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}
function is_email($value)
{
    return filter_var($value, FILTER_VALIDATE_EMAIL);
}
function __($message): string
{
    return $message;
}
function get_bloginfo($name): string
{
    return $name === 'charset' ? 'UTF-8' : 'Example';
}
function network_home_url(): string
{
    return 'https://example.com';
}
function wp_parse_url($url, $component = -1)
{
    return parse_url($url, $component);
}
function register_deactivation_hook($file, $callback): void {}
function wp_unslash($value): string
{
    return stripslashes($value);
}
function sanitize_text_field($value): string
{
    return trim(str_replace(["\r", "\n"], ' ', strip_tags($value)));
}
function get_current_user_id(): int
{
    return 7;
}
function admin_url($path): string
{
    return 'https://example.com/wp-admin/'.$path;
}
function wp_safe_redirect($url): bool
{
    $GLOBALS['redirect'] = $url;

    return true;
}
function esc_html($value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function esc_attr($value): string
{
    return esc_html($value);
}
function esc_url($value): string
{
    return esc_html($value);
}
function checked($value): void
{
    echo $value ? 'checked' : '';
}
function wp_nonce_field($action): void
{
    echo '<input type="hidden" name="_wpnonce" value="test-nonce">';
}
function submit_button($label, $type = 'primary'): void
{
    echo '<button>'.esc_html($label).'</button>';
}
function current_user_can($capability): bool
{
    return $GLOBALS['allowed'];
}
function wp_die($message, $title = '', $arguments = []): never
{
    throw new RuntimeException($message);
}
function check_admin_referer($action): void
{
    if (! $GLOBALS['nonce_valid']) {
        throw new RuntimeException('Invalid nonce');
    }
}
class WP_Error
{
    public function __construct(public string $code, public string $message, public $data = null) {}

    public function get_error_message(): string
    {
        return $this->message;
    }
}

require ABSPATH.WPINC.'/PHPMailer/Exception.php';
require ABSPATH.WPINC.'/PHPMailer/PHPMailer.php';
require ABSPATH.WPINC.'/PHPMailer/SMTP.php';
require ABSPATH.WPINC.'/class-wp-phpmailer.php';
require ABSPATH.WPINC.'/pluggable.php';
require ABSPATH.WPINC.'/version.php';
require __DIR__.'/../grandauto-microsoft-smtp/grandauto-microsoft-smtp.php';
require __DIR__.'/../grandauto-microsoft-smtp/includes/OAuthSmtp.php';

class FakeSmtp extends OAuthSmtp
{
    public array $commands = [];

    public array $connection = [];

    public string $message = '';

    public bool $live = false;

    public bool $tls = false;

    public bool $tlsWorks = true;

    public array $methods = ['XOAUTH2'];

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->connection = [$host, $port, $timeout, $options];
        $this->live = true;

        return true;
    }

    public function connected()
    {
        return $this->live;
    }

    public function close()
    {
        $this->live = false;
    }

    public function hello($host = '')
    {
        $this->server_caps = ['EHLO' => 'test', 'STARTTLS' => true, 'AUTH' => $this->methods];

        return true;
    }

    public function startTLS()
    {
        $this->tls = $this->tlsWorks;

        return $this->tlsWorks;
    }

    protected function sendCommand($command, $commandstring, $expect)
    {
        if (str_starts_with($commandstring, 'AUTH') && ! $this->tls) {
            throw new RuntimeException('Authentication before TLS');
        }
        $this->commands[] = $commandstring;
        $this->last_reply = '235 OK';

        return true;
    }

    public function data($msg_data)
    {
        $this->message = $msg_data;

        return true;
    }
}

$checks = 0;
function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$message);
    }
    $GLOBALS['checks']++;
    echo 'PASS '.$message."\n";
}
function rejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        verify(true, $message);

        return;
    }
    verify(false, $message);
}
function freshMailer(): FakeSmtp
{
    $smtp = new FakeSmtp;
    $GLOBALS['phpmailer'] = new WP_PHPMailer(true);
    $GLOBALS['phpmailer']->setSMTPInstance($smtp);

    return $smtp;
}

$settings = [
    'enabled' => true,
    'tenant_id' => 'contoso.onmicrosoft.com',
    'client_id' => '11111111-2222-3333-4444-555555555555',
    'client_secret' => Crypto::encrypt('test-secret'),
    'mailbox' => 'noreply@contoso.com',
    'from_name' => 'Grandauto',
];
update_option(Plugin::OPTION, $settings);
$cipher = Crypto::encrypt('test-secret');
verify($cipher !== 'test-secret' && Crypto::decrypt($cipher) === 'test-secret', 'credential encryption round-trip');
verify($cipher !== Crypto::encrypt('test-secret'), 'encryption uses fresh nonces');
$binary = base64_decode($cipher);
$binary[14] = chr(ord($binary[14]) ^ 1);
rejects(fn () => Crypto::decrypt(base64_encode($binary)), 'modified ciphertext is rejected');
$GLOBALS['salt'] = 'rotated';
rejects(fn () => Crypto::decrypt($cipher), 'rotated salts reject saved credentials');
$GLOBALS['salt'] = 'test-only-wordpress-salt';
rejects(fn () => Plugin::validate(array_replace($settings, ['tenant_id' => '../common'])), 'tenant URL injection is rejected');
rejects(fn () => Plugin::validate(array_replace($settings, ['client_id' => 'invalid'])), 'invalid client IDs are rejected');
rejects(fn () => Plugin::validate(array_replace($settings, ['mailbox' => "mail@contoso.com\r\nInjected: value"])), 'mailbox header injection is rejected');

$smtp = freshMailer();
$attachment = tempnam(sys_get_temp_dir(), 'gac-smtp-test-');
file_put_contents($attachment, 'attachment-content');
verify(wp_mail('recipient@example.com', 'Integration test', '<b>Body</b>', ['From: Visitor <visitor@example.com>', 'Cc: cc@example.com', 'Bcc: bcc@example.com', 'Content-Type: text/html'], [$attachment]), 'real wp_mail sends through real PHPMailer using fake SMTP');
verify($smtp->connection[0] === 'smtp.office365.com' && $smtp->connection[1] === 587 && $smtp->tls, 'Microsoft host and port with STARTTLS');
verify($smtp->connection[3]['ssl']['verify_peer'] && $smtp->connection[3]['ssl']['verify_peer_name'] && ! $smtp->connection[3]['ssl']['allow_self_signed'], 'TLS certificate checks enforced');
$auth = array_values(array_filter($smtp->commands, fn ($command) => str_starts_with($command, 'AUTH XOAUTH2 ')));
verify(count($auth) === 1 && base64_decode(substr($auth[0], 13)) === "user=noreply@contoso.com\x01auth=Bearer test-token\x01\x01", 'real SMTP authentication encodes the expected XOAUTH2 payload');
verify(str_contains($smtp->message, 'From: Grandauto <noreply@contoso.com>') && in_array('MAIL FROM:<noreply@contoso.com>', $smtp->commands, true), 'From and envelope sender locked to mailbox');
verify(in_array('visitor@example.com', array_column($GLOBALS['phpmailer']->getReplyToAddresses(), 0), true), 'caller From is retained as Reply-To');
verify(count($GLOBALS['phpmailer']->getCcAddresses()) === 1 && count($GLOBALS['phpmailer']->getBccAddresses()) === 1 && count($GLOBALS['phpmailer']->getAttachments()) === 1 && str_contains($smtp->message, 'text/html'), 'CC, BCC, HTML and attachment retained');
unlink($attachment);
verify(count($GLOBALS['http_calls']) === 1, 'token requested once for first message');
[$url, $request] = $GLOBALS['http_calls'][0];
verify(str_ends_with($url, '/contoso.onmicrosoft.com/oauth2/v2.0/token') && $request['body']['grant_type'] === 'client_credentials' && $request['body']['scope'] === Tokens::SCOPE && $request['body']['client_secret'] === 'test-secret' && $request['sslverify'] && $request['redirection'] === 0, 'Microsoft request uses correct grant, scope and HTTPS policy');
verify(! str_contains(serialize($GLOBALS['transients']), 'test-token'), 'cached access token encrypted');
$smtp = freshMailer();
verify(wp_mail('recipient@example.com', 'Second', 'Body', ['From: Visitor <visitor@example.com>', 'Reply-To: Help <help@example.com>']), 'second message sends');
verify(count($GLOBALS['http_calls']) === 1 && in_array('help@example.com', array_column($GLOBALS['phpmailer']->getReplyToAddresses(), 0), true) && count($GLOBALS['phpmailer']->getReplyToAddresses()) === 1, 'cached token reused and explicit Reply-To preserved');
$tokenCache = get_transient(Tokens::CACHE);
$tokenCache['expires'] = time() + 299;
set_transient(Tokens::CACHE, $tokenCache, 500);
Tokens::get($settings);
verify(count($GLOBALS['http_calls']) === 2, 'token renewed before expiry');
$changed = array_replace($settings, ['client_secret' => Crypto::encrypt('replacement-secret')]);
Tokens::get($changed);
verify(count($GLOBALS['http_calls']) === 3 && end($GLOBALS['http_calls'])[1]['body']['client_secret'] === 'replacement-secret', 'changed credentials cannot reuse old cached token');

$failures = [];
add_action('wp_mail_failed', static function ($error) use (&$failures): void {
    $failures[] = $error;
});
delete_transient(Tokens::CACHE);
$GLOBALS['http_response'] = ['status' => 401, 'body' => json_encode(['error_description' => 'sensitive test-secret', 'error_codes' => [7000215]])];
$smtp = freshMailer();
verify(wp_mail('recipient@example.com', 'Failure', 'Body') === false && ! $smtp->connection, 'token failure returns false before SMTP, without PHP-mail fallback');
verify(str_contains(end($failures)->message, 'AADSTS7000215') && ! str_contains(end($failures)->message, 'test-secret'), 'safe Microsoft diagnostics reach wp_mail_failed');
$GLOBALS['http_response'] = new WP_Error('http_error', 'private network details');
verify(wp_mail('recipient@example.com', 'Network', 'Body') === false && ! str_contains(end($failures)->message, 'private network'), 'HTTP failure is controlled and redacted');
$GLOBALS['http_response'] = ['status' => 200, 'body' => '{invalid-json'];
verify(wp_mail('recipient@example.com', 'Malformed', 'Body') === false, 'malformed Microsoft response fails closed');
$GLOBALS['http_response'] = ['status' => 200, 'body' => json_encode(['access_token' => 'short-token', 'token_type' => 'Bearer', 'expires_in' => 200])];
Tokens::get($settings);
verify(get_transient(Tokens::CACHE) === false, 'short-lived tokens are not cached past their lifetime');
$GLOBALS['http_response'] = ['status' => 200, 'body' => json_encode(['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600])];
$smtp = freshMailer();
$smtp->tlsWorks = false;
verify(wp_mail('recipient@example.com', 'TLS failure', 'Body') === false && ! array_filter($smtp->commands, fn ($command) => str_starts_with($command, 'AUTH')), 'failed STARTTLS prevents authentication and sending');
$smtp = freshMailer();
$smtp->methods = ['LOGIN', 'PLAIN'];
verify(wp_mail('recipient@example.com', 'No OAuth', 'Body') === false && ! array_filter($smtp->commands, fn ($command) => str_starts_with($command, 'AUTH')), 'missing XOAUTH2 never falls back to password authentication');
update_option(Plugin::OPTION, array_replace($settings, ['client_secret' => 'broken']));
verify(wp_mail('recipient@example.com', 'Unreadable', 'Body') === false, 'unreadable credentials return a normal WordPress mail failure');
update_option(Plugin::OPTION, array_replace($settings, ['enabled' => false]));
verify(Plugin::guard(null, []) === null, 'disabled plugin leaves mail to WordPress');
verify(Plugin::guard(true, []) === true, 'already handled mail is not sent again');
$GLOBALS['allowed'] = false;
rejects(fn () => Plugin::save(), 'non-administrator cannot save credentials');
rejects(fn () => Plugin::test(), 'non-administrator cannot send a test');
$GLOBALS['allowed'] = true;
$GLOBALS['nonce_valid'] = false;
rejects(fn () => Plugin::save(), 'invalid nonce cannot save credentials');
rejects(fn () => Plugin::test(), 'invalid nonce cannot send a test');
Plugin::deactivate();
verify(get_transient(Tokens::CACHE) === false && get_option(Plugin::OPTION) !== false, 'deactivation clears tokens and retains settings');
define('WP_UNINSTALL_PLUGIN', true);
require __DIR__.'/../grandauto-microsoft-smtp/uninstall.php';
verify(get_option(Plugin::OPTION) === false && get_transient(Tokens::CACHE) === false, 'uninstall removes current-site credentials and tokens');
// save() ends with WordPress's normal redirect + exit. Verify its result on shutdown.
update_option(Plugin::OPTION, $settings);
verify(! str_contains(serialize(Plugin::forAdmin()), 'test-secret') && ! str_contains(serialize(Plugin::forAdmin()), $settings['client_id']), 'admin view data contains masks instead of full credentials');
update_option(Plugin::OPTION, array_replace($settings, ['client_secret' => Crypto::encrypt('12345')]));
verify(Plugin::forAdmin()['credential_masks']['client_secret'] === '•••••', 'short secrets are fully masked instead of revealing the entire value');
update_option(Plugin::OPTION, array_replace($settings, ['client_secret' => 'broken']));
verify(str_contains(Plugin::forAdmin()['credential_masks']['client_secret'], 'unreadable'), 'unreadable saved secrets still allow the settings page to render');
update_option(Plugin::OPTION, $settings);
set_transient(Tokens::CACHE, 'cached-before-save', 3600);
$GLOBALS['nonce_valid'] = true;
$_POST = [
    'enabled' => '1',
    'tenant_id' => '',
    'client_id' => '',
    'client_secret' => '',
    'mailbox' => $settings['mailbox'],
    'from_name' => '<b>Updated Name</b>',
];
register_shutdown_function(static function () use ($settings, $wp_version): void {
    $saved = get_option(Plugin::OPTION);
    verify(Crypto::decrypt($saved['client_secret']) === 'test-secret' && $saved['client_secret'] === $settings['client_secret'], 'blank secret on settings save preserves existing encrypted secret');
    verify($saved['tenant_id'] === $settings['tenant_id'] && $saved['client_id'] === $settings['client_id'], 'blank ID fields preserve the saved tenant and client ID');
    verify($saved['from_name'] === 'Updated Name' && $GLOBALS['autoload'][Plugin::OPTION] === false, 'settings sanitized and saved without autoload');
    verify(get_transient(Tokens::CACHE) === false && str_contains($GLOBALS['redirect'], 'options-general.php?page=gac-microsoft-smtp'), 'settings save invalidates tokens and redirects to settings');
    ob_start();
    Plugin::page();
    $html = ob_get_clean();
    verify(str_contains($html, 'name="client_secret" value=""') && ! str_contains($html, 'test-secret') && ! str_contains($html, $settings['client_secret']), 'settings page never renders saved secret or ciphertext');
    verify(! str_contains($html, $settings['tenant_id']) && ! str_contains($html, $settings['client_id']), 'settings page never renders complete saved tenant or client IDs');
    foreach (['tenant_id' => $settings['tenant_id'], 'client_id' => $settings['client_id'], 'client_secret' => 'test-secret'] as $key => $value) {
        verify(str_contains($html, 'Saved: •••••'.substr($value, -5).'.'), 'only the last five characters are shown for '.$key);
    }
    verify(str_contains($html, '<h1>GAH Microsoft OAuth SMTP</h1>'), 'settings use GAH company branding');
    verify(str_contains($html, 'gac_ms_smtp_save') && str_contains($html, 'gac_ms_smtp_test') && str_contains($html, 'Settings saved.'), 'admin settings and test forms render with save result');
    echo "\n".$GLOBALS['checks']." checks passed against WordPress $wp_version, PHP ".PHP_VERSION.". No external mail sent.\n";
});
Plugin::save();
