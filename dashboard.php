<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">

<!-- Firebase (compat builds for namespaced API like firebase.firestore()) -->
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-database-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore-compat.js"></script>

<style>
/* DASHBOARD UI FIXES */
.dashboard-container {
    width: calc(100% - 260px);
    max-width: 100%;
    padding: 20px;
}

/* Prevent horizontal page scroll caused by fixed sidebar + full-width content */
html, body {
    overflow-x: hidden;
}

.cards {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.card {
    padding: 20px;
    border-radius: 12px;
    width: 300px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.15);
}

.card h2 {
    font-size: 20px;
    margin-bottom: 10px;
}

.card .value {
    font-size: 40px;
    font-weight: bold;
    color: #2c3e50;
}

.latest-box {
    margin-top: 20px;
    width: 100%;
}

.latest-box .card {
    width: 100%;
}

.table-wrapper {
    overflow-x: auto;
    margin-top: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    table-layout: fixed;
}

th, td {
    padding: 10px;
    text-align: center;
    vertical-align: middle;
    word-break: break-word;
    white-space: normal;
}

th {
    background: #2c3e50;
    color: white;
}

td {
    background: white;
}

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
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <div class="dashboard-container">

        <h1>Dashboard</h1>

        <!-- CARDS -->
        <div class="cards">
            <div class="card">
                <h2>Total Users</h2>
                <div class="value" id="totalUsers">0</div>
            </div>

            <div class="card">
                <h2>Total Sensor Records</h2>
                <div class="value" id="totalSensors">0</div>
            </div>
        </div>
    </div>
</div>

<script>
// FIREBASE CONFIG
const firebaseConfig = {
    apiKey: "AIzaSyDs6eEpYkKzOIbit60mitGDY6qbLMclxvs",
    authDomain: "esp32-connecttest.firebaseapp.com",
    databaseURL: "https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app",
    projectId: "esp32-connecttest",
    storageBucket: "esp32-connecttest.firebasestorage.app",
    messagingSenderId: "950000610308",
    appId: "1:950000610308:web:a39583249e23784128d951"
};

firebase.initializeApp(firebaseConfig);
console.log('Firebase init:', firebase && firebase.apps ? firebase.apps.length : 'n/a');
/* Use Firestore (client-side) */
const db = firebase.firestore();

/* TOTAL USERS */
db.collection("users").onSnapshot(snapshot => {
    const count = snapshot.size || 0;
    document.getElementById("totalUsers").innerText = count;
}, err => console.error('users snapshot error', err));

/* TOTAL SENSOR RECORDS */
db.collection("sensor_data").onSnapshot(snapshot => {
    const count = snapshot.size || 0;
    document.getElementById("totalSensors").innerText = count;
}, err => console.error('sensor_data count snapshot error', err));
</script>
