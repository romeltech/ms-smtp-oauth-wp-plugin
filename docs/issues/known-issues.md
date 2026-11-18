# Plugin known issues

## WordPress Microsoft SMTP: live deployment verification pending (2026-11-18)

The standalone companion plugin has 51 automated checks against WordPress 7.1.4's
actual mail pipeline with stubbed storage/hooks/HTTP and fake SMTP. No authorized
Microsoft 365 tenant or installed WordPress admin session was supplied. Before
production use, upload the ZIP to the target WordPress site, verify admin settings
and its test email, confirm inbox receipt and exercise a password reset/form send.
WordPress 6.5 is the declared minimum based on the APIs used; the local runtime
verification used 7.1.4 with PHP 8.4.27, not the full supported version matrix.
See [the setup guide](../../grandauto-microsoft-smtp/MICROSOFT-SMTP.md).
