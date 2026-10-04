<?php
session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] !== "Admin") {
    die("Access Denied");
}

require_once "../db.php";

/* =========================
   STORE SETTINGS
========================= */

$store_name = "My Super Store";
$currency = "AFN";

$settings_sql = "
    SELECT store_name, currency
    FROM settings
    ORDER BY id ASC
    LIMIT 1
";

$settings_result = mysqli_query($conn, $settings_sql);

if ($settings_result && mysqli_num_rows($settings_result) > 0) {

    $settings = mysqli_fetch_assoc($settings_result);

    if (!empty($settings['store_name'])) {
        $store_name = $settings['store_name'];
    }

    if (!empty($settings['currency'])) {
        $currency = $settings['currency'];
    }
}

/* =========================
   BACKUP DIRECTORY
========================= */

$backup_dir = __DIR__ . "/backups";

if (!is_dir($backup_dir)) {
    @mkdir($backup_dir, 0755, true);
}

/* =========================
   BACKUP FILES
========================= */

$backup_files = [];

if (is_dir($backup_dir)) {

    $files = scandir($backup_dir);

    foreach ($files as $file) {

        if ($file === "." || $file === "..") {
            continue;
        }

        $full_path = $backup_dir . "/" . $file;

        if (is_file($full_path) && pathinfo($file, PATHINFO_EXTENSION) === "sql") {

            $backup_files[] = [
                "name" => $file,
                "size" => filesize($full_path),
                "date" => filemtime($full_path)
            ];
        }
    }

    usort($backup_files, function ($a, $b) {
        return $b["date"] <=> $a["date"];
    });
}

/* =========================
   TOTAL BACKUP SIZE
========================= */

$total_backup_size = 0;

foreach ($backup_files as $backup) {
    $total_backup_size += $backup["size"];
}

/* =========================
   FORMAT FILE SIZE
========================= */

function formatSize($bytes)
{
    if ($bytes <= 0) {
        return "0 B";
    }

    $units = ["B", "KB", "MB", "GB"];

    $power = floor(log($bytes, 1024));

    $power = min($power, count($units) - 1);

    return number_format(
        $bytes / pow(1024, $power),
        2
    ) . " " . $units[$power];
}

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

<title>Backup & Restore - <?php echo e($store_name); ?></title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f4f6f9;
    font-family: Arial, Helvetica, sans-serif;
    color: #1f2937;
}

/* =========================
   HEADER
========================= */

.header {
    background: #111827;
    color: white;
    padding: 18px 25px;
}

.header-inner {
    max-width: 1200px;
    margin: auto;

    display: flex;
    align-items: center;
    justify-content: space-between;

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
    background: #374151;
    color: white;
    padding: 10px 15px;
    border-radius: 7px;
    font-size: 14px;
}

.back-btn:hover {
    background: #4b5563;
}

/* =========================
   CONTAINER
========================= */

.container {
    width: 94%;
    max-width: 1200px;
    margin: 30px auto;
}

/* =========================
   ALERT
========================= */

.alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* =========================
   STATS
========================= */

.stats {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 22px;

    box-shadow:
        0 3px 15px rgba(0,0,0,0.06);

    border-left: 5px solid #2563eb;
}

.stat-card h3 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #6b7280;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
}

.stat-card:nth-child(2) {
    border-left-color: #16a34a;
}

.stat-card:nth-child(3) {
    border-left-color: #f59e0b;
}

/* =========================
   MAIN GRID
========================= */

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* =========================
   CARD
========================= */

.card {
    background: white;
    border-radius: 12px;

    padding: 25px;

    box-shadow:
        0 3px 15px rgba(0,0,0,0.06);
}

.card h2 {
    margin-top: 0;
    margin-bottom: 8px;

    font-size: 20px;
}

.card-description {
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;

    margin-bottom: 22px;
}

/* =========================
   ACTION BUTTONS
========================= */

.actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.action-btn {
    display: flex;
    align-items: center;

    gap: 14px;

    padding: 15px;

    border-radius: 9px;

    text-decoration: none;

    transition: 0.2s;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.action-icon {
    width: 42px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    font-size: 21px;

    background: rgba(255,255,255,0.2);
}

.action-content strong {
    display: block;
    font-size: 15px;
}

.action-content span {
    display: block;

    font-size: 12px;

    margin-top: 3px;

    opacity: 0.85;
}

.backup-btn {
    background: #2563eb;
    color: white;
}

.backup-btn:hover {
    background: #1d4ed8;
}

.restore-btn {
    background: #dc2626;
    color: white;
}

.restore-btn:hover {
    background: #b91c1c;
}

/* =========================
   SECURITY BOX
========================= */

.security-box {
    margin-top: 20px;

    background: #fff7ed;

    border: 1px solid #fed7aa;

    border-radius: 10px;

    padding: 17px;
}

.security-box strong {
    color: #9a3412;
}

.security-box p {
    margin: 7px 0 0;

    font-size: 13px;

    line-height: 1.6;

    color: #7c2d12;
}

/* =========================
   BACKUP LIST
========================= */

.backup-list {
    margin-top: 20px;
}

.backup-list h2 {
    margin-bottom: 15px;
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

.empty {
    text-align: center;
    padding: 30px;
    color: #6b7280;
}

/* =========================
   DOWNLOAD BUTTON
========================= */

.download-btn {
    display: inline-block;

    background: #16a34a;

    color: white;

    text-decoration: none;

    padding: 7px 11px;

    border-radius: 6px;

    font-size: 12px;
}

.download-btn:hover {
    background: #15803d;
}

/* =========================
   INFO
========================= */

.info-section {
    margin-top: 20px;

    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px rgba(0,0,0,0.06);
}

.info-section h2 {
    margin-top: 0;
}

.info-list {
    margin: 0;
    padding-left: 20px;
}

.info-list li {
    margin-bottom: 9px;

    font-size: 14px;

    color: #4b5563;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 800px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .header-inner {
        flex-direction: column;
        align-items: flex-start;
    }

}

@media (max-width: 500px) {

    .container {
        width: 96%;
        margin: 20px auto;
    }

    .card,
    .info-section {
        padding: 18px;
    }

    .header {
        padding: 16px;
    }

    .header h1 {
        font-size: 20px;
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
                💾 Backup & Restore
            </h1>

            <p>
                <?php echo e($store_name); ?>
                — Database Protection Center
            </p>

        </div>

        <a href="../dashboard.php"
           class="back-btn">

            ← Dashboard

        </a>

    </div>

</div>


<div class="container">


<?php if (isset($_GET['success'])): ?>

    <div class="alert alert-success">

        <?php if ($_GET['success'] === "backup"): ?>

            ✅ Database backup created successfully.

        <?php elseif ($_GET['success'] === "restore"): ?>

            ✅ Database restored successfully.

        <?php elseif ($_GET['success'] === "delete"): ?>

            ✅ Backup deleted successfully.

        <?php else: ?>

            ✅ Operation completed successfully.

        <?php endif; ?>

    </div>

<?php endif; ?>


<?php if (isset($_GET['error'])): ?>

    <div class="alert alert-error">

        ❌

        <?php

        $error = $_GET['error'];

        if ($error === "backup_failed") {
            echo "Database backup failed.";
        }
        elseif ($error === "restore_failed") {
            echo "Database restore failed.";
        }
        elseif ($error === "invalid_file") {
            echo "Invalid backup file.";
        }
        elseif ($error === "file_not_found") {
            echo "Backup file was not found.";
        }
        else {
            echo "An error occurred.";
        }

        ?>

    </div>

<?php endif; ?>


<!-- =========================
     STATISTICS
========================= -->

<div class="stats">

    <div class="stat-card">

        <h3>
            Available Backups
        </h3>

        <div class="stat-number">
            <?php echo count($backup_files); ?>
        </div>

    </div>


    <div class="stat-card">

        <h3>
            Backup Storage
        </h3>

        <div class="stat-number">
            <?php echo formatSize($total_backup_size); ?>
        </div>

    </div>


    <div class="stat-card">

        <h3>
            Database
        </h3>

        <div class="stat-number"
             style="font-size:20px;">

            super_store

        </div>

    </div>

</div>


<!-- =========================
     ACTIONS
========================= -->

<div class="grid">


    <!-- CREATE BACKUP -->

    <div class="card">

        <h2>
            💾 Create Database Backup
        </h2>

        <div class="card-description">

            Create a complete backup of your
            <strong>super_store</strong> database,
            including tables, structure and data.

        </div>


        <div class="actions">

            <a href="database_backup.php"
               class="action-btn backup-btn">

                <div class="action-icon">
                    💾
                </div>

                <div class="action-content">

                    <strong>
                        Create New Backup
                    </strong>

                    <span>
                        Generate SQL database backup
                    </span>

                </div>

            </a>

        </div>


        <div class="security-box">

            <strong>
                🔐 Security Recommendation
            </strong>

            <p>

                Backup خپل فایل یوازې په
                کمپیوټر کې مه ساتئ.
                یوه کاپي په External Drive
                یا بل خوندي ځای کې هم وساتئ.

            </p>

        </div>

    </div>


    <!-- RESTORE -->

    <div class="card">

        <h2>
            🔄 Restore Database
        </h2>

        <div class="card-description">

            Restore your database from a previously
            created SQL backup file.

            <strong>
                This operation can replace existing data.
            </strong>

        </div>


        <div class="actions">

            <a href="database_restore.php"
               class="action-btn restore-btn">

                <div class="action-icon">
                    🔄
                </div>

                <div class="action-content">

                    <strong>
                        Restore Database
                    </strong>

                    <span>
                        Restore from SQL backup
                    </span>

                </div>

            </a>

        </div>


        <div class="security-box">

            <strong>
                ⚠️ Important
            </strong>

            <p>

                د Restore څخه مخکې حتمي خپل
                اوسنی Database Backup کړه.
                Restore ممکن موجود معلومات بدل کړي.

            </p>

        </div>

    </div>

</div>


<!-- =========================
     BACKUP FILES
========================= -->

<div class="card backup-list">

    <h2>
        📁 Available Database Backups
    </h2>

    <div class="card-description">

        لاندې هغه SQL backup files دي چې ستا
        سیستم کې موجود دي.

    </div>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        Backup File
                    </th>

                    <th>
                        Size
                    </th>

                    <th>
                        Created
                    </th>

                    <th>
                        Action
                    </th>

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
                                <?php echo e($backup["name"]); ?>
                            </strong>

                        </td>


                        <td>

                            <?php echo formatSize(
                                $backup["size"]
                            ); ?>

                        </td>


                        <td>

                            <?php echo date(
                                "d M Y, h:i A",
                                $backup["date"]
                            ); ?>

                        </td>


                        <td>

                            <a
                                href="backups/<?php echo rawurlencode($backup["name"]); ?>"
                                class="download-btn"
                                download
                            >

                                ⬇ Download

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="empty"
                    >

                        📂 No database backups found.

                        <br><br>

                        Create your first backup
                        using the button above.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================
     INFORMATION
========================= -->

<div class="info-section">

    <h2>
        🛡 Backup Best Practices
    </h2>

    <ul class="info-list">

        <li>
            هره ورځ یا لږ تر لږه هره اوونۍ
            Database Backup واخلئ.
        </li>

        <li>
            مهم Backup فایلونه له اصلي کمپیوټر
            څخه په بل ځای کې هم وساتئ.
        </li>

        <li>
            د Restore کولو څخه مخکې د اوسني
            Database یو نوی Backup واخلئ.
        </li>

        <li>
            Backup فایلونه عام Web Access ته
            مه پرېږدئ.
        </li>

        <li>
            د Backup فایل نوم باید د نېټې او وخت
            معلومات ولري.
        </li>

    </ul>

</div>


</div>

</body>

</html>