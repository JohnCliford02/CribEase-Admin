<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">
<?php include 'includes/maintenance_banner.php'; ?>

<style>
/* Highlight active user row */
.user-row.active {
    background: #e8f4f8;
    border-left: 4px solid #2980b9;
}

.user-row.active td:first-child {
    font-weight: bold;
}

/* Responsive table wrapper and truncation to avoid page horizontal scroll */
# Make the table wrapper allow visible overflow for interactive elements
.table-wrapper{ max-width:100%; overflow:visible; }
.table-wrapper .scrollable{ overflow-x:auto; }
#userTable, .table-wrapper table { width:100%; border-collapse:collapse; table-layout:fixed; }
#userTable th, #userTable td, .table-wrapper table th, .table-wrapper table td {
    padding:8px 10px; border:1px solid #eee; vertical-align:top; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
/* Allow certain cells to wrap if needed (e.g., long sensor logs) */
.wrap-allow { white-space:normal; word-break:break-word; }

/* Make action buttons stay visible */
#userTable td .btn { white-space:nowrap; }

/* ID cell expands on hover */
#userTable td:first-child {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: default;
}

#userTable td:first-child:hover {
    white-space: normal;
    overflow: visible;
    word-break: break-word;
    background-color: #f9f9f9;
    z-index: 10;
    position: relative;
    max-width: 300px;
}

/* Device ID column (6th column) expands on hover */
#userTable td:nth-child(6) {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: default;
}

#userTable td:nth-child(6):hover {
    white-space: normal;
    overflow: visible;
    word-break: break-word;
    background-color: #f9f9f9;
    z-index: 10;
    position: relative;
    max-width: 300px;
}

/* Allow Action column contents (buttons, dropdowns, etc.) to expand without being clipped */
#userTable th:last-child, #userTable td:last-child {
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    position: relative;
    z-index: 50;
}

/* Ensure interactive elements inside the action cell can position themselves */
.user-action { display: inline-block; position: relative; }

/* Keep action buttons compact but prevent them from being truncated */
#userTable td .btn { white-space: nowrap; overflow: visible; }

/* Search box styling */
.search-box {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-box input {
    flex: 1;
    max-width: 400px;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.search-box input:focus {
    outline: none;
    border-color: #2980b9;
    box-shadow: 0 0 5px rgba(41, 128, 185, 0.3);
}

.search-box button {
    padding: 10px 15px;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 1000px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

.modal-header h2 {
    margin: 0;
    font-size: 22px;
    color: #2c3e50;
}

.modal-close-btn {
    background: #c0392b;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.modal-close-btn:hover {
    background: #a93226;
}

.modal-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.modal-table th, .modal-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}

.modal-table th {
    background: #2c3e50;
    color: white;
    font-weight: bold;
}

.modal-table tr:nth-child(even) {
    background: #f9f9f9;
}

.modal-table tr:hover {
    background: #f0f0f0;
}
</style>

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php" class="active">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="maintenance.php">Maintenance</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <div id="maintenanceBannerContainer"></div>
    <h1>User Management</h1>

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by name, email, or role...">
        <button class="btn" onclick="clearSearch()">Clear</button>
    </div>

    <div class="table-wrapper">
        <div class="scrollable">
            <table id="userTable">
                <tr>
                    <th style="width:10%">ID</th>
                    <th style="width:25%">Full Name</th>
                    <th style="width:20%">Email</th>
                    <th style="width:12%">Birthdate</th>
                    <th style="width:8%">Role</th>
                    <th style="width:10%">Device ID</th>
                    <th style="width:12%">Created At</th>
                    <th style="width:10%">Action</th>
                </tr>
            </table>
        </div>
    </div>

    <div id="userRecords" style="margin-top:30px;"></div>
</div>

<!-- Modal for Sensor History -->
<div id="sensorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Sensor History</h2>
            <button class="modal-close-btn" onclick="closeSensorModal()">Close</button>
        </div>
        <div id="modalBody">
            <p>Loading...</p>
        </div>
    </div>
</div>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import {
    getFirestore,
    collection,
    onSnapshot,
    doc,
    deleteDoc,
    getDoc,
    getDocs,
    query,
    where,
    orderBy,
    limit
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";
import { getDatabase, ref, get, remove } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-database.js";

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

const userTable = document.getElementById("userTable");

// Search functionality
window.filterUsers = function() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = userTable.querySelectorAll('tr:not(:first-child)');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
};

window.clearSearch = function() {
    document.getElementById('searchInput').value = '';
    filterUsers();
};

document.getElementById('searchInput').addEventListener('keyup', filterUsers);

// Query users ordered by creation timestamp descending so newest registered users appear first
const usersQuery = query(collection(db, "users"), orderBy("createdAt", "desc"));
onSnapshot(usersQuery, (snapshot) => {
    // Clear existing rows (header)
    userTable.innerHTML = `
        <tr>
            <th>ID</th><th>Full Name</th><th>Email</th><th>Birthdate</th><th>Role</th><th>Device ID</th><th>Created At</th><th>Action</th>
        </tr>
    `;

    if (snapshot.empty) return;

    snapshot.forEach(docSnap => {
        const id = docSnap.id;
        const u = docSnap.data();

        const row = document.createElement("tr");
        const birthdate = u.birthdate ? u.birthdate : "-";
        const role = u.role ? u.role : "-";
        const deviceId = u.deviceID ?? u.device_id ?? u.deviceId ?? "-";
        
        // Format createdAt timestamp
        let createdTime = '-';
        if (u.createdAt) {
            try {
                if (u.createdAt.toDate) {
                    createdTime = u.createdAt.toDate().toLocaleString();
                } else {
                    createdTime = new Date(u.createdAt).toLocaleString();
                }
            } catch (e) {
                createdTime = '-';
            }
        }

        // Build display name from available fields; prioritize fullName, fullname, name, then firstName/lastName
        const displayName = u.fullName ?? u.fullname ?? u.name ?? ((u.firstName || u.lastName) ? `${u.firstName ?? ''} ${u.lastName ?? ''}`.trim() : '-');

        row.innerHTML = `
            <td>${id}</td>
            <td><a href="#" title="${escapeHtml(displayName)}" onclick="showUserRecords('${id}', this);return false;">${escapeHtml(displayName)}</a></td>
            <td><span title="${escapeHtml(u.email ?? '-')}">${escapeHtml(u.email ?? '-')}</span></td>
            <td><span title="${escapeHtml(birthdate)}">${escapeHtml(birthdate)}</span></td>
            <td><span title="${escapeHtml(role)}">${escapeHtml(role)}</span></td>
            <td><span title="${escapeHtml(deviceId)}">${escapeHtml(deviceId)}</span></td>
            <td><span title="${escapeHtml(createdTime)}">${escapeHtml(createdTime)}</span></td>
            <td>
                <a class="btn" href="edit_user.php?id=${id}">Edit</a>
                <button class="btn" style="background:#c0392b;" onclick="deleteUser('${id}')">
                    Delete
                </button>
            </td>
        `;
        row.className = 'user-row';
        userTable.appendChild(row);
    });
}, err => console.error('users snapshot error', err));

// -------------------------------------------
// Delete user from Firestore
// -------------------------------------------
window.deleteUser = async function(id) {
    if (!confirm("Are you sure you want to delete this user? This will also delete all associated sensor data.")) return;
    try {
        // First, fetch the user to get their device ID
        let userDeviceId = null;
        try {
            const userDoc = await getDoc(doc(db, "users", id));
            if (userDoc.exists()) {
                const userData = userDoc.data();
                userDeviceId = userData.deviceID ?? userData.device_id ?? userData.deviceId ?? null;
            }
        } catch (e) {
            console.warn('Could not fetch user data:', e);
        }

        // Delete user from Firestore
        await deleteDoc(doc(db, "users", id));

        // Also delete associated device sensor data from Realtime Database
        try {
            if (userDeviceId) {
                // Delete the specific device that matches this user's device ID
                await remove(ref(rtdb, `devices/${userDeviceId}`));
                console.log(`Deleted device ${userDeviceId} for user ${id}`);
            } else {
                console.log('No device ID found for user, skipping device deletion');
            }
        } catch (e) {
            console.warn('Could not delete associated device data:', e);
        }
    } catch (e) {
        console.error('deleteUser error', e);
        alert('Failed to delete user. See console for details.');
    }
};

// Utility to escape HTML for display inside the table
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"'`]/g, function (s) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '`': '&#x60;'
        })[s];
    });
}

// Close sensor records and remove highlight
window.closeUserRecords = function() {
    document.getElementById('userRecords').innerHTML = '';
    document.querySelectorAll('.user-row').forEach(row => row.classList.remove('active'));
};

// Close modal
window.closeSensorModal = function() {
    document.getElementById('sensorModal').style.display = 'none';
};

// Show sensor records in modal from Realtime Database
window.showUserRecords = async function(userId, linkElement) {
    const modal = document.getElementById('sensorModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');

    // Remove active class from all rows and mark this one
    document.querySelectorAll('.user-row').forEach(row => row.classList.remove('active'));
    if (linkElement) {
        linkElement.closest('tr').classList.add('active');
    }

    // Fetch user details for modal title
    let fullname = '-';
    try {
        const userDoc = await getDoc(doc(db, 'users', userId));
        if (userDoc.exists()) {
            const u = userDoc.data();
            fullname = u.fullName ?? u.fullname ?? u.name ?? ((u.firstName || u.lastName) ? `${u.firstName ?? ''} ${u.lastName ?? ''}`.trim() : '-');
        }
    } catch (e) {
        console.error('failed to fetch user for modal title', e);
    }

    modalTitle.innerText = `Sensor History - ${escapeHtml(fullname)}`;
    modalBody.innerHTML = '<p>Loading sensor history...</p>';
    modal.style.display = 'block';

    // Query Realtime Database devices for sensor records
    try {
        console.log('Fetching device sensor data for user:', userId);
        
        // Fetch user to get their device ID
        let userDeviceId = null;
        try {
            const userDoc = await getDoc(doc(db, 'users', userId));
            if (userDoc.exists()) {
                const userData = userDoc.data();
                userDeviceId = userData.deviceID ?? userData.device_id ?? userData.deviceId ?? null;
                console.log('User device ID:', userDeviceId);
            }
        } catch (e) {
            console.error('Could not fetch user device ID:', e);
        }

        // If user has no device ID, show message
        if (!userDeviceId) {
            modalBody.innerHTML = '<p style="text-align:center; padding:20px; color:#e74c3c;">This user does not have an assigned device ID.</p>';
            return;
        }

        const devicesRef = ref(rtdb, 'devices');
        const devicesSnap = await get(devicesRef);
        
        if (!devicesSnap.exists()) {
            modalBody.innerHTML = '<p style="text-align:center; padding:20px;">No device data found in Realtime Database.</p>';
            return;
        }

        const devicesData = devicesSnap.val();
        console.log('Total devices in DB:', Object.keys(devicesData).length);
        
        const records = [];
        
        // Collect ALL sensor records from the user's device (historical data)
        if (devicesData[userDeviceId]) {
            const device = devicesData[userDeviceId];
            const sensor = device.sensor || {};
            const info = device.info || {};
            
            console.log('Device data:', device);
            console.log('Sensor object keys:', Object.keys(sensor));
            
            const sensorKeys = Object.keys(sensor);
            
            if (sensorKeys.length > 0) {
                // Check if this looks like a flat sensor object (has temperature, fallCount, etc.)
                if (sensor.temperature !== undefined || sensor.fallCount !== undefined || sensor.fallStatus !== undefined) {
                    // Flat sensor structure - single record
                    records.push({
                        ...sensor,
                        device_id: userDeviceId,
                        deviceStartTime: info.deviceStartTime,
                        deviceLastActive: info.deviceLastActive
                    });
                } else {
                    // Nested structure - collect ALL entries (historical records)
                    sensorKeys.forEach(key => {
                        const sensorEntry = sensor[key];
                        // Skip if not an object
                        if (typeof sensorEntry !== 'object' || sensorEntry === null) return;
                        
                        records.push({
                            ...sensorEntry,
                            device_id: userDeviceId,
                            deviceStartTime: info.deviceStartTime,
                            deviceLastActive: info.deviceLastActive
                        });
                    });
                }
            }
        }

        console.log('Sensor records found for user:', records.length);
        if (records.length > 0) {
            console.log('First record fields:', Object.keys(records[0]));
            console.log('First record data:', records[0]);
            console.log('Last record data:', records[records.length - 1]);
            // Log all field values
            Object.entries(records[0]).forEach(([key, value]) => {
                console.log(`First record - ${key}:`, value);
            });
        }

        if (records.length === 0) {
            modalBody.innerHTML = '<p style="text-align:center; padding:20px;">No sensor records found for this user.</p>';
            return;
        }

        // Sort by timestamp descending if available
        records.sort((a, b) => {
            const timeA = a.timestamp ? (a.timestamp.toDate ? a.timestamp.toDate().getTime() : new Date(a.timestamp).getTime()) : 0;
            const timeB = b.timestamp ? (b.timestamp.toDate ? b.timestamp.toDate().getTime() : new Date(b.timestamp).getTime()) : 0;
            return timeB - timeA;
        });

        let html = `<table class="modal-table"><thead><tr>
                        <th>Device ID</th>
                        
                        <th>Fall Count</th>
                        <th>Fall Status</th>
                        
                        <th>Sleep Pattern</th>
                        <th>Sound</th>
                        <th>Temperature</th>
                        <th>Start Time</th>
                        <th>Last Active</th>
                    </tr></thead><tbody>`;

        // Get the first (oldest) and last (most recent) sensor timestamps
        const firstRecord = records[records.length - 1]; // Last item is oldest after sorting desc
        const lastRecord = records[0]; // First item is most recent after sorting desc
        
        // Format timestamps - check if they're already formatted strings or need conversion
        const formatTimestamp = (timestamp) => {
            if (!timestamp) return '-';
            
            // If it's already a formatted string like "12/09/2025 - 13:50:15", convert to 12-hour format
            if (typeof timestamp === 'string' && timestamp.includes('-')) {
                return formatTo12Hour(timestamp);
            }
            
            // Otherwise try to convert from Date/Timestamp object
            let date;
            try {
                if (timestamp.toDate && typeof timestamp.toDate === 'function') {
                    date = timestamp.toDate();
                } else if (typeof timestamp === 'string') {
                    date = new Date(timestamp);
                } else if (typeof timestamp === 'number') {
                    date = new Date(timestamp);
                } else if (timestamp instanceof Date) {
                    date = timestamp;
                } else if (typeof timestamp === 'object' && timestamp.seconds) {
                    date = new Date(timestamp.seconds * 1000);
                } else {
                    return '-';
                }
                
                if (isNaN(date.getTime())) return '-';
                
                const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
                const formatted = date.toLocaleString('en-US', options);
                return formatted.replace(', ', ' - ');
            } catch (e) {
                console.error('Error formatting timestamp:', timestamp, e);
                return '-';
            }
        };
        
        // Helper function to convert 24-hour to 12-hour format (matching sensors.php)
        const formatTo12Hour = (dateTimeStr) => {
            if (!dateTimeStr || !dateTimeStr.includes('-')) return '-';
            
            try {
                const parts = dateTimeStr.split(' - ');
                const timePart = parts[1];
                const [hours, minutes, seconds] = timePart.split(':');
                
                let hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                if (hour > 12) hour -= 12;
                if (hour === 0) hour = 12;
                
                return `${parts[0]} - ${String(hour).padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
            } catch (e) {
                return dateTimeStr;
            }
        };
        
        const startTimeFormatted = firstRecord ? (
            formatTimestamp(firstRecord.deviceStartTime) || 
            formatTimestamp(firstRecord.startTime) || 
            formatTimestamp(firstRecord.timestamp) || 
            '-'
        ) : '-';
        
        const lastActiveFormatted = lastRecord ? (
            formatTimestamp(lastRecord.deviceLastActive) || 
            formatTimestamp(lastRecord.lastActive) || 
            formatTimestamp(lastRecord.timestamp) || 
            '-'
        ) : '-';

        records.forEach(s => {
            const time = s.timestamp ? (s.timestamp.toDate ? s.timestamp.toDate().toLocaleString() : s.timestamp) : '-';
            html += `<tr>
                        <td>${escapeHtml(s.device_id ?? '-')}</td>
                        
                        <td>${s.fallCount ?? '-'}</td>
                        <td>${s.fallStatus ?? '-'}</td>
                        
                        <td>${escapeHtml(s.sleepPattern ?? s.sleep_pattern ?? '-')}</td>
                        <td>${s.sound ?? '-'}</td>
                        <td>${s.temperature ?? '-'}</td>
                        <td>${startTimeFormatted}</td>
                        <td>${lastActiveFormatted}</td>
                    </tr>`;
        });

        html += '</tbody></table>';
        modalBody.innerHTML = html;
    } catch (e) {
        console.error('showUserRecords error:', e);
        let errorMsg = e.message || 'Unknown error';
        modalBody.innerHTML = `<p style="color:red;">Error loading records: ${escapeHtml(errorMsg)}</p>`;
    }
};

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('sensorModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
};
</script>

