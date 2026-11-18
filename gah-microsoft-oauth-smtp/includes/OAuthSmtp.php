<?php

namespace Grandauto\MicrosoftSmtp;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if (! defined('ABSPATH')) {
    exit;
}

class OAuthSmtp extends SMTP
{
    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        $methods = $this->getServerExt('AUTH');
        if (! is_array($methods) || ! in_array('XOAUTH2', $methods, true)) {
            throw new Exception('The SMTP server does not advertise XOAUTH2. Password authentication is disabled.');
        }

        return parent::authenticate($username, '', 'XOAUTH2', $OAuth);
    }
}
