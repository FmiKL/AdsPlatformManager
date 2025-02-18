<?php

namespace AdvertisingApi\MicrosoftApi;

use AdvertisingApi\Config;
use Microsoft\BingAds\Auth\ServiceClientType;
use Microsoft\BingAds\V13\CustomerManagement\AddClientLinksRequest;
use Microsoft\BingAds\V13\CustomerManagement\ClientLink;
use Microsoft\BingAds\V13\CustomerManagement\Paging;
use Microsoft\BingAds\V13\CustomerManagement\SearchClientLinksRequest;

/**
 * Manager for Microsoft Advertising account linking operations
 * 
 * Handles account linking operations including sending invitations,
 * checking account access and retrieving linked accounts.
 */
class AccountLinkManager
{
    private array $config;

    public function __construct(
        private AuthClient $authClient,
    ) {
        $this->config = Config::get('bing_ads');
    }

    /**
     * Sends an account linking invitation to another Microsoft Advertising account
     * 
     * @param string $number Account number to link with
     * @throws \Exception If invitation fails to send or SOAP fault occurs
     */
    public function sendInvitation(string $number): void
    {
        $client = $this->authClient->createClient(ServiceClientType::CustomerManagementVersion13);

        $clientLink = new ClientLink();
        $clientLink->Type = 'AccountLink';
        $clientLink->ClientEntityNumber = $number;
        $clientLink->ManagingCustomerId = $this->config['login_customer_id'];
        $clientLink->IsBillToClient = true;
        $clientLink->SuppressNotification = false;

        $addClientLinksRequest = new AddClientLinksRequest();
        $addClientLinksRequest->ClientLinks = [$clientLink];

        try {
            $response = $client->GetService()->AddClientLinks($addClientLinksRequest);

            if ($response === null || !empty($response->OperationErrors)) {
                throw new \Exception('Failed to send invitation.');
            }
        }
        catch (\SoapFault $fault) {
            throw new \Exception(sprintf('SOAP Fault: %s', $fault->getMessage()));
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Error sending invitation: %s', $e->getMessage()));
        }
    }

    /**
     * Checks if the current account can manage the specified account
     * 
     * @param string $accountId Account ID to check permissions for
     * @return bool True if account can be managed, false otherwise
     * @throws \Exception If verification process fails
     */
    public function canManageAccount(string $accountId): bool
    {
        $linkedAccounts = $this->getLinkedAccounts();

        foreach ($linkedAccounts as $account) {
            if (in_array($accountId, $account)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves all linked accounts with active status
     * 
     * Performs paginated requests to get all linked accounts. For each active link,
     * returns the client ID and account number.
     * 
     * @return array List of linked accounts with their details
     * @throws \Exception If retrieval fails or SOAP fault occurs
     */
    public function getLinkedAccounts(): array
    {
        $client = $this->authClient->createClient(ServiceClientType::CustomerManagementVersion13);

        $allAccounts = [];
        $pageIndex = 0;
        $pageSize = 100;

        do {
            $searchClientLinksRequest = new SearchClientLinksRequest();
  
            $paging = new Paging();
            $paging->Index = $pageIndex;
            $paging->Size = $pageSize;
            $searchClientLinksRequest->PageInfo = $paging;

            try {
                $response = $client->GetService()->SearchClientLinks($searchClientLinksRequest);

                if ($response === null || empty($response->ClientLinks->ClientLink)) {
                    return [];
                }

                foreach ($response->ClientLinks->ClientLink as $clientLink) {
                    if ($clientLink->Status === 'Active') {
                        $allAccounts[] = [
                            'client_id' => $clientLink->ClientEntityId,
                            'client_number' => $clientLink->ClientEntityNumber,
                        ];
                    }
                }

                $pageIndex++;
            }
            catch (\SoapFault $fault) {
                throw new \Exception(sprintf('SOAP Fault: %s', $fault->getMessage()));
            } catch (\Exception $e) {
                throw new \Exception(sprintf('Error retrieving linked accounts: %s', $e->getMessage()));
            }
        }
        while (count($response->ClientLinks->ClientLink) === $pageSize);

        return $allAccounts;
    }
}
