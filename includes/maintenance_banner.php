<?php
/**
 * Maintenance Banner Display
 * Include this file at the top of your login and main pages to display maintenance messages
 * 
 * Usage: include 'includes/maintenance_banner.php';
 */

// Ensure session is started so we can detect admin sessions.
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// If the current visitor is an admin (admin pages), skip rendering the banner.
if (isset($_SESSION['admin']) && $_SESSION['admin']) {
    // Output an HTML comment so include point remains valid, then stop execution of this file.
    echo "<!-- Maintenance banner skipped for admin users -->";
    return;
}
?>

<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
    import {
        getFirestore,
        collection,
        query,
        orderBy,
        limit,
        onSnapshot
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

    // Listen for latest maintenance message
    const messagesRef = collection(db, 'maintenance_messages');
    const q = query(messagesRef, orderBy('createdAt', 'desc'), limit(1));

    onSnapshot(q, (snapshot) => {
        const bannerContainer = document.getElementById('maintenanceBannerContainer');
        
        if (!snapshot.empty) {
            const latestMessage = snapshot.docs[0].data();
            const timestamp = new Date(latestMessage.timestamp).toLocaleString();
            
            const severityClass = `banner-${latestMessage.severity || 'info'}`;
            const severityIcon = {
                'info': 'ℹ️',
                'warning': '⚠️',
                'critical': '🚨'
            }[latestMessage.severity] || 'ℹ️';

            const bannerHTML = `
                <div class="maintenance-banner ${severityClass}">
                    <div class="banner-content">
                        <div class="banner-header">
                            <span class="banner-icon">${severityIcon}</span>
                            <strong>${latestMessage.title}</strong>
                            <button class="banner-close" onclick="this.parentElement.parentElement.style.display='none';">×</button>
                        </div>
                        <div class="banner-body">
                            <p>${latestMessage.content}</p>
                            <small class="banner-timestamp">Last updated: ${timestamp}</small>
                        </div>
                    </div>
                </div>
            `;

            bannerContainer.innerHTML = bannerHTML;
        }
    }, (error) => {
        console.error('Error listening to maintenance messages:', error);
    });
</script>

<style>
    #maintenanceBannerContainer {
        margin-bottom: 20px;
    }

    .maintenance-banner {
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        border-left: 5px solid;
    }

    .banner-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border-color: #bee5eb;
    }

    .banner-warning {
        background-color: #fff3cd;
        color: #856404;
        border-color: #ffeeba;
    }

    .banner-critical {
        background-color: #f8d7da;
        color: #721c24;
        border-color: #f5c6cb;
    }

    .banner-content {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .banner-header {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
    }

    .banner-icon {
        font-size: 20px;
    }

    .banner-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        margin-left: auto;
        padding: 0;
        opacity: 0.7;
        transition: opacity 0.2s;
    }

    .banner-close:hover {
        opacity: 1;
    }

    .banner-body {
        margin-left: 30px;
    }

    .banner-body p {
        margin: 5px 0;
        line-height: 1.5;
    }

    .banner-timestamp {
        font-size: 12px;
        opacity: 0.8;
        display: block;
        margin-top: 8px;
    }
</style>
