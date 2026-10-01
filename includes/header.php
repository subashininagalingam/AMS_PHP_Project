<?php
/**
 * Shared header + sidebar.
 * The including page must define $root ('' or '../') and $page (current page key)
 * before including this file, e.g.:
 *   $root = '';
 *   $page = 'dashboard';
 *   $page_title = 'Dashboard';
 */
if (!isset($root)) $root = '';
if (!isset($page)) $page = '';
if (!isset($page_title)) $page_title = 'Attendance Management System';

$is_admin = (($_SESSION['user_role'] ?? '') === 'admin');

function nav_active($key, $page) {
    return $key === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?> | Attendance Management System</title>
<link rel="stylesheet" href="<?php echo $root; ?>css/style.css">
</head>
<body>
<div class="app-shell">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">AMS</span>
            <span class="brand-text">Attendance<br>Management</span>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo $root; ?>dashboard.php" class="<?php echo nav_active('dashboard', $page); ?>">Dashboard</a>
            <?php if ($is_admin): ?>
            <a href="<?php echo $root; ?>students/list.php" class="<?php echo nav_active('students', $page); ?>">Students</a>
            <a href="<?php echo $root; ?>faculty/list.php" class="<?php echo nav_active('faculty', $page); ?>">Faculty</a>
            <a href="<?php echo $root; ?>courses/list.php" class="<?php echo nav_active('courses', $page); ?>">Courses / Classes</a>
            <a href="<?php echo $root; ?>subjects/list.php" class="<?php echo nav_active('subjects', $page); ?>">Subjects</a>
            <?php endif; ?>
            <a href="<?php echo $root; ?>attendance/mark.php" class="<?php echo nav_active('attendance', $page); ?>">Mark Attendance</a>
            <a href="<?php echo $root; ?>reports/reports.php" class="<?php echo nav_active('reports', $page); ?>">Reports</a>
            <a href="<?php echo $root; ?>reports/low_attendance.php" class="<?php echo nav_active('low_attendance', $page); ?>">Low Attendance</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?php echo $root; ?>logout.php" class="logout-link">Logout</a>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <div class="topbar-user">
                <?php if (isset($_SESSION['user_name'])): ?>
                    Signed in as <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                    <span class="role-badge"><?php echo $is_admin ? 'Admin' : 'Faculty'; ?></span>
                <?php endif; ?>
            </div>
        </header>
        <main class="content">
<?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>
