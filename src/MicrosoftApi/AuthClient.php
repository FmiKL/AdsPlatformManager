<?php

namespace AdvertisingApi\MicrosoftApi;

use AdvertisingApi\Config;
use AdvertisingApi\Logger;
use Microsoft\BingAds\Auth\ApiEnvironment;
use Microsoft\BingAds\Auth\AuthorizationData;
use Microsoft\BingAds\Auth\OAuthWebAuthCodeGrant;
use Microsoft\BingAds\Auth\ServiceClient;

/**
 * Authentication client for Microsoft Advertising API
 * 
 * Handles OAuth2 authentication process and token management for Microsoft Advertising API,
 * including automatic token refresh and environment configuration.
 */
class AuthClient
{
    private OAuthWebAuthCodeGrant $authentication;

    private array $config;
    private Logger $logger;

    public function __construct()
    {
        $this->config = Config::get('bing_ads');
        $this->logger = Logger::get();
        
        $this->initializeOAuth();
    }

    /**
     * Initialize OAuth authentication process for Microsoft Advertising API
     * 
     * Authentication process involves two steps:
     * 
     * 1. Get initial authorization code:
     *    URL: https://login.microsoftonline.com/common/oauth2/v2.0/authorize
     *    Required parameters:
     *    - client_id: Application ID
     *    - response_type: code
     *    - redirect_uri: Callback URL after authentication
     *    - scope: openid offline_access https://ads.microsoft.com/msads.manage
     * 
     * 2. Exchange code for token:
     *    Endpoint: https://login.microsoftonline.com/common/oauth2/v2.0/token
     *    Method: POST
     *    Headers: Content-Type: application/x-www-form-urlencoded
     *    Body:
     *    - client_id: Application ID
     *    - client_secret: Application secret
     *    - grant_type: authorization_code
     *    - code: Code received from step 1
     *    - redirect_uri: Callback URL
     * 
     * @throws \Exception If token acquisition fails
     * @throws \SoapFault For SOAP-related errors
     */
    private function initializeOAuth(): void
    {
        $this->authentication = new OAuthWebAuthCodeGrant();
        $this->authentication->withClientSecret($this->config['client_secret']);
        $this->authentication->withClientId($this->config['client_id']);

        try {
            $oAuthTokens = $this->authentication->requestOAuthTokensByRefreshToken($this->config['refresh_token']);

            if (empty($oAuthTokens->AccessToken)) {
                throw new \Exception('Failed to obtain access token.');
            }

            if ($oAuthTokens->RefreshToken !== $this->config['refresh_token']) {
                $this->updateEnvRefreshToken($oAuthTokens->RefreshToken);
            }
        }
        catch (\SoapFault $fault) {
            $this->logger->get()->error("A SOAP fault error occurred: {$fault->getMessage()}", [
                'exception' => $fault,
                'trace' => $fault->getTraceAsString(),
            ]);
        } catch (\Exception $e) {
            $this->logger->get()->error("An error occurred: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Updates the refresh token in the .env file
     * 
     * @param string $refreshToken New refresh token to store
     * @throws \RuntimeException If unable to read or write to .env file
     */
    private function updateEnvRefreshToken(string $refreshToken): void
    {
        $envFilePath = dirname(__DIR__, 2) . '/.env';
        $envFileContent = file_get_contents($envFilePath);

        if ($envFileContent === false) {
            throw new \RuntimeException('Unable to read the .env file.');
        }

        $newEnvFileContent = preg_replace(
            '/^BING_ADS_REFRESH_TOKEN=.*$/m',
            'BING_ADS_REFRESH_TOKEN=' . $refreshToken,
            $envFileContent
        );

        if ($newEnvFileContent === null) {
            throw new \RuntimeException('Error processing the .env file.');
        }

        if (file_put_contents($envFilePath, $newEnvFileContent) === false) {
            throw new \RuntimeException('Unable to write to the .env file.');
        }
    }

    /**
     * Creates an authenticated service client for Microsoft Advertising API
     * 
     * @param string $serviceType Type of service to initialize
     * @param string|null $accountId Optional account ID for specific account operations
     * @return ServiceClient Authenticated service client instance
     */
    public function createClient(string $serviceType, string $accountId = null): ServiceClient
    {
        $authorizationData = (new AuthorizationData())
            ->withAuthentication($this->authentication)
            ->withCustomerId($this->config['login_customer_id'])
            ->withDeveloperToken($this->config['developer_token']);

        if ($accountId !== null) {
            $authorizationData->withAccountId($accountId);
        }

        return new ServiceClient(
            $serviceType,
            $authorizationData,
            ApiEnvironment::Production
        );
    }
}
