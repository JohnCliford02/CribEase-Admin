<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">

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
.table-wrapper{ max-width:100%; overflow:hidden; }
.table-wrapper .scrollable{ overflow-x:auto; }
#userTable, .table-wrapper table { width:100%; border-collapse:collapse; table-layout:fixed; }
#userTable th, #userTable td, .table-wrapper table th, .table-wrapper table td {
    padding:8px 10px; border:1px solid #eee; vertical-align:top; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
/* Make sure the Action column is wide enough to show buttons */
#userTable td:last-child, #userTable th:last-child {
    min-width:120px;
    white-space:nowrap;
    overflow:visible;
}
/* Allow certain cells to wrap if needed (e.g., long sensor logs) */
.wrap-allow { white-space:normal; word-break:break-word; }

/* Make action buttons stay visible */
#userTable td .btn { white-space:nowrap; }

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
</style>

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <h1>User Management</h1>

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by name, email, or role...">
        <button class="btn" onclick="clearSearch()">Clear</button>
    </div>

    <a class="btn" href="add_user.php">+ Add User</a>

    <div class="table-wrapper">
        <div class="scrollable">
            <table id="userTable">
                <tr>
                    <th style="width:8%">ID</th>
                    <th style="width:26%">Full Name</th>
                    <th style="width:22%">Email</th>
                    <th style="width:12%">Birthdate</th>
                    <th style="width:10%">Role</th>
                    <th style="width:10%">Created Via</th>
                    <th style="width:12%">Account Creation</th>
                    <th style="width:10%">Action</th>
                </tr>
            </table>
        </div>
    </div>

    <div id="userRecords" style="margin-top:30px;"></div>
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

// Helper: get a user's display name checking common Firestore field variants
function getDisplayName(u) {
    if (!u) return '-';
    const nameFromParts = (u.firstName || u.lastName) ? `${u.firstName ?? ''} ${u.lastName ?? ''}`.trim() : '';
    return u.fullname ?? u.fullName ?? u.full_name ?? u.displayName ?? u.name ?? (nameFromParts || (u.email ?? '-'));
}

// Helper: determine how the user was created (website, firebase/auth provider, or unknown)
function getCreationSource(u) {
    if (!u) return 'Unknown';

    const explicit = u.createdVia ?? u.created_via ?? u.registeredVia ?? u.createdFrom ?? u.source ?? u.createdBy;
    if (explicit) {
        const e = String(explicit).toLowerCase();
        if (e.includes('web') || e.includes('admin') || e.includes('site')) return 'Admin';
        if (e.includes('firebase') || e.includes('auth') || e.includes('google') || e.includes('facebook')) return 'Firebase Auth';
        return String(explicit);
    }

   /* // Detect mobile app indicators: explicit mobile flags or common mobile fields
    const mobileExplicit = (u.createdVia && String(u.createdVia).toLowerCase().includes('mobile'))
        || (u.created_via && String(u.created_via).toLowerCase().includes('mobile'))
        || (u.createdFromApp || u.createdFromApp === true) || u.createdFromMobile || u.createdViaApp;
    const mobileFields = u.deviceId || u.platform || u.appVersion || u.os || u.device || u.mobile || u.client === 'mobile';
    if (mobileExplicit || mobileFields) {
        // try to show platform if available
        const plat = (u.platform || u.os || u.device || '').toString();
        if (plat) {
            const p = plat.toLowerCase();
            if (p.includes('android')) return 'Mobile App (Android)';
            if (p.includes('ios') || p.includes('iphone') || p.includes('ipad')) return 'Mobile App (iOS)';
        }
        return 'Mobile App';
    }*/

    // If provider fields exist, it's from Firebase Auth
    const provider = u.providerId ?? u.provider ?? u.authProvider;
    if (provider) return `Firebase Auth (${provider})`;

    // presence of a password field or explicit admin flags => Admin-created via website
    if (u.password || u.createdBy === 'admin' || u.createdByAdmin || u.createdBy === 'website') return 'Admin';

    // Otherwise assume the document was created directly in Firestore
    return 'Firestore';
}

//  newest registered users appear first
const usersQuery = query(collection(db, "users"), orderBy("createdAt", "desc"));
onSnapshot(usersQuery, (snapshot) => {

    userTable.innerHTML = `
        <tr>
            <th style="width:8%">ID</th>
            <th style="width:22%">Full Name</th>
            <th style="width:20%">Email</th>
            <th style="width:10%">Birthdate</th>
            <th style="width:8%">Role</th>
            <th style="width:10%">Created Via</th>
            <th style="width:12%">Account Creation</th>
            <th style="width:10%">Action</th>
        </tr>
    `;

    if (snapshot.empty) return;

        snapshot.forEach(docSnap => {
        const id = docSnap.id;
        const u = docSnap.data();

        const row = document.createElement("tr");
        const birthdate = u.birthdate ? u.birthdate : "-";
        const role = u.role ? u.role : "-";

        // Build display name from available fields; use helper to cover multiple Firestore field variants
        const displayName = getDisplayName(u);

        // Format createdAt to time-only (HH:MM:SS local). Support Firestore Timestamp or ISO/date string
        let createdTime = '-';
        if (u.createdAt) {
            try {
                if (u.createdAt.toDate) {
                    createdTime = u.createdAt.toDate().toLocaleTimeString();
                } else {
                    createdTime = new Date(u.createdAt).toLocaleTimeString();
                }
            } catch (e) {
                createdTime = '-';
            }
        }

        row.innerHTML = `
            <td>${id}</td>
            <td><a href="#" title="${escapeHtml(displayName)}" onclick="showUserRecords('${id}', this);return false;">${escapeHtml(displayName)}</a></td>
            <td><span title="${escapeHtml(u.email ?? '-')}">${escapeHtml(u.email ?? '-')}</span></td>
            <td><span title="${escapeHtml(birthdate)}">${escapeHtml(birthdate)}</span></td>
            <td><span title="${escapeHtml(role)}">${escapeHtml(role)}</span></td>
            <td><span title="${escapeHtml(getCreationSource(u))}">${escapeHtml(getCreationSource(u))}</span></td>
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

// Delete user from Firestore
window.deleteUser = async function(id) {
    if (!confirm("Are you sure you want to delete this user?")) return;
    try {
        await deleteDoc(doc(db, "users", id));
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

// Show sensor records for a specific user below the table
window.showUserRecords = async function(userId, linkElement) {
    const container = document.getElementById('userRecords');

    // Remove active class from all rows
    document.querySelectorAll('.user-row').forEach(row => row.classList.remove('active'));
    
    // Add active class to the clicked row
    if (linkElement) {
        linkElement.closest('tr').classList.add('active');
    }

    // Fetch the user's display name from Firestore to show in the header
    let fullname = '-';
    try {
        const userDoc = await getDoc(doc(db, 'users', userId));
        if (userDoc.exists()) {
            const u = userDoc.data();
            fullname = getDisplayName(u);
        }
    } catch (e) {
        console.error('failed to fetch user for records header', e);
    }

    container.innerHTML = `<h2>Sensor Records for ${escapeHtml(fullname)} <button onclick="closeUserRecords();" style="float:right; padding:5px 10px; background:#999; color:white; border:none; cursor:pointer;">Close</button></h2>`;

    // Query Firestore sensor_data where user_id == userId
    try {
        console.log('Querying sensor_data for user_id:', userId);
        const q = query(collection(db, 'sensor_data'), where('user_id', '==', userId), limit(50));
        const snap = await getDocs(q);

        console.log('Found', snap.size, 'sensor records');

        if (snap.empty) {
            container.innerHTML += '<p>No sensor records found for this user.</p>';
            return;
        }

        // Sort records by timestamp descending
        const records = [];
        snap.forEach(d => records.push(d.data()));
        records.sort((a, b) => {
            const timeA = a.timestamp ? (a.timestamp.toDate ? a.timestamp.toDate().getTime() : new Date(a.timestamp).getTime()) : 0;
            const timeB = b.timestamp ? (b.timestamp.toDate ? b.timestamp.toDate().getTime() : new Date(b.timestamp).getTime()) : 0;
            return timeB - timeA; // descending
        });

        let html = `
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Temperature</th>
                                <th>Environmental Log</th>
                                <th>Sleep Pattern</th>
                                <th>Fall</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        records.forEach(s => {
            const time = s.timestamp ? (s.timestamp.toDate ? s.timestamp.toDate().toLocaleString() : s.timestamp) : '-';
            html += `
                <tr>
                    <td>${s.temperature ?? '-'}</td>
                    <td>${escapeHtml(s.environmental_log ?? '-')}</td>
                    <td>${escapeHtml(s.sleep_pattern ?? '-')}</td>
                    <td>${s.fall_detection ? 'Yes' : 'No'}</td>
                    <td>${time}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        container.innerHTML += html;
    } catch (e) {
        console.error('showUserRecords error:', e);
        console.error('Error code:', e.code);
        console.error('Error message:', e.message);
        let errorMsg = e.message || 'Unknown error';
        if (e.code === 'failed-precondition') {
            errorMsg = 'Firestore index or permission issue. Check your security rules and ensure user_id field exists in sensor_data documents.';
        }
        container.innerHTML += `<p style="color:red;">Error loading records: ${escapeHtml(errorMsg)}</p><p style="font-size:12px;">Check the browser console for full error details.</p>`;
    }
};
</script>
