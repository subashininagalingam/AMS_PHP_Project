-- =========================================================
-- Attendance Management System - Database Schema
-- Technology: MySQL
-- =========================================================

CREATE DATABASE IF NOT EXISTS if0_42931850_attendance_db;
USE attendance_db;

-- ---------------------------------------------------------
-- Table: admin
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- ---------------------------------------------------------
-- Table: courses  (also represents "Class")
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(100) NOT NULL,
    semester_year VARCHAR(50) NOT NULL,
    section VARCHAR(20) DEFAULT NULL
);

-- ---------------------------------------------------------
-- Table: students
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    register_no VARCHAR(50) NOT NULL UNIQUE,
    class_id INT NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (class_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- Table: faculty
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS faculty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) DEFAULT NULL
);

-- ---------------------------------------------------------
-- Table: subjects
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    course_id INT NOT NULL,
    faculty_id INT DEFAULT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Table: attendance
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present','Absent') NOT NULL,
    faculty_id INT DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (student_id, subject_id, attendance_date)
);

-- =========================================================
-- Migration: add Faculty login support (RBAC)
-- Safe to run even on a fresh database created just above —
-- the faculty table already has the password column, so this
-- ALTER is a no-op there. It matters when you run this whole
-- file against an OLDER existing attendance_db that predates
-- faculty login, since it adds the missing column without
-- touching any existing rows.
-- =========================================================

ALTER TABLE faculty
    ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL AFTER email;

-- ---------------------------------------------------------
-- Optional: give your existing faculty rows a login password.
-- The hash below is bcrypt for the password: faculty123
-- Faculty log in with their EMAIL + this password, then should
-- change it via a future "change password" feature or have the
-- admin set a new one from Faculty > Edit.
--
-- Uncomment and edit the WHERE clause (or remove it to apply to
-- every faculty member that has no password yet):
-- ---------------------------------------------------------
-- UPDATE faculty
-- SET password = '$2y$10$BKK5suHGMEAJiJugMZ2.I.a7ifR.A7qeNiVedr.KsvG.KuxFQW7FC'
-- WHERE password IS NULL;

-- =========================================================
-- Sample / Demo Data
-- =========================================================

-- Default admin login: username = admin , password = admin123
-- (password stored as bcrypt hash below)
INSERT INTO admin (username, password) VALUES
('admin', '$2y$10$rHe/ZqdARWVN0KmHMXlp8O.XZm7IP7mU6DunP8LUC7zrgA5OLIPyC');

-- Courses / Classes
INSERT INTO courses (course_name, semester_year, section) VALUES
('B.Sc Computer Science', '3rd Year', 'A'),
('B.Com', '2nd Year', 'B'),
('BCA', '1st Year', 'A');

-- Faculty
-- Demo faculty login password for all four rows below: faculty123
-- (password stored as bcrypt hash; login uses the email address as the username)
INSERT INTO faculty (name, department, phone, email, password) VALUES
('Senthil Pandi V', 'Computer Science', '9000000001', 'senthil@example.com', '$2y$10$BKK5suHGMEAJiJugMZ2.I.a7ifR.A7qeNiVedr.KsvG.KuxFQW7FC'),
('Akash A', 'Commerce', '9000000002', 'akash@example.com', '$2y$10$BKK5suHGMEAJiJugMZ2.I.a7ifR.A7qeNiVedr.KsvG.KuxFQW7FC'),
('Sathish Vishnu A', 'Computer Applications', '9000000003', 'sathish@example.com', '$2y$10$BKK5suHGMEAJiJugMZ2.I.a7ifR.A7qeNiVedr.KsvG.KuxFQW7FC'),
('Subashini N', 'Computer Science', '9000000004', 'subashini@example.com', '$2y$10$BKK5suHGMEAJiJugMZ2.I.a7ifR.A7qeNiVedr.KsvG.KuxFQW7FC');

-- Subjects
INSERT INTO subjects (subject_name, course_id, faculty_id) VALUES
('Data Structures', 1, 1),
('Database Systems', 1, 4),
('Financial Accounting', 2, 2),
('Business Statistics', 2, 2),
('Web Technology', 3, 3),
('Programming in C', 3, 3);

-- Students
INSERT INTO students (name, register_no, class_id, phone, email) VALUES
('Arun Kumar', 'CS2023001', 1, '9876500001', 'arun@example.com'),
('Divya S', 'CS2023002', 1, '9876500002', 'divya@example.com'),
('Karthik R', 'CS2023003', 1, '9876500003', 'karthik@example.com'),
('Meena T', 'CS2023004', 1, '9876500004', 'meena@example.com'),
('Ravi Shankar', 'COM2023001', 2, '9876500005', 'ravi@example.com'),
('Priya M', 'COM2023002', 2, '9876500006', 'priya@example.com'),
('Suresh Babu', 'COM2023003', 2, '9876500007', 'suresh@example.com'),
('Kavya N', 'BCA2023001', 3, '9876500008', 'kavya@example.com'),
('Naveen K', 'BCA2023002', 3, '9876500009', 'naveen@example.com'),
('Sneha R', 'BCA2023003', 3, '9876500010', 'sneha@example.com');

-- Sample attendance records (last few days) for demo purposes
INSERT INTO attendance (student_id, subject_id, attendance_date, status, faculty_id) VALUES
(1, 1, CURDATE() - INTERVAL 3 DAY, 'Present', 1),
(2, 1, CURDATE() - INTERVAL 3 DAY, 'Present', 1),
(3, 1, CURDATE() - INTERVAL 3 DAY, 'Absent', 1),
(4, 1, CURDATE() - INTERVAL 3 DAY, 'Present', 1),
(1, 1, CURDATE() - INTERVAL 2 DAY, 'Present', 1),
(2, 1, CURDATE() - INTERVAL 2 DAY, 'Absent', 1),
(3, 1, CURDATE() - INTERVAL 2 DAY, 'Absent', 1),
(4, 1, CURDATE() - INTERVAL 2 DAY, 'Present', 1),
(1, 1, CURDATE() - INTERVAL 1 DAY, 'Present', 1),
(2, 1, CURDATE() - INTERVAL 1 DAY, 'Present', 1),
(3, 1, CURDATE() - INTERVAL 1 DAY, 'Absent', 1),
(4, 1, CURDATE() - INTERVAL 1 DAY, 'Present', 1),
(5, 3, CURDATE() - INTERVAL 2 DAY, 'Present', 2),
(6, 3, CURDATE() - INTERVAL 2 DAY, 'Present', 2),
(7, 3, CURDATE() - INTERVAL 2 DAY, 'Absent', 2),
(5, 3, CURDATE() - INTERVAL 1 DAY, 'Absent', 2),
(6, 3, CURDATE() - INTERVAL 1 DAY, 'Present', 2),
(7, 3, CURDATE() - INTERVAL 1 DAY, 'Absent', 2),
(8, 5, CURDATE() - INTERVAL 1 DAY, 'Present', 3),
(9, 5, CURDATE() - INTERVAL 1 DAY, 'Present', 3),
(10, 5, CURDATE() - INTERVAL 1 DAY, 'Present', 3);