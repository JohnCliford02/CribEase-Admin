<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">

<style>
/* Modal styles */
.modal { display: none; position: fixed; inset: 0; z-index: 9998; }
.modal[aria-hidden="false"] { display: block; }
.modal-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.modal-content { position: relative; max-width: 700px; margin: 6vh auto; background: #fff; padding: 20px; border-radius: 8px; z-index: 9999; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
.modal-close { position: absolute; right: 12px; top: 8px; background: transparent; border: none; font-size: 22px; cursor: pointer; }
.modal h2 { margin-top: 0; }
</style>

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php">Sales Report</a>
    <a href="subscriptions.php" class="active">Subscriptions</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content" style="padding:30px;">
    <h1>Subscriptions</h1>

    <!-- Add Subscription button -->
    <div style="margin:14px 0;">
        <button id="openSubModalBtn" class="btn">Add Subscription</button>
    </div>

    <!-- Modal: Add New Subscription -->
    <div id="subModal" class="modal" aria-hidden="true">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button id="closeSubModal" class="modal-close" aria-label="Close">&times;</button>
            <h2>Add New Subscription</h2>
            <form id="subForm" style="display:grid; gap:12px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <input type="text" id="subName" placeholder="Customer Name" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                    <input type="email" id="subEmail" placeholder="Email" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <input type="text" id="subDeviceId" placeholder="Device ID" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                    <input type="text" id="subPlan" placeholder="Plan (e.g., Basic, Premium)" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <input type="number" id="subPrice" placeholder="Price" required readonly style="padding:8px; border:1px solid #ddd; border-radius:4px; background:#f7f7f7;" />
                    <select id="subStatus" required style="padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <option value="">Select Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn">Add Subscription</button>
                    <button type="reset" class="btn btn-secondary">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <div style="margin-bottom:20px; display:flex; gap:10px; align-items:center;">
        <input type="text" id="subsSearch" placeholder="Search by name, email, device ID or plan..." style="flex:1; max-width:500px; padding:8px;" />
        <button class="btn" id="clearSubs">Clear</button>
        <button class="btn btn-secondary" id="exportSubs">Export CSV</button>
    </div>

    <div class="table-wrapper">
        <table id="subsTable">
            <thead>
                <tr>
                    <th>Doc ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Device ID</th>
                    <th>Plan</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Sale Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="subsList">
                <tr><td colspan="8">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getFirestore, collection, query, orderBy, onSnapshot, deleteDoc, doc, addDoc } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";
import { getAuth, signInAnonymously } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";

// Use the same Firebase config as other pages
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
const auth = getAuth(app);

// Sign in anonymously so client has an authenticated identity for Firestore rules
signInAnonymously(auth).then(() => console.log('Signed in anonymously')).catch(e => console.warn('Anonymous sign-in failed', e));

console.log('Firebase initialized, Firestore connected');

const subsList = document.getElementById('subsList');
const subsSearch = document.getElementById('subsSearch');
const clearSubs = document.getElementById('clearSubs');
const exportSubs = document.getElementById('exportSubs');
const openSubModalBtn = document.getElementById('openSubModalBtn');
const subModalElem = document.getElementById('subModal');
const closeSubModalBtn = document.getElementById('closeSubModal');
const subForm = document.getElementById('subForm');
const subName = document.getElementById('subName');
const subEmail = document.getElementById('subEmail');
const subDeviceId = document.getElementById('subDeviceId');
const subPlan = document.getElementById('subPlan');
const subPrice = document.getElementById('subPrice');
const subStatus = document.getElementById('subStatus');

function formatTimestamp(ts) {
    if (!ts) return '-';
    try {
        const date = ts.toDate ? ts.toDate() : new Date(ts);
        const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        return date.toLocaleString('en-US', options).replace(', ', ' - ');
    } catch (e) { return '-'; }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;').replace(/'/g,'&#039;');
}

// Capitalize first letter of a string for display (e.g. "active" -> "Active")
function capitalize(str) {
    if (!str) return '';
    str = String(str);
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Map a plan name to its fixed price (in PHP pesos)
function mapPlanToPrice(plan) {
    if (!plan) return 0;
    const p = String(plan).trim().toLowerCase();
    if (p === 'premium' || p === 'prem' || p.includes('prem')) return 99;
    if (p === 'basic' || p.includes('basic')) return 0;
    // default fallback price
    return 0;
}

function adjustPriceFromPlan() {
    try {
        const price = mapPlanToPrice(subPlan.value);
        subPrice.value = Number(price);
    } catch (e) { console.warn('Failed to map plan to price', e); }
}

// update price when plan input changes
if (typeof subPlan !== 'undefined' && subPlan) {
    subPlan.addEventListener('change', adjustPriceFromPlan);
    subPlan.addEventListener('input', adjustPriceFromPlan);
}

// realtime listener (clean implementation)
const subsQuery = query(collection(db, 'subscriptions'), orderBy('saleDate', 'asc'));
let _subsRows = [];
onSnapshot(subsQuery, (snap) => {
    console.log('Firestore subscriptions snapshot:', snap.size, 'documents');
    if (!snap.size) {
        subsList.innerHTML = '<tr><td colspan="9" style="text-align:center;">No subscriptions found.</td></tr>';
        _subsRows = [];
        return;
    }

    const rows = [];
    let html = '';
    snap.forEach((docSnap) => {
        const d = docSnap.data() || {};
        const id = docSnap.id;
        const name = d.name || '-';
        const email = d.email || '-';
        // handle both deviceID and deviceId naming
        const deviceId = d.deviceID || d.deviceId || '-';
        const plan = d.plan || '-';
        const price = (d.price != null) ? d.price : '-';
        const statusRaw = d.status || '-';
        const status = statusRaw === '-' ? '-' : capitalize(statusRaw);
        const saleDate = d.saleDate ? formatTimestamp(d.saleDate) : '-';

        rows.push({ id, name, email, deviceId, plan, price, status, saleDate });

        html += `<tr>
            <td>${escapeHtml(id)}</td>
            <td>${escapeHtml(name)}</td>
            <td>${escapeHtml(email)}</td>
            <td><span class="device-ids" title="${escapeHtml(deviceId)}">${escapeHtml(deviceId)}</span></td>
            <td>${escapeHtml(plan)}</td>
            <td>${escapeHtml(price)}</td>
            <td>${escapeHtml(status)}</td>
            <td>${escapeHtml(saleDate)}</td>
            <td><button data-id="${escapeHtml(id)}" class="delete-subscription">Delete</button></td>
        </tr>`;
    });

    subsList.innerHTML = html;
    _subsRows = rows;

    // attach delete handlers
    document.querySelectorAll('.delete-subscription').forEach(btn => {
        btn.addEventListener('click', async (ev) => {
            const id = btn.getAttribute('data-id');
            if (!confirm('Delete this subscription?')) return;
            try { await deleteDoc(doc(db, 'subscriptions', id)); } catch (e) { console.error(e); alert('Failed to delete. See console.'); }
        });
    });
});

window.deleteSub = async (id) => {
    if (!confirm('Delete this subscription?')) return;
    try { await deleteDoc(doc(db, 'subscriptions', id)); } catch (e) { console.error(e); alert('Failed to delete. See console.'); }
};

// Form submission - Add new subscription
subForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const name = subName.value.trim();
    const email = subEmail.value.trim();
    const deviceId = subDeviceId.value.trim();
    const plan = subPlan.value.trim();
    const price = parseFloat(subPrice.value) || 0;
    const status = subStatus.value.trim();
    
    if (!name || !email || !deviceId || !plan || !status) {
        alert('Please fill in all fields.');
        return;
    }
    
    try {
        const now = new Date();
        await addDoc(collection(db, 'subscriptions'), {
            name,
            email,
            deviceID: deviceId,
            plan,
            price,
            status,
            saleDate: now,
            userID: ''
        });
        
        subForm.reset();
        try { if (subModalElem) { subModalElem.style.display = 'none'; subModalElem.setAttribute('aria-hidden', 'true'); } } catch(e){}
        alert('Subscription added successfully!');
    } catch (err) {
        console.error('Error adding subscription:', err);
        // Show clearer error to the user for debugging (message may contain permission details)
        const msg = (err && err.message) ? err.message : String(err);
        alert('Failed to add subscription: ' + msg + '\n\nCheck console for full details.');
    }
});

// Modal open/close handlers
function openSubModal() {
    if (!subModalElem) return;
    subModalElem.style.display = 'block';
    subModalElem.setAttribute('aria-hidden', 'false');
    subForm.reset();
    // reset and set default price based on plan (if any)
    try { adjustPriceFromPlan(); } catch(e){}
    setTimeout(() => { try { subName.focus(); } catch(e){} }, 50);
}
function closeSubModal() {
    if (!subModalElem) return;
    subModalElem.style.display = 'none';
    subModalElem.setAttribute('aria-hidden', 'true');
}
if (openSubModalBtn) openSubModalBtn.addEventListener('click', openSubModal);
if (closeSubModalBtn) closeSubModalBtn.addEventListener('click', closeSubModal);
if (subModalElem) {
    const overlay = subModalElem.querySelector('.modal-overlay');
    if (overlay) overlay.addEventListener('click', closeSubModal);
}
document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') closeSubModal(); });

// Search
subsSearch.addEventListener('keyup', () => {
    const q = subsSearch.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#subsTable tbody tr');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
});
clearSubs.addEventListener('click', () => { subsSearch.value = ''; subsSearch.dispatchEvent(new Event('keyup')); });

// Export CSV
exportSubs.addEventListener('click', () => {
    const rows = _subsRows || [];
    if (!rows.length) { alert('No data to export'); return; }
    const header = ['Doc ID','Name','Email','Device ID','Plan','Price','Status','Sale Date'];
    const csv = [header.join(',')];
    rows.forEach(r => {
        const values = [r.id, r.name, r.email, r.deviceId, r.plan, r.price, r.status, r.saleDate];
        const line = values.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',');
        csv.push(line);
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'subscriptions.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
});
</script>
