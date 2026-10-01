<?php
/**
 * Database connection settings.
 * Default values match a standard XAMPP / WAMP local MySQL setup.
 * Change these if your MySQL username/password/host is different.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
