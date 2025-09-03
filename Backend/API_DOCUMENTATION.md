# Monitoring System API Documentation v2.0

## Overview

This document describes the API endpoints for the Monitoring System backend. The system uses a database-driven approach for storing all monitoring data, providing scalability and efficient querying capabilities.

## Base URLs

- **Local Development**: `http://localhost/`
- **Production**: `http://your-server-domain/`
- **Custom Port**: `http://your-server:port/`

## Authentication

Currently, the API uses IP-based authentication. All requests are automatically associated with the client's IP address (`$_SERVER['REMOTE_ADDR']`).

## API Endpoints

---

## 1. Event Handler API

**Endpoint**: `POST /eventhandler.php`  
**Purpose**: Handle real-time monitoring events (KeyLog, BrowserHistory, USBLog, Tic)  
**Content-Type**: `application/json`

### Event Types

#### 1.1 Tic Event (Heartbeat)
**Event**: `Tic`  
**Purpose**: Regular heartbeat to indicate system is online

**Request Body**:
```json
{
  "Event": "Tic",
  "Version": "1.0.0",
  "MacAddress": "AA:BB:CC:DD:EE:FF",
  "Username": "john.doe"
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Event processed successfully",
  "data": {
    "interval": 30
  }
}
```

#### 1.2 Browser History Event
**Event**: `BrowserHistory`  
**Purpose**: Upload browser browsing history data

**Request Body**:
```json
{
  "Event": "BrowserHistory",
  "Version": "1.0.0",
  "MacAddress": "AA:BB:CC:DD:EE:FF",
  "Username": "john.doe",
  "BrowserHistories": [
    {
      "browser": "Chrome",
      "url": "https://www.google.com",
      "title": "Google",
      "last_visit": 1693123456,
      "date": "2023-08-27 10:30:00"
    },
    {
      "browser": "Firefox",
      "url": "https://github.com",
      "title": "GitHub: Where the world builds software",
      "last_visit": 1693123500,
      "date": "2023-08-27 10:31:00"
    }
  ]
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Event processed successfully",
  "data": {
    "inserted_count": 2,
    "interval": 120
  }
}
```

#### 1.3 Key Log Event
**Event**: `KeyLog`  
**Purpose**: Upload keyboard activity logs

**Request Body**:
```json
{
  "Event": "KeyLog",
  "Version": "1.0.0",
  "MacAddress": "AA:BB:CC:DD:EE:FF",
  "Username": "john.doe",
  "KeyLogs": [
    {
      "date": "2023-08-27 10:30:00",
      "application": "Notepad",
      "key": "Hello World"
    },
    {
      "date": "2023-08-27 10:31:00",
      "application": "Chrome",
      "key": "Enter"
    }
  ]
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Event processed successfully",
  "data": {
    "inserted_count": 2,
    "interval": 60
  }
}
```

#### 1.4 USB Log Event
**Event**: `USBLog`  
**Purpose**: Upload USB device connection/disconnection logs

**Request Body**:
```json
{
  "Event": "USBLog",
  "Version": "1.0.0",
  "MacAddress": "AA:BB:CC:DD:EE:FF",
  "Username": "john.doe",
  "USBLogs": [
    {
      "date": "2023-08-27 10:30:00",
      "device_name": "USB Flash Drive",
      "device_path": "/dev/sdb1",
      "device_type": "Storage",
      "action": "Connected"
    },
    {
      "date": "2023-08-27 10:35:00",
      "device_name": "USB Mouse",
      "device_path": "/dev/input/mouse0",
      "device_type": "Input",
      "action": "Disconnected"
    }
  ]
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Event processed successfully",
  "data": {
    "inserted_count": 2,
    "interval": 30
  }
}
```

### Event Handler Response Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 400 | Bad Request (Invalid JSON, Unknown Event Type) |
| 500 | Internal Server Error |

---

## 2. Screenshot Upload API

**Endpoint**: `POST /webapi.php?type=upload`  
**Purpose**: Upload screenshot images with metadata  
**Content-Type**: `multipart/form-data`

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `com_name` | text | Yes | Computer/device identifier |
| `ctime` | text | Yes | Capture timestamp (format: YYYY-MM-DD HH:MM:SS) |
| `imagefile` | file | Yes | Image file (JPEG, PNG, GIF supported) |

### Example Request

**cURL**:
```bash
curl -X POST "http://your-server/webapi.php?type=upload" \
  -F "com_name=DESKTOP-ABC123" \
  -F "ctime=2023-08-27 10:30:00" \
  -F "imagefile=@screenshot.jpg"
```

**Postman**:
- Method: `POST`
- URL: `http://your-server/webapi.php?type=upload`
- Body: `form-data`
- Fields:
  - `com_name`: `DESKTOP-ABC123`
  - `ctime`: `2023-08-27 10:30:00`
  - `imagefile`: [Select image file]

### Response

**Success**:
```json
{
  "status": "success",
  "message": "Screenshot uploaded successfully",
  "data": {
    "filename": "192_168_1_40_2023-08-27_10.30.00.jpg",
    "file_size": 245760,
    "image_width": 1920,
    "image_height": 1080,
    "interval": 5
  }
}
```

**Error**:
```json
{
  "error": "Failed to upload screenshot"
}
```

### File Naming Convention

Screenshots are automatically named using the format:
```
{ip_address}_{datetime}.{extension}
```

**Example**: `192_168_1_40_2023-08-27_10.30.00.jpg`

### Features

- **Automatic Thumbnail Generation**: 200x150px thumbnails created automatically
- **IP-based Organization**: Files organized by client IP address
- **Date-based Directories**: Files stored in date-based subdirectories
- **Database Metadata**: All file information stored in database for easy querying

---

## 3. Username Update API

**Endpoint**: `POST /webapi.php?type=username`  
**Purpose**: Update username for a monitor IP  
**Content-Type**: `application/json`

### Request Body

```json
{
  "username": "john.doe"
}
```

### Example Request

**cURL**:
```bash
curl -X POST "http://your-server/webapi.php?type=username" \
  -H "Content-Type: application/json" \
  -d '{"username": "john.doe"}'
```

### Response

**Success**:
```json
{
  "status": "success",
  "message": "Username updated successfully"
}
```

**Error**:
```json
{
  "error": "Username is required"
}
```

---

## 4. Data Storage Architecture

### Database Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `tbl_monitor_ips` | Monitor client information | IP address, MAC address, username, app version |
| `browser_history_logs` | Browser history data | URL, title, browser, visit date |
| `key_logs` | Keyboard activity | Application, key pressed, timestamp |
| `usb_device_logs` | USB device activity | Device name, action, timestamp |
| `screenshot_metadata` | Screenshot information | Filename, file path, dimensions, size |

### File Storage Structure

```
html/data/
├── screens/
│   └── ip_192.168.1.40/
│       └── 2023-08-27/
│           ├── 192_168_1_40_2023-08-27_10.30.00.jpg
│           ├── 192_168_1_40_2023-08-27_11.15.00.png
│           └── 192_168_1_40_2023-08-27_14.20.00.gif
├── thumbnails/
│   └── ip_192.168.1_40/
│       └── 2023-08-27/
│           ├── 192_168_1_40_2023-08-27_10.30.00.jpg
│           ├── 192_168_1_40_2023-08-27_11.15.00.png
│           └── 192_168_1_40_2023-08-27_14.20.00.gif
└── logs/
    └── ip_192.168.1.40/
        └── [legacy - no longer used]
```

---

## 5. Best Practices

### Client Implementation

1. **Regular Heartbeats**: Send Tic events every 30 seconds
2. **Batch Data**: Collect and send data in batches rather than individual records
3. **Error Handling**: Implement retry logic for failed requests
4. **Rate Limiting**: Respect the interval values returned in responses

### Data Formatting

1. **Timestamps**: Use ISO 8601 format (YYYY-MM-DD HH:MM:SS)
2. **File Uploads**: Ensure proper multipart/form-data encoding
3. **JSON Validation**: Validate JSON structure before sending
4. **Character Encoding**: Use UTF-8 for all text data

### Security Considerations

1. **IP-based Access**: Currently uses client IP for identification
2. **Input Validation**: All inputs are sanitized and validated
3. **File Upload Limits**: Implement appropriate file size limits
4. **Rate Limiting**: Consider implementing request rate limiting

---

## 6. Error Handling

### Common Error Scenarios

| Error | Cause | Solution |
|-------|-------|----------|
| 400 Bad Request | Invalid JSON format | Check JSON syntax and structure |
| 400 Bad Request | Unknown event type | Verify event type is one of: Tic, BrowserHistory, KeyLog, USBLog |
| 500 Internal Server Error | Database connection failure | Check database server status |
| 500 Internal Server Error | File upload failure | Verify file permissions and disk space |

### Error Response Format

```json
{
  "error": "Error description message"
}
```

---

## 7. Testing

### Postman Collection

Import this collection for testing all endpoints:

```json
{
  "info": {
    "name": "Monitoring System API",
    "description": "Complete API testing collection"
  },
  "item": [
    {
      "name": "Event Handler - Tic",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "url": "{{base_url}}/eventhandler.php",
        "body": {
          "mode": "raw",
          "raw": "{\"Event\":\"Tic\",\"Version\":\"1.0.0\",\"MacAddress\":\"AA:BB:CC:DD:EE:FF\",\"Username\":\"test.user\"}"
        }
      }
    }
  ]
}
```

### Test Data

Use the example request bodies provided in each endpoint section for testing.

---

## 8. Support

For technical support or questions about the API:

- **Documentation**: This document
- **Error Logs**: Check server error logs for debugging
- **Database**: Verify data is being stored correctly
- **File Permissions**: Ensure proper write permissions for uploads

---

*Last Updated: September 2025*  
*Version: 2.0*  
*API Status: Production Ready* 