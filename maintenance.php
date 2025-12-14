<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    header("Location: index.php"); 
    exit;
}
// Handle server-side POST to send maintenance message to Firestore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'send_maintenance') {
    // Read JSON body
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    header('Content-Type: application/json');

    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $projectId = 'esp32-connecttest'; // Your Firebase project ID
    $firestoreUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents";
    
    $title = isset($data['title']) ? $data['title'] : '';
    $content = isset($data['content']) ? $data['content'] : '';
    $severity = isset($data['severity']) ? $data['severity'] : 'info';
    $timestamp = isset($data['timestamp']) ? $data['timestamp'] : date(DATE_ISO8601);
    $recipients = isset($data['recipients']) && is_array($data['recipients']) ? $data['recipients'] : [];

    $successCount = 0;
    $errors = [];
    $fcmSent = 0;
    $fcmErrors = [];

    // Create a single maintenance message document in Firestore
    $docId = 'maintenance_' . time() . '_' . uniqid();
    $maintenanceDocUrl = "{$firestoreUrl}/maintenance/{$docId}";

    $firestorePayload = [
        'fields' => [
            'title' => ['stringValue' => $title],
            'content' => ['stringValue' => $content],
            'severity' => ['stringValue' => $severity],
            'timestamp' => ['timestampValue' => $timestamp],
            'recipients' => ['arrayValue' => ['values' => array_map(function($r) { return ['stringValue' => $r]; }, $recipients)]],
            'read' => ['booleanValue' => false],
            'createdAt' => ['timestampValue' => date(DATE_ISO8601)]
        ]
    ];

    $ch = curl_init($maintenanceDocUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestorePayload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($resp !== false && $httpCode >= 200 && $httpCode < 300) {
        $successCount = 1;
        error_log("Maintenance message created in Firestore: {$docId}");
        
        // Send FCM notifications to recipients
        $fcmResult = sendFCMNotifications($title, $content, $severity, $recipients);
        $fcmSent = $fcmResult['sent'];
        $fcmErrors = $fcmResult['errors'];
    } else {
        $errors[] = ['firestore' => $httpCode, 'curl' => $curlErr, 'resp' => $resp];
    }

    echo json_encode([
        'success' => true,
        'sent' => $successCount,
        'docId' => $docId,
        'fcmSent' => $fcmSent,
        'errors' => $errors,
        'fcmErrors' => $fcmErrors
    ]);
    exit;
}

/**
 * Send FCM push notifications to specified recipients
 */
function sendFCMNotifications($title, $content, $severity, $recipients = []) {
    $sent = 0;
    $errors = [];

    // Check if service account key exists
    $keyPath = __DIR__ . '/includes/firebase-adminsdk-key.json';
    if (!file_exists($keyPath)) {
        return ['sent' => 0, 'errors' => ['Service account key not found at ' . $keyPath]];
    }

    // Load service account credentials
    $serviceAccount = json_decode(file_get_contents($keyPath), true);
    if (!$serviceAccount) {
        return ['sent' => 0, 'errors' => ['Invalid service account key']];
    }

    // Get FCM access token
    $accessToken = getFirebaseAccessToken($serviceAccount);
    if (!$accessToken) {
        return ['sent' => 0, 'errors' => ['Failed to get Firebase access token']];
    }

    $projectId = $serviceAccount['project_id'];
    $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    // Query Firestore to get all user FCM tokens
    $userTokens = getAllUserFCMTokens($accessToken, $projectId);

    // Send notification to each token
    foreach ($userTokens as $token) {
        $notificationPayload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $content
                ],
                'data' => [
                    'severity' => $severity,
                    'type' => 'maintenance',
                    'timestamp' => date(DATE_ISO8601)
                ]
            ]
        ];

        $ch = curl_init($fcmUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $sent++;
        } else {
            $errors[] = ['token' => substr($token, 0, 20) . '...', 'http' => $httpCode];
        }
    }

    return ['sent' => $sent, 'errors' => $errors];
}

/**
 * Get Firebase access token using service account credentials
 */
function getFirebaseAccessToken($serviceAccount) {
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $claim = json_encode([
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $header_b64 = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $claim_b64 = rtrim(strtr(base64_encode($claim), '+/', '-_'), '=');
    $signature_input = $header_b64 . '.' . $claim_b64;

    // Sign the token with the private key
    $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
    openssl_sign($signature_input, $signature, $privateKey, 'RSA-SHA256');
    $signature_b64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    $jwt = $signature_input . '.' . $signature_b64;

    // Exchange JWT for access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $respData = json_decode($resp, true);
        return $respData['access_token'] ?? null;
    }

    return null;
}

/**
 * Get all user FCM tokens from Firestore
 */
function getAllUserFCMTokens($accessToken, $projectId) {
    $tokens = [];
    $firestoreUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/users";

    $ch = curl_init($firestoreUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($resp, true);
        if (isset($data['documents'])) {
            foreach ($data['documents'] as $doc) {
                $fcmToken = $doc['fields']['fcmToken']['stringValue'] ?? null;
                if ($fcmToken) {
                    $tokens[] = $fcmToken;
                }
            }
        }
    }

    return $tokens;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management - CribEase</title>
    <link rel="stylesheet" href="assets/style.css?v=2">
    <style>
        .maintenance-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .message-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.2);
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-send {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-send:hover {
            background-color: #45a049;
        }

        .btn-clear {
            background-color: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-clear:hover {
            background-color: #da190b;
        }

        .recipient-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }

        .recipient-section h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .recipient-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .recipient-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .recipient-item input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }

        .recipient-item label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }

        .select-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .select-buttons button {
            background-color: #2196F3;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .select-buttons button:hover {
            background-color: #0b7dda;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .message-history {
            margin-top: 40px;
        }

        .message-history h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .message-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #4CAF50;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .message-item h4 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .message-item p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }

        .message-content {
            background: #f9f9f9;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 3px solid #2196F3;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="maintenance.php" class="active">Maintenance</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <div class="maintenance-container">
        <h1>Maintenance Message Management</h1>
        
        <div id="alertContainer"></div>

        <div class="message-form">
            <h2>Send Maintenance Message to Users</h2>
            
            <div class="form-group">
                <label for="messageTitle">Message Title</label>
                <input type="text" id="messageTitle" placeholder="e.g., System Maintenance Scheduled">
            </div>

            <div class="form-group">
                <label for="messageContent">Message Content</label>
                <textarea id="messageContent" placeholder="Enter your maintenance message here..."></textarea>
            </div>

            <div class="form-group">
                <label for="messageSeverity">Severity Level</label>
                <select id="messageSeverity">
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="critical">Critical</option>
                </select>
            </div>

            <div class="recipient-section">
                <h3>Select Recipients (By Device ID)</h3>
                
                <div class="select-buttons">
                    <button onclick="selectAllUsers()">Select All</button>
                    <button onclick="deselectAllUsers()">Deselect All</button>
                </div>

                <div id="recipientContainer" class="recipient-list">
                    <p style="text-align: center; color: #999; padding: 20px;">Loading users...</p>
                </div>
            </div>

            <div class="button-group">
                <button class="btn-send" onclick="sendMaintenanceMessage()">
                    <span id="sendButtonText">Send Message</span>
                    <span id="sendButtonSpinner" class="spinner" style="display: none; margin-left: 8px;"></span>
                </button>
                <button class="btn-clear" onclick="clearForm()">Clear Form</button>
            </div>
        </div>

        <div class="message-history">
            <h2>Recent Maintenance Messages</h2>
            <div id="messageHistoryContainer">
                <p style="text-align: center; color: #999;">Loading message history...</p>
            </div>
        </div>
    </div>
</div>

<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
    import {
        getFirestore,
        collection,
        onSnapshot,
        addDoc,
        query,
        orderBy,
        limit,
        getDocs
    } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";
    import { getDatabase, ref, get, update, set } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";

    // Firebase config
    const firebaseConfig = {
        apiKey: "AIzaSyDs6eEpYkKzOIbit60mitGDY6qbLMclxvs",
        authDomain: "esp32-connecttest.firebaseapp.com",
        databaseURL: "https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app",
        projectId: "esp32-connecttest",
        storageBucket: "esp32-connecttest.firebasestorage.app",
        messagingSenderId: "950000610308",
        appId: "1:950000610308:web:a39583249e23784128d951"
    };

    // Initialize Firebase
    const app = initializeApp(firebaseConfig);
    const db = getFirestore(app);
    const rtdb = getDatabase(app);

    let allUsers = [];
    let selectedUsers = new Set();

    // Load users from Firestore
    async function loadUsers() {
        try {
            const usersRef = collection(db, 'users');
            const snapshot = await getDocs(usersRef);
            allUsers = [];
            
            snapshot.forEach(doc => {
                const userData = doc.data();
                allUsers.push({
                    id: doc.id,
                    // Prefer 'deviceID' (how it's stored in Firestore), fallback to 'deviceId', then to doc id
                    deviceId: userData.deviceID || userData.deviceId || doc.id,
                    email: userData.email || 'No email'
                });
            });

            displayUsers();
        } catch (error) {
            console.error('Error loading users:', error);
            showAlert('Error loading users: ' + error.message, 'error');
        }
    }

    // Display users as checkboxes
    function displayUsers() {
        const container = document.getElementById('recipientContainer');
        
        if (allUsers.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No users found</p>';
            return;
        }

        container.innerHTML = allUsers.map(user => `
            <div class="recipient-item">
                <input type="checkbox" id="user_${user.id}" value="${user.id}" 
                       onchange="toggleUser('${user.id}')">
                <label for="user_${user.id}">
                    <strong>${user.deviceId}</strong><br>
                    <small style="color: #999;">${user.email}</small>
                </label>
            </div>
        `).join('');
    }

    // Toggle user selection
    window.toggleUser = function(userId) {
        if (selectedUsers.has(userId)) {
            selectedUsers.delete(userId);
        } else {
            selectedUsers.add(userId);
        }
    };

    // Select all users
    window.selectAllUsers = function() {
        allUsers.forEach(user => selectedUsers.add(user.id));
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
    };

    // Deselect all users
    window.deselectAllUsers = function() {
        selectedUsers.clear();
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    };

    // Send maintenance message
    window.sendMaintenanceMessage = async function() {
        const title = document.getElementById('messageTitle').value.trim();
        const content = document.getElementById('messageContent').value.trim();
        const severity = document.getElementById('messageSeverity').value;

        if (!title || !content) {
            showAlert('Please fill in both title and message content', 'error');
            return;
        }

        if (selectedUsers.size === 0) {
            showAlert('Please select at least one recipient', 'error');
            return;
        }

        const sendButton = document.querySelector('.btn-send');
        const spinnerEl = document.getElementById('sendButtonSpinner');
        const buttonText = document.getElementById('sendButtonText');

        sendButton.disabled = true;
        buttonText.style.display = 'none';
        spinnerEl.style.display = 'inline-block';

        try {
            const timestamp = new Date().toISOString();

            // Resolve device IDs for the selected users
            const selectedUsersList = Array.from(selectedUsers);
            const deviceIds = selectedUsersList.map(uid => {
                const u = allUsers.find(x => x.id === uid);
                return u ? u.deviceId : null;
            }).filter(Boolean);

            // POST to server-side endpoint to write to RTDB (both devices/{deviceId} and users/{userId} paths)
            const resp = await fetch(window.location.pathname + '?action=send_maintenance', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    title, content, severity, timestamp, 
                    recipients: deviceIds,
                    userIds: selectedUsersList  // also send Firestore user IDs for fallback path
                })
            });

            const result = await resp.json();
            if (!result || !result.success) {
                console.error('Server failed to send maintenance', result);
                showAlert('Failed to send maintenance to devices: ' + (result && result.error ? result.error : 'server error'), 'error');
            } else {
                // Add message to Firestore maintenance messages collection (history)
                const messageData = {
                    title: title,
                    content: content,
                    severity: severity,
                    timestamp: timestamp,
                    createdAt: new Date(),
                    recipients: selectedUsersList,
                    recipientCount: selectedUsersList.length
                };

                await addDoc(collection(db, 'maintenance_messages'), messageData);

                showAlert(`Maintenance message sent successfully to ${result.sent || 0}/${deviceIds.length} devices!`, 'success');

                // Clear form
                clearForm();

                // Reload message history
                loadMessageHistory();
            }

        } catch (error) {
            console.error('Error sending message:', error);
            showAlert('Error sending message: ' + error.message, 'error');
        } finally {
            sendButton.disabled = false;
            buttonText.style.display = 'inline';
            spinnerEl.style.display = 'none';
        }
    };

    // Load message history
    async function loadMessageHistory() {
        try {
            const messagesRef = collection(db, 'maintenance_messages');
            const q = query(messagesRef, orderBy('createdAt', 'desc'), limit(10));
            const snapshot = await getDocs(q);

            const historyContainer = document.getElementById('messageHistoryContainer');
            
            if (snapshot.empty) {
                historyContainer.innerHTML = '<p style="text-align: center; color: #999;">No maintenance messages sent yet</p>';
                return;
            }

            historyContainer.innerHTML = snapshot.docs.map((doc, index) => {
                const data = doc.data();
                const timestamp = new Date(data.timestamp).toLocaleString();
                const severityColor = {
                    'info': '#2196F3',
                    'warning': '#FF9800',
                    'critical': '#f44336'
                }[data.severity] || '#666';

                return `
                    <div class="message-item">
                        <h4 style="color: ${severityColor}; margin-top: 0;">
                            [${data.severity.toUpperCase()}] ${data.title}
                        </h4>
                        <p><strong>Sent:</strong> ${timestamp}</p>
                        <p><strong>Recipients:</strong> ${data.recipientCount || 0} user(s)</p>
                        <div class="message-content">${data.content}</div>
                    </div>
                `;
            }).join('');

        } catch (error) {
            console.error('Error loading message history:', error);
            document.getElementById('messageHistoryContainer').innerHTML = 
                '<p style="color: #f44336;">Error loading message history</p>';
        }
    }

    // Show alert
    window.showAlert = function(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alertElement = document.createElement('div');
        alertElement.className = `alert alert-${type}`;
        alertElement.textContent = message;
        alertContainer.innerHTML = '';
        alertContainer.appendChild(alertElement);

        setTimeout(() => {
            alertElement.remove();
        }, 5000);
    };

    // Clear form
    window.clearForm = function() {
        document.getElementById('messageTitle').value = '';
        document.getElementById('messageContent').value = '';
        document.getElementById('messageSeverity').value = 'info';
        deselectAllUsers();
    };

    // Initialize on page load
    loadUsers();
    loadMessageHistory();
</script>

</body>
</html>
