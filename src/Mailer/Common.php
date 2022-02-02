<?php

declare(strict_types=1);

namespace Difra\Mailer;

use Difra\Envi;
use Difra\Exception;
use Difra\Locales;
use Difra\View;

/**
 * Class Common
 * @package Drafton\Mailer
 */
abstract class Common
{
    protected const EOL = "\r\n";
    /** @var string|array From address: string or [string mail,string name] */
    protected string|array $from = [];
    /** @var array From addresses array */
    protected array $to = [];
    /** @var array CC addresses array */
    protected array $cc = [];
    /** @var array BCC addresses array */
    protected array $bcc = [];
    /** @var string Subject */
    protected string $subject = '';
    /** @var string Body */
    protected string $body = '';
    /** @var string[] Additional headers */
    protected array $headers = [];

    /**
     * Send mail
     * @return bool|null
     */
    abstract public function send(): ?bool;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->from = 'noreply@' . Envi::getHost(true);
    }

    /**
     * Load configuration
     * @param array $config
     * @throws \Difra\Exception
     */
    public function loadConfig(array $config): void
    {
        if (!empty($config['from'])) {
            $this->setFrom($config['from']);
        }
    }

    /**
     * Set From address
     * @param array|string $address
     * @throws \Difra\Exception
     */
    public function setFrom(array|string $address)
    {
        $this->from = $this->makeAddress($address);
    }

    /**
     * Get headers
     * @param bool $implode
     * @param bool $full
     * @return string|string[]
     * @throws \Difra\Exception
     */
    protected function getHeaders(bool $implode = false, bool $full = false): string|array
    {
        $from = $this->formatFrom();
        $to = $this->formatTo();
        $headers = array_merge([
            'Mime-Version: 1.0',
            "Content-Type: text/html; charset=\"UTF-8\"",
            'Date: ' . date('r'),
            'Message-Id: <' . md5(microtime()) . '-' . md5($from . implode('', $to)) . '@' . Envi::getHost(true) . '>',
            'Content-Transfer-Encoding: 8bit',
            "From: $from"
        ], $this->headers);
        if ($full) {
            foreach ($this->formatTo() as $to) {
                $headers[] = "To: $to";
            }
            $headers[] = 'Subject: ' . $this->formatSubject();
        }
        return $implode ? implode(self::EOL, $headers) : $headers;
    }

    /**
     * Add additional header
     * @param string $header
     */
    public function addHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    /**
     * Clean additional headers
     */
    public function cleanHeaders(): void
    {
        $this->headers = [];
    }

    /**
     * Make address record from mail and name
     * @param array|string $address
     * @param bool $onlyMail
     * @return string
     * @throws Exception
     */
    protected function formatAddress(array|string $address, bool $onlyMail = false): string
    {
        if (!is_array($address)) {
            return $address;
        } elseif (!isset($address[0])) {
            throw new Exception('Mailer::formatAddress got unexpected input');
        } elseif (empty($address[1]) or $onlyMail) {
            return $address[0];
        } elseif (preg_match('/[\\x80-\\xff]+/', $address[1])) {
            return '=?utf-8?B?' . base64_encode($address[1]) . "==?= <$address[0]>";
        } else {
            return "$address[1] <$address[0]>";
        }
    }

    /**
     * Add To address
     * @param array|string $address
     * @throws Exception
     */
    public function setTo(array|string $address): void
    {
        $this->cleanTo();
        $this->to[] = $this->makeAddress($address);
    }

    /**
     * Add To address
     * @param array|string $address
     * @throws Exception
     */
    public function addTo(array|string $address): void
    {
        $this->to[] = $this->makeAddress($address);
    }

    /**
     * Clean To addresses
     */
    public function cleanTo(): void
    {
        $this->to = [];
    }

    /**
     * Make address from string|array
     * @param array|string $address
     * @return array
     * @throws Exception
     */
    protected function makeAddress(array|string $address): array
    {
        if (!is_array($address)) {
            return [$address];
        } elseif (!isset($address[0])) {
            throw new Exception('Mailer::makeAddress got unexpected input');
        } elseif (empty($address[1])) {
            return [$address[0]];
        } else {
            return [$address[0], $address[1]];
        }
    }

    /**
     * Get formatted To list
     * @param bool $onlyMail
     * @return \string[]
     * @throws \Difra\Exception
     */
    protected function formatTo(bool $onlyMail = false): array
    {
        $res = [];
        foreach ($this->to as $to) {
            $res[] = $this->formatAddress($to, $onlyMail);
        }
        return $res;
    }

    /**
     * Get formatted From string
     * @param bool $onlyMail
     * @return string
     * @throws \Difra\Exception
     */
    protected function formatFrom(bool $onlyMail = false): string
    {
        return $this->formatAddress($this->from, $onlyMail);
    }

    /**
     * Get formatted Subject string
     * @param string|null $subject
     * @return string
     */
    public function formatSubject(?string $subject = null): string
    {
        if (!$subject) {
            $subject = $this->subject;
        }
        if (!preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $subject, $matches)) {
            return $subject;
        }

        $mb_length = mb_strlen($subject);
        $length = 63;
        $avgLength = floor($length * ($mb_length / strlen($subject)) * .75);
        $encoded = [];
        for ($position = 0; $position < $mb_length; $position += $offset) {
            $lookBack = 0;
            do {
                $offset = $avgLength - $lookBack;
                $chunk = mb_substr($subject, $position, $offset);
                $chunk = base64_encode($chunk);
                $lookBack++;
            } while (strlen($chunk) > $length);
            $encoded[] = $chunk;
        }
        return '=?utf-8?B?' . implode("?=\n =?utf-8?B?", $encoded) . '?=';
    }

    /**
     * Set mail subject
     * @param string $subject
     */
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    /**
     * Set mail body
     * @param string $body
     */
    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    /**
     * Get formatted body
     * @param bool $binary
     * @return string
     */
    protected function formatBody(bool $binary = false): string
    {
        $body = $this->body;
        // escape dots when it's line first character
        if (!$binary) {
            $chunks = mb_split('\r\n\.', $body);
            if (sizeof($chunks) > 1) {
                $body = implode("\r\n..", $chunks);
            }
            $lines = explode("\r\n", $body);
            $maxLength = 598;
            $chopped = [];
            foreach ($lines as $line) {
                if (strlen($line) <= $maxLength) {
                    $chopped[] = $line;
                    continue;
                }
                $newLine = '';
                $words = preg_split('/([ ]+)/', $line, flags: PREG_SPLIT_DELIM_CAPTURE);
                foreach ($words as $word) {
                    if (strlen($newLine) + strlen($word) + 1 > $maxLength) {
                        if ($newLine) {
                            $chopped[] = $newLine;
                            $newLine = '';
                        }
                        $pos = 0;
                        while ($pos < mb_strlen($word)) {
                            $sub = mb_strcut($word, $pos, $maxLength);
                            $chopped[] = $sub;
                            $pos += mb_strlen($sub);
                        }
                    } elseif ($newLine) {
                        $newLine .= ' ' . $word;
                    } else {
                        $newLine = $word;
                    }
                }
                if ($newLine) {
                    $chopped[] = $newLine;
                }
            }
            $body = implode("\r\n", $chopped);
        }
        return $body;
    }

    /**
     * Generate and send e-mail message
     * Data are passed to templates as <mail> node attributes.
     * Message template can contain following tags: from, fromtext, subject, text
     * @param string $to
     * @param string $template
     * @param array $data
     * @throws \Difra\Exception
     * @deprecated
     */
    public function createMail(string $to, string $template, array $data)
    {
        // render template
        $xml = new \DOMDocument();
        /** @var \DOMelement $root */
        $root = $xml->appendChild($xml->createElement('mail'));
        $root->setAttribute('host', Envi::getHost(true));
        Locales::getInstance()->getLocaleXML($root);
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $root->setAttribute($key, $value);
            }
        }
        $templateText = View::render($xml, $template, true);

        $this->setTo($to);

        // get template strings
        if (empty($this->subject)) {
            preg_match('|<subject[^>]*>(.*)</subject>|Uis', $templateText, $subject);
            if (!empty($subject[1])) {
                $this->setSubject($subject[1]);
            }
        }
        if (empty($this->from)) {
            preg_match('|<from[^>]*>(.*)</from>|Uis', $templateText, $fromMail);
            $fromMail = !empty($fromMail[1]) ? $fromMail[1] : null;
            preg_match('|<fromtext[^>]*>(.*)</fromtext>|Uis', $templateText, $fromText);
            $fromText = !empty($fromText[1]) ? $fromText[1] : null;
            if (!empty($fromMail[1])) {
                $this->setFrom([$fromMail[1], !empty($fromText[1]) ? $fromText[1] : null]);
            }
        }
        preg_match('|<text[^>]*>(.*)</text>|Uis', $templateText, $mailText);
        $this->setBody(!empty($mailText[1]) ? $mailText[1] : $templateText);

        $this->send();
    }

    /**
     * Render message body from template
     * @param string $template
     * @param array $data
     * @throws \Difra\Exception
     */
    public function render(string $template, array $data): void
    {
        $xml = new \DOMDocument();
        /** @var \DOMelement $root */
        $root = $xml->appendChild($xml->createElement('mail'));
        $root->setAttribute('host', Envi::getHost(true));
        Locales::getInstance()->getLocaleXML($root);
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $root->setAttribute($key, $value);
            }
        }
        $view = new View();
        $view->setTemplateInstance($template);
        $view->setFillXML(false);
        $view->setNormalize(true);
        $this->body = $view->process($xml);
    }
}
