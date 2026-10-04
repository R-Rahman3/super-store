<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$user_role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
*/

$product_id = intval($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    header("Location: manage.php?error=invalid_product");
    exit();
}

/*
|--------------------------------------------------------------------------
| GET PRODUCT
|--------------------------------------------------------------------------
*/

$product = null;

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id,
        p.name,
        p.barcode,
        p.stock,
        p.unit,
        c.name AS category_name
     FROM products p
     LEFT JOIN categories c
        ON p.category_id = c.category_id
     WHERE p.id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($result) {
    $product = mysqli_fetch_assoc($result);
}

mysqli_stmt_close($stmt);

if (!$product) {
    header("Location: manage.php?error=product_not_found");
    exit();
}

/*
|--------------------------------------------------------------------------
| STOCK MOVEMENT HISTORY
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        sm.id,
        sm.type,
        sm.quantity,
        sm.stock_before,
        sm.stock_after,
        sm.reference_id,
        sm.reason,
        sm.created_at,
        u.name AS user_name
     FROM stock_movements sm
     LEFT JOIN users u
        ON sm.user_id = u.id
     WHERE sm.product_id = ?
     ORDER BY sm.id DESC"
);

mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);

$history_result = mysqli_stmt_get_result($stmt);

$movements = [];

if ($history_result) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $movements[] = $row;
    }
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$total_movements = count($movements);

$total_added = 0;
$total_removed = 0;

foreach ($movements as $movement) {

    if (
        $movement['type'] === 'Purchase' ||
        $movement['type'] === 'Adjustment Add'
    ) {
        $total_added += (float)$movement['quantity'];
    }

    if (
        $movement['type'] === 'Sale' ||
        $movement['type'] === 'Adjustment Remove'
    ) {
        $total_removed += (float)$movement['quantity'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Stock History - Super Store</title>

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

/* HEADER */

.header {
    background: #fff;
    border-bottom: 1px solid #ddd;
    padding: 18px 25px;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.header h1 {
    font-size: 24px;
    margin-bottom: 5px;
}

.header p {
    color: #777;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-back {
    background: #343a40;
    color: white;
}

.btn-print {
    background: #198754;
    color: white;
}

/* CONTAINER */

.container {
    width: 96%;
    max-width: 1450px;
    margin: 25px auto;
}

/* PRODUCT CARD */

.product-card {
    background: white;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 20px;

    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
}

.product-name {
    font-size: 23px;
    font-weight: bold;
    margin-bottom: 6px;
}

.product-details {
    color: #777;
    font-size: 14px;
}

/* SUMMARY */

.summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 12px;

    box-shadow: 0 3px 12px rgba(0,0,0,0.06);

    border-left: 5px solid #007bff;
}

.card-green {
    border-left-color: #198754;
}

.card-red {
    border-left-color: #dc3545;
}

.card-purple {
    border-left-color: #6f42c1;
}

.card-title {
    color: #777;
    font-size: 13px;
    margin-bottom: 7px;
}

.card-value {
    font-size: 23px;
    font-weight: bold;
}

/* TABLE */

.table-box {
    background: white;
    border-radius: 12px;
    padding: 20px;

    box-shadow: 0 3px 12px rgba(0,0,0,0.06);

    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 18px;
}

.table-header h2 {
    font-size: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

thead {
    background: #f1f3f5;
}

th {
    padding: 13px 10px;
    text-align: left;

    font-size: 13px;
    color: #555;

    border-bottom: 1px solid #ddd;
}

td {
    padding: 13px 10px;

    font-size: 13px;

    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #fafafa;
}

/* BADGES */

.badge {
    display: inline-block;
    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;
    font-weight: bold;
}

.badge-purchase {
    background: #d4edda;
    color: #155724;
}

.badge-sale {
    background: #f8d7da;
    color: #721c24;
}

.badge-add {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-remove {
    background: #fff3cd;
    color: #856404;
}

.badge-set {
    background: #e2d9f3;
    color: #4b2e83;
}

/* QUANTITY */

.quantity-add {
    color: #198754;
    font-weight: bold;
}

.quantity-remove {
    color: #dc3545;
    font-weight: bold;
}

.quantity-set {
    color: #6f42c1;
    font-weight: bold;
}

/* EMPTY */

.empty {
    text-align: center;
    padding: 45px;
    color: #777;
}

/* PRINT */

@media print {

    .header-actions {
        display: none;
    }

    body {
        background: white;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .card,
    .product-card,
    .table-box {
        box-shadow: none;
    }

}

/* RESPONSIVE */

@media (max-width: 900px) {

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 600px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .container {
        width: 94%;
    }

}

</style>

</head>

<body>

<!-- HEADER -->

<div class="header">

    <div>

        <h1>📊 Stock History</h1>

        <p>
            Complete stock movement history
        </p>

    </div>

    <div class="header-actions">

        <a
            href="manage.php"
            class="btn btn-back"
        >
            ← Back to Stock
        </a>

        <button
            onclick="window.print()"
            class="btn btn-print"
        >
            🖨 Print
        </button>

    </div>

</div>


<div class="container">


<!-- PRODUCT -->

<div class="product-card">

    <div class="product-name">

        <?= htmlspecialchars($product['name']) ?>

    </div>

    <div class="product-details">

        Barcode:
        <strong>
            <?= htmlspecialchars($product['barcode']) ?>
        </strong>

        &nbsp; | &nbsp;

        Category:
        <strong>
            <?= htmlspecialchars(
                $product['category_name'] ?? 'Uncategorized'
            ) ?>
        </strong>

        &nbsp; | &nbsp;

        Current Stock:
        <strong>
            <?= number_format(
                (float)$product['stock'],
                2
            ) ?>
            <?= htmlspecialchars($product['unit']) ?>
        </strong>

    </div>

</div>


<!-- SUMMARY -->

<div class="summary">

    <div class="card">

        <div class="card-title">
            Total Movements
        </div>

        <div class="card-value">
            <?= number_format($total_movements) ?>
        </div>

    </div>


    <div class="card card-green">

        <div class="card-title">
            Total Added
        </div>

        <div class="card-value">
            <?= number_format($total_added, 2) ?>
        </div>

    </div>


    <div class="card card-red">

        <div class="card-title">
            Total Removed
        </div>

        <div class="card-value">
            <?= number_format($total_removed, 2) ?>
        </div>

    </div>


    <div class="card card-purple">

        <div class="card-title">
            Current Stock
        </div>

        <div class="card-value">

            <?= number_format(
                (float)$product['stock'],
                2
            ) ?>

            <?= htmlspecialchars($product['unit']) ?>

        </div>

    </div>

</div>


<!-- HISTORY TABLE -->

<div class="table-box">

    <div class="table-header">

        <h2>
            📋 Movement History
        </h2>

        <span>
            <?= number_format($total_movements) ?> records
        </span>

    </div>


    <?php if (!empty($movements)): ?>

    <table>

        <thead>

            <tr>

                <th>#</th>

                <th>Type</th>

                <th>Quantity</th>

                <th>Stock Before</th>

                <th>Stock After</th>

                <th>Reference</th>

                <th>Reason</th>

                <th>User</th>

                <th>Date & Time</th>

            </tr>

        </thead>


        <tbody>

        <?php

        $counter = 1;

        foreach ($movements as $movement):

            $type = $movement['type'];

            $badge_class = "badge-set";

            $quantity_class = "quantity-set";

            $quantity_sign = "";

            if ($type === "Purchase") {

                $badge_class = "badge-purchase";
                $quantity_class = "quantity-add";
                $quantity_sign = "+";

            } elseif ($type === "Sale") {

                $badge_class = "badge-sale";
                $quantity_class = "quantity-remove";
                $quantity_sign = "-";

            } elseif ($type === "Adjustment Add") {

                $badge_class = "badge-add";
                $quantity_class = "quantity-add";
                $quantity_sign = "+";

            } elseif ($type === "Adjustment Remove") {

                $badge_class = "badge-remove";
                $quantity_class = "quantity-remove";
                $quantity_sign = "-";

            } elseif ($type === "Adjustment Set") {

                $badge_class = "badge-set";
                $quantity_class = "quantity-set";
            }

        ?>

        <tr>

            <td>
                <?= $counter++ ?>
            </td>


            <td>

                <span class="badge <?= $badge_class ?>">

                    <?= htmlspecialchars($type) ?>

                </span>

            </td>


            <td>

                <span class="<?= $quantity_class ?>">

                    <?= $quantity_sign ?>

                    <?= number_format(
                        (float)$movement['quantity'],
                        2
                    ) ?>

                </span>

            </td>


            <td>

                <?= number_format(
                    (float)$movement['stock_before'],
                    2
                ) ?>

            </td>


            <td>

                <strong>

                    <?= number_format(
                        (float)$movement['stock_after'],
                        2
                    ) ?>

                </strong>

            </td>


            <td>

                <?php if (!empty($movement['reference_id'])): ?>

                    #<?= intval($movement['reference_id']) ?>

                <?php else: ?>

                    <span style="color:#999;">
                        —
                    </span>

                <?php endif; ?>

            </td>


            <td>

                <?= !empty($movement['reason'])
                    ? htmlspecialchars($movement['reason'])
                    : '<span style="color:#999;">—</span>'
                ?>

            </td>


            <td>

                <?= !empty($movement['user_name'])
                    ? htmlspecialchars($movement['user_name'])
                    : '<span style="color:#999;">System</span>'
                ?>

            </td>


            <td>

                <?= date(
                    'Y-m-d H:i',
                    strtotime($movement['created_at'])
                ) ?>

            </td>

        </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    <?php else: ?>

        <div class="empty">

            <h3>No Stock History</h3>

            <p>
                No stock movements have been recorded for this product yet.
            </p>

        </div>

    <?php endif; ?>

</div>


</div>

</body>

</html>