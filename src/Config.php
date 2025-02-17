<?php

namespace AdvertisingApi;

use Dotenv\Dotenv;

/**
 * Configuration service
 * 
 * Handles environment-based configuration loading and access
 */
class Config
{
    private static array $config = [];

    /**
     * Loads configuration from environment variables
     * 
     * @throws \Dotenv\Exception\InvalidPathException If .env file is not found
     */
    public static function load(): void
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();

        self::$config = [
            'google_ads' => [
                'client_id' => $_ENV['GOOGLE_ADS_CLIENT_ID'],
                'client_secret' => $_ENV['GOOGLE_ADS_CLIENT_SECRET'],
                'refresh_token' => $_ENV['GOOGLE_ADS_REFRESH_TOKEN'],
                'developer_token' => $_ENV['GOOGLE_ADS_DEVELOPER_TOKEN'],
                'login_customer_id' => $_ENV['GOOGLE_ADS_LOGIN_CUSTOMER_ID'],
            ],
            'bing_ads' => [
                'client_id' => $_ENV['BING_ADS_CLIENT_ID'],
                'client_secret' => $_ENV['BING_ADS_CLIENT_SECRET'],
                'refresh_token' => $_ENV['BING_ADS_REFRESH_TOKEN'],
                'developer_token' => $_ENV['BING_ADS_DEVELOPER_TOKEN'],
                'login_customer_id' => $_ENV['BING_ADS_LOGIN_CUSTOMER_ID'],
            ],
        ];
    }

    /**
     * Retrieves configuration values
     * 
     * @param string|null $key Configuration key to retrieve
     * @return array|string|null Complete config or specific section
     */
    public static function get(string $key = null)
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? null;
    }
}
