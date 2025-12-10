<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit;
}
?>
<link rel="stylesheet" href="assets/style.css?v=2">

<style>
/* Truncate device IDs and expand inline on hover (no popup) */
/* Make the table container horizontally scrollable when needed */
.table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
.table-wrapper table { width: 100%; min-width: 900px; border-collapse: collapse; table-layout: fixed; }
.table-wrapper th:nth-child(2), .table-wrapper td:nth-child(2) { width: 320px; }
</style>

<style>
/* Modal styles for Add Sale form */
.modal { display: none; position: fixed; inset: 0; z-index: 9998; }
.modal[aria-hidden="false"] { display: block; }
.modal-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.modal-content { position: relative; max-width: 820px; margin: 6vh auto; background: #fff; padding: 20px; border-radius: 8px; z-index: 9999; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
.modal-close { position: absolute; right: 12px; top: 8px; background: transparent; border: none; font-size: 22px; cursor: pointer; }
.modal h2 { margin-top: 0; }

/* Keep existing table/device styles */
.table-wrapper td { position: relative; }
/* Make the device id element participate in document flow so it expands the cell/row
    instead of overlaying other rows. */
.device-ids {
    display: block;
    max-width: 220px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    vertical-align: middle;
    cursor: pointer;
}
/* On hover, allow wrapping inside the cell and break long tokens if needed.
   This forces the row to grow vertically instead of overlapping other rows. */
.device-ids:hover {
    white-space: normal;
    overflow: visible;
    max-width: 100%;
    word-break: break-word;
    overflow-wrap: anywhere;
}
</style>

<div class="sidebar">
    <h2>CribEase</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="users.php">Users</a>
    <a href="sensors.php">Sensor Data</a>
    <a href="feedback.php">Feedback</a>
    <a href="sales.php" class="active">Sales Report</a>
    <a href="subscriptions.php">Subscriptions</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content" style="padding:30px;">
    <h1>Sales Report</h1>
    <div style="background:#fff3cd; color:#856404; border:1px solid #ffeeba; padding:12px; border-radius:6px; margin-bottom:20px; max-width:800px;">
        <strong>Buy 3 or more devices and get 5% discount</strong>
    </div>

    <!-- Add Sale button (opens modal) -->
    <div style="margin:14px 0;">
        <button id="openSaleModalBtn" class="btn">Add Sale</button>
    </div>

    <!-- Modal: Add New Sale -->
    <div id="saleModal" class="modal" aria-hidden="true">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button id="closeSaleModal" class="modal-close" aria-label="Close">&times;</button>
            <h2 id="modalTitle">Add New Sale</h2>
            <form id="saleForm" style="display:grid; gap:12px;">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <input type="text" id="deviceIds" placeholder="Device IDs (comma separated)" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                    <input type="number" id="quantity" min="1" value="1" placeholder="Quantity" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                    <input type="text" id="fullName" placeholder="Customer Name" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                    <input type="email" id="email" placeholder="Email" required style="padding:8px; border:1px solid #ddd; border-radius:4px;" />
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div style="padding:8px; background:#e8f4f8; border-radius:4px; display:flex; align-items:center;">
                        <span style="color:#666; font-size:14px;">Total Price: <strong id="totalPrice">₱2,000.00</strong></span>
                    </div>
                    <div style="padding:8px; background:#e8f4f8; border-radius:4px; display:flex; align-items:center;">
                        <span style="color:#666; font-size:14px;">Date/Time: <strong id="currentDateTime"></strong></span>
                    </div>
                    <div style="padding:8px; background:#e8f4f8; border-radius:4px; display:flex; align-items:center;">
                        <span style="color:#666; font-size:14px;">Order ID: <strong id="nextOrderId">ORD-001</strong></span>
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn">Add Sale</button>
                    <button type="reset" class="btn btn-secondary">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <div style="margin-bottom:20px; display:flex; gap:10px; align-items:center;">
        <input type="text" id="searchInput" placeholder="Search by device ID, buyer, or order ID..." style="flex:1; max-width:500px; padding:8px;" />
        <button class="btn" id="clearBtn">Clear</button>
        <button class="btn btn-secondary" id="exportBtn">Export CSV</button>
    </div>

    <div class="table-wrapper">
        <table id="salesTable">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Device IDs</th>
                    <th>Buyer</th>
                    <th>Email</th>
                    <th>Quantity</th>
                    <th>Discount %</th>
                    <th>Total Price</th>
                    <th>Sale Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="salesList">
                <tr><td colspan="9">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import { getFirestore, collection, query, orderBy, onSnapshot, deleteDoc, doc, addDoc, serverTimestamp, where, getDocs } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore.js";

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

const saleForm = document.getElementById('saleForm');
const deviceIdsInput = document.getElementById('deviceIds');
const quantityInput = document.getElementById('quantity');
const fullNameInput = document.getElementById('fullName');
const emailInput = document.getElementById('email');
const totalPriceSpan = document.getElementById('totalPrice');
const salesList = document.getElementById('salesList');
const searchInput = document.getElementById('searchInput');
const clearBtn = document.getElementById('clearBtn');
const exportBtn = document.getElementById('exportBtn');
const currentDateTimeSpan = document.getElementById('currentDateTime');
const nextOrderIdSpan = document.getElementById('nextOrderId');
const openSaleModalBtn = document.getElementById('openSaleModalBtn');
const saleModalElem = document.getElementById('saleModal');
const closeSaleModalBtn = document.getElementById('closeSaleModal');

const FIXED_PRICE = 2000; // ₱2,000.00
const DISCOUNT_THRESHOLD = 3; // Discount applies for 3 or more
const DISCOUNT_RATE = 0.05; // 5% discount

// Update current date/time display
function updateDateTime() {
    const now = new Date();
    const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
    currentDateTimeSpan.textContent = now.toLocaleString('en-US', options).replace(', ', ' - ');
}
updateDateTime();
setInterval(updateDateTime, 1000);

// Get next Order ID by counting existing sales
async function updateNextOrderId() {
    try {
        const salesSnap = await getDocs(collection(db, 'sales'));
        const nextNumber = salesSnap.size + 1;
        nextOrderIdSpan.textContent = `ORD-${String(nextNumber).padStart(3, '0')}`;
    } catch (e) {
        console.error('Error updating order ID:', e);
        nextOrderIdSpan.textContent = 'ORD-001';
    }
}
updateNextOrderId();

// Calculate and update total price
function updateTotalPrice() {
    const quantity = parseInt(quantityInput.value, 10) || 1;
    let total = FIXED_PRICE * quantity;
    let discount = 0;
    if (quantity >= DISCOUNT_THRESHOLD) {
        discount = total * DISCOUNT_RATE;
        total -= discount;
    }
    totalPriceSpan.textContent = `₱${total.toLocaleString(undefined, {minimumFractionDigits:2})}`;
}
quantityInput.addEventListener('input', updateTotalPrice);
deviceIdsInput.addEventListener('input', updateTotalPrice);
updateTotalPrice();

// Form submission - Add new sale
saleForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const deviceIdsRaw = deviceIdsInput.value.trim();
    const deviceIds = deviceIdsRaw.split(',').map(d => d.trim()).filter(Boolean);
    const quantity = parseInt(quantityInput.value, 10) || 1;
    const fullName = fullNameInput.value.trim();
    const email = emailInput.value.trim();
    
    // Validate required fields
    if (!deviceIdsRaw || deviceIds.length !== quantity || !fullName || !email) {
        alert('Please enter the correct number of Device IDs, Customer Name, and Email.');
        return;
    }
    
    // Check for duplicate deviceIds (orders store deviceIds array)
    for (const deviceId of deviceIds) {
        const devicesQuery = query(collection(db, 'sales'), where('deviceIds', 'array-contains', deviceId));
        const existingDocs = await getDocs(devicesQuery);
        if (!existingDocs.empty) {
            alert(`Device ID ${deviceId} already exists in sales. Each device can only be sold once.`);
            return;
        }
    }
    
    try {
        // Get next Order ID
        const salesSnap = await getDocs(collection(db, 'sales'));
        const nextNumber = salesSnap.size + 1;
        const orderId = `ORD-${String(nextNumber).padStart(3, '0')}`;

        // Use current timestamp automatically
        const now = new Date();

        // Compute totals and discount
        let subtotal = FIXED_PRICE * quantity;
        let discountAmount = 0;
        let appliedDiscountRate = 0;
        if (quantity >= DISCOUNT_THRESHOLD) {
            appliedDiscountRate = DISCOUNT_RATE;
            discountAmount = subtotal * appliedDiscountRate;
        }
        const totalPrice = subtotal - discountAmount;

        // Store single order document with deviceIds array
        await addDoc(collection(db, 'sales'), {
            orderId,
            deviceIds: deviceIds,
            quantity,
            unitPrice: FIXED_PRICE,
            discountRate: appliedDiscountRate,
            discountAmount,
            totalPrice,
            fullName,
            email,
            saleDate: now,
            createdAt: serverTimestamp()
        });

        // Clear form and close modal
        saleForm.reset();
        updateNextOrderId(); // Update next order ID display
        updateTotalPrice();
        try { if (saleModalElem) { saleModalElem.style.display = 'none'; saleModalElem.setAttribute('aria-hidden', 'true'); } } catch(e){}
        alert('Order added successfully!');
    } catch (err) {
        console.error('Error adding sale:', err);
        alert('Failed to add sale. See console for details.');
    }
});

// Modal open/close handlers
function openSaleModal() {
    if (!saleModalElem) return;
    saleModalElem.style.display = 'block';
    saleModalElem.setAttribute('aria-hidden', 'false');
    // reset and prepare the form
    saleForm.reset();
    updateNextOrderId();
    updateTotalPrice();
    setTimeout(() => { try { deviceIdsInput.focus(); } catch(e){} }, 50);
}
function closeSaleModal() {
    if (!saleModalElem) return;
    saleModalElem.style.display = 'none';
    saleModalElem.setAttribute('aria-hidden', 'true');
}
if (openSaleModalBtn) openSaleModalBtn.addEventListener('click', openSaleModal);
if (closeSaleModalBtn) closeSaleModalBtn.addEventListener('click', closeSaleModal);
if (saleModalElem) {
    const overlay = saleModalElem.querySelector('.modal-overlay');
    if (overlay) overlay.addEventListener('click', closeSaleModal);
}
document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') closeSaleModal(); });

function formatTimestamp(ts) {
    if (!ts) return '-';
    try {
        const date = ts.toDate ? ts.toDate() : new Date(ts);
        const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        return date.toLocaleString('en-US', options).replace(', ', ' - ');
    } catch (e) {
        return '-';
    }
}

// Load sales collection (realtime)
const salesQuery = query(collection(db, 'sales'), orderBy('saleDate', 'asc'));
onSnapshot(salesQuery, (snapshot) => {
    if (!snapshot.size) {
        salesList.innerHTML = '<tr><td colspan="9" style="text-align:center;">No sales records found.</td></tr>';
        return;
    }
    const rows = [];
    let html = '';
    snapshot.forEach(docSnap => {
        const d = docSnap.data();
        const deviceIds = d.deviceIds ? d.deviceIds.join(', ') : (d.deviceId || '-');
        const buyer = d.fullName || d.name || '-';
        const email = d.email || '-';
        const quantity = d.quantity || (d.deviceIds ? d.deviceIds.length : 1);
        const discountPercent = d.discountRate ? (Number(d.discountRate) * 100).toFixed(2) + '%' : '0%';
        const totalPrice = d.totalPrice != null ? Number(d.totalPrice).toFixed(2) : '-';
        const saleDate = formatTimestamp(d.saleDate);
        const orderId = d.orderId || docSnap.id;

        const rowObj = { orderId, deviceIds, buyer, email, quantity, discountPercent, totalPrice, saleDate };
        rows.push(rowObj);

        // Device IDs are shown truncated and will expand inline on hover
        html += `<tr>
                    <td>${escapeHtml(orderId)}</td>
                    <td><span class="device-ids" title="${escapeHtml(deviceIds)}">${escapeHtml(deviceIds)}</span></td>
                    <td>${escapeHtml(buyer)}</td>
                    <td>${escapeHtml(email)}</td>
                    <td>${escapeHtml(quantity)}</td>
                    <td>${escapeHtml(discountPercent)}</td>
                    <td>₱${escapeHtml(totalPrice)}</td>
                    <td>${escapeHtml(saleDate)}</td>
                    <td><button class="btn btn-danger" onclick="deleteSale('${docSnap.id}')">Delete</button></td>
                </tr>`;
    });
    salesList.innerHTML = html;
    window._salesRows = rows;
});

window.deleteSale = async (id) => {
    if (!confirm('Delete this sale record?')) return;
    try {
        await deleteDoc(doc(db, 'sales', id));
    } catch (e) {
        console.error('Delete sale failed', e);
        alert('Failed to delete sale. See console for details.');
    }
};

// Simple search
searchInput.addEventListener('keyup', () => {
    const q = searchInput.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#salesTable tbody tr');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
});
clearBtn.addEventListener('click', () => { searchInput.value = ''; searchInput.dispatchEvent(new Event('keyup')); });

// Export CSV
exportBtn.addEventListener('click', () => {
    const rows = window._salesRows || [];
    if (!rows.length) { alert('No data to export'); return; }
    const header = ['Order ID','Device IDs','Buyer','Email','Quantity','Discount %','Total Price','Sale Date'];
    const csv = [header.join(',')];
    rows.forEach(r => {
        const line = [r.orderId, r.deviceIds, r.buyer, r.email, r.quantity, r.discountPercent, r.totalPrice, r.saleDate].map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',');
        csv.push(line);
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'sales_report.csv'; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>
