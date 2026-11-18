<?php

namespace PHPMailer\PHPMailer;

if (! defined('ABSPATH')) {
    exit;
}

// WordPress 6.0.2's PHPMailer accepts this contract but omits the interface file.
// Prefer the core definition when running this package on a newer WordPress.
if (! interface_exists(OAuthTokenProvider::class)) {
    if (is_file(ABSPATH.WPINC.'/PHPMailer/OAuthTokenProvider.php')) {
        require_once ABSPATH.WPINC.'/PHPMailer/OAuthTokenProvider.php';
    } else {
        interface OAuthTokenProvider
        {
            public function getOauth64();
        }
    }
}
