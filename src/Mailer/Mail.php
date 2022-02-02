<?php

declare(strict_types=1);

namespace Difra\Mailer;

/**
 * Class Mail
 * Send mail using mail() function
 * @package Difra\Mailer
 */
class Mail extends Common
{
    /**
     * Send mail
     * @throws \Exception
     */
    public function send(): bool
    {
        $tos = $this->formatTo();
        $headers = $this->getHeaders(true);
        $subject = $this->formatSubject();
        $body = $this->body;
        $success = true;
        foreach ($tos as $to) {
            if (!mail($to, $subject, $body, $headers)) {
                if (sizeof($this->to) == 1) {
                    throw new \Difra\Exception('Failed to send message.');
                }
                $success = false;
            }
        }
        return $success;
    }
}
