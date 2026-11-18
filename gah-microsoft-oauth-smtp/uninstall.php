<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('gac_ms_smtp_settings');
delete_transient('gac_ms_smtp_token');
