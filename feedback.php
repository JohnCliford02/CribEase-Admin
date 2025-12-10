<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">

<style>
     /* Match other pages: table fills content area; keep message cells left-aligned */
     #feedbackTable { width: 100%; }
     #feedbackTable th, #feedbackTable td { text-align: center; }
     #feedbackTable td.wrap-allow { text-align: left; }
</style>
<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="feedback.php" class="active">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content" style="padding:30px;">
    <h1>Feedback</h1>

    <!-- Admin view: feedback submission removed. Only listing is shown. -->

    <div style="max-width:1100px;">
        <!-- <h2>Feedbacks from users </h2> -->
        <div class="table-wrapper">
            <table id="feedbackTable" class="modal-table">
                <thead>
                    <tr>
                        <th style="width:30%">Name</th>
                        <th style="width:25%">Email</th>
                        <th style="width:35%">Message</th>
                        <th style="width:10%">Created At</th>
                        <th style="width:8%">Action</th>
                    </tr>
                </thead>
                <tbody id="feedbackList">
                    <tr><td colspan="5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import {
    getFirestore,
    collection,
    onSnapshot,
    query,
    orderBy,
    deleteDoc,
    doc
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

// Firebase config - match the config used elsewhere in the app
const firebaseConfig = {
    apiKey: "AIzaSyDs6eEpYkKzOIbit60mitGDY6qbLMclxvs",
    authDomain: "esp32-connecttest.firebaseapp.com",
    databaseURL: "https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app",
    projectId: "esp32-connecttest",
    storageBucket: "esp32-connecttest.firebasestorage.app",
    messagingSenderId: "950000610308",
    appId: "1:950000610308:web:a39583249e23784128d951"
};

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

const feedbackList = document.getElementById('feedbackList');

// Admin interface does not accept new feedback submissions here.
// Feedback is expected to be created from the public app/device
// and only viewed/managed by the admin on this page.

// Render helper
const formatTimestamp = (ts) => {
    if (!ts) return '-';
    try {
        const date = ts.toDate ? ts.toDate() : new Date(ts);
        const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        return date.toLocaleString('en-US', options).replace(', ', ' - ');
    } catch (e) {
        return '-';
    }
};

// Realtime listener for feedback collection
const q = query(collection(db, 'feedback'), orderBy('createdAt', 'desc'));
onSnapshot(q, (snapshot) => {
    if (!snapshot.size) {
        feedbackList.innerHTML = '<tr><td colspan="5" style="text-align:center;">No feedback yet.</td></tr>';
        return;
    }
    let html = '';
    snapshot.forEach(docSnap => {
        const data = docSnap.data();
        const createdAt = data.createdAt;
        html += `<tr>
                    <td>${escapeHtml(data.fullName ?? data.name ?? '-')}</td>
                    <td>${escapeHtml(data.email ?? '-')}</td>
                    <td class="wrap-allow">${escapeHtml(data.message ?? '-')}</td>
                    <td>${formatTimestamp(createdAt)}</td>
                    <td><button class="btn btn-danger" data-id="${docSnap.id}" onclick="deleteFeedback('${docSnap.id}')">Delete</button></td>
                </tr>`;
    });
    feedbackList.innerHTML = html;
});

window.deleteFeedback = async (id) => {
    if (!confirm('Delete this feedback?')) return;
    try {
        await deleteDoc(doc(db, 'feedback', id));
    } catch (e) {
        console.error('Delete failed', e);
        alert('Failed to delete. See console for details.');
    }
};

// Minimal XSS escape (same helper used in users.php)
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
