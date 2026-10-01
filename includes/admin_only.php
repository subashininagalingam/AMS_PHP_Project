<?php
/**
 * Admin-only guard. Include this AFTER includes/auth.php on any page that
 * only Admin should be able to reach (Students, Faculty, Courses, Subjects
 * management). Faculty accounts are redirected back to the dashboard with
 * an "access denied" message.
 *
 * The including page must define $root beforehand, same as auth.php.
 */
if (!isset($root)) {
    $root = '';
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    $_SESSION['flash_error'] = 'Access denied. That area is restricted to administrators.';
    header("Location: " . $root . "dashboard.php");
    exit;
}
