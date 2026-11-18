<?php

namespace Grandauto\MicrosoftSmtp;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuthTokenProvider;
use Throwable;

if (! defined('ABSPATH')) {
    exit;
}

final class TokenProvider implements OAuthTokenProvider
{
    public function __construct(private array $settings) {}

    public function getOauth64(): string
    {
        try {
            $token = Tokens::get($this->settings);

            return base64_encode('user='.$this->settings['mailbox']."\x01auth=Bearer ".$token."\x01\x01");
        } catch (Throwable $error) {
            // WordPress catches PHPMailer exceptions around send(), not phpmailer_init.
            throw new Exception($error->getMessage());
        }
    }
}
