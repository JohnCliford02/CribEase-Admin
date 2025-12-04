<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/style.css">
    <meta http-equiv="refresh" content="2;url=index.php">
</head>
<body>

<div style="
    max-width:400px;
    margin:120px auto;
    background:white;
    padding:25px;
    text-align:center;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.3);
">
    <h2 style="color:#27ae60;">You have successfully logged out</h2>
    <p>Redirecting to login page...</p>
</div>

</body>
</html>
