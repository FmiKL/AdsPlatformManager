<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvertisingApi\Config;
use AdvertisingApi\GoogleApi\AccountLinkManager;
use AdvertisingApi\GoogleApi\AuthClient;
use AdvertisingApi\Logger;

Config::load();
Logger::get();

try {
    $authClient = new AuthClient();
    $accountManager = new AccountLinkManager($authClient);

    $customerId = '1234567890';
    $emailToInvite = 'user@example.com';

    // Example 1: Check account access
    echo "🔍 Checking access to account {$customerId}...\n";
    
    if ($accountManager->canManageAccount($customerId)) {
        echo "✅ Access granted to account {$customerId}\n";
        
        // Example 2: Send invitation
        echo "\n📧 Sending invitation to {$emailToInvite}...\n";
        
        try {
            $accountManager->sendInvitation($customerId, $emailToInvite);
            echo "✅ Invitation sent successfully\n";
        } catch (\Exception $e) {
            echo "❌ Error sending invitation: {$e->getMessage()}\n";
        }
    } else {
        echo "❌ Access denied to account {$customerId}\n";
    }

} catch (\Exception $e) {
    Logger::get()->error("An error occurred", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
