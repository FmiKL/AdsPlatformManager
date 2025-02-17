<?php

namespace AdvertisingApi;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

/**
 * Logger service
 * 
 * Provides a centralized logging system using Monolog
 */
class Logger
{
    private static ?MonologLogger $logger = null;

    /**
     * Initializes the logging system
     * 
     * Sets up logging handlers:
     * - File rotation for logs
     * - Error streaming to stderr
     * 
     * @throws \RuntimeException If unable to create log directory
     */
    public static function init(): void
    {
        $logDir = dirname(__DIR__) . '/logs';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        self::$logger = new MonologLogger('advertising-api');
        self::$logger->pushHandler(new RotatingFileHandler($logDir . '/app.log', 30, MonologLogger::DEBUG));
        self::$logger->pushHandler(new StreamHandler('php://stderr', MonologLogger::ERROR));
    }

    /**
     * Returns the logger instance
     * 
     * @return MonologLogger Configured logger instance
     */
    public static function get(): MonologLogger
    {
        if (self::$logger === null) {
            self::init();
        }

        return self::$logger;
    }
}
