<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

/* =========================
   ACCESS CONTROL
========================= */

if ($user_role === 'Cashier') {
    header("Location: manage.php?error=access_denied");
    exit();
}

$error = "";

$selected_supplier = (int)($_POST['supplier_id'] ?? 0);
$amount = $_POST['amount'] ?? "";
$description = trim($_POST['description'] ?? "");
$payment_date = $_POST['payment_date'] ?? date('Y-m-d');

/* =========================
   LOAD SUPPLIERS
========================= */

$suppliers = [];

$supplier_sql = "
    SELECT id, name, phone, address, balance
    FROM suppliers
    ORDER BY name ASC
";

$supplier_result = mysqli_query($conn, $supplier_sql);

if ($supplier_result) {

    while ($supplier = mysqli_fetch_assoc($supplier_result)) {
        $suppliers[] = $supplier;
    }
}

/* =========================
   SAVE PAYMENT
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selected_supplier = (int)($_POST['supplier_id'] ?? 0);
    $amount = trim($_POST['amount'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));

    /* =========================
       VALIDATION
    ========================= */

    if ($selected_supplier <= 0) {

        $error = "Please select a supplier.";

    } elseif ($amount === '' || !is_numeric($amount)) {

        $error = "Please enter a valid payment amount.";

    } elseif ((float)$amount <= 0) {

        $error = "Payment amount must be greater than zero.";

    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {

        $error = "Invalid payment date.";

    } else {

        $amount = (float)$amount;

        /* =========================
           START TRANSACTION
        ========================= */

        mysqli_begin_transaction($conn);

        try {

            /* =========================
               LOCK SUPPLIER
            ========================= */

            $supplier_stmt = mysqli_prepare(
                $conn,
                "
                SELECT id, name, phone, balance
                FROM suppliers
                WHERE id = ?
                FOR UPDATE
                "
            );

            if (!$supplier_stmt) {
                throw new Exception(
                    "Supplier query preparation failed."
                );
            }

            mysqli_stmt_bind_param(
                $supplier_stmt,
                "i",
                $selected_supplier
            );

            mysqli_stmt_execute($supplier_stmt);

            $supplier_locked =
                mysqli_stmt_get_result($supplier_stmt);

            if (
                !$supplier_locked ||
                mysqli_num_rows($supplier_locked) === 0
            ) {
                throw new Exception(
                    "Supplier not found."
                );
            }

            $supplier = mysqli_fetch_assoc(
                $supplier_locked
            );

            $supplier_balance =
                (float)$supplier['balance'];

            /* =========================
               CHECK BALANCE
            ========================= */

            if ($supplier_balance <= 0) {

                throw new Exception(
                    "This supplier has no outstanding balance."
                );
            }

            if ($amount > $supplier_balance) {

                throw new Exception(
                    "Payment cannot be greater than supplier balance. " .
                    "Current balance: " .
                    number_format(
                        $supplier_balance,
                        2
                    )
                );
            }

            /* =========================
               CALCULATE NEW BALANCE
            ========================= */

            $new_balance =
                $supplier_balance - $amount;

            /* =========================
               INSERT PAYMENT
            ========================= */

            $payment_type = "Supplier Payment";

            $payment_stmt = mysqli_prepare(
                $conn,
                "
                INSERT INTO payments
                (
                    customer_id,
                    supplier_id,
                    amount,
                    payment_type,
                    description,
                    payment_date,
                    user_id
                )
                VALUES
                (
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
                "
            );

            if (!$payment_stmt) {

                throw new Exception(
                    "Payment query preparation failed."
                );
            }

            mysqli_stmt_bind_param(
                $payment_stmt,
                "idsssi",
                $selected_supplier,
                $amount,
                $payment_type,
                $description,
                $payment_date,
                $user_id
            );

            if (!mysqli_stmt_execute($payment_stmt)) {

                throw new Exception(
                    "Supplier payment could not be saved."
                );
            }

            /* =========================
               UPDATE SUPPLIER BALANCE
            ========================= */

            $balance_stmt = mysqli_prepare(
                $conn,
                "
                UPDATE suppliers
                SET balance = ?
                WHERE id = ?
                "
            );

            if (!$balance_stmt) {

                throw new Exception(
                    "Supplier balance update preparation failed."
                );
            }

            mysqli_stmt_bind_param(
                $balance_stmt,
                "di",
                $new_balance,
                $selected_supplier
            );

            if (!mysqli_stmt_execute($balance_stmt)) {

                throw new Exception(
                    "Supplier balance could not be updated."
                );
            }

            /* =========================
               COMMIT
            ========================= */

            mysqli_commit($conn);

            /* =========================
               REDIRECT
            ========================= */

            header(
                "Location: manage.php?success=supplier_payment"
            );

            exit();

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
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

    <title>Supplier Payment - Super Store</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #222;
        }

        .container {
            width: 95%;
            max-width: 900px;
            margin: 35px auto;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 20px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.07);

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 15px;
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 25px;
            margin-bottom: 6px;
        }

        .header p {
            color: #777;
            font-size: 14px;
        }

        /* =========================
           BUTTONS
        ========================= */

        .btn {
            border: none;
            padding: 11px 17px;
            border-radius: 7px;

            cursor: pointer;
            text-decoration: none;

            font-size: 14px;
            display: inline-block;
        }

        .btn-primary {
            background: #dc3545;
            color: #fff;
        }

        .btn-secondary {
            background: #343a40;
            color: #fff;
        }

        .btn:hover {
            opacity: .9;
        }

        /* =========================
           FORM
        ========================= */

        .form-box {
            background: #fff;
            padding: 28px;
            border-radius: 12px;

            box-shadow:
                0 3px 12px rgba(0,0,0,.07);
        }

        .form-title {
            font-size: 20px;
            margin-bottom: 22px;

            padding-bottom: 15px;

            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;

            font-size: 14px;
            font-weight: bold;

            margin-bottom: 7px;
        }

        input,
        select,
        textarea {
            width: 100%;

            padding: 12px;

            border: 1px solid #ddd;
            border-radius: 7px;

            outline: none;

            font-size: 14px;

            background: #fff;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #dc3545;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .row {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 18px;
        }

        /* =========================
           SUPPLIER INFO
        ========================= */

        .supplier-info {
            display: none;

            background: #fff5f5;

            border: 1px solid #f1c2c2;

            border-radius: 9px;

            padding: 16px;

            margin-top: -5px;
            margin-bottom: 20px;
        }

        .supplier-info.show {
            display: block;
        }

        .supplier-info-title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 10px;
        }

        .info-item {
            background: #fff;

            padding: 10px;

            border-radius: 7px;
        }

        .info-label {
            font-size: 11px;
            color: #777;

            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            font-weight: bold;
        }

        .balance {
            color: #dc3545;
        }

        /* =========================
           ALERT
        ========================= */

        .alert {
            padding: 13px 15px;

            border-radius: 7px;

            margin-bottom: 18px;

            font-size: 14px;
        }

        .alert-error {
            background: #f8d7da;

            color: #842029;

            border: 1px solid #f1aeb5;
        }

        /* =========================
           ACTIONS
        ========================= */

        .form-actions {
            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 25px;

            padding-top: 20px;

            border-top: 1px solid #eee;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 650px) {

            .container {
                width: 94%;
                margin: 15px auto;
            }

            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .form-box {
                padding: 20px;
            }

            .header h1 {
                font-size: 21px;
            }

            .header {
                align-items: flex-start;
            }

            .header .btn {
                width: 100%;
                text-align: center;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                text-align: center;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>💳 Supplier Payment</h1>

            <p>
                Record payment made to a supplier
            </p>

        </div>

        <a
            href="manage.php"
            class="btn btn-secondary"
        >
            ← Payment Management
        </a>

    </div>


    <!-- FORM -->

    <div class="form-box">

        <div class="form-title">
            Make Supplier Payment
        </div>


        <!-- ERROR -->

        <?php if (!empty($error)): ?>

            <div class="alert alert-error">

                ❌
                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            onsubmit="return validatePayment();"
        >

            <!-- SUPPLIER -->

            <div class="form-group">

                <label for="supplier_id">
                    Supplier *
                </label>

                <select
                    name="supplier_id"
                    id="supplier_id"
                    required
                    onchange="showSupplierInfo()"
                >

                    <option value="">
                        -- Select Supplier --
                    </option>

                    <?php foreach ($suppliers as $supplier): ?>

                        <option
                            value="<?= (int)$supplier['id'] ?>"
                            data-name="<?= htmlspecialchars($supplier['name']) ?>"
                            data-phone="<?= htmlspecialchars($supplier['phone'] ?? '') ?>"
                            data-address="<?= htmlspecialchars($supplier['address'] ?? '') ?>"
                            data-balance="<?= (float)$supplier['balance'] ?>"
                            <?= $selected_supplier == $supplier['id'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars($supplier['name']) ?>

                            <?php if (!empty($supplier['phone'])): ?>

                                -
                                <?= htmlspecialchars($supplier['phone']) ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SUPPLIER INFO -->

            <div
                class="supplier-info"
                id="supplierInfo"
            >

                <div class="supplier-info-title">
                    Supplier Account
                </div>

                <div class="info-grid">

                    <div class="info-item">

                        <div class="info-label">
                            Supplier
                        </div>

                        <div
                            class="info-value"
                            id="infoName"
                        >
                            -
                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Phone
                        </div>

                        <div
                            class="info-value"
                            id="infoPhone"
                        >
                            -
                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Current Balance
                        </div>

                        <div
                            class="info-value balance"
                            id="infoBalance"
                        >
                            0.00 AFN
                        </div>

                    </div>

                </div>

            </div>


            <!-- AMOUNT + DATE -->

            <div class="row">

                <div class="form-group">

                    <label for="amount">
                        Payment Amount *
                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        step="0.01"
                        min="0.01"
                        placeholder="Enter payment amount"
                        value="<?= htmlspecialchars($amount) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="payment_date">
                        Payment Date *
                    </label>

                    <input
                        type="date"
                        name="payment_date"
                        id="payment_date"
                        value="<?= htmlspecialchars($payment_date) ?>"
                        required
                    >

                </div>

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    name="description"
                    id="description"
                    placeholder="Optional payment description..."
                ><?= htmlspecialchars($description) ?></textarea>

            </div>


            <!-- ACTIONS -->

            <div class="form-actions">

                <a
                    href="manage.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    💾 Save Supplier Payment
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function showSupplierInfo() {

    const select =
        document.getElementById("supplier_id");

    const option =
        select.options[select.selectedIndex];

    const info =
        document.getElementById("supplierInfo");

    if (!select.value) {

        info.classList.remove("show");

        return;
    }

    const name =
        option.getAttribute("data-name") || "-";

    const phone =
        option.getAttribute("data-phone") || "-";

    const balance =
        parseFloat(
            option.getAttribute("data-balance") || "0"
        );

    document.getElementById("infoName")
        .textContent = name;

    document.getElementById("infoPhone")
        .textContent = phone;

    document.getElementById("infoBalance")
        .textContent =
        balance.toFixed(2) + " AFN";

    info.classList.add("show");
}


function validatePayment() {

    const select =
        document.getElementById("supplier_id");

    const amountInput =
        document.getElementById("amount");

    if (!select.value) {

        alert("Please select a supplier.");

        return false;
    }

    const option =
        select.options[select.selectedIndex];

    const balance =
        parseFloat(
            option.getAttribute("data-balance") || "0"
        );

    const amount =
        parseFloat(amountInput.value || "0");

    if (amount <= 0) {

        alert(
            "Payment amount must be greater than zero."
        );

        return false;
    }

    if (balance <= 0) {

        alert(
            "This supplier has no outstanding balance."
        );

        return false;
    }

    if (amount > balance) {

        alert(
            "Payment cannot be greater than supplier balance.\n" +
            "Current balance: " +
            balance.toFixed(2) +
            " AFN"
        );

        return false;
    }

    return confirm(
        "Are you sure you want to record this supplier payment?"
    );
}


/* =========================
   SHOW INFO ON PAGE LOAD
========================= */

document.addEventListener(
    "DOMContentLoaded",
    function() {
        showSupplierInfo();
    }
);

</script>

</body>
</html>