<?php
/**
 * Slack Configuration
 * Settings for Slack webhook integration
 */

// Slack webhook URL - Replace with your actual webhook URL
define('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/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');

// Status check settings
define('SLACK_CHECK_INTERVAL_MINUTES', 60);  // How often to check status (minutes)
define('SLACK_INACTIVITY_THRESHOLD_MINUTES', 15);  // Consider client inactive after X minutes

// Message settings
define('SLACK_MESSAGE_ENABLED', true);  // Enable/disable Slack notifications
define('SLACK_DEBUG_MODE', false);  // Enable debug logging

// Timezone for timestamps
// define('SLACK_TIMEZONE', 'Asia/Vladivostok');

// Log file path
define('SLACK_LOG_FILE', '../logs/slack_notifications.log');

// Server type: 'i' or 'e'
define('SLACK_SERVER_TYPE', 'e');

// Ensure log directory exists
$log_dir = dirname(SLACK_LOG_FILE);
if (!file_exists($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
?>
