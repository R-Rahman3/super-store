<?php

session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    die("Access Denied");
}

require_once "../db.php";

/* =========================
   DATABASE INFORMATION
========================= */

$database = "super_store";

$host = "localhost";
$username = "root";
$password = "";

/* =========================
   BACKUP DIRECTORY
========================= */

$backup_dir = __DIR__ . "/backups";

if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0755, true);
}

/* =========================
   FIND MYSQL
========================= */

$possible_mysql_paths = [

    "C:\\xampp\\mysql\\bin\\mysql.exe",

    "D:\\xampp\\mysql\\bin\\mysql.exe",

    "E:\\xampp\\mysql\\bin\\mysql.exe",

    "F:\\xampp\\mysql\\bin\\mysql.exe",

    "G:\\xampp\\mysql\\bin\\mysql.exe",

    "H:\\xampp\\mysql\\bin\\mysql.exe"

];

$mysql = null;

foreach ($possible_mysql_paths as $path) {

    if (file_exists($path)) {
        $mysql = $path;
        break;
    }
}

/* =========================
   ERROR IF MYSQL NOT FOUND
========================= */

if ($mysql === "G:\zampp\mysql\bin\mysql.exe") {

    die(
        "mysql.exe was not found. " .
        "Please check your XAMPP MySQL installation path."
    );
}

/* =========================
   RESTORE PROCESS
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $backup_file = isset($_POST["backup_file"])
        ? trim($_POST["backup_file"])
        : "";

    $confirmation = isset($_POST["confirmation"])
        ? trim($_POST["confirmation"])
        : "";

    /* =========================
       BASIC VALIDATION
    ========================= */

    if ($backup_file === "") {

        header(
            "Location: database_restore.php?error=invalid_file"
        );

        exit();
    }

    if ($confirmation !== "RESTORE") {

        header(
            "Location: database_restore.php?error=confirmation"
        );

        exit();
    }

    /* =========================
       FILE NAME SECURITY
    ========================= */

    /*
     * Only allow simple file names.
     * This prevents directory traversal.
     */

    $safe_filename = basename($backup_file);

    if ($safe_filename !== $backup_file) {

        header(
            "Location: database_restore.php?error=invalid_file"
        );

        exit();
    }

    /* Only SQL files */

    if (
        strtolower(
            pathinfo($safe_filename, PATHINFO_EXTENSION)
        ) !== "sql"
    ) {

        header(
            "Location: database_restore.php?error=invalid_file"
        );

        exit();
    }

    /* =========================
       FULL FILE PATH
    ========================= */

    $filepath = $backup_dir . DIRECTORY_SEPARATOR . $safe_filename;

    /* =========================
       CHECK FILE
    ========================= */

    if (!file_exists($filepath) || !is_file($filepath)) {

        header(
            "Location: database_restore.php?error=file_not_found"
        );

        exit();
    }

    /* =========================
       FILE SIZE CHECK
    ========================= */

    if (filesize($filepath) <= 0) {

        header(
            "Location: database_restore.php?error=empty_file"
        );

        exit();
    }

    /* =========================
       SQL FILE VALIDATION
    ========================= */

    $handle = @fopen($filepath, "rb");

    if (!$handle) {

        header(
            "Location: database_restore.php?error=file_read"
        );

        exit();
    }

    $sample = fread($handle, 100000);

    fclose($handle);

    if ($sample === false || trim($sample) === "") {

        header(
            "Location: database_restore.php?error=invalid_file"
        );

        exit();
    }

    /*
     * Basic SQL validation.
     *
     * mysqldump normally contains
     * CREATE TABLE, INSERT, SET, etc.
     */

    $sql_signatures = [

        "CREATE TABLE",
        "INSERT INTO",
        "DROP TABLE",
        "SET ",
        "-- MySQL dump",
        "LOCK TABLES"

    ];

    $valid_sql = false;

    foreach ($sql_signatures as $signature) {

        if (
            stripos($sample, $signature) !== false
        ) {

            $valid_sql = true;
            break;
        }
    }

    if (!$valid_sql) {

        header(
            "Location: database_restore.php?error=invalid_sql"
        );

        exit();
    }

    /* =========================
       CREATE CURRENT BACKUP
       BEFORE RESTORE
    ========================= */

    $pre_restore_backup = $backup_dir .
        DIRECTORY_SEPARATOR .
        "before_restore_" .
        date("Y-m-d_H-i-s") .
        "_" .
        bin2hex(random_bytes(4)) .
        ".sql";

    /*
     * Create a safety backup of the
     * current database before restoring.
     */

    if ($password === "") {

        $backup_command =
            '"' . $possible_mysql_paths[0] . '"';

        /*
         * Find mysqldump separately.
         */

        $mysqldump_paths = [

            "C:\\xampp\\mysql\\bin\\mysqldump.exe",
            "D:\\xampp\\mysql\\bin\\mysqldump.exe",
            "E:\\xampp\\mysql\\bin\\mysqldump.exe",
            "F:\\xampp\\mysql\\bin\\mysqldump.exe",
            "G:\\xampp\\mysql\\bin\\mysqldump.exe",
            "H:\\xampp\\mysql\\bin\\mysqldump.exe"

        ];

        $mysqldump = null;

        foreach ($mysqldump_paths as $path) {

            if (file_exists($path)) {

                $mysqldump = $path;
                break;
            }
        }

        if ($mysqldump === null) {

            header(
                "Location: database_restore.php?error=backup_before_restore_failed"
            );

            exit();
        }

        $backup_command =
            '"' . $mysqldump . '"' .
            ' --host=' . escapeshellarg($host) .
            ' --user=' . escapeshellarg($username) .
            ' --single-transaction' .
            ' --routines' .
            ' --triggers' .
            ' --events' .
            ' ' . escapeshellarg($database) .
            ' > ' .
            escapeshellarg($pre_restore_backup) .
            ' 2>&1';

    } else {

        /*
         * Find mysqldump.
         */

        $mysqldump_paths = [

            "C:\\xampp\\mysql\\bin\\mysqldump.exe",
            "D:\\xampp\\mysql\\bin\\mysqldump.exe",
            "E:\\xampp\\mysql\\bin\\mysqldump.exe",
            "F:\\xampp\\mysql\\bin\\mysqldump.exe",
            "G:\\xampp\\mysql\\bin\\mysqldump.exe",
            "H:\\xampp\\mysql\\bin\\mysqldump.exe"

        ];

        $mysqldump = null;

        foreach ($mysqldump_paths as $path) {

            if (file_exists($path)) {

                $mysqldump = $path;
                break;
            }
        }

        if ($mysqldump === null) {

            header(
                "Location: database_restore.php?error=backup_before_restore_failed"
            );

            exit();
        }

        $backup_command =
            '"' . $mysqldump . '"' .
            ' --host=' . escapeshellarg($host) .
            ' --user=' . escapeshellarg($username) .
            ' --password=' . escapeshellarg($password) .
            ' --single-transaction' .
            ' --routines' .
            ' --triggers' .
            ' --events' .
            ' ' . escapeshellarg($database) .
            ' > ' .
            escapeshellarg($pre_restore_backup) .
            ' 2>&1';
    }

    $backup_output = [];

    $backup_return_code = 0;

    exec(
        $backup_command,
        $backup_output,
        $backup_return_code
    );

    /*
     * Do not restore if safety backup failed.
     */

    if (
        $backup_return_code !== 0 ||
        !file_exists($pre_restore_backup) ||
        filesize($pre_restore_backup) <= 0
    ) {

        if (file_exists($pre_restore_backup)) {
            @unlink($pre_restore_backup);
        }

        header(
            "Location: database_restore.php?error=backup_before_restore_failed"
        );

        exit();
    }

    /* =========================
       RESTORE DATABASE
    ========================= */

    if ($password === "") {

        $restore_command =
            '"' . $mysql . '"' .
            ' --host=' . escapeshellarg($host) .
            ' --user=' . escapeshellarg($username) .
            ' ' . escapeshellarg($database) .
            ' < ' . escapeshellarg($filepath) .
            ' 2>&1';

    } else {

        $restore_command =
            '"' . $mysql . '"' .
            ' --host=' . escapeshellarg($host) .
            ' --user=' . escapeshellarg($username) .
            ' --password=' . escapeshellarg($password) .
            ' ' . escapeshellarg($database) .
            ' < ' . escapeshellarg($filepath) .
            ' 2>&1';
    }

    $restore_output = [];

    $restore_return_code = 0;

    exec(
        $restore_command,
        $restore_output,
        $restore_return_code
    );

    /* =========================
       RESTORE RESULT
    ========================= */

    if ($restore_return_code !== 0) {

        header(
            "Location: database_restore.php?error=restore_failed"
        );

        exit();
    }

    /* =========================
       SUCCESS
    ========================= */

    header(
        "Location: index.php?success=restore"
    );

    exit();
}


/* =========================
   GET BACKUP FILES
========================= */

$backup_files = [];

if (is_dir($backup_dir)) {

    $files = scandir($backup_dir);

    foreach ($files as $file) {

        if ($file === "." || $file === "..") {
            continue;
        }

        $full_path =
            $backup_dir .
            DIRECTORY_SEPARATOR .
            $file;

        if (
            is_file($full_path) &&
            strtolower(
                pathinfo($file, PATHINFO_EXTENSION)
            ) === "sql"
        ) {

            $backup_files[] = [

                "name" => $file,

                "size" => filesize($full_path),

                "date" => filemtime($full_path)

            ];
        }
    }

    usort(
        $backup_files,
        function ($a, $b) {

            return $b["date"] <=> $a["date"];

        }
    );
}


/* =========================
   FORMAT SIZE
========================= */

function formatSize($bytes)
{
    if ($bytes <= 0) {
        return "0 B";
    }

    $units = [
        "B",
        "KB",
        "MB",
        "GB"
    ];

    $power = floor(
        log($bytes, 1024)
    );

    $power = min(
        $power,
        count($units) - 1
    );

    return number_format(
        $bytes / pow(1024, $power),
        2
    ) . " " . $units[$power];
}


/* =========================
   ESCAPE
========================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Restore Database</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #f4f6f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #1f2937;
}

.header {

    background: #111827;

    color: white;

    padding: 18px 25px;
}

.header-inner {

    max-width: 1100px;

    margin: auto;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.header h1 {

    margin: 0;

    font-size: 23px;
}

.header p {

    margin: 5px 0 0;

    color: #d1d5db;

    font-size: 13px;
}

.back-btn {

    text-decoration: none;

    color: white;

    background: #374151;

    padding: 10px 15px;

    border-radius: 7px;

    font-size: 14px;
}

.container {

    width: 94%;

    max-width: 1100px;

    margin: 30px auto;
}

.card {

    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 20px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.06);
}

.card h2 {

    margin-top: 0;

    font-size: 20px;
}

.warning {

    background: #fff7ed;

    border: 1px solid #fed7aa;

    color: #9a3412;

    padding: 16px;

    border-radius: 9px;

    line-height: 1.6;

    font-size: 14px;

    margin-bottom: 20px;
}

.alert {

    padding: 14px 18px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
}

.error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;
}

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;
}

thead {

    background: #111827;

    color: white;
}

th,
td {

    padding: 13px;

    border-bottom:
        1px solid #e5e7eb;

    text-align: left;

    font-size: 13px;

    white-space: nowrap;
}

tbody tr:hover {

    background: #f8fafc;
}

.restore-btn {

    border: none;

    background: #dc2626;

    color: white;

    padding: 8px 13px;

    border-radius: 6px;

    cursor: pointer;

    font-size: 12px;
}

.restore-btn:hover {

    background: #b91c1c;
}

.empty {

    text-align: center;

    padding: 35px;

    color: #6b7280;
}

.info {

    margin-top: 20px;

    padding: 15px;

    background: #eff6ff;

    border: 1px solid #bfdbfe;

    color: #1e40af;

    border-radius: 8px;

    font-size: 13px;

    line-height: 1.6;
}

@media (max-width: 700px) {

    .header-inner {

        flex-direction: column;

        align-items: flex-start;
    }

    .container {

        width: 96%;

        margin: 20px auto;
    }

    .card {

        padding: 18px;
    }
}

</style>

</head>

<body>


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <div class="header-inner">

        <div>

            <h1>
                🔄 Restore Database
            </h1>

            <p>
                Super Store Database Recovery
            </p>

        </div>

        <a href="index.php"
           class="back-btn">

            ← Backup Center

        </a>

    </div>

</div>


<div class="container">


<!-- =========================
     ERROR MESSAGES
========================= -->

<?php if (isset($_GET["error"])): ?>

    <div class="alert error">

        ❌

        <?php

        $error = $_GET["error"];

        switch ($error) {

            case "invalid_file":
                echo "Invalid backup file.";
                break;

            case "file_not_found":
                echo "The selected backup file was not found.";
                break;

            case "empty_file":
                echo "The backup file is empty.";
                break;

            case "file_read":
                echo "The backup file could not be read.";
                break;

            case "invalid_sql":
                echo "The selected file does not appear to be a valid SQL backup.";
                break;

            case "confirmation":
                echo "Please type RESTORE to confirm the operation.";
                break;

            case "backup_before_restore_failed":
                echo "The safety backup could not be created. Restore was cancelled.";
                break;

            case "restore_failed":
                echo "Database restore failed. Your previous database backup was preserved.";
                break;

            default:
                echo "An error occurred during the restore operation.";
        }

        ?>

    </div>

<?php endif; ?>


<!-- =========================
     WARNING
========================= -->

<div class="warning">

    <strong>
        ⚠️ Important Warning
    </strong>

    <br><br>

    Restore کول کولی شي ستا اوسنی Database
    معلومات بدل یا Replace کړي.

    <br>

    سیستم به د Restore څخه مخکې د اوسني Database
    یو Safety Backup هم جوړ کړي.

    <br><br>

    <strong>
        د Restore لپاره د Confirmation په برخه کې
        باید RESTORE ولیکل شي.
    </strong>

</div>


<!-- =========================
     BACKUP FILES
========================= -->

<div class="card">

    <h2>
        📁 Available SQL Backups
    </h2>

    <p style="color:#6b7280;font-size:14px;">

        له لاندې لیست څخه هغه Backup انتخاب کړه
        چې غواړې Database پرې Restore کړې.

    </p>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>Backup File</th>

                    <th>Size</th>

                    <th>Created</th>

                    <th>Action</th>

                </tr>

            </thead>

            <tbody>


            <?php if (count($backup_files) > 0): ?>


                <?php foreach ($backup_files as $index => $backup): ?>

                    <tr>

                        <td>
                            <?php echo $index + 1; ?>
                        </td>

                        <td>

                            <strong>
                                <?php
                                echo e($backup["name"]);
                                ?>
                            </strong>

                        </td>

                        <td>

                            <?php
                            echo formatSize(
                                $backup["size"]
                            );
                            ?>

                        </td>

                        <td>

                            <?php
                            echo date(
                                "d M Y, h:i A",
                                $backup["date"]
                            );
                            ?>

                        </td>

                        <td>

                            <form
                                method="POST"
                                onsubmit="return confirmRestore(this);"
                            >

                                <input
                                    type="hidden"
                                    name="backup_file"
                                    value="<?php
                                    echo e($backup["name"]);
                                    ?>"
                                >

                                <button
                                    type="submit"
                                    class="restore-btn"
                                >

                                    🔄 Restore

                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="empty"
                    >

                        📂 No SQL backup files found.

                        <br><br>

                        First create a database backup.

                    </td>

                </tr>

            <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


<!-- =========================
     RESTORE INFORMATION
========================= -->

<div class="card">

    <h2>
        🛡 Restore Safety
    </h2>

    <div class="info">

        Before restoring:

        <br><br>

        1. د اوسني Database Safety Backup
        جوړېږي.

        <br>

        2. بیا ټاکل شوی SQL Backup
        Restore کېږي.

        <br>

        3. که Restore ناکام شي،
        موجود Safety Backup لا هم
        په <strong>backups</strong> فولډر کې پاتې کېږي.

        <br>

        4. Restore یوازې Admin کولی شي.

    </div>

</div>


</div>


<script>

function confirmRestore(form)
{
    const confirmation = prompt(
        "WARNING!\n\n" +
        "This will restore the selected database backup.\n" +
        "The current database may be replaced.\n\n" +
        "Type RESTORE to continue:"
    );

    if (confirmation === null) {
        return false;
    }

    if (confirmation.trim() !== "RESTORE") {

        alert(
            "Restore cancelled.\n\n" +
            "You must type RESTORE exactly."
        );

        return false;
    }

    const hiddenInput =
        document.createElement("input");

    hiddenInput.type = "hidden";

    hiddenInput.name = "confirmation";

    hiddenInput.value = "RESTORE";

    form.appendChild(hiddenInput);

    return true;
}

</script>

</body>

</html>