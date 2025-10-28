<?php
/**
 * Quick test to check if WP Cron is working
 * 
 * Run this from the WordPress root: php wp-content/plugins/farmers-floorplans/test-cron.php
 */

// Load WordPress
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "=== WP Cron Diagnostic ===\n\n";

// Check if cron is disabled
$cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
echo "DISABLE_WP_CRON: " . ($cron_disabled ? 'YES (cron disabled)' : 'NO (cron enabled)') . "\n";

// Check if functions exist
$spawn_cron = function_exists('spawn_cron');
$fastcgi = function_exists('fastcgi_finish_request');

echo "spawn_cron() available: " . ($spawn_cron ? 'YES' : 'NO') . "\n";
echo "fastcgi_finish_request() available: " . ($fastcgi ? 'YES' : 'NO') . "\n\n";

// Check scheduled events
echo "Scheduled 'ffp_run_sync' events:\n";
$cron_array = _get_cron_array();
$found = false;

if ($cron_array) {
    foreach ($cron_array as $timestamp => $cron) {
        foreach ($cron as $hook => $dings) {
            if ($hook === 'ffp_run_sync') {
                $found = true;
                echo "  - Found at timestamp: " . date('Y-m-d H:i:s', $timestamp) . " (" . human_time_diff(time(), $timestamp) . " from now)\n";
            }
        }
    }
}

if (!$found) {
    echo "  - No scheduled events found\n";
}

echo "\n";

// Test spawning cron
echo "Attempting to spawn cron...\n";
if ($spawn_cron) {
    $result = spawn_cron();
    echo "spawn_cron() returned: " . ($result ? 'TRUE' : 'FALSE') . "\n";
} else {
    echo "spawn_cron() not available\n";
}

echo "\nDone!\n";

