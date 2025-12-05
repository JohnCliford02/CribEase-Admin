<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/style.css">
    <!-- Removed meta refresh; we'll show a modal here and redirect via JS so modal appears on logout page -->
</head>
<body>

<!-- Modal overlay shown on logout page -->
<div id="logoutOverlay" style="position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:2000;">
    <div style="background:#fff;padding:24px 28px;border-radius:8px;max-width:420px;width:92%;text-align:center;box-shadow:0 8px 30px rgba(0,0,0,0.2);">
        <h2 style="color:#27ae60;margin:0 0 8px;">You have successfully logged out</h2>
        <p style="margin:0;color:#333;">Redirecting to login page...</p>
    </div>
</div>

<script>
// Auto-redirect after delay but allow user to close/stay
setTimeout(function() {
    window.location.href = 'index.php';
}, 2000);
</script>

</body>
</html>
