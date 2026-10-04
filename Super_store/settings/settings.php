<?php

session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
    die("Access Denied. Only Admin can manage settings.");
}

/* =========================
   DATABASE
========================= */

require_once "../db.php";

/* =========================
   ADMIN INFO
========================= */

$username = $_SESSION["username"] ?? "Admin";

/* =========================
   CREATE SETTINGS ROW
   IF NOT EXISTS
========================= */

$check_settings = mysqli_query(
    $conn,
    "SELECT id FROM settings ORDER BY id ASC LIMIT 1"
);

if (!$check_settings || mysqli_num_rows($check_settings) === 0) {

    $insert_default = mysqli_prepare(
        $conn,
        "INSERT INTO settings
        (store_name, phone, address, currency, logo, receipt_footer)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $default_store = "My Super Store";
    $default_phone = "";
    $default_address = "";
    $default_currency = "AFN";
    $default_logo = "";
    $default_footer = "Thank you for shopping with us!";

    mysqli_stmt_bind_param(
        $insert_default,
        "ssssss",
        $default_store,
        $default_phone,
        $default_address,
        $default_currency,
        $default_logo,
        $default_footer
    );

    mysqli_stmt_execute($insert_default);
    mysqli_stmt_close($insert_default);
}

/* =========================
   LOAD SETTINGS
========================= */

$settings_result = mysqli_query(
    $conn,
    "SELECT
        id,
        store_name,
        phone,
        address,
        currency,
        logo,
        receipt_footer
     FROM settings
     ORDER BY id ASC
     LIMIT 1"
);

$settings = mysqli_fetch_assoc($settings_result);

$settings_id = (int)$settings["id"];

$store_name = $settings["store_name"];
$phone = $settings["phone"];
$address = $settings["address"];
$currency = $settings["currency"];
$logo = $settings["logo"];
$receipt_footer = $settings["receipt_footer"];

$error = "";
$success = "";

/* =========================
   FORM SUBMIT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $store_name = trim($_POST["store_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $currency = trim($_POST["currency"] ?? "");
    $receipt_footer = trim($_POST["receipt_footer"] ?? "");

    /* =========================
       VALIDATION
    ========================= */

    if ($store_name === "") {

        $error = "Store name is required.";

    } elseif (strlen($store_name) < 2) {

        $error = "Store name must contain at least 2 characters.";

    } elseif ($currency === "") {

        $error = "Currency is required.";

    } elseif (strlen($currency) > 20) {

        $error = "Currency is too long.";

    } elseif (strlen($phone) > 50) {

        $error = "Phone number is too long.";

    } elseif (strlen($address) > 255) {

        $error = "Address is too long.";

    } elseif (strlen($receipt_footer) > 255) {

        $error = "Receipt footer is too long.";
    }


    /* =========================
       LOGO UPLOAD
    ========================= */

    $new_logo = $logo;

    if (
        $error === "" &&
        isset($_FILES["logo"]) &&
        $_FILES["logo"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES["logo"]["error"] !== UPLOAD_ERR_OK) {

            $error = "Logo upload failed.";

        } else {

            $allowed_types = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            $file_type = mime_content_type(
                $_FILES["logo"]["tmp_name"]
            );

            if (!in_array($file_type, $allowed_types, true)) {

                $error = "Only JPG, PNG and WEBP logo files are allowed.";

            } elseif ($_FILES["logo"]["size"] > 2 * 1024 * 1024) {

                $error = "Logo size must not exceed 2MB.";

            } else {

                $upload_dir = __DIR__ . "/uploads/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $extension = "";

                if ($file_type === "image/jpeg") {
                    $extension = "jpg";
                } elseif ($file_type === "image/png") {
                    $extension = "png";
                } elseif ($file_type === "image/webp") {
                    $extension = "webp";
                }

                $file_name =
                    "store_logo_" .
                    time() .
                    "_" .
                    bin2hex(random_bytes(4)) .
                    "." .
                    $extension;

                $target_file = $upload_dir . $file_name;

                if (
                    move_uploaded_file(
                        $_FILES["logo"]["tmp_name"],
                        $target_file
                    )
                ) {

                    $new_logo = "uploads/" . $file_name;

                } else {

                    $error = "Unable to save the logo.";
                }
            }
        }
    }


    /* =========================
       UPDATE SETTINGS
    ========================= */

    if ($error === "") {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE settings
             SET
                store_name = ?,
                phone = ?,
                address = ?,
                currency = ?,
                logo = ?,
                receipt_footer = ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssssssi",
            $store_name,
            $phone,
            $address,
            $currency,
            $new_logo,
            $receipt_footer,
            $settings_id
        );

        if (mysqli_stmt_execute($stmt)) {

            $logo = $new_logo;

            $success = "Settings updated successfully.";

        } else {

            $error = "Failed to update settings. Please try again.";
        }

        mysqli_stmt_close($stmt);
    }
}


/* =========================
   REMOVE LOGO
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["remove_logo"])
) {

    $old_logo = $logo;

    $empty_logo = "";

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE settings
         SET logo = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $empty_logo,
        $settings_id
    );

    if (mysqli_stmt_execute($stmt)) {

        if (
            $old_logo !== "" &&
            strpos($old_logo, "uploads/") === 0
        ) {

            $old_file = __DIR__ . "/" . $old_logo;

            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        $logo = "";

        $success = "Logo removed successfully.";

    } else {

        $error = "Failed to remove logo.";
    }

    mysqli_stmt_close($stmt);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Settings - Super Store
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;

            color: #1f2937;
        }

        /* =========================
           TOPBAR
        ========================= */

        .topbar {
            background: #111827;

            color: white;

            padding: 18px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 3px 12px rgba(0,0,0,.12);
        }

        .topbar h1 {
            font-size: 22px;
        }

        .topbar-right {
            display: flex;

            align-items: center;

            gap: 15px;
        }

        .user-badge {
            background: #1f2937;

            border: 1px solid #374151;

            padding: 8px 14px;

            border-radius: 8px;

            font-size: 14px;
        }

        .dashboard-btn {
            color: white;

            text-decoration: none;

            background: #2563eb;

            padding: 9px 15px;

            border-radius: 7px;

            font-size: 14px;
        }

        .dashboard-btn:hover {
            background: #1d4ed8;
        }

        /* =========================
           CONTAINER
        ========================= */

        .container {
            max-width: 1000px;

            margin: 35px auto;

            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h2 {
            font-size: 28px;

            margin-bottom: 8px;
        }

        .page-header p {
            color: #6b7280;
        }

        /* =========================
           CARD
        ========================= */

        .card {
            background: white;

            border-radius: 14px;

            padding: 30px;

            box-shadow:
                0 4px 20px rgba(0,0,0,.07);

            margin-bottom: 25px;
        }

        .card-title {
            font-size: 19px;

            font-weight: 700;

            margin-bottom: 5px;
        }

        .card-description {
            color: #6b7280;

            font-size: 13px;

            margin-bottom: 25px;
        }

        /* =========================
           ALERT
        ========================= */

        .alert {
            padding: 14px 17px;

            border-radius: 8px;

            margin-bottom: 22px;

            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;

            color: #166534;

            border: 1px solid #bbf7d0;
        }

        /* =========================
           FORM
        ========================= */

        .form-grid {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 22px;
        }

        .form-group {
            display: flex;

            flex-direction: column;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;

            font-size: 14px;

            margin-bottom: 8px;

            color: #374151;
        }

        input,
        textarea,
        select {
            width: 100%;

            padding: 12px 14px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

            outline: none;

            background: white;

            font-family: inherit;
        }

        textarea {
            min-height: 100px;

            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37,99,235,.10);
        }

        .help-text {
            margin-top: 6px;

            font-size: 12px;

            color: #6b7280;
        }

        /* =========================
           LOGO
        ========================= */

        .logo-section {
            display: flex;

            align-items: center;

            gap: 25px;

            flex-wrap: wrap;
        }

        .logo-preview {
            width: 140px;

            height: 140px;

            border: 1px solid #d1d5db;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            background: #f9fafb;
        }

        .logo-preview img {
            width: 100%;

            height: 100%;

            object-fit: contain;
        }

        .no-logo {
            color: #9ca3af;

            text-align: center;

            font-size: 13px;

            padding: 15px;
        }

        .logo-upload {
            flex: 1;

            min-width: 250px;
        }

        .remove-logo-form {
            margin-top: 10px;
        }

        /* =========================
           BUTTONS
        ========================= */

        .form-actions {
            margin-top: 28px;

            padding-top: 22px;

            border-top: 1px solid #e5e7eb;

            display: flex;

            justify-content: flex-end;

            gap: 12px;
        }

        .btn {
            border: none;

            border-radius: 8px;

            padding: 12px 20px;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            text-decoration: none;

            display: inline-block;
        }

        .btn-save {
            background: #2563eb;

            color: white;
        }

        .btn-save:hover {
            background: #1d4ed8;
        }

        .btn-cancel {
            background: #e5e7eb;

            color: #374151;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .btn-danger {
            background: #dc2626;

            color: white;

            padding: 8px 12px;

            font-size: 12px;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        /* =========================
           INFO
        ========================= */

        .info-box {
            padding: 17px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            border-radius: 9px;

            color: #1e40af;

            font-size: 13px;

            line-height: 1.6;
        }

        .info-box strong {
            display: block;

            margin-bottom: 5px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 700px) {

            .topbar {
                padding: 15px 18px;

                flex-direction: column;

                align-items: flex-start;

                gap: 12px;
            }

            .topbar-right {
                width: 100%;

                justify-content: space-between;
            }

            .container {
                margin: 25px auto;
            }

            .card {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }

            .logo-section {
                flex-direction: column;

                align-items: flex-start;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;

                text-align: center;
            }

        }

    </style>

</head>

<body>


<!-- =========================
     TOPBAR
========================= -->

<div class="topbar">

    <h1>
        Super Store Management System
    </h1>

    <div class="topbar-right">

        <div class="user-badge">

            Admin:
            <?php
            echo htmlspecialchars($username);
            ?>

        </div>

        <a
            href="../dashboard.php"
            class="dashboard-btn"
        >
            Dashboard
        </a>

    </div>

</div>


<!-- =========================
     MAIN
========================= -->

<div class="container">


    <div class="page-header">

        <h2>
            Store Settings
        </h2>

        <p>
            Manage your store information, logo and receipt settings.
        </p>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <?php
            echo htmlspecialchars($success);
            ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         STORE INFORMATION
    ========================= -->

    <div class="card">

        <div class="card-title">
            Store Information
        </div>

        <div class="card-description">
            These details will be used throughout the Super Store system.
        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
            autocomplete="off"
        >


            <div class="form-grid">


                <!-- STORE NAME -->

                <div class="form-group">

                    <label for="store_name">
                        Store Name
                    </label>

                    <input
                        type="text"
                        id="store_name"
                        name="store_name"
                        value="<?php echo htmlspecialchars($store_name); ?>"
                        placeholder="Enter store name"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?php echo htmlspecialchars($phone); ?>"
                        placeholder="Enter phone number"
                        maxlength="50"
                    >

                </div>


                <!-- ADDRESS -->

                <div class="form-group full">

                    <label for="address">
                        Store Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                        maxlength="255"
                        placeholder="Enter store address"
                    ><?php echo htmlspecialchars($address); ?></textarea>

                </div>


                <!-- CURRENCY -->

                <div class="form-group">

                    <label for="currency">
                        Currency
                    </label>

                    <input
                        type="text"
                        id="currency"
                        name="currency"
                        value="<?php echo htmlspecialchars($currency); ?>"
                        placeholder="AFN"
                        maxlength="20"
                        required
                    >

                    <div class="help-text">
                        Example: AFN, USD, EUR
                    </div>

                </div>


            </div>


            <!-- =========================
                 LOGO
            ========================= -->

            <div
                style="
                    margin-top:30px;
                    padding-top:25px;
                    border-top:1px solid #e5e7eb;
                "
            >

                <label>
                    Store Logo
                </label>

                <div class="logo-section">


                    <div class="logo-preview">

                        <?php if ($logo !== ""): ?>

                            <img
                                src="<?php echo htmlspecialchars($logo); ?>"
                                alt="Store Logo"
                            >

                        <?php else: ?>

                            <div class="no-logo">
                                No logo uploaded
                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="logo-upload">

                        <input
                            type="file"
                            name="logo"
                            accept=".jpg,.jpeg,.png,.webp"
                        >

                        <div class="help-text">

                            Allowed:
                            JPG, PNG, WEBP

                            <br>

                            Maximum size:
                            2MB

                        </div>


                        <?php if ($logo !== ""): ?>

                            <div class="remove-logo-form">

                                <button
                                    type="submit"
                                    name="remove_logo"
                                    value="1"
                                    class="btn btn-danger"
                                    onclick="
                                        return confirm(
                                            'Are you sure you want to remove the store logo?'
                                        );
                                    "
                                >
                                    Remove Logo
                                </button>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- =========================
                 RECEIPT FOOTER
            ========================= -->

            <div
                style="
                    margin-top:30px;
                    padding-top:25px;
                    border-top:1px solid #e5e7eb;
                "
            >

                <div class="form-group">

                    <label for="receipt_footer">
                        Receipt Footer
                    </label>

                    <input
                        type="text"
                        id="receipt_footer"
                        name="receipt_footer"
                        value="<?php echo htmlspecialchars($receipt_footer); ?>"
                        placeholder="Thank you for shopping with us!"
                        maxlength="255"
                    >

                    <div class="help-text">
                        This message will appear at the bottom of printed receipts.
                    </div>

                </div>

            </div>


            <!-- =========================
                 INFO
            ========================= -->

            <div class="info-box" style="margin-top:25px;">

                <strong>
                    Settings Information
                </strong>

                Store name, phone, address, currency and receipt footer
                can be used by the POS, invoices, receipts and reports.

            </div>


            <!-- =========================
                 ACTIONS
            ========================= -->

            <div class="form-actions">

                <a
                    href="../dashboard.php"
                    class="btn btn-cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-save"
                >
                    Save Settings
                </button>

            </div>


        </form>

    </div>


</div>


</body>

</html>