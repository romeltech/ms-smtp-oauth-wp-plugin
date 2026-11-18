# WordPress 6.0.2 / PHP 8.4 package

This separate in-house package is version `1.0.2-wp602`, authored by Romel Indemne.
Build it with `php build.php --wp602`. The standard package remains unchanged.
It uses the same plugin implementation plus an OAuth interface fallback for the
file omitted by WordPress 6.0.2's bundled PHPMailer, with WordPress 6.0.2 and PHP 8.4 minimum
requirements declared in its plugin header. OAuth, From Email, encrypted settings
and SMTP behavior are retained.

The package keeps the same plugin directory and settings keys. Install it as a
replacement for the standard package, not as an additional active SMTP plugin.
Back up the site and test on staging before replacing the installed package.
Do not uninstall the existing plugin first: uninstall deletes its saved settings.

The plugin directory and entry file are now `gah-microsoft-oauth-smtp/` and
`gah-microsoft-oauth-smtp.php`. If upgrading from the older
`grandauto-microsoft-smtp/` directory, deactivate that copy before activating the
renamed package. Saved settings remain available. Do not use WordPress's Delete
action on the old copy, as its uninstall routine removes those shared settings.

## Compatibility scope

WordPress 6.0 does not officially support PHP 8.4. This plugin cannot correct
compatibility problems in WordPress core, themes or other plugins. See the
[official WordPress PHP compatibility matrix](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/).

The integration suite exercises WordPress's actual `wp_mail` and bundled
PHPMailer with fake HTTP and SMTP services. It covers OAuth authentication,
TLS enforcement, From Email, Reply-To, attachments, token handling and settings.
It is not a full WordPress site or live Microsoft 365 delivery test.

Verified on 2026-11-18: all 63 integration checks passed against WordPress
6.0.2 on PHP 8.4.27, testing the extracted installable package with all PHP
warnings and deprecations treated as failures.

To reproduce after downloading and extracting official WordPress 6.0.2:

```powershell
php build.php --wp602
Expand-Archive dist/gah-microsoft-oauth-smtp-wp-6.0.2-php-8.4.zip .test-runtime/package-wp602 -Force
php tests/run.php .test-runtime/wp-6.0.2/wordpress .test-runtime/package-wp602/gah-microsoft-oauth-smtp
```
