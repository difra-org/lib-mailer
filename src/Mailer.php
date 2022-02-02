<?php

declare(strict_types=1);

namespace Difra;

/**
 * Class Mailer
 * @package Difra
 */
class Mailer
{
    /**
     * Factory
     * @param string $instance
     * @return Mailer\Common
     * @throws Exception
     */
    public static function getInstance($instance = null)
    {
        $config = Config::getInstance()->getValue('email', $instance ?: 'default');
        if (empty($config)) {
            if ($instance) {
                throw new Exception('Mailer target ' . $instance . ' is not configured.');
            } else {
                $config = [];
            }
        }
        if (!isset($config['method'])) {
            $config['method'] = 'mail';
        }
        switch ($config['method']) {
            case 'mail':
                $mailer = new Mailer\Mail();
                break;
            case 'smtp':
                $mailer = new Mailer\SMTP();
                break;
            default:
                throw new Exception('Mailer method ' . $config['method'] . ' is not implemented.');
        }
        $mailer->loadConfig($config);
        return $mailer;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }
}
