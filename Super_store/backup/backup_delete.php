<?php
session_start();

require_once "../db.php";

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    header("Location: index.php?error=access_denied");
    exit();
}

/* =========================
   POST ONLY
========================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

/* =========================
   GET FILE NAME
========================= */

$backup_file = isset($_POST['backup_file'])
    ? trim($_POST['backup_file'])
    : '';

/* =========================
   VALIDATE FILE NAME
========================= */

if ($backup_file === '') {
    header("Location: index.php?error=invalid_file");
    exit();
}

/*
   basename() prevents:
   ../../important.sql
   etc.
*/
$backup_file = basename($backup_file);

/* Only SQL files are allowed */
if (strtolower(pathinfo($backup_file, PATHINFO_EXTENSION)) !== 'sql') {
    header("Location: index.php?error=invalid_file");
    exit();
}

/* =========================
   BACKUP DIRECTORY
========================= */

$backup_dir = __DIR__ . DIRECTORY_SEPARATOR . "backups";

if (!is_dir($backup_dir)) {
    header("Location: index.php?error=file_not_found");
    exit();
}

/* =========================
   FULL FILE PATH
========================= */

$file_path = $backup_dir . DIRECTORY_SEPARATOR . $backup_file;

/* =========================
   SECURITY CHECK
========================= */

$real_backup_dir = realpath($backup_dir);
$real_file_path = realpath($file_path);

if (
    $real_backup_dir === false ||
    $real_file_path === false ||
    strpos($real_file_path, $real_backup_dir . DIRECTORY_SEPARATOR) !== 0
) {
    header("Location: index.php?error=invalid_file");
    exit();
}

/* =========================
   CHECK FILE
========================= */

if (!is_file($real_file_path)) {
    header("Location: index.php?error=file_not_found");
    exit();
}

/* =========================
   DELETE BACKUP
========================= */

if (@unlink($real_file_path)) {

    header("Location: index.php?success=delete");
    exit();

} else {

    header("Location: index.php?error=delete_failed");
    exit();
}
?>