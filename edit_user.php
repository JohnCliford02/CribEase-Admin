<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid user ID");
}

$uid = $_GET['id'];
?>
<link rel="stylesheet" href="assets/style.css?v=2">

<style>
/* Form styling for add/edit user pages */
.form-container {
    max-width: 500px;
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.form-container label {
    display: block;
    margin-top: 15px;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.form-container input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-container input:focus {
    outline: none;
    border-color: #2980b9;
    box-shadow: 0 0 5px rgba(41, 128, 185, 0.3);
}

.form-container select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
    background-color: white;
    cursor: pointer;
}

.form-container select:focus {
    outline: none;
    border-color: #2980b9;
    box-shadow: 0 0 5px rgba(41, 128, 185, 0.3);
}

.form-container button {
    width: 100%;
    margin-top: 20px;
}

.back-link {
    display: inline-block;
    margin-bottom: 15px;
    color: #2980b9;
    text-decoration: none;
    font-size: 14px;
}

.back-link:hover {
    text-decoration: underline;
}
</style>

<!-- Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js"></script>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getFirestore, doc, getDoc, updateDoc } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

// CONFIG
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
const uid = "<?= $uid ?>";

// Load user data from Firestore
async function loadUser() {
    try {
        const userDocSnap = await getDoc(doc(db, "users", uid));
        if (!userDocSnap.exists()) {
            alert("User not found");
            return;
        }
        const u = userDocSnap.data();
        document.getElementById("fullname").value = u.fullname || '';
        document.getElementById("email").value = u.email || '';
        document.getElementById("birthdate").value = u.birthdate || '';
        document.getElementById("role").value = u.role || '';
    } catch (e) {
        console.error('loadUser error', e);
        alert('Error loading user. See console for details.');
    }
}

loadUser();

// Save updates to Firestore
window.saveUser = async function(event) {
    event.preventDefault();

    const fullname = document.getElementById("fullname").value;
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const birthdate = document.getElementById("birthdate").value;
    const role = document.getElementById("role").value;

    const updateData = { fullname, email, birthdate, role };

    if (password.trim() !== "") {
        updateData.password = md5(password);
    }

    try {
        await updateDoc(doc(db, "users", uid), updateData);
        window.location.href = "users.php?updated=1";
    } catch (e) {
        console.error('saveUser error', e);
        alert('Error saving user. See console for details.');
    }
};
</script>

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <h1>Edit User</h1>
    
    <a class="back-link" href="users.php">← Back to Users</a>

    <form onsubmit="saveUser(event)" class="form-container">
        <label>Full Name</label>
        <input type="text" id="fullname" required>

        <label>Email</label>
        <input type="email" id="email" required>

        <label>Birthdate</label>
        <input type="date" id="birthdate">

        <label>Role</label>
        <select id="role" required>
            <option value="">-- Select a Role --</option>
            <option value="Parent">Parent</option>
            <option value="Caregiver">Caregiver</option>
            <option value="Guardian">Guardian</option>
            <option value="Doctor">Doctor</option>
            <option value="Nurse">Nurse</option>
        </select>

        <label>New Password (optional)</label>
        <input type="password" id="password">

        <button class="btn">Save</button>
    </form>
</div>
