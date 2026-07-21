<?php
// ============================================================
// 🔓 Logout – Session destroy and redirect to home page
// ============================================================

session_start();
session_destroy();
header("Location: index.php");
exit;
