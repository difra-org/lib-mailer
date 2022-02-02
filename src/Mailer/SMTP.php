<?php

declare(strict_types=1);

namespace Difra\Mailer;

use Difra\Envi;
use Difra\Mailer\Exception\Temp;
use Difra\Mailer\SMTP\Reply;

/**
 * Class SMTP
 * Send mail using SMTP
 * @package Difra\Mailer
 */
class SMTP extends Common
{
    /**
     * Settings
     */
    protected const CONNECT_TIMEOUT = 10; // connect timeout (seconds)
    protected const READ_TIMEOUT = 5000; // default socket read timeout (milliseconds)
    protected const READ_LIMIT = 10240; // maximum number of bytes to expect from server by default
    /**
     * Fields
     */
    /** @var string SMTP host */
    protected string $host = 'tcp://127.0.0.1:25';
    /** @var resource */
    protected static $connections = [];
    /** @var array Flushed data */
    protected array $flushed = [];

    /**
     * Load config
     * @param array $config
     * @throws \Difra\Exception
     */
    public function loadConfig(array $config): void
    {
        parent::loadConfig($config);
        if (!empty($config['host'])) {
            $this->host = $config['host'];
        }
    }

    /**
     * Connect
     * @param bool $ping
     * @return resource
     * @throws \Difra\Mailer\Exception\Temp|\Difra\Mailer\Exception\Fatal|\Difra\Exception
     */
    protected function connect(bool $ping = false)
    {
        // use cached connection if exists
        if (!empty(self::$connections[$this->host]) and !feof(self::$connections[$this->host])) {
            // no ping requested, return cached result
            if (!$ping) {
                return self::$connections[$this->host];
            }
            // ping server
            try {
                $this->command('NOOP');
                return self::$connections[$this->host];
            } catch (\Exception) {
            }
        }

        // connect
        $context = stream_context_create();
        $errorNum = 0;
        $errorString = '';
        self::$connections[$this->host] = stream_socket_client(
            $this->host,
            $errorNum,
            $errorString,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!self::$connections[$this->host]) {
            throw new Temp('Failed to connect SMTP host ' . $this->host);
        }
        $this->read(self::CONNECT_TIMEOUT);
        return self::$connections[$this->host];
    }

    /**
     * Write to stream
     * @param $string
     * @param bool $eol
     * @throws \Difra\Mailer\Exception\Temp|\Difra\Mailer\Exception\Fatal|\Difra\Exception
     */
    protected function write($string, bool $eol = true): void
    {
        if ($eol) {
            $string .= self::EOL;
        }
        $result = fwrite(
            $this->connect(),
            $string
        );
        if (!$result) {
            throw new Temp('Error writing to SMTP socket');
        }
    }

    /**
     * Read from stream
     * @param int $timeout
     * @param int $limit
     * @param bool $parse
     * @param bool $exceptions
     * @return string|\Difra\Mailer\SMTP\Reply|null
     * @throws \Difra\Exception
     * @throws \Difra\Mailer\Exception\Fatal
     * @throws \Difra\Mailer\Exception\Temp
     */
    protected function read(int $timeout = 5000, int $limit = self::READ_LIMIT, bool $parse = true, bool $exceptions = true): string|Reply|null
    {
        $connection = $this->connect();
//        stream_set_timeout($connection, $timeout / 1000, 1000 * ($timeout % 1000));
        $result = fgets(
            $connection,
            $limit
        );
        if (empty($result)) {
            $meta = stream_get_meta_data($connection);
            if (!empty($meta['timed_out'])) {
                fclose($connection);
                self::$connections[$this->host] = null;
                throw new Temp('Read from SMTP server timed out');
            }
            return null;
        }
        if (mb_substr($result, -2) == "\r\n") {
            $result = mb_substr($result, 0, mb_strlen($result) - 2);
        }
        return $parse ? Reply::parse($result, $exceptions) : $result;
    }

    /**
     * Flush input
     * @throws \Difra\Mailer\Exception\Temp|\Difra\Mailer\Exception\Fatal|\Difra\Exception
     */
    protected function flush()
    {
        $connection = $this->connect();
        while (true) {
            $meta = stream_get_meta_data($connection);
            if (empty($meta['unread_bytes'])) {
                return;
            }
            $flushed = $this->read();
//            echo '- ', $flushed->getSource(), PHP_EOL;
            $this->flushed[] = $flushed;
        }
    }

    /**
     * @param string $command
     * @param int $timeout
     * @param bool $exceptions
     * @return \Difra\Mailer\SMTP\Reply|null
     * @throws \Difra\Exception
     * @throws \Difra\Mailer\Exception\Fatal
     * @throws \Difra\Mailer\Exception\Temp
     */
    protected function command(string $command, int $timeout = self::READ_TIMEOUT, bool $exceptions = true): Reply|null
    {
        $this->flush();
//        echo "> ", $command, PHP_EOL;
        $this->write($command);
        $extends = [];
        while (true) {
            $reply = $this->read($timeout, self::READ_LIMIT, true, $exceptions);
//            echo "< ", $reply->getSource(), PHP_EOL;
            if ($reply->isExtended()) {
                $extends[] = $reply;
                continue;
            }
            $reply->setExtends($extends);
            return $reply;
        }
    }

    /**
     * Send mail
     * @throws \Difra\Mailer\Exception\Temp|\Difra\Mailer\Exception\Fatal|\Difra\Exception
     */
    public function send(): ?bool
    {
        $this->connect(true);
        // todo: move EHLO to connect()
        $this->command('EHLO ' . Envi::getHost(true));
//        $this->command('EHLO ' . Envi::getHost(true));
        $this->command('MAIL FROM:' . $this->formatFrom(true));
        foreach ($this->formatTo(true) as $to) {
            $this->command('RCPT TO:' . $to);
        }
//        $this->command('RCPT TO:' . implode('+', $this->formatTo(true)));
        $message = $this->getHeaders(true, true) . self::EOL . $this->formatBody();
//        $this->command('CHUNKING ' . mb_strlen($message, '8bit'));
//        $this->write($message, false);
        $this->command('DATA');
        $this->write($message);
        $this->command('.');
        // todo: move to __destruct()
        $this->command('QUIT');
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        // todo: say goodbye (QUIT) to SMTP
    }
}
