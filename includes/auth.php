<?php
/**
 * Session guard. Include this at the very top of every protected page
 * (before any HTML output) to make sure only logged-in users - Admin OR
 * Faculty - can access it.
 *
 * Reads the session that login.php created:
 *   $_SESSION['user_role']  => 'admin' or 'faculty'
 *   $_SESSION['user_id']    => admin.id or faculty.id
 *   $_SESSION['user_name']  => display name
 *   $_SESSION['faculty_id'] => set only when user_role === 'faculty'
 *
 * For pages that must be Admin-only, also include includes/admin_only.php
 * right after this file.
 *
 * The including page must define $root beforehand:
 *   $root = '';    // for pages in the project root
 *   $root = '../'; // for pages one folder deep (students/, faculty/, etc.)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($root)) {
    $root = '';
}

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'faculty'], true)) {
    header("Location: " . $root . "login.php");
    exit;
}
