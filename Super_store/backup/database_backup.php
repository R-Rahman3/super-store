<?php

session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

/* ONLY ADMIN */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    die("Access Denied");
}

require_once "../db.php";

/* =========================
   BACKUP DIRECTORY
========================= */

$backup_dir = __DIR__ . "/backups";

/*
 * Create backups directory if it
 * does not exist.
 */

if (!is_dir($backup_dir)) {

    if (!mkdir($backup_dir, 0755, true)) {

        header("Location: index.php?error=backup_failed");
        exit();
    }
}

/* =========================
   DATABASE INFORMATION
========================= */

$database = "super_store";

/*
 * These values must match db.php
 */

$host = "localhost";
$username = "root";
$password = "";

/* =========================
   BACKUP FILE NAME
========================= */

$date = date("Y-m-d_H-i-s");

$random = bin2hex(random_bytes(4));

$filename = "super_store_backup_" . $date . "_" . $random . ".sql";

$filepath = $backup_dir . "/" . $filename;


/* =========================
   FIND MYSQLDUMP
========================= */

/*
 * XAMPP usually keeps mysqldump here:
 *
 * C:\xampp\mysql\bin\mysqldump.exe
 *
 * If your XAMPP is installed in another
 * drive/path, change this path.
 */

$possible_paths = [

    "C:\\xampp\\mysql\\bin\\mysqldump.exe",

    "D:\\xampp\\mysql\\bin\\mysqldump.exe",

    "E:\\xampp\\mysql\\bin\\mysqldump.exe",

    "F:\\xampp\\mysql\\bin\\mysqldump.exe",

    "G:\\xampp\\mysql\\bin\\mysqldump.exe",

    "H:\\xampp\\mysql\\bin\\mysqldump.exe"

];

$mysqldump = null;

foreach ($possible_paths as $path) {

    if (file_exists($path)) {

        $mysqldump = $path;
        break;
    }
}


/* =========================
   IF MYSQLDUMP NOT FOUND
========================= */

$mysqldump = "G:\zampp\mysql\bin\mysqldump.exe";

if (!file_exists($mysqldump)) {
    die("mysqldump.exe not found: " . $mysqldump);
}


/* =========================
   BUILD COMMAND
========================= */

/*
 * Password is empty in the current
 * XAMPP configuration.
 */

if ($password === "") {

    $command =
        '"' . $mysqldump . '"' .
        ' --host=' . escapeshellarg($host) .
        ' --user=' . escapeshellarg($username) .
        ' --single-transaction' .
        ' --routines' .
        ' --triggers' .
        ' --events' .
        ' ' . escapeshellarg($database) .
        ' > ' . escapeshellarg($filepath) .
        ' 2>&1';

} else {

    /*
     * If MySQL has a password,
     * include it in the command.
     */

    $command =
        '"' . $mysqldump . '"' .
        ' --host=' . escapeshellarg($host) .
        ' --user=' . escapeshellarg($username) .
        ' --password=' . escapeshellarg($password) .
        ' --single-transaction' .
        ' --routines' .
        ' --triggers' .
        ' --events' .
        ' ' . escapeshellarg($database) .
        ' > ' . escapeshellarg($filepath) .
        ' 2>&1';
}


/* =========================
   EXECUTE BACKUP
========================= */

$output = [];

$return_code = 0;

exec($command, $output, $return_code);


/* =========================
   CHECK RESULT
========================= */

if (
    $return_code !== 0 ||
    !file_exists($filepath) ||
    filesize($filepath) <= 0
) {

    /*
     * Delete incomplete backup
     */

    if (file_exists($filepath)) {
        @unlink($filepath);
    }

    header("Location: index.php?error=backup_failed");
    exit();
}


/* =========================
   PROTECT BACKUP FILE
========================= */

/*
 * Create an .htaccess file inside
 * backups directory to prevent direct
 * browser access on Apache.
 */

$htaccess = $backup_dir . "/.htaccess";

if (!file_exists($htaccess)) {

    $htaccess_content = <<<HTACCESS
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
HTACCESS;

    @file_put_contents(
        $htaccess,
        $htaccess_content
    );
}


/* =========================
   SUCCESS
========================= */

header("Location: index.php?success=backup");
exit();

?>