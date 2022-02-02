<?php

namespace Difra\Mailer\SMTP;

use Difra\Mailer\Exception\Fatal;
use Difra\Mailer\Exception\Temp;

/**
 * Class Reply
 * @package Difra\Mailer\SMTP
 */
class Reply
{
    /**
     * Server reply statuses
     */
    const STATUS_SUCCESS = 200; // nonstandard success response, see rfc876
    const STATUS_INFO = 211; // System status, or system help reply
    const STATUS_HELP = 214; // Help message
    const STATUS_READY = 220; // Service ready
    const STATUS_CLOSING = 221; // Service closing transmission channel
    const STATUS_OK = 250; // Requested mail action okay, completed
    const STATUS_FORWARDED = 251; // User not local; will forward
    const STATUS_NO_VERIFY = 252; // Cannot VRFY user, but will accept message and attempt delivery
    const STATUS_INPUT = 354; // Start mail input; end with <CRLF>.<CRLF>
    const STATUS_UNAVAILABLE_SERVICE = 421; // Service not available, closing transmission channel
    const STATUS_UNAVAILABLE_MAILBOX = 450; // Requested mail action not taken: mailbox unavailable
    const STATUS_UNAVAILABLE_ABORTED = 451; // Requested action aborted: local error in processing
    const STATUS_UNAVAILABLE_SPACE = 452; // Requested action not taken: insufficient system storage
    const STATUS_ERROR_COMMAND = 500; // Syntax error, command unrecognised
    const STATUS_ERROR_PARAMETERS = 501; // Syntax error in parameters or arguments
    const STATUS_ERROR_NOT_IMPLEMENTED = 502; // Command not implemented
    const STATUS_ERROR_SEQUENCE = 503; // Bad sequence of commands
    const STATUS_ERROR_PARAM_NOT_IMPLEMENTED = 504; // Command parameter not implemented
    const STATUS_ERROR_NO_MAIL = 521; // <domain> does not accept mail (see rfc1846)
    const STATUS_ERROR_NO_ACCESS = 530; // Access denied
    const STATUS_ERROR_NO_MAILBOX = 550; // Requested action not taken: mailbox unavailable
    const STATUS_ERROR_NOT_LOCAL = 551; // User not local; please try <forward-path>
    const STATUS_ERROR_STORAGE = 552; // Requested mail action aborted: exceeded storage allocation
    const STATUS_ERROR_NAME = 553; // Requested action not taken: mailbox name not allowed
    const STATUS_ERROR_TRANSACTION = 554; // Transaction failed
    const STATUS_OK_ENUM = [
        self::STATUS_SUCCESS,
        self::STATUS_INFO,
        self::STATUS_HELP,
        self::STATUS_CLOSING,
        self::STATUS_OK,
        self::STATUS_FORWARDED,
        self::STATUS_NO_VERIFY
    ];
    const STATUS_INTERACTIVE_ENUM = [
        self::STATUS_INPUT
    ];
    const STATUS_UNAVAILABLE = [
        self::STATUS_UNAVAILABLE_SERVICE,
        self::STATUS_UNAVAILABLE_MAILBOX,
        self::STATUS_UNAVAILABLE_ABORTED,
        self::STATUS_UNAVAILABLE_SPACE
    ];
    const STATUS_ERROR = [
        self::STATUS_ERROR_COMMAND,
        self::STATUS_ERROR_PARAMETERS,
        self::STATUS_ERROR_NOT_IMPLEMENTED,
        self::STATUS_ERROR_SEQUENCE,
        self::STATUS_ERROR_PARAM_NOT_IMPLEMENTED,
        self::STATUS_ERROR_NO_MAIL,
        self::STATUS_ERROR_NO_ACCESS,
        self::STATUS_ERROR_NO_MAILBOX,
        self::STATUS_ERROR_NOT_LOCAL,
        self::STATUS_ERROR_STORAGE,
        self::STATUS_ERROR_NAME,
        self::STATUS_ERROR_TRANSACTION
    ];
    const RESULT_OK = 'ok';
    const RESULT_INTERACTIVE = 'interactive';
    const RESULT_UNAVAILABLE = 'unavailable';
    const RESULT_ERROR = 'error';
    const RESULT_UNKNOWN = 'unknown';

    /**
     * Object fields
     */
    /** @var int Status */
    private $code = null;
    /** @var string Message */
    private $text = null;
    /** @var string */
    private $result = null;
    /** @var bool Extended (more to come) */
    private $extended = false;
    /** @var string */
    private $source = null;
    /** @var self[] */
    private $extends = null;

    /**
     * Parse SMTP server line
     * @param string $reply
     * @param bool $exceptions
     * @return static
     * @throws Fatal
     * @throws Temp
     * @throws \Difra\Exception
     */
    public static function parse(string $reply, bool$exceptions = true): static
    {
        $code = mb_substr($reply, 0, 3);
        if (mb_strlen($code) != 3 or !ctype_digit($code)) {
            throw new \Difra\Exception('Cannot parse SMTP server reply: ' . $reply);
        }
        $delimiter = mb_substr($reply, 3, 1);
        if ($delimiter === ' ') {
            $extended = false;
        } elseif ($delimiter == '-') {
            $extended = true;
        } else {
            throw new \Difra\Exception('Cannot parse SMTP server reply: ' . $reply);
        }
        $obj = new static();
        $obj->source = $reply;
        $obj->code = $code;
        $obj->text = mb_substr($reply, 4);
        $obj->extended = $extended;
        if (in_array($code, self::STATUS_OK_ENUM)) {
            $obj->result = self::RESULT_OK;
        } elseif (in_array($code, self::STATUS_INTERACTIVE_ENUM)) {
            $obj->result = self::RESULT_INTERACTIVE;
        } elseif (in_array($code, self::STATUS_UNAVAILABLE)) {
            if ($exceptions) {
                throw new Temp('Server replied: ' . $reply);
            } else {
                $obj->result = self::RESULT_UNAVAILABLE;
            }
        } elseif (in_array($code, self::STATUS_ERROR)) {
            if ($exceptions) {
                throw new Fatal('Server replied: ' . $reply);
            } else {
                $obj->result = self::RESULT_ERROR;
            }
        } else {
            $obj->result = self::RESULT_UNKNOWN;
        }
        return $obj;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
    }

    /**
     * Get result code
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get result text
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Get result type
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Extended result line
     * @return boolean
     */
    public function isExtended()
    {
        return $this->extended;
    }

    /**
     * Get source line
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get additional result lines
     * @return self[]
     */
    public function getExtends()
    {
        return $this->extends;
    }

    /**
     * Set additional result lines
     * @param self[] $extends
     */
    public function setExtends($extends)
    {
        $this->extends = $extends;
    }
}
