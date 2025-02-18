<?php

namespace AdvertisingApi\GoogleApi;

use AdvertisingApi\Config;
use Google\Ads\GoogleAds\V16\Enums\AccessRoleEnum\AccessRole;
use Google\Ads\GoogleAds\V16\Enums\ManagerLinkStatusEnum\ManagerLinkStatus;
use Google\Ads\GoogleAds\V16\Resources\CustomerUserAccessInvitation;
use Google\Ads\GoogleAds\V16\Services\CustomerUserAccessInvitationOperation;
use Google\Ads\GoogleAds\V16\Services\MutateCustomerUserAccessInvitationRequest;
use Google\Ads\GoogleAds\V16\Services\SearchGoogleAdsStreamRequest;

/**
 * Manager for Google Ads account linking operations
 * 
 * Handles account linking operations including sending invitations
 * and checking account access permissions.
 */
class AccountLinkManager
{
    private const ACCESS_ROLE = 'ADMIN';

    private array $config;

    public function __construct(
        private AuthClient $authClient,
    ) {
        $this->config = Config::get('google_ads');
    }

    /**
     * Sends an account access invitation to a specific email address
     * 
     * Creates and sends an admin access invitation for a Google Ads account
     * to the specified email address.
     * 
     * @param string $customerId Customer ID to grant access to
     * @param string $emailAddress Email address to send invitation to
     * @throws \Exception If invitation fails to send
     */
    public function sendInvitation(string $customerId, string $emailAddress): void
    {
        $googleAdsClient = $this->authClient->getClient();
        $customerUserAccessInvitationServiceClient = $googleAdsClient->getCustomerUserAccessInvitationServiceClient();

        $customerUserAccessInvitation = new CustomerUserAccessInvitation([
            'email_address' => $emailAddress,
            'access_role' => AccessRole::value(self::ACCESS_ROLE)
        ]);

        $customerUserAccessInvitationOperation = new CustomerUserAccessInvitationOperation();
        $customerUserAccessInvitationOperation->setCreate($customerUserAccessInvitation);

        $request = new MutateCustomerUserAccessInvitationRequest([
            'customer_id' => $customerId,
            'operation' => $customerUserAccessInvitationOperation,
        ]);

        $customerUserAccessInvitationServiceClient->mutateCustomerUserAccessInvitation($request);
    }

    /**
     * Checks if the current account can manage the specified account
     * 
     * Verifies if there is an active management link between the current
     * account and the target account.
     * 
     * @param string $customerId Customer ID to check permissions for
     * @return bool True if account can be managed, false otherwise
     * @throws \Exception If verification process fails
     */
    public function canManageAccount(string $customerId): bool
    {
        $managerCustomerId = $this->config['login_customer_id'];

        $query = "
            SELECT customer_client_link.status
            FROM customer_client_link
            WHERE customer_client_link.manager_link_id = '{$managerCustomerId}'
                AND customer_client_link.client_customer = 'customers/{$customerId}'
        ";

        $results = $this->executeSearchQuery($managerCustomerId, $query);

        foreach ($results as $googleAdsRow) {
            $status = $googleAdsRow->getCustomerClientLink()->getStatus();
            if ($status === ManagerLinkStatus::ACTIVE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Executes a search query against the Google Ads API
     * 
     * Performs a streaming search request and aggregates all results
     * into a single array.
     * 
     * @param string $customerId Customer ID to execute the query for
     * @param string $query GAQL query to execute
     * @return array Array of search results
     * @throws \Exception If the query execution fails
     */
    private function executeSearchQuery(string $customerId, string $query): array
    {
        $googleAdsClient = $this->authClient->getClient();
        $googleAdsServiceClient = $googleAdsClient->getGoogleAdsServiceClient();

        $stream = $googleAdsServiceClient->searchStream(
            new SearchGoogleAdsStreamRequest([
                'customer_id' => $customerId,
                'query' => $query,
            ])
        );

        $results = [];
        foreach ($stream->readAll() as $googleAdsRow) {
            $results = array_merge($results, iterator_to_array($googleAdsRow->getResults()->getIterator()));
        }

        return $results;
    }
}
