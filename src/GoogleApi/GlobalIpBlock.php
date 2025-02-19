<?php

namespace AdvertisingApi\GoogleApi;

use Google\Ads\GoogleAds\V16\Common\IpBlockInfo;
use Google\Ads\GoogleAds\V16\Enums\CriterionTypeEnum\CriterionType;
use Google\Ads\GoogleAds\V16\Resources\CustomerNegativeCriterion;
use Google\Ads\GoogleAds\V16\Services\CustomerNegativeCriterionOperation;
use Google\Ads\GoogleAds\V16\Services\MutateCustomerNegativeCriteriaRequest;
use Google\Ads\GoogleAds\V16\Services\SearchGoogleAdsStreamRequest;

/**
 * Manager for global IP blocking operations in Google Ads
 * 
 * Handles account-level IP blocking operations including adding
 * and removing IP addresses from account exclusions.
 */
class GlobalIpBlock
{
    public function __construct(
        private AuthClient $authClient,
    ) {}

    /**
     * Blocks an IP address at the account level
     * 
     * Creates a customer negative criterion to block the specified IP address
     * from seeing ads across all campaigns in the account.
     * 
     * @param string $customerId Customer ID to apply the block to
     * @param string $ipAddress IP address to block
     * @throws \Exception If the blocking operation fails
     */
    public function blockIp(string $customerId, string $ipAddress): void
    {
        $ipBlockInfo = new IpBlockInfo(['ip_address' => $ipAddress]);

        $customerNegativeCriterion = new CustomerNegativeCriterion([
            'type' => CriterionType::IP_BLOCK,
            'ip_block' => $ipBlockInfo
        ]);

        $customerNegativeCriterionOperation = new CustomerNegativeCriterionOperation();
        $customerNegativeCriterionOperation->setCreate($customerNegativeCriterion);

        $this->mutateCustomerNegativeCriteria($customerId, [$customerNegativeCriterionOperation]);
    }

    /**
     * Removes an IP block at the account level
     * 
     * Removes the customer negative criterion for the specified IP address,
     * allowing the IP to see ads across all campaigns in the account.
     * 
     * @param int $customerId Customer ID to remove the block from
     * @param string $ipAddress IP address to unblock
     * @throws \Exception If the unblocking operation fails
     */
    public function unblockIp(int $customerId, string $ipAddress): void
    {
        $formatedIp = addslashes($ipAddress) . '/32';

        $query = '
            SELECT customer_negative_criterion.resource_name
            FROM customer_negative_criterion
            WHERE customer_negative_criterion.type = "IP_BLOCK"
                AND customer_negative_criterion.ip_block.ip_address = "' . $formatedIp . '"
        ';

        $results = $this->executeSearchQuery($customerId, $query);

        $operations = [];
        foreach ($results as $result) {
            $resourceName = $result->getCustomerNegativeCriterion()->getResourceName();
            $customerNegativeCriterionOperation = new CustomerNegativeCriterionOperation();
            $customerNegativeCriterionOperation->setRemove($resourceName);
            $operations[] = $customerNegativeCriterionOperation;
        }

        if (!empty($operations)) {
            $this->mutateCustomerNegativeCriteria($customerId, $operations);
        }
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

    /**
     * Applies customer negative criteria mutations
     * 
     * Sends a batch of customer negative criteria operations to the API.
     * 
     * @param string $customerId Customer ID to apply mutations for
     * @param array $operations Array of operations to apply
     * @throws \Exception If the mutation operation fails
     */
    private function mutateCustomerNegativeCriteria(string $customerId, array $operations): void
    {
        $googleAdsClient = $this->authClient->getClient();
        $customerNegativeCriterionServiceClient = $googleAdsClient->getCustomerNegativeCriterionServiceClient();

        $request = new MutateCustomerNegativeCriteriaRequest([
            'customer_id' => $customerId,
            'operations' => $operations,
        ]);

        $customerNegativeCriterionServiceClient->mutateCustomerNegativeCriteria($request);
    }
}
