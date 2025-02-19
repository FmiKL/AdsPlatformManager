<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvertisingApi\Config;
use AdvertisingApi\GoogleApi\AuthClient;
use AdvertisingApi\GoogleApi\CampaignIpBlock;
use AdvertisingApi\GoogleApi\GlobalIpBlock;
use AdvertisingApi\Logger;

Config::load();
Logger::get();

$authClient = new AuthClient();
$globalIpBlock = new GlobalIpBlock($authClient);
$campaignIpBlock = new CampaignIpBlock($authClient);

try {
    $customerId = '123456789';
    $campaignId = '987654321';
    $ipToBlock = '192.168.1.1';

    // Example 1: Block IP at account level
    echo "\nBlocking IP at account level...\n";
    $globalIpBlock->blockIp($customerId, $ipToBlock);
    echo "✅ IP {$ipToBlock} blocked globally\n";

    // Example 2: Block IP at campaign level
    echo "\nBlocking IP at campaign level...\n";
    $campaignIpBlock->blockIp($customerId, $campaignId, $ipToBlock);
    echo "✅ IP {$ipToBlock} blocked for campaign {$campaignId}\n";

} catch (\Exception $e) {
    Logger::get()->error("An error occurred", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
