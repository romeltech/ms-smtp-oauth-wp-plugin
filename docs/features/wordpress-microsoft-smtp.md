# GAH Microsoft OAuth SMTP companion

The maintainer requested and approved a separate WordPress version of Grandauto's
Microsoft 365 app-only OAuth SMTP implementation on 2026-11-18.

- Project root: `C:\dev\WP-plugins\gah-microsoft-oauth-smtp`.
- Source: `grandauto-microsoft-smtp/`.
- Installable package: `dist/gah-microsoft-oauth-smtp.zip`.
- Separate installation/setup/behavior guide:
  [MICROSOFT-SMTP.md](../../grandauto-microsoft-smtp/MICROSOFT-SMTP.md).
- Decision: [SITE-002](../decisions/SITE-002-wordpress-microsoft-smtp.md).

It reuses ADR-043's client-credentials Exchange Online scope and XOAUTH2 approach,
adapting storage to encrypted WordPress options/transients and mail delivery to
WordPress's bundled PHPMailer. It is independent of the Laravel application.
There are no Laravel routes, database migrations, builder changes or new runtime
dependencies. See the guide for requirements, security boundaries and verification.

Version 1.0.1 uses the GAH company name in the plugin list, settings menu/page and
test email. Saved tenant/client IDs and client secret show only a fixed mask and
their last five characters; blank inputs retain their values. Short secrets are
fully masked, and unreadable secrets show a replacement prompt. Full credentials
are excluded from admin view data and markup. The client secret remains encrypted
using a key derived from `wp_salt('auth')`; one-way hashing cannot be used because
Microsoft requires the original secret. Tenant/client IDs are non-secret identifiers
and remain in the settings option as before. Internal folder/option names are
retained for compatibility. The new ZIP replaces the earlier archive.

The maintainer requested moving all plugin work out of Grandauto into the separate
project above. Source, package, guide, tests, development WordPress copy and these
notes live there. Grandauto's working tree is restored to its pre-plugin state.

Verification on 2026-11-18: 51 local checks passed using WordPress 7.1.4's real
`wp_mail()` and bundled PHPMailer, fake HTTP/SMTP and WordPress service stubs on
PHP 8.4.27. Checks include encryption/tampering, credential validation, token
request/caching/renewal, TLS refusal, XOAUTH2-only authentication, sender/Reply-To,
CC/BCC/HTML/attachments, WordPress failure events, administrator/nonce gates,
blank-secret retention, settings page output and uninstall. Pint passed. The
archive contains the plugin and guide only. Live tenant and installed admin UI
verification remain release checks in [known-issues.md](../issues/known-issues.md).
