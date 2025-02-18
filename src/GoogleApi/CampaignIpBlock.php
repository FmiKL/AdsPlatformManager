<?php

namespace AdvertisingApi\GoogleApi;

use Google\Ads\GoogleAds\V16\Common\IpBlockInfo;
use Google\Ads\GoogleAds\V16\Resources\CampaignCriterion;
use Google\Ads\GoogleAds\V16\Services\CampaignCriterionOperation;
use Google\Ads\GoogleAds\V16\Services\MutateCampaignCriteriaRequest;
use Google\Ads\GoogleAds\V16\Services\SearchGoogleAdsStreamRequest;

/**
 * Manager for IP blocking operations at campaign level in Google Ads
 * 
 * Handles IP blocking operations for specific campaigns including
 * adding and removing IP addresses from campaign exclusions.
 */
class CampaignIpBlock
{
    public function __construct(
        private AuthClient $authClient,
    ) {}

    /**
     * Blocks an IP address for a specific campaign
     * 
     * Creates a negative campaign criterion to block the specified IP address
     * from seeing ads in the given campaign.
     * 
     * @param string $customerId Customer ID owning the campaign
     * @param string $campaignId Campaign ID to apply the block to
     * @param string $ipAddress IP address to block
     * @throws \Exception If the blocking operation fails
     */
    public function blockIp(string $customerId, string $campaignId, string $ipAddress): void
    {
        $campaignCriterion = new CampaignCriterion([
            'campaign' => 'customers/' . $customerId . '/campaigns/' . $campaignId,
            'negative' => true,
            'ip_block' => new IpBlockInfo(['ip_address' => $ipAddress])
        ]);

        $campaignCriterionOperation = new CampaignCriterionOperation();
        $campaignCriterionOperation->setCreate($campaignCriterion);

        $operations[] = $campaignCriterionOperation;

        $this->mutateCampaignCriteria($customerId, $operations);
    }

    /**
     * Removes an IP block from a specific campaign
     * 
     * Removes the negative campaign criterion for the specified IP address,
     * allowing the IP to see ads in the campaign again.
     * 
     * @param string $customerId Customer ID owning the campaign
     * @param string $campaignId Campaign ID to remove the block from
     * @param string $ipAddress IP address to unblock
     * @throws \Exception If the unblocking operation fails
     */
    public function unblockIp(string $customerId, string $campaignId, string $ipAddress): void
    {
        $formatedIp = addslashes($ipAddress) . '/32';
        $resourceName = $this->getIpBlockResourceName($customerId, $campaignId, $formatedIp);

        if ($resourceName) {
            $campaignCriterionOperation = new CampaignCriterionOperation();
            $campaignCriterionOperation->setRemove($resourceName);

            $operations[] = $campaignCriterionOperation;

            $this->mutateCampaignCriteria($customerId, $operations);
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
     * Applies campaign criteria mutations
     * 
     * Sends a batch of campaign criteria operations to the API.
     * 
     * @param string $customerId Customer ID to apply mutations for
     * @param array $operations Array of operations to apply
     * @throws \Exception If the mutation operation fails
     */
    private function mutateCampaignCriteria(string $customerId, array $operations): void
    {
        $googleAdsClient = $this->authClient->getClient();
        $campaignCriterionServiceClient = $googleAdsClient->getCampaignCriterionServiceClient();

        $request = new MutateCampaignCriteriaRequest([
            'customer_id' => $customerId,
            'operations' => $operations,
        ]);

        $campaignCriterionServiceClient->mutateCampaignCriteria($request);
    }

    /**
     * Retrieves the resource name for an IP block criterion
     * 
     * Searches for an existing IP block criterion in the specified campaign
     * matching the given IP address.
     * 
     * @param string $customerId Customer ID owning the campaign
     * @param string $campaignId Campaign ID to search in
     * @param string $ipAddress IP address to find
     * @return string|null Resource name if found, null otherwise
     * @throws \Exception If the search operation fails
     */
    private function getIpBlockResourceName(string $customerId, string $campaignId, string $ipAddress): ?string
    {
        $query = '
            SELECT campaign_criterion.resource_name
            FROM campaign_criterion
            WHERE campaign_criterion.type = "IP_BLOCK"
                AND campaign_criterion.campaign = "customers/' . $customerId . '/campaigns/' . $campaignId . '"
                AND campaign_criterion.ip_block.ip_address = "' . addslashes($ipAddress) . '"
        ';

        $results = $this->executeSearchQuery($customerId, $query);

        foreach ($results as $result) {
            return $result->getCampaignCriterion()->getResourceName();
        }

        return null;
    }
}
