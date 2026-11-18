# SITE-002: Standalone WordPress Microsoft SMTP companion

- **Status:** Accepted; implementation approved by the maintainer
- **Date:** 2026-11-18
- **Decider:** Project maintainer

## Context

The maintainer requested a WordPress plugin matching Grandauto's Microsoft OAuth
SMTP implementation and confirmed the app-only flow, settings, encryption, token
renewal, test email and installable ZIP scope. A separate Markdown guide was requested.

## Decision

Keep a standalone plugin project at `C:\dev\WP-plugins\gah-microsoft-oauth-smtp`,
with source under `grandauto-microsoft-smtp/`. Use WordPress
APIs and its bundled PHPMailer without adding runtime dependencies. Follow builder
ADR-043's Microsoft commercial-cloud client-credentials grant and Exchange scope;
authenticate to smtp.office365.com using XOAUTH2 with verified, required STARTTLS.
This is not a change to Laravel authentication or its mail transport.

Store per-site settings in a non-autoloaded WordPress option. Encrypt the client
secret and transient token with AES-256-GCM using a WordPress-auth-salt-derived key.
Require manage_options and an action nonce for settings and test sends. Lock the
sender to the mailbox; retain caller Reply-To behavior. Preserve settings on
deactivation and remove current-site configuration on WordPress uninstall.

## Consequences

WordPress can use the existing tenant authorization model without Laravel or an
interactive Microsoft sign-in. Salts must be retained with backups or credentials
re-entered after restore. No queue or delivery-history database is introduced.
Network activation, personal Microsoft accounts and sovereign cloud endpoints are
outside this version. Real tenant delivery and a target WordPress admin UI require
deployment verification; automated checks use real WordPress mail code with fake
HTTP/SMTP and WordPress service stubs, without external mail delivery.
