<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../../db.php";

/* =========================
   GET PURCHASE ID
========================= */

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: manage.php?error=invalid_purchase");
    exit();
}

/* =========================
   GET PURCHASE INFORMATION
========================= */

$sql = "
    SELECT 
        p.id,
        p.invoice_no,
        p.total_amount,
        p.paid_amount,
        p.due_amount,
        p.purchase_date,
        s.name AS supplier_name,
        s.phone AS supplier_phone,
        s.address AS supplier_address
    FROM purchases p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$purchase = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$purchase) {
    header("Location: manage.php?error=purchase_not_found");
    exit();
}

/* =========================
   GET PURCHASE ITEMS
========================= */

$sql_items = "
    SELECT
        pi.id,
        pi.quantity,
        pi.purchase_price,
        pi.total,
        pr.barcode,
        pr.name AS product_name,
        pr.unit
    FROM purchase_items pi
    INNER JOIN products pr ON pi.product_id = pr.id
    WHERE pi.purchase_id = ?
    ORDER BY pi.id ASC
";

$stmt = mysqli_prepare($conn, $sql_items);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$items_result = mysqli_stmt_get_result($stmt);

$items = [];

while ($row = mysqli_fetch_assoc($items_result)) {
    $items[] = $row;
}

mysqli_stmt_close($stmt);

/* =========================
   SETTINGS
========================= */

$currency = "AFN";
$store_name = "My Super Store";
$store_phone = "";
$store_address = "";
$store_logo = "";

$settings_sql = "
    SELECT store_name, phone, address, currency, logo
    FROM settings
    ORDER BY id ASC
    LIMIT 1
";

$settings_result = mysqli_query($conn, $settings_sql);

if ($settings_result && mysqli_num_rows($settings_result) > 0) {

    $settings = mysqli_fetch_assoc($settings_result);

    $store_name = $settings['store_name'] ?: $store_name;
    $store_phone = $settings['phone'] ?: "";
    $store_address = $settings['address'] ?: "";
    $currency = $settings['currency'] ?: "AFN";
    $store_logo = $settings['logo'] ?: "";
}

/* =========================
   HELPERS
========================= */

function money($amount, $currency)
{
    return number_format((float)$amount, 2) . " " . htmlspecialchars($currency);
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Purchase View - <?php echo e($purchase['invoice_no']); ?></title>

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

.container {
    width: 95%;
    max-width: 1100px;
    margin: 30px auto;
}

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 10px;
}

.top-bar h1 {
    margin: 0;
    font-size: 25px;
}

.buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    border: none;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    display: inline-block;
}

.btn-back {
    background: #6b7280;
    color: white;
}

.btn-print {
    background: #2563eb;
    color: white;
}

.btn-edit {
    background: #f59e0b;
    color: white;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.07);
}

.store-header {
    text-align: center;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 20px;
    margin-bottom: 20px;
}

.store-logo {
    max-width: 90px;
    max-height: 90px;
    object-fit: contain;
    margin-bottom: 10px;
}

.store-header h2 {
    margin: 5px 0;
    font-size: 25px;
}

.store-header p {
    margin: 4px 0;
    color: #6b7280;
}

.purchase-header {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

.info-box {
    background: #f8fafc;
    border-radius: 9px;
    padding: 16px;
    border: 1px solid #e5e7eb;
}

.info-box h3 {
    margin-top: 0;
    margin-bottom: 12px;
    font-size: 17px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin: 8px 0;
}

.info-label {
    color: #6b7280;
}

.info-value {
    font-weight: 600;
    text-align: right;
}

.invoice-number {
    color: #2563eb;
    font-size: 18px;
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
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    white-space: nowrap;
}

th {
    font-size: 13px;
}

td {
    font-size: 14px;
}

tbody tr:hover {
    background: #f8fafc;
}

.text-right {
    text-align: right;
}

.summary {
    margin-top: 20px;
    margin-left: auto;
    max-width: 400px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.summary-row.total {
    font-size: 19px;
    font-weight: bold;
    border-bottom: none;
}

.summary-row.paid {
    color: #16a34a;
}

.summary-row.due {
    color: #dc2626;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-paid {
    background: #dcfce7;
    color: #166534;
}

.status-due {
    background: #fee2e2;
    color: #991b1b;
}

.status-partial {
    background: #fef3c7;
    color: #92400e;
}

.footer {
    text-align: center;
    margin-top: 25px;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
    color: #6b7280;
    font-size: 13px;
}

/* PRINT */

@media print {

    body {
        background: white;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .top-bar {
        display: none;
    }

    .card {
        box-shadow: none;
        border-radius: 0;
        padding: 10px;
    }

    .no-print {
        display: none !important;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
    }

}

/* RESPONSIVE */

@media (max-width: 700px) {

    .container {
        width: 96%;
        margin: 15px auto;
    }

    .top-bar {
        align-items: flex-start;
        flex-direction: column;
    }

    .purchase-header {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 15px;
    }

    th,
    td {
        padding: 9px;
        font-size: 12px;
    }

    .summary {
        max-width: 100%;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- TOP BAR -->

    <div class="top-bar no-print">

        <h1>Purchase Details</h1>

        <div class="buttons">

            <a href="manage.php" class="btn btn-back">
                ← Back
            </a>

            <a href="edit.php?id=<?php echo $purchase['id']; ?>"
               class="btn btn-edit">
                Edit
            </a>

            <button onclick="window.print()" class="btn btn-print">
                🖨 Print
            </button>

        </div>

    </div>


    <!-- MAIN CARD -->

    <div class="card">

        <!-- STORE HEADER -->

        <div class="store-header">

            <?php if (!empty($store_logo)): ?>

                <img
                    src="../<?php echo e($store_logo); ?>"
                    class="store-logo"
                    alt="Store Logo"
                >

            <?php endif; ?>

            <h2><?php echo e($store_name); ?></h2>

            <?php if (!empty($store_phone)): ?>

                <p>Phone: <?php echo e($store_phone); ?></p>

            <?php endif; ?>

            <?php if (!empty($store_address)): ?>

                <p><?php echo e($store_address); ?></p>

            <?php endif; ?>

        </div>


        <!-- PURCHASE / SUPPLIER INFORMATION -->

        <div class="purchase-header">

            <div class="info-box">

                <h3>Purchase Information</h3>

                <div class="info-row">

                    <span class="info-label">
                        Invoice No:
                    </span>

                    <span class="info-value invoice-number">
                        <?php echo e($purchase['invoice_no']); ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Purchase Date:
                    </span>

                    <span class="info-value">
                        <?php echo e(date(
                            "d M Y, h:i A",
                            strtotime($purchase['purchase_date'])
                        )); ?>
                    </span>

                </div>

                <div class="info-row">

                    <span class="info-label">
                        Items:
                    </span>

                    <span class="info-value">
                        <?php echo count($items); ?>
                    </span>

                </div>

            </div>


            <div class="info-box">

                <h3>Supplier Information</h3>

                <div class="info-row">

                    <span class="info-label">
                        Name:
                    </span>

                    <span class="info-value">
                        <?php echo e(
                            $purchase['supplier_name'] ?: "Walk-in Supplier"
                        ); ?>
                    </span>

                </div>

                <?php if (!empty($purchase['supplier_phone'])): ?>

                <div class="info-row">

                    <span class="info-label">
                        Phone:
                    </span>

                    <span class="info-value">
                        <?php echo e($purchase['supplier_phone']); ?>
                    </span>

                </div>

                <?php endif; ?>


                <?php if (!empty($purchase['supplier_address'])): ?>

                <div class="info-row">

                    <span class="info-label">
                        Address:
                    </span>

                    <span class="info-value">
                        <?php echo e($purchase['supplier_address']); ?>
                    </span>

                </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- ITEMS -->

        <h3>Purchased Products</h3>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Barcode</th>

                        <th>Product</th>

                        <th>Unit</th>

                        <th>Quantity</th>

                        <th>Purchase Price</th>

                        <th class="text-right">
                            Total
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($items) > 0): ?>

                    <?php
                    $counter = 1;
                    ?>

                    <?php foreach ($items as $item): ?>

                    <tr>

                        <td>
                            <?php echo $counter++; ?>
                        </td>

                        <td>
                            <?php echo e($item['barcode']); ?>
                        </td>

                        <td>
                            <strong>
                                <?php echo e($item['product_name']); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo e($item['unit']); ?>
                        </td>

                        <td>
                            <?php echo e($item['quantity']); ?>
                        </td>

                        <td>
                            <?php echo money(
                                $item['purchase_price'],
                                $currency
                            ); ?>
                        </td>

                        <td class="text-right">

                            <?php echo money(
                                $item['total'],
                                $currency
                            ); ?>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="7" style="text-align:center;">
                            No purchase items found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- SUMMARY -->

        <div class="summary">

            <div class="summary-row">

                <span>
                    Total Amount
                </span>

                <strong>
                    <?php echo money(
                        $purchase['total_amount'],
                        $currency
                    ); ?>
                </strong>

            </div>


            <div class="summary-row paid">

                <span>
                    Paid Amount
                </span>

                <strong>
                    <?php echo money(
                        $purchase['paid_amount'],
                        $currency
                    ); ?>
                </strong>

            </div>


            <div class="summary-row due">

                <span>
                    Due Amount
                </span>

                <strong>
                    <?php echo money(
                        $purchase['due_amount'],
                        $currency
                    ); ?>
                </strong>

            </div>


            <div class="summary-row total">

                <span>
                    Purchase Total
                </span>

                <strong>
                    <?php echo money(
                        $purchase['total_amount'],
                        $currency
                    ); ?>
                </strong>

            </div>


            <div style="margin-top:15px;text-align:right;">

                <?php if ((float)$purchase['due_amount'] <= 0): ?>

                    <span class="status status-paid">
                        PAID

                    </span>

                <?php elseif (
                    (float)$purchase['paid_amount'] > 0
                ): ?>

                    <span class="status status-partial">
                        PARTIALLY PAID
                    </span>

                <?php else: ?>

                    <span class="status status-due">
                        UNPAID
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <!-- FOOTER -->

        <div class="footer">

            Purchase Invoice:
            <?php echo e($purchase['invoice_no']); ?>

            <br>

            Generated from
            <?php echo e($store_name); ?>

        </div>

    </div>

</div>

</body>

</html>