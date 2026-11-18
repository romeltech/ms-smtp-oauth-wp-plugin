<?php

/**
 * Plugin Name: GAH Microsoft OAuth SMTP
 * Description: Send WordPress mail through Microsoft 365 SMTP using app-only OAuth and XOAUTH2.
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: GAH
 * License: GPL-2.0-or-later
 * Text Domain: grandauto-microsoft-smtp
 */

namespace Grandauto\MicrosoftSmtp;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__.'/includes/Crypto.php';
require_once __DIR__.'/includes/Tokens.php';
require_once __DIR__.'/includes/Plugin.php';

Plugin::boot();
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);
