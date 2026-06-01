<?php
require_once 'includes/functions.php';

// Hapus semua data session
session_destroy();

// Redirect ke halaman login
header("Location: " . BASE_URL . "/index.php");
exit();
?>
