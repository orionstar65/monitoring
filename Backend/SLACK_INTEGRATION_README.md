# Slack Status Integration - Setup Instructions

## Overview
This integration sends Slack notifications when monitoring clients become inactive (no activity for more than 5 minutes). The system groups clients by `slack_id` (or falls back to username) and sends attachment-style messages with individual notifications per user.

## Key Features

- ✅ **Slack ID Support** - Group notifications by Slack ID for flexible user management
- ✅ **Separate Messages** - Sends individual messages per user to avoid Slack length limits
- ✅ **Overall Status First** - Initial message shows summary statistics
- ✅ **Direct Mentions** - Uses `<@slack_id>` format for direct notifications
- ✅ **Attachment-Style Format** - Clean, readable code blocks for each computer
- ✅ **Rate Limiting Protection** - 500ms delay between messages
- ✅ **Only Inactive Clients** - Sends notifications only when there are inactive clients

## Files Created/Modified

### New Files:
1. **`Backend/html/app/config/slack_config.php`** - Configuration file
2. **`Backend/services/SlackService.php`** - Slack API integration
3. **`Backend/services/StatusMonitorService.php`** - Status monitoring logic
4. **`Backend/check_status.php`** - Cron job entry point with multiple commands
5. **`Backend/add_slack_id_column.sql`** - Database migration script
6. **`Backend/test_slack_id.php`** - Test script for integration

### Modified Files:
1. **`Backend/services/DatabaseService.php`** - Added methods to get inactive clients grouped by slack_id/username
2. **`Backend/html/app/views/monitors/edit.php`** - Added Slack ID input field
3. **`Backend/html/app/views/monitors/index.php`** - Added Slack ID column to table
4. **`Backend/html/app/controllers/Monitors.php`** - Updated to handle slack_id field

## Setup Instructions

### 1. Add Database Column
Run the SQL migration to add the `slack_id` column:
```bash
mysql -u root -p monitoring < Backend/add_slack_id_column.sql
```

Or manually execute:
```sql
ALTER TABLE `tbl_monitor_ips` 
ADD COLUMN `slack_id` varchar(100) DEFAULT NULL AFTER `username`;
CREATE INDEX `idx_slack_id` ON `tbl_monitor_ips` (`slack_id`);
```

### 2. Configure Slack Webhook
Edit `Backend/html/app/config/slack_config.php`:
```php
define('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK');
define('SLACK_INACTIVITY_THRESHOLD_MINUTES', 5);
```

### 3. Assign Slack IDs to Monitors
- Navigate to the monitors page in the web interface
- Click "Edit" on any monitor
- Enter a Slack ID in the "Slack ID" field
- Monitors with the same Slack ID will be grouped together in notifications
- If Slack ID is empty, it will fall back to username

### 4. Test the Integration
```bash
# Navigate to Backend directory
cd Backend

# Test Slack connection
php check_status.php test

# Get status summary
php check_status.php summary

# Run status check manually
php check_status.php check

# Test Slack ID functionality
php test_slack_id.php
```

### 5. Setup Cron Job
Add to crontab to run every 5 minutes:
```bash
# Edit crontab
crontab -e

# Add this line (adjust path as needed)
*/5 * * * * cd /path/to/Backend && php check_status.php >> logs/slack_notifications.log 2>&1
```

## Message Format

### Message Sequence
When there are inactive clients, Slack will receive:

**Message 1: Overall Status Summary**
```
[Empty message text]

[Attachment with statistics:]
├─ Color: Red (for offline) or Yellow (for inactive)
├─ Title: "Monitoring System Status - Dec 20 14:30"
├─ Footer: "Last check: 14:30 • Total inactive: 5 computers"
└─ Statistics (via fields)
```

**Message 2: User 1 Details**
```
[Empty message text]

[Attachment:]
├─ Color: Based on user's computers
├─ Title: "🫵🏻 <@john.doe>"
├─ Text: Code blocks with computer details
└─ Footer: "Last checked: 14:30"
```

**Message 3: User 2 Details**
```
[Same format as Message 2]
```

### Computer Detail Format
Each computer's information appears in a code block:
```
🔴 john.doe (desktop): Offline from 12:45
📸 12:40   ⌨️ 12:42   🌐 12:35   🔌 12:38
192.168.1.10    AA:BB:CC:DD:EE:FF
```

### Key Details:
- **Status Icon**: 🔴 Offline | 🟡 Inactive
- **Computer Name**: Username or IP address (truncated to 30 chars)
- **Device Type**: desktop | laptop | server
- **Status**: Offline | Inactive
- **Last Update**: HH:MM format
- **Activity Times**: 📸 Screenshot | ⌨️ Keylog | 🌐 Browser | 🔌 USB
- **Network Info**: IP address and MAC address

## Configuration Options

In `Backend/html/app/config/slack_config.php`:
- `SLACK_WEBHOOK_URL` - Your Slack webhook URL
- `SLACK_INACTIVITY_THRESHOLD_MINUTES` - Consider inactive after X minutes (default: 5)
- `SLACK_MESSAGE_ENABLED` - Enable/disable notifications (default: true)
- `SLACK_DEBUG_MODE` - Enable debug logging (default: false)
- `SLACK_LOG_FILE` - Path to log file (default: "logs/slack_notifications.log")
- `SLACK_TIMEZONE` - Timezone for timestamps (default: "Asia/Seoul")

## Grouping Logic

The system groups clients using the following priority:
1. **slack_id** - If a monitor has a Slack ID assigned
2. **username** - If Slack ID is empty, uses username
3. **"Unknown"** - Fallback if both are empty

**Example:**
- Monitor A: slack_id="U123456", username="john.doe" → Groups under "U123456"
- Monitor B: slack_id="", username="john.doe" → Groups under "john.doe"
- Monitor C: slack_id="", username="" → Groups under "Unknown"

## Commands Available

The `check_status.php` script supports multiple commands:

```bash
# Test Slack webhook connection
php check_status.php test

# Get summary of inactive clients
php check_status.php summary

# Run full status check and send notifications
php check_status.php check

# Show help
php check_status.php help
```

## Troubleshooting

### 1. Check Logs
```bash
tail -f Backend/logs/slack_notifications.log
```

### 2. Test Webhook Connection
```bash
php check_status.php test
```

### 3. Verify Database Setup
```bash
php test_slack_id.php
```

### 4. Common Issues

**Issue**: Messages not being sent
- Check webhook URL is configured correctly
- Verify `SLACK_MESSAGE_ENABLED` is true
- Check logs for errors
- Test with `php check_status.php test`

**Issue**: Too many messages
- Adjust `SLACK_INACTIVITY_THRESHOLD_MINUTES` to a higher value
- Check if multiple monitors have the same Slack ID

**Issue**: Messages grouped incorrectly
- Verify Slack IDs are assigned correctly in monitor edit page
- Check that monitors intended to group together share the same Slack ID

## Slack Mentions

The system uses Slack's mention format `<@slack_id>` to notify users directly. This:
- Sends them a notification
- Highlights the message for that user
- Works with Slack user IDs (starting with "U")

**To get a Slack User ID:**
1. Open Slack in a browser
2. Right-click on the user in the members list
3. Click "Copy member ID"
4. Use this ID as the `slack_id` in the monitor settings

## Development Notes

### Message Flow
1. **StatusMonitorService** queries inactive clients grouped by slack_id/username
2. **SlackService** builds message payloads:
   - First message: Overall status summary
   - Subsequent messages: One per user with their computer details
3. **SlackService** sends messages with 500ms delay between each
4. Results are logged

### Rate Limiting
- 500ms delay between messages (configurable in `sendMultipleMessages()`)
- Prevents hitting Slack's rate limits
- Can be adjusted in `StatusMonitorService.php` line 58

### Error Handling
- Individual message failures are logged but don't stop the process
- Returns success count vs total count
- All errors logged to `Backend/logs/slack_notifications.log`

## Database Schema

### New Column: `slack_id`
```sql
ALTER TABLE tbl_monitor_ips 
ADD COLUMN slack_id VARCHAR(100) DEFAULT NULL AFTER username;
CREATE INDEX idx_slack_id ON tbl_monitor_ips(slack_id);
```

### Query Logic
The system uses `COALESCE(m.slack_id, m.username)` to group by Slack ID first, then username.

## Testing Workflow

1. **Add test data** with inactive clients in database
2. **Assign Slack IDs** via monitor edit page
3. **Run test**: `php check_status.php summary`
4. **Check output** for correct grouping
5. **Send test message**: `php check_status.php check`
6. **Verify in Slack** that messages are received correctly
7. **Check format** - overall status first, then individual user messages

## API Usage

### In Code
```php
require_once 'services/StatusMonitorService.php';

$statusMonitor = new StatusMonitorService();
$statusMonitor->checkAndNotify(); // Checks and sends notifications
```

### Direct Slack Service
```php
require_once 'services/SlackService.php';

$slackService = new SlackService();
$slackService->testConnection(); // Test webhook
```

## Security Considerations

- Slack webhook URLs are stored in config file (not in repository)
- Database queries use prepared statements
- All user input is escaped for Slack
- Log files contain no sensitive information
- Rate limiting prevents abuse

## Future Enhancements

Potential improvements:
- [ ] Slack Block Kit for richer formatting
- [ ] Configurable notification schedules
- [ ] Different webhook URLs per department/group
- [ ] Message templates customization
- [ ] Historical tracking in database
- [ ] Webhook retry logic
- [ ] Slack channel assignment per user
