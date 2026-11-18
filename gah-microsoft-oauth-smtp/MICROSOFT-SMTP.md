# GAH Microsoft OAuth SMTP for WordPress

Version 1.0.2. A standalone plugin for GAH based on Grandauto's `MicrosoftTokenService`
and `MicrosoftOAuthTransport` (builder ADR-043). It does not need Laravel,
Composer, a Microsoft SDK, an OAuth redirect URL or an interactive login.

## Requirements

- WordPress 6.5 or later, PHP 8.1 or later with OpenSSL.
- A Microsoft 365 organization with an Exchange Online sending mailbox.
- An Entra administrator and Exchange administrator to authorize the application.
- Outbound HTTPS to `login.microsoftonline.com:443` and SMTP to
  `smtp.office365.com:587`, with trusted CA certificates on the host.
- Personal Outlook.com/Hotmail accounts and sovereign Microsoft clouds are outside
  this version's scope. Use the commercial Microsoft 365 service endpoints.

## Install

1. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
2. Upload `gah-microsoft-oauth-smtp.zip`, install and activate it.
3. Disable other SMTP/mail transport plugins to avoid competing hooks.
4. Open **Settings → GAH Microsoft OAuth SMTP**.
5. Enter your tenant ID (or tenant domain), application/client ID, client secret
   **Value**, sending mailbox, optional From Email and optional From name.
6. Enable Microsoft SMTP, save settings, then send a test email to an inbox you own.
7. Verify receipt, then exercise a real password reset and a form from your site.

Saved tenant ID, client ID and client secret show a fixed mask plus only the last
five characters (letters or digits), for example `•••••abc12`. Their edit inputs
remain empty; leave them blank to retain the saved values or enter replacements.
Values of five characters or fewer are fully masked. Full saved credentials are
not included in HTML, hidden inputs or the admin view data. A secret that cannot
be decrypted shows a replacement prompt instead.

The original internal plugin folder and WordPress option names are retained so
this branding update can replace the earlier package without losing settings.

The test uses saved settings and requests a fresh token. A success notice means
the mail transport accepted the message; check the inbox and spam folder for delivery.

## Microsoft setup

This follows Microsoft's documented [client credentials SMTP flow](https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth).

1. In **Microsoft Entra admin center → App registrations**, create a single-tenant
   application. Record its **Application (client) ID** and **Directory (tenant) ID**.
2. Under **Certificates & secrets**, create a client secret. Record its **Value**
   securely and schedule renewal before its expiry. The secret ID is not usable.
3. Under **API permissions → Add a permission → APIs my organization uses → Office
   365 Exchange Online → Application permissions**, add **SMTP.SendAsApp**.
   Grant administrator consent for the tenant. A Microsoft Graph `Mail.Send`
   permission does not authorize this SMTP flow.
4. In Exchange Online PowerShell, register the application's service principal
   once and grant access to the sending mailbox. Replace the example values:

   ```powershell
   Connect-ExchangeOnline
   $smtpClient = 'YOUR-APPLICATION-CLIENT-ID'
   $enterpriseObject = 'YOUR-ENTERPRISE-APPLICATION-OBJECT-ID'
   $smtpMailbox = 'noreply@contoso.com'

   New-ServicePrincipal -AppId $smtpClient -ObjectId $enterpriseObject -DisplayName 'WordPress SMTP'
   $smtpPrincipal = Get-ServicePrincipal -Identity $enterpriseObject
   Add-MailboxPermission -Identity $smtpMailbox -User $smtpPrincipal.Identity -AccessRights FullAccess
   Add-RecipientPermission -Identity $smtpMailbox -Trustee $smtpPrincipal.Identity -AccessRights SendAs
   Set-CASMailbox -Identity $smtpMailbox -SmtpClientAuthenticationDisabled $false
   ```

   Use the Object ID from **Enterprise applications**, not the different Object ID
   on **App registrations**. If already registered, skip `New-ServicePrincipal`.
   Have the Exchange administrator review the mailbox grants and tenant policies.

5. Confirm [SMTP AUTH is permitted](https://learn.microsoft.com/en-us/exchange/clients-and-mobile-in-exchange-online/authenticated-client-smtp-submission)
   by your organization's policies. The mailbox switch alone cannot override
   every tenant security restriction. Allow time for permission changes to propagate.
6. Configure the plugin and send its test email.

These commands change Microsoft tenant permissions; the plugin does not run them.
The plugin itself makes outbound calls only when sending email or its explicit test.

## Behavior

- Uses `client_credentials` with the scope `https://outlook.office365.com/.default`.
- Uses WordPress's bundled PHPMailer via `phpmailer_init`, with **XOAUTH2 only**,
  port **587**, required **STARTTLS**, TLS 1.2 or later and certificate verification.
- Applies to mail sent through `wp_mail()`: core notifications, password resets
  and plugins that use that function. Plugins sending via their own APIs bypass it.
- Uses the sending mailbox for authentication and the envelope sender. The visible
  From address uses the optional From Email setting, falling back to the mailbox
  when blank. Existing installations keep their mailbox-based sender until changed.
  Microsoft 365 must authorize sending as the From Email address. For a different
  sender mailbox, grant the application's Exchange service principal SendAs on
  that sender using `Add-RecipientPermission` as described in the Microsoft setup
  reference above; the plugin does not grant permissions. An unauthorized address
  can be rejected by Exchange with SendAsDenied. A caller's different
  From address becomes Reply-To if no Reply-To was supplied. Existing Reply-To,
  recipients, CC/BCC, attachments and message content are retained.
- From name is configurable; when blank, the caller's From name is retained.
- Stores the client secret and cached access token encrypted using AES-256-GCM
  with a key derived from WordPress auth salts. Displays only masked credential suffixes.
  Leaving the secret input blank keeps it; entering a value rotates it.
- Caches tokens until five minutes before expiry and renews automatically on demand.
  Short-lived tokens (five minutes or less) are not cached. No refresh token is
  needed for this app-only flow. Saving settings clears the cached token.
- When enabled, invalid settings or token failures produce `wp_mail_failed` and
  return `false`; there is no PHP-mail fallback. When disabled, WordPress's normal
  mail behavior applies. Another `pre_wp_mail` provider can short-circuit sending;
  do not combine competing mail providers.
- Sends synchronously, like normal `wp_mail()`. It adds no queue, message history,
  tracking or automatic retry. SMTP exceptions remain available to the normal
  `wp_mail_failed` hook; the admin test displays controlled diagnostics only.

## Credential handling and removal

The per-site settings option is `gac_ms_smtp_settings` (autoload disabled). The
encrypted token is in transient `gac_ms_smtp_token`. Authentication failures never
include the raw Microsoft response or token in this plugin's notices. Third-party
logging plugins are responsible for what they capture from WordPress mail hooks.

Changing WordPress authentication salts makes existing ciphertext unreadable.
Re-enter the client secret and save afterwards. Encryption protects database-only
copies; a server administrator with both the database and WordPress salts can
decrypt the values. Never commit real credentials or tenant exports to this repo.

Deactivation clears the token and retains settings for reactivation. Deleting the
plugin through WordPress removes the current site's settings and token. Per-user
admin notices expire after two minutes. This release is intended for activation
on individual sites; network activation and network-wide uninstall are not supported.
Remove each site's saved configuration before deleting from a multisite network.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Microsoft sign-in failed / AADSTS7000215 | Use the client secret Value, not its ID; confirm tenant and client ID. |
| AADSTS7000222 | Renew the expired secret, paste the replacement and save. |
| Consent or authorization error | Exchange Online application `SMTP.SendAsApp`, admin consent, service principal and mailbox grants. |
| SMTP authentication failure | Enterprise application Object ID, correct mailbox, SMTP AUTH policy and permission propagation. |
| SendAsDenied | Mailbox SendAs grant and configured sender address. |
| Connection or TLS failure | Outbound 587/443, DNS, OpenSSL, trusted CA store and tenant security policy. TLS verification must remain enabled. |
| Test accepted but nothing received | Spam/quarantine, Exchange message trace and recipient rules. |
| Saved credentials unreadable | Re-enter the secret after changing WordPress salts or restoring onto a different site. |
| Emails use a different provider | Disable competing SMTP plugins and check whether the sender bypasses `wp_mail()`. |

## Development and verification

Project root: `C:\dev\WP-plugins\gah-microsoft-oauth-smtp`. Plugin source:
`gah-microsoft-oauth-smtp/`. The ZIP contains only this
folder, including this guide; no application secrets or Laravel dependencies.

Local verification uses WordPress core's actual `wp_mail()` and bundled PHPMailer,
with stubbed WordPress storage/hooks/HTTP and a fake SMTP server. Run:

```powershell
php tests/run.php .test-runtime/wordpress
php build.php
```

Run these commands from the project root. Download/extract WordPress separately
into `.test-runtime/wordpress` before running the checks. The local copy used
during development moved with this project; it is not included in the plugin ZIP.
The 63 checks passed on 2026-11-18 using WordPress 7.1.4 and PHP 8.4.27. The checks
never contact Microsoft or deliver email. Real WordPress admin/browser
behavior and end-to-end delivery still require a target WordPress site and an
authorized Microsoft 365 tenant; no live tenant credentials are bundled.

## License

The plugin's original code is licensed under GPL-2.0-or-later:
https://www.gnu.org/licenses/old-licenses/gpl-2.0.html.
WordPress and PHPMailer are supplied by WordPress, not redistributed in this ZIP.
