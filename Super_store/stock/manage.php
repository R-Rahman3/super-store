<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| USER ACCESS
|--------------------------------------------------------------------------
| Admin / Manager = Full Stock Access
| Cashier = View Only
|--------------------------------------------------------------------------
*/

$user_role = $_SESSION['role'] ?? '';

$can_adjust = ($user_role === "Admin" || $user_role === "Manager");

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$category_id = intval($_GET['category_id'] ?? 0);
$status = $_GET['status'] ?? '';

/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

$cat_sql = "SELECT category_id, name FROM categories ORDER BY name ASC";
$cat_result = mysqli_query($conn, $cat_sql);

if ($cat_result) {
    while ($cat = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $cat;
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$types = "";

/* Search */
if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.barcode LIKE ?)";
    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "ss";
}

/* Category */
if ($category_id > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

/* Status */
if ($status === "out") {

    $where[] = "p.stock <= 0";

} elseif ($status === "low") {

    $where[] = "p.stock > 0 AND p.stock <= p.min_stock";

} elseif ($status === "expired") {

    $where[] = "p.expiry_date IS NOT NULL AND p.expiry_date < CURDATE()";

} elseif ($status === "expiring") {

    $where[] = "p.expiry_date IS NOT NULL
                AND p.expiry_date >= CURDATE()
                AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";

} elseif ($status === "in_stock") {

    $where[] = "p.stock > p.min_stock
                AND (
                    p.expiry_date IS NULL
                    OR p.expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                )";
}

$where_sql = "";

if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

$sql = "
    SELECT
        p.id,
        p.barcode,
        p.name,
        p.purchase_price,
        p.selling_price,
        p.stock,
        p.min_stock,
        p.unit,
        p.expiry_date,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.category_id
    $where_sql
    ORDER BY p.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$products = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| SUMMARY STATISTICS
|--------------------------------------------------------------------------
*/

$summary_sql = "
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock), 0) AS total_quantity,
        COALESCE(SUM(stock * purchase_price), 0) AS stock_cost_value,
        COALESCE(SUM(stock * selling_price), 0) AS stock_selling_value,

        SUM(
            CASE
                WHEN stock <= 0 THEN 1
                ELSE 0
            END
        ) AS out_of_stock,

        SUM(
            CASE
                WHEN stock > 0 AND stock <= min_stock THEN 1
                ELSE 0
            END
        ) AS low_stock,

        SUM(
            CASE
                WHEN expiry_date IS NOT NULL
                AND expiry_date < CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS expired,

        SUM(
            CASE
                WHEN expiry_date IS NOT NULL
                AND expiry_date >= CURDATE()
                AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                THEN 1
                ELSE 0
            END
        ) AS expiring_soon

    FROM products
";

$summary_result = mysqli_query($conn, $summary_sql);

$summary = mysqli_fetch_assoc($summary_result);

$total_products = intval($summary['total_products'] ?? 0);
$total_quantity = floatval($summary['total_quantity'] ?? 0);
$stock_cost_value = floatval($summary['stock_cost_value'] ?? 0);
$stock_selling_value = floatval($summary['stock_selling_value'] ?? 0);
$out_of_stock = intval($summary['out_of_stock'] ?? 0);
$low_stock = intval($summary['low_stock'] ?? 0);
$expired = intval($summary['expired'] ?? 0);
$expiring_soon = intval($summary['expiring_soon'] ?? 0);

$potential_profit = $stock_selling_value - $stock_cost_value;

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Stock Management - Super Store</title>

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
    background: #ffffff;
    border-bottom: 1px solid #ddd;
    padding: 18px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.header-left h1 {
    font-size: 25px;
    margin-bottom: 5px;
}

.header-left p {
    color: #777;
    font-size: 14px;
}

.header-right {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 14px;
    border: none;
    cursor: pointer;
}

.btn-dashboard {
    background: #343a40;
    color: white;
}

.btn-adjust {
    background: #007bff;
    color: white;
}

/* CONTAINER */

.container {
    width: 96%;
    max-width: 1500px;
    margin: 25px auto;
}

/* CARDS */

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
    border-left: 5px solid #007bff;
}

.card-title {
    font-size: 13px;
    color: #777;
    margin-bottom: 8px;
}

.card-value {
    font-size: 25px;
    font-weight: bold;
}

.card-blue {
    border-left-color: #007bff;
}

.card-green {
    border-left-color: #28a745;
}

.card-orange {
    border-left-color: #fd7e14;
}

.card-red {
    border-left-color: #dc3545;
}

.card-purple {
    border-left-color: #6f42c1;
}

/* FILTER */

.filter-box {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
    margin-bottom: 25px;
}

.filter-box h3 {
    margin-bottom: 15px;
}

.filter-form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto auto;
    gap: 12px;
    align-items: end;
}

.form-group label {
    display: block;
    font-size: 13px;
    margin-bottom: 6px;
    color: #555;
}

.form-control {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #ccc;
    border-radius: 7px;
    outline: none;
}

.form-control:focus {
    border-color: #007bff;
}

.btn-search {
    background: #007bff;
    color: white;
}

.btn-reset {
    background: #6c757d;
    color: white;
}

/* STOCK VALUES */

.value-box {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
}

.value-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.value-item {
    border-right: 1px solid #eee;
}

.value-item:last-child {
    border-right: none;
}

.value-label {
    color: #777;
    font-size: 13px;
    margin-bottom: 6px;
}

.value-number {
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
    min-width: 1100px;
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
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

tr:hover {
    background: #fafafa;
}

/* PRODUCT */

.product-name {
    font-weight: bold;
}

.barcode {
    font-family: monospace;
    color: #555;
}

/* STOCK */

.stock-number {
    font-size: 16px;
    font-weight: bold;
}

.stock-good {
    color: #28a745;
}

.stock-low {
    color: #fd7e14;
}

.stock-out {
    color: #dc3545;
}

/* BADGES */

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge-green {
    background: #d4edda;
    color: #155724;
}

.badge-orange {
    background: #fff3cd;
    color: #856404;
}

.badge-red {
    background: #f8d7da;
    color: #721c24;
}

.badge-blue {
    background: #dbeafe;
    color: #1d4ed8;
}

/* ACTION */

.action-btn {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    margin-right: 4px;
}

.action-adjust {
    background: #007bff;
    color: white;
}

.action-history {
    background: #343a40;
    color: white;
}

/* EMPTY */

.empty {
    text-align: center;
    padding: 40px;
    color: #777;
}

/* PRINT */

.print-btn {
    background: #198754;
    color: white;
}

/* RESPONSIVE */

@media (max-width: 1100px) {

    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .filter-form {
        grid-template-columns: 1fr 1fr;
    }

    .value-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 650px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .filter-form {
        grid-template-columns: 1fr;
    }

    .container {
        width: 94%;
    }

}

@media print {

    .header-right,
    .filter-box,
    .action-column,
    .print-hide {
        display: none !important;
    }

    body {
        background: white;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .table-box,
    .value-box,
    .card {
        box-shadow: none;
    }

}

</style>

</head>

<body>

<!-- HEADER -->

<div class="header">

    <div class="header-left">

        <h1>📦 Stock Management</h1>

        <p>
            Manage and monitor your store inventory
        </p>

    </div>

    <div class="header-right">

        <a href="../dashboard.php" class="btn btn-dashboard">
            ← Dashboard
        </a>

        <?php if ($can_adjust): ?>

            <a href="adjustment.php" class="btn btn-adjust">
                + Stock Adjustment
            </a>

        <?php endif; ?>

        <button onclick="window.print()" class="btn print-btn">
            🖨 Print
        </button>

    </div>

</div>


<div class="container">


<!-- SUMMARY CARDS -->

<div class="cards">

    <div class="card card-blue">

        <div class="card-title">
            Total Products
        </div>

        <div class="card-value">
            <?= number_format($total_products) ?>
        </div>

    </div>


    <div class="card card-green">

        <div class="card-title">
            Total Quantity
        </div>

        <div class="card-value">
            <?= number_format($total_quantity, 2) ?>
        </div>

    </div>


    <div class="card card-orange">

        <div class="card-title">
            Low Stock
        </div>

        <div class="card-value">
            <?= number_format($low_stock) ?>
        </div>

    </div>


    <div class="card card-red">

        <div class="card-title">
            Out of Stock
        </div>

        <div class="card-value">
            <?= number_format($out_of_stock) ?>
        </div>

    </div>


    <div class="card card-red">

        <div class="card-title">
            Expired
        </div>

        <div class="card-value">
            <?= number_format($expired) ?>
        </div>

    </div>


    <div class="card card-orange">

        <div class="card-title">
            Expiring Soon
        </div>

        <div class="card-value">
            <?= number_format($expiring_soon) ?>
        </div>

    </div>


    <div class="card card-purple">

        <div class="card-title">
            Stock Cost Value
        </div>

        <div class="card-value">
            <?= number_format($stock_cost_value, 2) ?> AFN
        </div>

    </div>


    <div class="card card-green">

        <div class="card-title">
            Potential Profit
        </div>

        <div class="card-value">
            <?= number_format($potential_profit, 2) ?> AFN
        </div>

    </div>

</div>


<!-- FILTER -->

<div class="filter-box">

    <h3>🔎 Search & Filter Stock</h3>

    <form method="GET" class="filter-form">

        <div class="form-group">

            <label>Search Product / Barcode</label>

            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Product name or barcode..."
                value="<?= htmlspecialchars($search) ?>"
            >

        </div>


        <div class="form-group">

            <label>Category</label>

            <select name="category_id" class="form-control">

                <option value="0">All Categories</option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= $category['category_id'] ?>"
                        <?= ($category_id == $category['category_id']) ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($category['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-group">

            <label>Stock Status</label>

            <select name="status" class="form-control">

                <option value="">All Status</option>

                <option value="in_stock" <?= $status === 'in_stock' ? 'selected' : '' ?>>
                    In Stock
                </option>

                <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>
                    Low Stock
                </option>

                <option value="out" <?= $status === 'out' ? 'selected' : '' ?>>
                    Out of Stock
                </option>

                <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>
                    Expired
                </option>

                <option value="expiring" <?= $status === 'expiring' ? 'selected' : '' ?>>
                    Expiring Soon
                </option>

            </select>

        </div>


        <button type="submit" class="btn btn-search">
            Search
        </button>


        <a href="manage.php" class="btn btn-reset">
            Reset
        </a>

    </form>

</div>


<!-- STOCK VALUE -->

<div class="value-box">

    <div class="value-grid">

        <div class="value-item">

            <div class="value-label">
                Total Stock Cost
            </div>

            <div class="value-number">
                <?= number_format($stock_cost_value, 2) ?> AFN
            </div>

        </div>


        <div class="value-item">

            <div class="value-label">
                Total Selling Value
            </div>

            <div class="value-number">
                <?= number_format($stock_selling_value, 2) ?> AFN
            </div>

        </div>


        <div class="value-item">

            <div class="value-label">
                Expected Gross Profit
            </div>

            <div class="value-number">
                <?= number_format($potential_profit, 2) ?> AFN
            </div>

        </div>

    </div>

</div>


<!-- TABLE -->

<div class="table-box">

    <div class="table-header">

        <h2>
            📋 Stock List
        </h2>

        <span>
            <?= count($products) ?> products found
        </span>

    </div>


    <?php if (!empty($products)): ?>

    <table>

        <thead>

            <tr>

                <th>#</th>

                <th>Barcode</th>

                <th>Product</th>

                <th>Category</th>

                <th>Purchase Price</th>

                <th>Selling Price</th>

                <th>Stock</th>

                <th>Min Stock</th>

                <th>Stock Value</th>

                <th>Expiry</th>

                <th>Status</th>

                <?php if ($can_adjust): ?>
                    <th class="action-column">Action</th>
                <?php endif; ?>

            </tr>

        </thead>


        <tbody>

        <?php

        $counter = 1;

        foreach ($products as $product):

            $stock = floatval($product['stock']);
            $min_stock = floatval($product['min_stock']);

            $stock_value =
                $stock * floatval($product['purchase_price']);

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $status_text = "In Stock";
            $status_class = "badge-green";
            $stock_class = "stock-good";

            if ($stock <= 0) {

                $status_text = "Out of Stock";
                $status_class = "badge-red";
                $stock_class = "stock-out";

            } elseif (
                $product['expiry_date'] !== null &&
                $product['expiry_date'] !== '' &&
                strtotime($product['expiry_date']) < strtotime(date('Y-m-d'))
            ) {

                $status_text = "Expired";
                $status_class = "badge-red";

            } elseif (
                $product['expiry_date'] !== null &&
                $product['expiry_date'] !== '' &&
                strtotime($product['expiry_date']) <= strtotime('+30 days')
            ) {

                $status_text = "Expiring Soon";
                $status_class = "badge-orange";

            } elseif ($stock <= $min_stock) {

                $status_text = "Low Stock";
                $status_class = "badge-orange";
                $stock_class = "stock-low";
            }

        ?>

            <tr>

                <td>
                    <?= $counter++ ?>
                </td>


                <td class="barcode">

                    <?= htmlspecialchars($product['barcode']) ?>

                </td>


                <td>

                    <div class="product-name">

                        <?= htmlspecialchars($product['name']) ?>

                    </div>

                    <small style="color:#777;">

                        <?= htmlspecialchars($product['unit'] ?? 'Piece') ?>

                    </small>

                </td>


                <td>

                    <?= htmlspecialchars(
                        $product['category_name'] ?? 'Uncategorized'
                    ) ?>

                </td>


                <td>

                    <?= number_format(
                        $product['purchase_price'],
                        2
                    ) ?>

                    AFN

                </td>


                <td>

                    <?= number_format(
                        $product['selling_price'],
                        2
                    ) ?>

                    AFN

                </td>


                <td>

                    <span class="stock-number <?= $stock_class ?>">

                        <?= number_format($stock, 2) ?>

                    </span>

                </td>


                <td>

                    <?= number_format($min_stock, 2) ?>

                </td>


                <td>

                    <?= number_format($stock_value, 2) ?>

                    AFN

                </td>


                <td>

                    <?php if (!empty($product['expiry_date'])): ?>

                        <?= htmlspecialchars($product['expiry_date']) ?>

                    <?php else: ?>

                        <span style="color:#999;">
                            No Expiry
                        </span>

                    <?php endif; ?>

                </td>


                <td>

                    <span class="badge <?= $status_class ?>">

                        <?= $status_text ?>

                    </span>

                </td>


                <?php if ($can_adjust): ?>

                <td class="action-column">

                    <a
                        href="adjustment.php?id=<?= $product['id'] ?>"
                        class="action-btn action-adjust"
                    >
                        Adjust
                    </a>

                    <a
                        href="history.php?product_id=<?= $product['id'] ?>"
                        class="action-btn action-history"
                    >
                        History
                    </a>

                </td>

                <?php endif; ?>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    <?php else: ?>

        <div class="empty">

            <h3>No products found</h3>

            <p>
                There are no products matching your search or filter.
            </p>

        </div>

    <?php endif; ?>

</div>


</div>

</body>

</html>