<?php
session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "Cashier";

/* =========================
   SEARCH & FILTER
========================= */

$search = trim($_GET["search"] ?? "");
$stock_status = $_GET["stock_status"] ?? "All";
$category_id = (int)($_GET["category_id"] ?? 0);

/* =========================
   CATEGORIES
========================= */

$categories = [];

$category_query = mysqli_query( $conn,"SELECT category_id, name FROM categories ORDER BY name ASC");

if ($category_query) {
    while ($category = mysqli_fetch_assoc($category_query)) {
        $categories[] = $category;
    }
}

/* =========================
   PRODUCT QUERY
========================= */

$sql = "
    SELECT
        p.id,
        p.barcode,
        p.name,
        p.category_id,
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
    WHERE 1=1
";

$params = [];
$types = "";

/* Search */

if ($search !== "") {

    $sql .= "
        AND (
            p.name LIKE ?
            OR p.barcode LIKE ?
            OR c.name LIKE ?
        )
    ";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}

/* Category */

if ($category_id > 0) {

    $sql .= " AND p.category_id = ? ";

    $params[] = $category_id;
    $types .= "i";
}

/* Stock Status */

if ($stock_status === "Out of Stock") {

    $sql .= " AND p.stock <= 0 ";

} elseif ($stock_status === "Low Stock") {

    $sql .= " AND p.stock > 0 AND p.stock <= p.min_stock ";

} elseif ($stock_status === "In Stock") {

    $sql .= " AND p.stock > p.min_stock ";

} elseif ($stock_status === "Expired") {

    $sql .= "
        AND p.expiry_date IS NOT NULL
        AND p.expiry_date < CURDATE()
    ";

} elseif ($stock_status === "Expiring Soon") {

    $sql .= "
        AND p.expiry_date IS NOT NULL
        AND p.expiry_date >= CURDATE()
        AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ";
}

$sql .= " ORDER BY p.name ASC ";


/* =========================
   PREPARE QUERY
========================= */

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Query Error: " . mysqli_error($conn));
}

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/* =========================
   PRODUCT DATA
========================= */

$products = [];

$total_products = 0;
$total_quantity = 0;
$total_stock_cost = 0;
$total_stock_value = 0;

$low_stock_count = 0;
$out_of_stock_count = 0;
$expired_count = 0;
$expiring_count = 0;

while ($row = mysqli_fetch_assoc($result)) {

    $stock = (float)$row["stock"];
    $purchase_price = (float)$row["purchase_price"];
    $selling_price = (float)$row["selling_price"];
    $min_stock = (float)$row["min_stock"];

    $stock_cost = $stock * $purchase_price;
    $stock_value = $stock * $selling_price;

    $row["stock_cost"] = $stock_cost;
    $row["stock_value"] = $stock_value;

    /* Stock Status */

    if ($stock <= 0) {

        $row["status"] = "Out of Stock";
        $row["status_class"] = "danger";

    } elseif ($stock <= $min_stock) {

        $row["status"] = "Low Stock";
        $row["status_class"] = "warning";

    } else {

        $row["status"] = "In Stock";
        $row["status_class"] = "success";
    }

    /* Expiry Status */

    $row["expiry_status"] = "";

    if (!empty($row["expiry_date"])) {

        $today = new DateTime();
        $expiry = new DateTime($row["expiry_date"]);

        if ($expiry < $today) {

            $row["expiry_status"] = "Expired";

        } else {

            $days = $today->diff($expiry)->days;

            if ($days <= 30) {
                $row["expiry_status"] = "Expiring Soon";
            }
        }
    }

    $products[] = $row;

    $total_products++;

    $total_quantity += $stock;

    $total_stock_cost += $stock_cost;

    $total_stock_value += $stock_value;

    if ($stock <= 0) {
        $out_of_stock_count++;
    } elseif ($stock <= $min_stock) {
        $low_stock_count++;
    }

    if ($row["expiry_status"] === "Expired") {
        $expired_count++;
    }

    if ($row["expiry_status"] === "Expiring Soon") {
        $expiring_count++;
    }
}

mysqli_stmt_close($stmt);


/* =========================
   ALL STOCK SUMMARY
========================= */

$summary_sql = "
    SELECT

        COUNT(*) AS total_products,

        COALESCE(SUM(stock), 0) AS total_quantity,

        COALESCE(
            SUM(stock * purchase_price),
            0
        ) AS stock_cost,

        COALESCE(
            SUM(stock * selling_price),
            0
        ) AS stock_value,

        COALESCE(
            SUM(
                CASE
                    WHEN stock <= 0
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS out_of_stock,

        COALESCE(
            SUM(
                CASE
                    WHEN stock > 0
                    AND stock <= min_stock
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS low_stock

    FROM products
";

$summary_result = mysqli_query(
    $conn,
    $summary_sql
);

$summary = mysqli_fetch_assoc(
    $summary_result
);


/* =========================
   EXPIRY SUMMARY
========================= */

$expiry_sql = "
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN expiry_date IS NOT NULL
                    AND expiry_date < CURDATE()
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS expired,

        COALESCE(
            SUM(
                CASE
                    WHEN expiry_date IS NOT NULL
                    AND expiry_date >= CURDATE()
                    AND expiry_date <= DATE_ADD(
                        CURDATE(),
                        INTERVAL 30 DAY
                    )
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS expiring

    FROM products
";

$expiry_result = mysqli_query(
    $conn,
    $expiry_sql
);

$expiry_summary = mysqli_fetch_assoc(
    $expiry_result
);


/* =========================
   CATEGORY SUMMARY
========================= */

$category_summary = [];

$category_sql = "
    SELECT

        COALESCE(c.name, 'Uncategorized') AS category_name,

        COUNT(p.id) AS product_count,

        COALESCE(SUM(p.stock), 0) AS quantity,

        COALESCE(
            SUM(p.stock * p.purchase_price),
            0
        ) AS stock_cost,

        COALESCE(
            SUM(p.stock * p.selling_price),
            0
        ) AS stock_value

    FROM products p

    LEFT JOIN categories c
        ON p.category_id = c.category_id

    GROUP BY p.category_id, c.name

    ORDER BY stock_value DESC
";

$category_result = mysqli_query(
    $conn,
    $category_sql
);

if ($category_result) {

    while ($row = mysqli_fetch_assoc($category_result)) {

        $category_summary[] = $row;
    }
}


/* =========================
   HELPER
========================= */

function money($amount)
{
    return number_format(
        (float)$amount,
        2
    );
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
    Stock Report - Super Store
</title>

<style>

/* =========================
   GENERAL
========================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #1f2937;
}


/* =========================
   HEADER
========================= */

.header {

    background: #111827;

    color: white;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.header-left h1 {

    margin: 0;

    font-size: 24px;
}

.header-left p {

    margin: 5px 0 0;

    color: #cbd5e1;

    font-size: 13px;
}

.header-right {

    text-align: right;

    font-size: 13px;
}

.header-right strong {

    display: block;

    font-size: 15px;
}


/* =========================
   CONTAINER
========================= */

.container {

    max-width: 1500px;

    margin: 25px auto;

    padding: 0 20px;
}


/* =========================
   FILTER
========================= */

.filter-box {

    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px rgba(0,0,0,.06);

    margin-bottom: 20px;
}

.filter-form {

    display: flex;

    gap: 12px;

    align-items: end;

    flex-wrap: wrap;
}

.field {

    display: flex;

    flex-direction: column;

    gap: 6px;
}

.field label {

    font-size: 13px;

    font-weight: bold;

    color: #475569;
}

.field input,
.field select {

    min-width: 190px;

    padding: 11px;

    border:
        1px solid #d1d5db;

    border-radius: 7px;

    font-size: 14px;

    background: white;
}


/* =========================
   BUTTONS
========================= */

.btn {

    border: none;

    padding: 11px 18px;

    border-radius: 7px;

    cursor: pointer;

    text-decoration: none;

    font-size: 14px;

    font-weight: bold;

    display: inline-block;
}

.btn-primary {

    background: #2563eb;

    color: white;
}

.btn-success {

    background: #059669;

    color: white;
}

.btn-secondary {

    background: #475569;

    color: white;
}

.btn:hover {

    opacity: .9;
}


/* =========================
   CARDS
========================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 20px;
}

.card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px rgba(0,0,0,.06);
}

.card-title {

    color: #64748b;

    font-size: 13px;

    margin-bottom: 8px;
}

.card-value {

    font-size: 24px;

    font-weight: bold;
}

.card small {

    display: block;

    margin-top: 7px;

    color: #94a3b8;
}

.blue {
    border-left: 5px solid #2563eb;
}

.green {
    border-left: 5px solid #059669;
}

.orange {
    border-left: 5px solid #f59e0b;
}

.red {
    border-left: 5px solid #dc2626;
}

.purple {
    border-left: 5px solid #7c3aed;
}

.cyan {
    border-left: 5px solid #0891b2;
}


/* =========================
   SECTIONS
========================= */

.section {

    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow:
        0 3px 15px rgba(0,0,0,.06);

    margin-bottom: 20px;

    overflow-x: auto;
}

.section h2 {

    margin: 0 0 18px;

    font-size: 18px;
}


/* =========================
   TABLE
========================= */

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;
}

th,
td {

    padding: 12px;

    border-bottom:
        1px solid #e5e7eb;

    text-align: left;

    font-size: 13px;
}

th {

    background: #f8fafc;

    color: #475569;

    font-weight: bold;
}

tr:hover td {

    background: #f8fafc;
}

.text-right {

    text-align: right;
}


/* =========================
   STATUS
========================= */

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.badge-success {

    background: #dcfce7;

    color: #166534;
}

.badge-warning {

    background: #fef3c7;

    color: #92400e;
}

.badge-danger {

    background: #fee2e2;

    color: #991b1b;
}

.badge-info {

    background: #dbeafe;

    color: #1e40af;
}


/* =========================
   VALUE COLORS
========================= */

.stock-good {

    color: #059669;

    font-weight: bold;
}

.stock-low {

    color: #d97706;

    font-weight: bold;
}

.stock-zero {

    color: #dc2626;

    font-weight: bold;
}

.money {

    font-weight: bold;
}


/* =========================
   PRINT
========================= */

.print-only {

    display: none;
}

@media print {

    body {

        background: white;
    }

    .header {

        background: white;

        color: black;

        border-bottom:
            2px solid #111827;
    }

    .header-left p,
    .header-right {

        color: #333;
    }

    .filter-box,
    .no-print {

        display: none !important;
    }

    .container {

        max-width: none;

        margin: 0;

        padding: 0;
    }

    .card,
    .section {

        box-shadow: none;

        border:
            1px solid #ddd;
    }

    .print-only {

        display: block;
    }

    table {

        min-width: 0;
    }
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1100px) {

    .cards {

        grid-template-columns:
            repeat(2, 1fr);
    }
}

@media (max-width: 650px) {

    .header {

        flex-direction: column;

        align-items: flex-start;

        gap: 12px;
    }

    .header-right {

        text-align: left;
    }

    .cards {

        grid-template-columns: 1fr;
    }

    .container {

        padding: 0 12px;
    }

    .filter-form {

        flex-direction: column;

        align-items: stretch;
    }

    .field input,
    .field select,
    .btn {

        width: 100%;
    }
}

</style>

</head>

<body>


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <div class="header-left">

        <h1>
            Stock / Inventory Report
        </h1>

        <p>
            Super Store Management System
        </p>

    </div>

    <div class="header-right">

        <strong>
            <?php
            echo htmlspecialchars($username);
            ?>
        </strong>

        <?php
        echo htmlspecialchars($role);
        ?>

    </div>

</div>


<div class="container">


<!-- =========================
     PRINT HEADER
========================= -->

<div class="print-only">

    <h2>
        Super Store - Stock Report
    </h2>

    <p>
        Generated on:
        <?php echo date("Y-m-d H:i"); ?>
    </p>

</div>


<!-- =========================
     FILTER
========================= -->

<div class="filter-box no-print">

    <form
        method="GET"
        class="filter-form"
    >

        <div class="field">

            <label>
                Search Product
            </label>

            <input
                type="text"
                name="search"
                placeholder="Name or Barcode"
                value="<?php
                echo htmlspecialchars($search);
                ?>"
            >

        </div>


        <div class="field">

            <label>
                Category
            </label>

            <select name="category_id">

                <option value="0">
                    All Categories
                </option>

                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?php
                        echo $category["category_id"];
                        ?>"
                        <?php
                        echo $category_id ==
                            $category["category_id"]
                            ? "selected"
                            : "";
                        ?>
                    >

                        <?php
                        echo htmlspecialchars(
                            $category["name"]
                        );
                        ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="field">

            <label>
                Stock Status
            </label>

            <select name="stock_status">

                <option value="All"
                    <?php
                    echo $stock_status === "All"
                        ? "selected"
                        : "";
                    ?>
                >
                    All
                </option>

                <option value="In Stock"
                    <?php
                    echo $stock_status === "In Stock"
                        ? "selected"
                        : "";
                    ?>
                >
                    In Stock
                </option>

                <option value="Low Stock"
                    <?php
                    echo $stock_status === "Low Stock"
                        ? "selected"
                        : "";
                    ?>
                >
                    Low Stock
                </option>

                <option value="Out of Stock"
                    <?php
                    echo $stock_status === "Out of Stock"
                        ? "selected"
                        : "";
                    ?>
                >
                    Out of Stock
                </option>

                <option value="Expired"
                    <?php
                    echo $stock_status === "Expired"
                        ? "selected"
                        : "";
                    ?>
                >
                    Expired
                </option>

                <option value="Expiring Soon"
                    <?php
                    echo $stock_status === "Expiring Soon"
                        ? "selected"
                        : "";
                    ?>
                >
                    Expiring Soon
                </option>

            </select>

        </div>


        <button
            type="submit"
            class="btn btn-primary"
        >
            Generate
        </button>


        <button
            type="button"
            onclick="window.print()"
            class="btn btn-success"
        >
            Print Report
        </button>


        <a
            href="index.php"
            class="btn btn-secondary"
        >
            Reports Dashboard
        </a>

    </form>

</div>


<!-- =========================
     SUMMARY CARDS
========================= -->

<div class="cards">


    <div class="card blue">

        <div class="card-title">
            TOTAL PRODUCTS
        </div>

        <div class="card-value">

            <?php
            echo number_format(
                $summary["total_products"]
            );
            ?>

        </div>

        <small>
            Products in inventory
        </small>

    </div>


    <div class="card green">

        <div class="card-title">
            TOTAL QUANTITY
        </div>

        <div class="card-value">

            <?php
            echo number_format(
                $summary["total_quantity"],
                2
            );
            ?>

        </div>

        <small>
            Total units in stock
        </small>

    </div>


    <div class="card purple">

        <div class="card-title">
            STOCK COST VALUE
        </div>

        <div class="card-value">

            AFN
            <?php
            echo money(
                $summary["stock_cost"]
            );
            ?>

        </div>

        <small>
            Based on purchase price
        </small>

    </div>


    <div class="card cyan">

        <div class="card-title">
            STOCK SELLING VALUE
        </div>

        <div class="card-value">

            AFN
            <?php
            echo money(
                $summary["stock_value"]
            );
            ?>

        </div>

        <small>
            Based on selling price
        </small>

    </div>


    <div class="card orange">

        <div class="card-title">
            LOW STOCK
        </div>

        <div class="card-value">

            <?php
            echo number_format(
                $summary["low_stock"]
            );
            ?>

        </div>

        <small>
            Need restocking
        </small>

    </div>


    <div class="card red">

        <div class="card-title">
            OUT OF STOCK
        </div>

        <div class="card-value">

            <?php
            echo number_format(
                $summary["out_of_stock"]
            );
            ?>

        </div>

        <small>
            No stock available
        </small>

    </div>


    <div class="card red">

        <div class="card-title">
            EXPIRED PRODUCTS
        </div>

        <div class="card-value">

            <?php
            echo number_format(
                $expiry_summary["expired"]
            );
            ?>

        </div>

        <small>
            Expiry date passed
        </small>

    </div>


    <div class="card orange">

        <div class="card-title">
            EXPIRING SOON
        </div>

        <div class="card-value">

            <?php
            echo number_format(
                $expiry_summary["expiring"]
            );
            ?>

        </div>

        <small>
            Within 30 days
        </small>

    </div>

</div>


<!-- =========================
     CATEGORY SUMMARY
========================= -->

<div class="section">

    <h2>
        Stock by Category
    </h2>

    <table>

        <thead>

            <tr>

                <th>
                    Category
                </th>

                <th>
                    Products
                </th>

                <th class="text-right">
                    Quantity
                </th>

                <th class="text-right">
                    Purchase Value
                </th>

                <th class="text-right">
                    Selling Value
                </th>

                <th class="text-right">
                    Potential Profit
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (!empty($category_summary)): ?>

            <?php foreach ($category_summary as $category): ?>

                <?php

                $potential_profit =
                    (float)$category["stock_value"]
                    -
                    (float)$category["stock_cost"];

                ?>

                <tr>

                    <td>
                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $category["category_name"]
                            );
                            ?>
                        </strong>
                    </td>

                    <td>
                        <?php
                        echo number_format(
                            $category["product_count"]
                        );
                        ?>
                    </td>

                    <td class="text-right">

                        <?php
                        echo number_format(
                            $category["quantity"],
                            2
                        );
                        ?>

                    </td>

                    <td class="text-right">

                        AFN
                        <?php
                        echo money(
                            $category["stock_cost"]
                        );
                        ?>

                    </td>

                    <td class="text-right">

                        AFN
                        <?php
                        echo money(
                            $category["stock_value"]
                        );
                        ?>

                    </td>

                    <td class="text-right stock-good">

                        AFN
                        <?php
                        echo money(
                            $potential_profit
                        );
                        ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td colspan="6">
                    No category data found.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =========================
     PRODUCT STOCK DETAILS
========================= -->

<div class="section">

    <h2>
        Product Stock Details
    </h2>

    <table>

        <thead>

            <tr>

                <th>#</th>

                <th>
                    Barcode
                </th>

                <th>
                    Product
                </th>

                <th>
                    Category
                </th>

                <th>
                    Purchase Price
                </th>

                <th>
                    Selling Price
                </th>

                <th>
                    Stock
                </th>

                <th>
                    Min Stock
                </th>

                <th>
                    Stock Cost
                </th>

                <th>
                    Stock Value
                </th>

                <th>
                    Expiry
                </th>

                <th>
                    Status
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (!empty($products)): ?>

            <?php $number = 1; ?>

            <?php foreach ($products as $product): ?>

                <tr>

                    <td>
                        <?php
                        echo $number++;
                        ?>
                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $product["barcode"]
                        );
                        ?>

                    </td>


                    <td>

                        <strong>

                            <?php
                            echo htmlspecialchars(
                                $product["name"]
                            );
                            ?>

                        </strong>

                    </td>


                    <td>

                        <?php
                        echo htmlspecialchars(
                            $product["category_name"]
                            ?? "Uncategorized"
                        );
                        ?>

                    </td>


                    <td>

                        AFN
                        <?php
                        echo money(
                            $product["purchase_price"]
                        );
                        ?>

                    </td>


                    <td>

                        AFN
                        <?php
                        echo money(
                            $product["selling_price"]
                        );
                        ?>

                    </td>


                    <td>

                        <span
                            class="<?php

                            if (
                                $product["stock"] <= 0
                            ) {

                                echo "stock-zero";

                            } elseif (
                                $product["stock"]
                                <=
                                $product["min_stock"]
                            ) {

                                echo "stock-low";

                            } else {

                                echo "stock-good";
                            }

                            ?>"
                        >

                            <?php
                            echo number_format(
                                (float)$product["stock"],
                                2
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $product["unit"]
                            );
                            ?>

                        </span>

                    </td>


                    <td>

                        <?php
                        echo number_format(
                            (float)$product["min_stock"],
                            2
                        );
                        ?>

                    </td>


                    <td class="money">

                        AFN
                        <?php
                        echo money(
                            $product["stock_cost"]
                        );
                        ?>

                    </td>


                    <td class="money">

                        AFN
                        <?php
                        echo money(
                            $product["stock_value"]
                        );
                        ?>

                    </td>


                    <td>

                        <?php if (
                            !empty(
                                $product["expiry_date"]
                            )
                        ): ?>

                            <?php
                            echo htmlspecialchars(
                                $product["expiry_date"]
                            );
                            ?>

                            <br>

                            <?php if (
                                $product["expiry_status"]
                                === "Expired"
                            ): ?>

                                <span
                                    class="badge badge-danger"
                                >
                                    Expired
                                </span>

                            <?php elseif (
                                $product["expiry_status"]
                                === "Expiring Soon"
                            ): ?>

                                <span
                                    class="badge badge-warning"
                                >
                                    Expiring Soon
                                </span>

                            <?php endif; ?>

                        <?php else: ?>

                            <span
                                style="color:#94a3b8;"
                            >
                                No Expiry
                            </span>

                        <?php endif; ?>

                    </td>


                    <td>

                        <?php if (
                            $product["status"]
                            === "Out of Stock"
                        ): ?>

                            <span
                                class="badge badge-danger"
                            >
                                Out of Stock
                            </span>

                        <?php elseif (
                            $product["status"]
                            === "Low Stock"
                        ): ?>

                            <span
                                class="badge badge-warning"
                            >
                                Low Stock
                            </span>

                        <?php else: ?>

                            <span
                                class="badge badge-success"
                            >
                                In Stock
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td colspan="12">

                    No products found for
                    the selected filter.

                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =========================
     REPORT FOOTER
========================= -->

<div
    style="
        text-align:center;
        color:#94a3b8;
        font-size:12px;
        padding:20px;
    "
>

    Generated by
    <strong>
        Super Store Management System
    </strong>

</div>


</div>

</body>

</html>