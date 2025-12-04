<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    header("Location: index.php");
    exit;
}
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

/* Info icon and tooltip styles */
.info-icon {
    display: inline-block;
    width: 16px;
    height: 16px;
    background: #2980b9;
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 16px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 6px;
    cursor: help;
    position: relative;
}

.info-icon:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    white-space: normal;
    width: 200px;
    font-size: 12px;
    z-index: 1000;
    font-weight: normal;
    text-align: center;
}

.info-icon:hover::before {
    content: '';
    position: absolute;
    bottom: 115%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #333;
    z-index: 1000;
}
</style>

<!-- Success modal styles -->
<style>
/* Simple centered modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}
.modal {
    background: #fff;
    padding: 24px 28px;
    border-radius: 8px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    max-width: 420px;
    width: 90%;
    text-align: center;
}
.modal h3 { margin: 0 0 8px 0; }
.modal p { margin: 0 0 16px 0; color: #333; }
/* .modal .ok-btn { padding: 8px 14px; background:#2980b9; color:#fff; border:none; border-radius:4px; cursor:pointer; }*/
</style>

<!-- Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js"></script>

<!-- MD5 Library (ensure available before module code that references it) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.19.0/js/md5.min.js"></script>

<!-- Global state for Firestore helpers -->
<script>
let firebaseDb = null;
let firebaseCollection = null;
let firebaseQuery = null;
let firebaseGetDocs = null;
</script>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getFirestore, collection, addDoc, serverTimestamp, query, where, getDocs } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

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

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

// Export to global scope so validation functions can access
window.firebaseDb = db;
window.firebaseCollection = collection;
window.firebaseQuery = query;
window.firebaseGetDocs = getDocs;

// Ensure the birthdate input cannot select a future date (sets HTML max attribute)
document.addEventListener('DOMContentLoaded', () => {
    const bd = document.querySelector("input[name='birthdate']");
    if (bd) {
        const today = new Date().toISOString().split('T')[0];
        bd.setAttribute('max', today);
    }
    
    // Add real-time validation listeners for fullname and email
    const fullnameInput = document.querySelector("input[name='fullname']");
    const emailInput = document.querySelector("input[name='email']");
    
    if (fullnameInput) {
        fullnameInput.addEventListener('blur', validateFullnameRealTime);
    }
    if (emailInput) {
        emailInput.addEventListener('blur', validateEmailRealTime);
    }
});

// Real-time fullname validation
async function validateFullnameRealTime() {
    const fullnameInput = document.querySelector("input[name='fullname']");
    const fullname = fullnameInput ? fullnameInput.value.trim() : '';
    const warningContainer = document.getElementById('fullnameWarning');
    
    if (!fullname) {
        if (warningContainer) warningContainer.innerHTML = '';
        return;
    }
    
    try {
        // Fetch all users and check for case-insensitive fullname match
        const allUsersQuery = window.firebaseQuery(window.firebaseCollection(window.firebaseDb, "users"));
        const allUsersSnapshot = await window.firebaseGetDocs(allUsersQuery);
        
        const fullnameLower = fullname.toLowerCase();
        let fullnameExists = false;
        
        allUsersSnapshot.forEach(doc => {
            const userData = doc.data();
            const existingFullnameLower = (userData.fullname || '').toLowerCase();
            if (existingFullnameLower === fullnameLower) {
                fullnameExists = true;
            }
        });
        
        if (warningContainer) {
            if (fullnameExists) {
                warningContainer.innerHTML = '<span style="color:#c0392b; font-size:12px;">⚠ This name is already been used</span>';
            } else {
                warningContainer.innerHTML = '';
            }
        }
    } catch (e) {
        console.error('fullname validation error', e);
    }
}

// Real-time email validation
async function validateEmailRealTime() {
    const emailInput = document.querySelector("input[name='email']");
    const email = emailInput ? emailInput.value.trim() : '';
    const warningContainer = document.getElementById('emailWarning');
    
    if (!email) {
        if (warningContainer) warningContainer.innerHTML = '';
        return;
    }
    
    try {
        // Fetch all users and check for case-insensitive email match
        const allUsersQuery = window.firebaseQuery(window.firebaseCollection(window.firebaseDb, "users"));
        const allUsersSnapshot = await window.firebaseGetDocs(allUsersQuery);
        
        const emailLower = email.toLowerCase();
        let emailExists = false;
        
        allUsersSnapshot.forEach(doc => {
            const userData = doc.data();
            const existingEmailLower = (userData.email || '').toLowerCase();
            if (existingEmailLower === emailLower) {
                emailExists = true;
            }
        });
        
        if (warningContainer) {
            if (emailExists) {
                warningContainer.innerHTML = '<span style="color:#c0392b; font-size:12px;">⚠ This email is already been used</span>';
            } else {
                warningContainer.innerHTML = '';
            }
        }
    } catch (e) {
        console.error('email validation error', e);
    }
}

// Submit User to Firestore
window.addUser = async function(event) {
    event.preventDefault();

    const fullname = document.querySelector("input[name='fullname']").value.trim();
    const email = trim(document.querySelector("input[name='email']").value);
    const pass = document.querySelector("input[name='password']").value;
    const birthdate = document.querySelector("input[name='birthdate']").value || null;
    
    // Validate birthdate is not in the future
    if (birthdate) {
        const selected = new Date(birthdate);
        const today = new Date();
        // normalize to date-only comparison
        selected.setHours(0,0,0,0);
        today.setHours(0,0,0,0);
        if (selected > today) {
            const errorContainer = document.getElementById('addUserError');
            if (errorContainer) errorContainer.innerText = 'Birthdate cannot be in the future.';
            return; // abort submission
        }
    }
    // role is a <select>, not an input — use select selector
    const role = document.querySelector("select[name='role']").value || "User";

    const errorContainer = document.getElementById('addUserError');
    errorContainer.innerText = '';
    const submitBtn = event.target.querySelector('button[type="submit"]');
    
    try {
        // disable button to prevent duplicate submits
        if (submitBtn) { submitBtn.disabled = true; submitBtn.innerText = 'Adding...'; }

        // Check if email already exists (case-insensitive)
        // Fetch all users and check client-side for case-insensitive email match
        const allUsersQueryEmail = window.firebaseQuery(window.firebaseCollection(window.firebaseDb, "users"));
        const allUsersSnapshotEmail = await window.firebaseGetDocs(allUsersQueryEmail);
        
        const emailLower = email.toLowerCase();
        let emailExists = false;
        
        allUsersSnapshotEmail.forEach(doc => {
            const userData = doc.data();
            const existingEmailLower = (userData.email || '').toLowerCase();
            if (existingEmailLower === emailLower) {
                emailExists = true;
            }
        });

        // Check if fullname already exists (case-insensitive)
        // Fetch all users and check client-side for case-insensitive fullname match
        const allUsersQuery = window.firebaseQuery(window.firebaseCollection(window.firebaseDb, "users"));
        const allUsersSnapshot = await window.firebaseGetDocs(allUsersQuery);
        
        const fullnameLower = fullname.toLowerCase();
        let fullnameExists = false;
        
        allUsersSnapshot.forEach(doc => {
            const userData = doc.data();
            const existingFullnameLower = (userData.fullname || '').toLowerCase();
            if (existingFullnameLower === fullnameLower) {
                fullnameExists = true;
            }
        });
        
        // Show appropriate error message based on what exists
        if (emailExists || fullnameExists) {
            const errorContainer = document.getElementById('addUserError');
            if (errorContainer) {
                let errorMsg = '';
                if (emailExists && fullnameExists) {
                    errorMsg = '⚠ The name and email you entered is already been used';
                } else if (emailExists) {
                    errorMsg = '⚠ The email you entered is already been used';
                } else {
                    errorMsg = '⚠ The name you entered is already been used';
                }
                errorContainer.innerHTML = '<span style="color:#c0392b;">' + errorMsg + '</span>';
            }
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerText = 'Add User'; }
            return; // abort submission
        }

        await addDoc(collection(db, "users"), { 
            fullname: fullname,
            fullname_lower: fullname.toLowerCase(),
            email: email.toLowerCase(),
            password: md5(pass),
            birthdate: birthdate,
            role: role,
            createdAt: serverTimestamp()
        });
        // show success modal then redirect shortly after
        showSuccessModal();
    } catch (e) {
        console.error('addUser error', e);
        // show error to user in page
        errorContainer.innerText = `Error adding user: ${e.message || e}`;
        if (e.code) errorContainer.innerText += ` (code: ${e.code})`;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerText = 'Add User'; }
    }
};

// Helper to trim whitespace
function trim(str) {
    return str ? str.trim() : '';
}

// Show/hide success modal and redirect after short delay
function showSuccessModal() {
    const ov = document.getElementById('successModalOverlay');
    if (!ov) return;
    ov.style.display = 'flex';
    // redirect after 2 seconds (allow user to read/press OK)
    setTimeout(() => { window.location.href = 'users.php?added=1'; }, 2000);
}

function hideSuccessModal() {
    const ov = document.getElementById('successModalOverlay');
    if (!ov) return;
    ov.style.display = 'none';
    // also redirect when user clicks OK
    window.location.href = 'users.php?added=1';
}

</script>

<!-- MD5 Library already loaded above the module script -->

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">
    <h1>Add New User</h1>
    
    <a class="back-link" href="users.php">← Back to Users</a>

    <form onsubmit="addUser(event)" class="form-container">
        <div id="addUserError" style="color:red;margin-bottom:10px;"></div>
        <label>Full Name </label>
        <input type="text" name="fullname" placeholder="Full Name" required>
        <div id="fullnameWarning" style="margin-top:4px;"></div>

        <label>Email</label>
        <input type="email" name="email" placeholder="Email" required>
        <div id="emailWarning" style="margin-top:4px;"></div>

        <label>Birthdate</label>
        <input type="date" name="birthdate" required>

        <label>Role</label>
        <select name="role" required>
            <option value="">-- Select a Role --</option>
            <option value="Parent">Parent</option>
            <option value="Caregiver">Caregiver</option>
            <option value="Doctor">Doctor</option>
            <option value="Nurse">Nurse</option>
        </select>

        <label>Password</label>
        <input type="password" name="password" placeholder="Password"required>

        <button type="submit" class="btn">Add User</button>
    </form>

    <!-- Success Modal -->
    <div id="successModalOverlay" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="successModalTitle">
        <div class="modal">
            <h3 id="successModalTitle">Successfully Registered</h3>
            <p>The user has been added successfully. Redirecting to Users page...</p>
            <!-- <button class="ok-btn" onclick="hideSuccessModal()">OK</button> -->
        </div>
    </div>
</div>
