<?php

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>GAH Microsoft OAuth SMTP</h1>
    <p>Send WordPress mail through Microsoft 365 using app-only OAuth. No interactive sign-in or redirect URI is needed. Personal Outlook.com accounts are not supported.</p>
    <?php if (is_array($notice)) { ?>
        <div class="notice <?php echo $notice['success'] ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html($notice['message']); ?></p></div>
    <?php } ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="gac_ms_smtp_save">
        <?php wp_nonce_field('gac_ms_smtp_save'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Sending</th>
                <td><label><input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>> Enable GAH Microsoft OAuth SMTP for wp_mail()</label></td>
            </tr>
            <?php foreach (['tenant_id' => 'Directory (tenant) ID or domain', 'client_id' => 'Application (client) ID'] as $key => $label) { ?>
                <tr>
                    <th scope="row"><label for="gac-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                    <td>
                        <input class="regular-text" id="gac-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" type="text" value="" autocomplete="off" maxlength="255">
                        <p class="description"><?php echo $settings['credential_masks'][$key] !== '' ? 'Saved: '.esc_html($settings['credential_masks'][$key]).'. Leave blank to keep it, or enter a replacement.' : 'Enter the value from Microsoft Entra.'; ?></p>
                    </td>
                </tr>
            <?php } ?>
            <?php foreach (['mailbox' => 'Sending mailbox', 'from_email' => 'From Email (optional)', 'from_name' => 'From name (optional)'] as $key => $label) { ?>
                <tr>
                    <th scope="row"><label for="gac-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                    <td>
                        <input class="regular-text" id="gac-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" type="<?php echo in_array($key, ['mailbox', 'from_email'], true) ? 'email' : 'text'; ?>" value="<?php echo esc_attr($settings[$key]); ?>" maxlength="255">
                        <?php if ($key === 'from_email') { ?>
                            <p class="description">Address recipients see as the sender. Leave blank to use the sending mailbox. Microsoft 365 must authorize sending as this address; a different mailbox requires Send As permission.</p>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <tr>
                <th scope="row"><label for="gac-client-secret">Client secret</label></th>
                <td>
                    <input class="regular-text" type="password" id="gac-client-secret" name="client_secret" value="" autocomplete="new-password">
                    <p class="description"><?php echo $settings['credential_masks']['client_secret'] !== '' ? 'Saved: '.esc_html($settings['credential_masks']['client_secret']).'. Leave blank to keep it, or enter a replacement.' : 'Paste the secret Value, not the secret ID.'; ?> Encrypted with this site's WordPress salts.</p>
                </td>
            </tr>
        </table>
        <p>Server: <code>smtp.office365.com:587</code>, required STARTTLS and XOAUTH2. Authentication and envelope sender use the sending mailbox. The visible sender uses From Email, or the mailbox when blank. An existing Reply-To is preserved.</p>
        <?php submit_button('Save settings'); ?>
    </form>
    <hr>
    <h2>Send a test email</h2>
    <p>Uses saved settings and requests a fresh access token. Save changes above first.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="gac_ms_smtp_test">
        <?php wp_nonce_field('gac_ms_smtp_test'); ?>
        <label for="gac-test-to">Recipient</label>
        <input class="regular-text" id="gac-test-to" name="test_to" type="email" required>
        <?php submit_button('Send test email', 'secondary'); ?>
    </form>
    <h2>Microsoft setup</h2>
    <ol>
        <li>Register a single-tenant application in Microsoft Entra and create a client secret.</li>
        <li>Add Office 365 Exchange Online application permission <code>SMTP.SendAsApp</code> and grant tenant admin consent.</li>
        <li>Register the Enterprise application's service principal in Exchange Online and grant access to the sending mailbox.</li>
        <li>Enable SMTP AUTH for the mailbox where tenant policy allows it. The server needs outbound HTTPS (443) and SMTP (587).</li>
    </ol>
    <p>See <code>MICROSOFT-SMTP.md</code> in the plugin ZIP for the setup commands and troubleshooting. Disable other SMTP plugins before enabling this one. Mail sent through a separate provider API bypasses wp_mail() and is not handled here.</p>
</div>
