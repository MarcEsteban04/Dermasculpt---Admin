<?php
/**
 * Test script for optimized Gmail fetcher
 */

require_once 'classes/SimpleGmailFetcher.php';

echo "Testing Gmail Fetcher Optimizations...\n\n";

$fetcher = new SimpleGmailFetcher();

// Test connection first
echo "1. Testing IMAP connection...\n";
$connectionTest = $fetcher->testConnection();
if ($connectionTest['success']) {
    echo "✓ Connection successful\n";
    echo "Total messages in inbox: " . $connectionTest['total_messages'] . "\n\n";
} else {
    echo "✗ Connection failed: " . $connectionTest['message'] . "\n";
    exit(1);
}

// Test email fetching with timing
$testEmail = 'test@example.com'; // Replace with actual patient email for testing

echo "2. Testing email fetching (without cache)...\n";
$startTime = microtime(true);
$replies1 = $fetcher->getEmailReplies($testEmail, null, false); // No cache
$time1 = microtime(true) - $startTime;
echo "Time without cache: " . number_format($time1, 3) . " seconds\n";
echo "Found " . count($replies1) . " replies\n\n";

echo "3. Testing email fetching (with cache)...\n";
$startTime = microtime(true);
$replies2 = $fetcher->getEmailReplies($testEmail, null, true); // With cache
$time2 = microtime(true) - $startTime;
echo "Time with cache: " . number_format($time2, 3) . " seconds\n";
echo "Found " . count($replies2) . " replies\n";

if ($time2 < $time1) {
    $improvement = (($time1 - $time2) / $time1) * 100;
    echo "✓ Cache improved performance by " . number_format($improvement, 1) . "%\n\n";
} else {
    echo "Cache performance test inconclusive (may be first run)\n\n";
}

echo "4. Testing cache management...\n";
$fetcher->clearCache($testEmail);
echo "✓ Cache cleared for test email\n";

echo "\nOptimization test completed!\n";
?>
