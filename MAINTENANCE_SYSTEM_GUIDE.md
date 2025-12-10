# Maintenance Message System - Implementation Guide

## Overview
This maintenance system allows you to send targeted maintenance messages to specific users (by device ID) and display them as banners on login and main pages.

## Files Created

### 1. `maintenance.php` (Admin Panel)
- **Location**: `/CribEase/maintenance.php`
- **Purpose**: Admin interface to create and send maintenance messages
- **Features**:
  - Write maintenance message title and content
  - Set severity level (Info, Warning, Critical)
  - Select specific users by device ID
  - View history of sent messages
  - Select All / Deselect All users

### 2. `maintenance_banner.php` (Display Component)
- **Location**: `/CribEase/includes/maintenance_banner.php`
- **Purpose**: Display maintenance banners to users
- **Features**:
  - Real-time updates from Firebase
  - Color-coded by severity level
  - Dismissible with close button
  - Shows timestamp of last update

## How to Use

### Step 1: Add Sidebar Link
Update your sidebar in all admin pages to include a link to maintenance:

```php
<a href="maintenance.php" class="active">Maintenance</a>
```

### Step 2: Display Banner on Login Page
Add this line at the very top of your login page (after `<?php` opening tag):

```php
<?php
session_start();
// ... your existing code ...
?>

<?php include 'includes/maintenance_banner.php'; ?>

<!-- Rest of your login page HTML -->
```

**Important**: Place the `<?php include ?>` statement BEFORE any HTML output to ensure the container div exists.

### Step 3: Display Banner on Main Pages
Do the same for your dashboard, sensors, users, and other main pages:

```php
<?php include 'includes/maintenance_banner.php'; ?>
```

Add this container div at the top of your page content:

```html
<div id="maintenanceBannerContainer"></div>
```

### Step 4: Create Firestore Collection
Make sure you have a `maintenance_messages` collection in your Firestore database. The messages will be stored with:
- `title` (string): Message title
- `content` (string): Message body
- `severity` (string): info, warning, or critical
- `timestamp` (string): ISO timestamp
- `createdAt` (date): Firebase timestamp
- `recipients` (array): User IDs who received the message
- `recipientCount` (number): Number of recipients

## Workflow

### For Admin Users:
1. Go to **Maintenance** page in sidebar
2. Write your maintenance message (title & content)
3. Select severity level
4. Choose which users to send it to (by device ID)
5. Click **Send Message**
6. Message is immediately visible to selected users as a banner

### For Regular Users:
1. Visit login page or main dashboard
2. See maintenance banner if there's an active message
3. Can dismiss banner with the close button (×)
4. Banner updates in real-time if message changes

## Message Severity Levels

- **Info** (Blue): General information, low priority
- **Warning** (Orange): Important notice, medium priority
- **Critical** (Red): Urgent maintenance, high priority

## Database Structure

### Firestore Collection: `maintenance_messages`

```
maintenance_messages/
├── [doc_id]/
│   ├── title: "System Maintenance Scheduled"
│   ├── content: "We will be performing..."
│   ├── severity: "warning"
│   ├── timestamp: "2025-12-10T15:30:00Z"
│   ├── createdAt: [Firebase timestamp]
│   ├── recipients: ["user1", "user2", "user3"]
│   └── recipientCount: 3
```

## Features Included

✅ Send messages to specific users by device ID  
✅ Severity-based styling (Info, Warning, Critical)  
✅ Real-time updates using Firestore listeners  
✅ Message history tracking  
✅ Select/Deselect all recipients  
✅ Dismissible banners  
✅ Responsive design  
✅ Loading states and error handling  

## Troubleshooting

### Banner not showing?
1. Verify `maintenance_messages` collection exists in Firestore
2. Check that you added `<div id="maintenanceBannerContainer"></div>`
3. Check browser console for Firebase errors
4. Ensure Firebase config is correct

### Messages not sending?
1. Verify users are loaded correctly from Firestore
2. Check Firestore read/write permissions
3. Review browser console for errors
4. Ensure recipients are selected

### Real-time updates not working?
1. Check Firestore listeners are active
2. Verify network connectivity
3. Check Firestore security rules allow reads
4. Check browser console for Firebase errors

## Next Steps (Optional)

You could enhance this system with:
- Schedule messages for future delivery
- Message expiration/archival
- User read receipts
- Message templates
- Email notifications along with banner
- User feedback on maintenance messages
