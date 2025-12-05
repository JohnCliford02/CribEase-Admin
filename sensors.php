<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    header("Location: index.php"); 
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <h1>Sensor Data</h1>

    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
        <input type="text" id="searchInput" placeholder="Search by user ID, temperature, or timestamp..." 
               style="flex: 1; max-width: 400px; padding: 10px 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
        <button class="btn" onclick="clearSearch()">Clear</button>
    </div>

    <div class="table-wrapper">
        <table id="sensorTable">
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>Temperature</th>
                <th>Environmental Log</th>
                <th>Sleep Pattern</th>
                <th>Fall Detection</th>
                <th>Timestamp</th>
            </tr>
        </table>
    </div>
</div>

<!-- Firebase SDK -->
<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getFirestore, collection, query, orderBy, limit, onSnapshot } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

// Local responsive table styles to prevent page horizontal scroll
const style = document.createElement('style');
style.innerHTML = `
    .table-wrapper { overflow-x: auto; }
    #sensorTable { width: 100%; border-collapse: collapse; table-layout: fixed; }
    #sensorTable th, #sensorTable td { padding: 10px; text-align: left; vertical-align: top; word-break: break-word; white-space: normal; }
    #sensorTable th { background: #34495e; color: white; }
`;
document.head.appendChild(style);

// Search functionality
window.filterSensors = function() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#sensorTable tr:not(:first-child)');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
};

window.clearSearch = function() {
    document.getElementById('searchInput').value = '';
    filterSensors();
};

document.getElementById('searchInput').addEventListener('keyup', filterSensors);

// Firebase Config
const firebaseConfig = {
    apiKey: "AIzaSyDs6eEpYkKzOIbit60mitGDY6qbLMclxvs",
    authDomain: "esp32-connecttest.firebaseapp.com",
    databaseURL: "https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app",
    projectId: "esp32-connecttest",
    storageBucket: "esp32-connecttest.firebasestorage.app",
    messagingSenderId: "950000610308",
    appId: "1:950000610308:web:a39583249e23784128d951"
};

// Init
const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

// Live update table from Firestore sensor_data collection
const sensorQuery = query(collection(db, "sensor_data"), orderBy("timestamp", "desc"), limit(100));
onSnapshot(sensorQuery, (snapshot) => {
    const table = document.getElementById("sensorTable");

    // Clear old rows except header
    table.innerHTML = `
        <tr>
            <th>ID</th>
            <th>User ID</th>
            <th>Temperature</th>
            <th>Environmental Log</th>
            <th>Sleep Pattern</th>
            <th>Fall Detection</th>
            <th>Timestamp</th>
        </tr>
    `;

    if (snapshot.empty) {
        table.innerHTML += '<tr><td colspan="7" style="text-align:center; padding:20px;">No sensor records found</td></tr>';
        return;
    }

    snapshot.forEach(docSnap => {
        const id = docSnap.id;
        const s = docSnap.data();

        // Format timestamp consistently
        let timeStr = "-";
        if (s.timestamp) {
            if (s.timestamp.toDate) {
                timeStr = s.timestamp.toDate().toLocaleString();
            } else {
                timeStr = s.timestamp;
            }
        }

        table.innerHTML += `
            <tr>
                <td>${id}</td>
                <td>${s.user_id ?? "-"}</td>
                <td>${s.temperature ?? "-"}</td>
                <td>${s.environmental_log ?? "-"}</td>
                <td>${s.sleep_pattern ?? "-"}</td>
                <td>${s.fall_detection ? "Yes" : "No"}</td>
                <td>${timeStr}</td>
            </tr>
        `;
    });
});
</script>
