<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "Cashier";

/* =========================
   SELECT DATE
========================= */

$report_date = $_GET["date"] ?? date("Y-m-d");

$date_obj = DateTime::createFromFormat("Y-m-d", $report_date);

if (!$date_obj || $date_obj->format("Y-m-d") !== $report_date) {
    $report_date = date("Y-m-d");
}

/* =========================
   HELPER
========================= */

function money($amount)
{
    return number_format((float)$amount, 2);
}

/* =========================
   SALES SUMMARY
========================= */

$sales = [
    "count" => 0,
    "subtotal" => 0,
    "discount" => 0,
    "total" => 0,
    "paid" => 0,
    "due" => 0
];

$stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS sale_count,
        COALESCE(SUM(subtotal), 0) AS subtotal,
        COALESCE(SUM(discount), 0) AS discount,
        COALESCE(SUM(total_amount), 0) AS total,
        COALESCE(SUM(paid_amount), 0) AS paid,
        COALESCE(SUM(due_amount), 0) AS due
    FROM sales
    WHERE DATE(sale_date) = ?
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $sales = [
        "count" => (int)$row["sale_count"],
        "subtotal" => (float)$row["subtotal"],
        "discount" => (float)$row["discount"],
        "total" => (float)$row["total"],
        "paid" => (float)$row["paid"],
        "due" => (float)$row["due"]
    ];
}

mysqli_stmt_close($stmt);


/* =========================
   PURCHASE SUMMARY
========================= */

$purchases = [
    "count" => 0,
    "total" => 0,
    "paid" => 0,
    "due" => 0
];

$stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS purchase_count,
        COALESCE(SUM(total_amount), 0) AS total,
        COALESCE(SUM(paid_amount), 0) AS paid,
        COALESCE(SUM(due_amount), 0) AS due
    FROM purchases
    WHERE purchase_date = ?
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $purchases = [
        "count" => (int)$row["purchase_count"],
        "total" => (float)$row["total"],
        "paid" => (float)$row["paid"],
        "due" => (float)$row["due"]
    ];
}

mysqli_stmt_close($stmt);


/* =========================
   EXPENSE SUMMARY
========================= */

$expenses = [
    "count" => 0,
    "total" => 0
];

$stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS expense_count,
        COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE expense_date = ?
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $expenses = [
        "count" => (int)$row["expense_count"],
        "total" => (float)$row["total"]
    ];
}

mysqli_stmt_close($stmt);


/* =========================
   COGS
========================= */

$cogs = 0;

$stmt = mysqli_prepare($conn, "
    SELECT
        COALESCE(
            SUM(
                sale_items.quantity *
                sale_items.purchase_price
            ), 0
        ) AS cogs
    FROM sale_items
    INNER JOIN sales
        ON sale_items.sale_id = sales.id
    WHERE DATE(sales.sale_date) = ?
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $cogs = (float)$row["cogs"];
}

mysqli_stmt_close($stmt);


/* =========================
   PROFIT CALCULATION
========================= */

$gross_profit = $sales["total"] - $cogs;

$net_profit = $gross_profit - $expenses["total"];

$profit_margin = 0;

if ($sales["total"] > 0) {
    $profit_margin =
        ($net_profit / $sales["total"]) * 100;
}


/* =========================
   PAYMENT METHODS
========================= */

$payment_methods = [];

$stmt = mysqli_prepare($conn, "
    SELECT
        payment_method,
        COUNT(*) AS transaction_count,
        COALESCE(SUM(total_amount), 0) AS total_amount,
        COALESCE(SUM(paid_amount), 0) AS paid_amount
    FROM sales
    WHERE DATE(sale_date) = ?
    GROUP BY payment_method
    ORDER BY total_amount DESC
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $payment_methods[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   TOP SELLING PRODUCTS
========================= */

$top_products = [];

$stmt = mysqli_prepare($conn, "
    SELECT
        products.name,
        products.barcode,
        SUM(sale_items.quantity) AS quantity,
        SUM(sale_items.total) AS sales_amount,
        SUM(
            sale_items.quantity *
            sale_items.purchase_price
        ) AS cost_amount
    FROM sale_items
    INNER JOIN sales
        ON sale_items.sale_id = sales.id
    INNER JOIN products
        ON sale_items.product_id = products.id
    WHERE DATE(sales.sale_date) = ?
    GROUP BY
        products.id,
        products.name,
        products.barcode
    ORDER BY quantity DESC
    LIMIT 10
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $row["profit"] =
        (float)$row["sales_amount"] -
        (float)$row["cost_amount"];

    $top_products[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   TOP CUSTOMERS
========================= */

$top_customers = [];

$stmt = mysqli_prepare($conn, "
    SELECT
        customers.name,
        customers.phone,
        COUNT(sales.id) AS invoice_count,
        COALESCE(SUM(sales.total_amount), 0) AS total_amount,
        COALESCE(SUM(sales.paid_amount), 0) AS paid_amount,
        COALESCE(SUM(sales.due_amount), 0) AS due_amount
    FROM sales
    LEFT JOIN customers
        ON sales.customer_id = customers.id
    WHERE DATE(sales.sale_date) = ?
    GROUP BY
        customers.id,
        customers.name,
        customers.phone
    ORDER BY total_amount DESC
    LIMIT 10
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $top_customers[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   TOP SUPPLIERS
========================= */

$top_suppliers = [];

$stmt = mysqli_prepare($conn, "
    SELECT
        suppliers.name,
        suppliers.phone,
        COUNT(purchases.id) AS purchase_count,
        COALESCE(SUM(purchases.total_amount), 0) AS total_amount,
        COALESCE(SUM(purchases.paid_amount), 0) AS paid_amount,
        COALESCE(SUM(purchases.due_amount), 0) AS due_amount
    FROM purchases
    LEFT JOIN suppliers
        ON purchases.supplier_id = suppliers.id
    WHERE purchases.purchase_date = ?
    GROUP BY
        suppliers.id,
        suppliers.name,
        suppliers.phone
    ORDER BY total_amount DESC
    LIMIT 10
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $top_suppliers[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   EXPENSE BY CATEGORY
========================= */

$expense_categories = [];

$stmt = mysqli_prepare($conn, "
    SELECT
        category,
        COUNT(*) AS expense_count,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM expenses
    WHERE expense_date = ?
    GROUP BY category
    ORDER BY total_amount DESC
");

mysqli_stmt_bind_param($stmt, "s", $report_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $expense_categories[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   STORE SETTINGS
========================= */

$store_name = "My Super Store";
$store_phone = "";
$store_address = "";
$currency = "AFN";
$logo = "";
$receipt_footer = "Thank you for shopping with us!";

$settings_result = mysqli_query(
    $conn,
    "SELECT * FROM settings ORDER BY id DESC LIMIT 1"
);

if ($settings_result && mysqli_num_rows($settings_result) > 0) {

    $settings = mysqli_fetch_assoc($settings_result);

    $store_name =
        $settings["store_name"] ?? $store_name;

    $store_phone =
        $settings["phone"] ?? "";

    $store_address =
        $settings["address"] ?? "";

    $currency =
        $settings["currency"] ?? "AFN";

    $logo =
        $settings["logo"] ?? "";

    $receipt_footer =
        $settings["receipt_footer"] ??
        $receipt_footer;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Daily Report - <?php echo htmlspecialchars($store_name); ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #1f2937;
}

.container {
    width: 95%;
    max-width: 1450px;
    margin: 30px auto;
}

/* =========================
   HEADER
========================= */

.header {
    background: #ffffff;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);

    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 20px;
}

.header h1 {
    margin: 0 0 8px;
    font-size: 28px;
}

.header p {
    margin: 0;
    color: #6b7280;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    border: none;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    display: inline-block;
}

.btn-dashboard {
    background: #111827;
    color: white;
}

.btn-print {
    background: #2563eb;
    color: white;
}

/* =========================
   DATE FILTER
========================= */

.filter-box {
    background: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    align-items: end;
    gap: 15px;
    flex-wrap: wrap;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group label {
    font-weight: bold;
    font-size: 14px;
}

.form-group input {
    width: 200px;
    padding: 11px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
}

.btn-view {
    background: #16a34a;
    color: white;
}

/* =========================
   STORE INFO
========================= */

.store-info {
    background: white;
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.store-info h2 {
    margin: 0 0 8px;
}

.store-info p {
    margin: 4px 0;
    color: #6b7280;
}

/* =========================
   SUMMARY CARDS
========================= */

.cards {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(210px, 1fr));

    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.card-title {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 10px;
}

.card-value {
    font-size: 25px;
    font-weight: bold;
}

.card-small {
    margin-top: 7px;
    color: #6b7280;
    font-size: 13px;
}

/* =========================
   COLORS
========================= */

.sales {
    border-left: 5px solid #2563eb;
}

.purchase {
    border-left: 5px solid #7c3aed;
}

.expense {
    border-left: 5px solid #dc2626;
}

.profit {
    border-left: 5px solid #16a34a;
}

.cogs {
    border-left: 5px solid #ea580c;
}

.due {
    border-left: 5px solid #f59e0b;
}

/* =========================
   SECTIONS
========================= */

.section {
    background: white;
    padding: 22px;
    border-radius: 15px;
    margin-bottom: 22px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.section-header h2 {
    margin: 0;
    font-size: 20px;
}

/* =========================
   TABLE
========================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th {
    background: #f3f4f6;
    text-align: left;
    padding: 13px;
    font-size: 13px;
}

table td {
    padding: 13px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

table tr:hover {
    background: #fafafa;
}

/* =========================
   STATUS
========================= */

.badge {
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.badge-green {
    background: #dcfce7;
    color: #166534;
}

.badge-yellow {
    background: #fef3c7;
    color: #92400e;
}

.badge-red {
    background: #fee2e2;
    color: #991b1b;
}

/* =========================
   TWO COLUMNS
========================= */

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

/* =========================
   EMPTY
========================= */

.empty {
    text-align: center;
    padding: 25px;
    color: #6b7280;
}

/* =========================
   FOOTER
========================= */

.footer {
    text-align: center;
    color: #6b7280;
    padding: 25px;
}

/* =========================
   PRINT
========================= */

@media print {

    body {
        background: white;
    }

    .container {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .header-actions,
    .filter-box {
        display: none;
    }

    .header,
    .section,
    .card,
    .store-info {
        box-shadow: none;
        border: 1px solid #ddd;
    }

    .cards {
        grid-template-columns:
            repeat(3, 1fr);
    }

    .section {
        break-inside: avoid;
    }

    table {
        font-size: 12px;
    }
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 850px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .two-columns {
        grid-template-columns: 1fr;
    }

    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }

    .form-group input {
        width: 100%;
    }
}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>
            <h1>Daily Business Report</h1>

            <p>
                Report Date:
                <strong>
                    <?php
                    echo date(
                        "d M Y",
                        strtotime($report_date)
                    );
                    ?>
                </strong>
            </p>
        </div>

        <div class="header-actions">

            <a
                href="../dashboard.php"
                class="btn btn-dashboard"
            >
                Dashboard
            </a>

            <button
                onclick="window.print()"
                class="btn btn-print"
            >
                Print Report
            </button>

        </div>

    </div>


    <!-- DATE FILTER -->

    <div class="filter-box">

        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-group">

                <label>
                    Select Report Date
                </label>

                <input
                    type="date"
                    name="date"
                    value="<?php echo htmlspecialchars($report_date); ?>"
                    required
                >

            </div>

            <button
                type="submit"
                class="btn btn-view"
            >
                View Report
            </button>

        </form>

    </div>


    <!-- STORE INFORMATION -->

    <div class="store-info">

        <h2>
            <?php
            echo htmlspecialchars($store_name);
            ?>
        </h2>

        <?php if (!empty($store_address)): ?>

            <p>
                <?php
                echo htmlspecialchars($store_address);
                ?>
            </p>

        <?php endif; ?>

        <?php if (!empty($store_phone)): ?>

            <p>
                Phone:
                <?php
                echo htmlspecialchars($store_phone);
                ?>
            </p>

        <?php endif; ?>

        <p>
            Daily Business Report —
            <?php
            echo date(
                "d M Y",
                strtotime($report_date)
            );
            ?>
        </p>

    </div>


    <!-- MAIN SUMMARY -->

    <div class="cards">

        <div class="card sales">

            <div class="card-title">
                Total Sales
            </div>

            <div class="card-value">
                <?php echo money($sales["total"]); ?>
                <?php echo htmlspecialchars($currency); ?>
            </div>

            <div class="card-small">
                <?php echo $sales["count"]; ?>
                invoices
            </div>

        </div>


        <div class="card purchase">

            <div class="card-title">
                Total Purchases
            </div>

            <div class="card-value">
                <?php echo money($purchases["total"]); ?>
                <?php echo htmlspecialchars($currency); ?>
            </div>

            <div class="card-small">
                <?php echo $purchases["count"]; ?>
                purchase invoices
            </div>

        </div>


        <div class="card expense">

            <div class="card-title">
                Total Expenses
            </div>

            <div class="card-value">
                <?php echo money($expenses["total"]); ?>
                <?php echo htmlspecialchars($currency); ?>
            </div>

            <div class="card-small">
                <?php echo $expenses["count"]; ?>
                expense records
            </div>

        </div>


        <div class="card cogs">

            <div class="card-title">
                Cost of Goods Sold
            </div>

            <div class="card-value">
                <?php echo money($cogs); ?>
                <?php echo htmlspecialchars($currency); ?>
            </div>

            <div class="card-small">
                Product cost for daily sales
            </div>

        </div>


        <div class="card profit">

            <div class="card-title">
                Gross Profit
            </div>

            <div class="card-value">
                <?php echo money($gross_profit); ?>
                <?php echo htmlspecialchars($currency); ?>
            </div>

            <div class="card-small">
                Sales - COGS
            </div>

        </div>


        <div class="card profit">

            <div class="card-title">
                Net Profit
            </div>

            <div class="card-value">
                <?php echo money($net_profit); ?>
                <?php echo htmlspecialchars($currency); ?>
            </div>

            <div class="card-small">
                Margin:
                <?php echo number_format($profit_margin, 2); ?>%
            </div>

        </div>

    </div>


    <!-- PAYMENT SUMMARY -->

    <div class="section">

        <div class="section-header">

            <h2>
                Sales Payment Summary
            </h2>

        </div>

        <div class="cards">

            <div class="card">

                <div class="card-title">
                    Sales Subtotal
                </div>

                <div class="card-value">
                    <?php echo money($sales["subtotal"]); ?>
                    <?php echo htmlspecialchars($currency); ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Discount
                </div>

                <div class="card-value">
                    <?php echo money($sales["discount"]); ?>
                    <?php echo htmlspecialchars($currency); ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Paid Amount
                </div>

                <div class="card-value">
                    <?php echo money($sales["paid"]); ?>
                    <?php echo htmlspecialchars($currency); ?>
                </div>

            </div>


            <div class="card due">

                <div class="card-title">
                    Customer Due
                </div>

                <div class="card-value">
                    <?php echo money($sales["due"]); ?>
                    <?php echo htmlspecialchars($currency); ?>
                </div>

            </div>

        </div>

    </div>


    <!-- PAYMENT METHODS -->

    <div class="section">

        <div class="section-header">

            <h2>
                Payment Methods
            </h2>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Payment Method</th>
                        <th>Transactions</th>
                        <th>Total Sales</th>
                        <th>Paid</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($payment_methods)): ?>

                    <?php foreach ($payment_methods as $payment): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $payment["payment_method"]
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo $payment["transaction_count"];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo money(
                                    $payment["total_amount"]
                                );
                                ?>
                                <?php
                                echo htmlspecialchars($currency);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo money(
                                    $payment["paid_amount"]
                                );
                                ?>
                                <?php
                                echo htmlspecialchars($currency);
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td
                            colspan="4"
                            class="empty"
                        >
                            No sales recorded for this date.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- PURCHASE & EXPENSE -->

    <div class="two-columns">

        <!-- PURCHASE -->

        <div class="section">

            <div class="section-header">

                <h2>
                    Purchase Summary
                </h2>

            </div>

            <table>

                <tr>
                    <th>Total Purchases</th>
                    <td>
                        <?php echo money($purchases["total"]); ?>
                        <?php echo htmlspecialchars($currency); ?>
                    </td>
                </tr>

                <tr>
                    <th>Paid to Suppliers</th>
                    <td>
                        <?php echo money($purchases["paid"]); ?>
                        <?php echo htmlspecialchars($currency); ?>
                    </td>
                </tr>

                <tr>
                    <th>Purchase Due</th>
                    <td>
                        <?php echo money($purchases["due"]); ?>
                        <?php echo htmlspecialchars($currency); ?>
                    </td>
                </tr>

                <tr>
                    <th>Purchase Invoices</th>
                    <td>
                        <?php echo $purchases["count"]; ?>
                    </td>
                </tr>

            </table>

        </div>


        <!-- EXPENSE -->

        <div class="section">

            <div class="section-header">

                <h2>
                    Expense Summary
                </h2>

            </div>

            <table>

                <tr>
                    <th>Total Expenses</th>
                    <td>
                        <?php echo money($expenses["total"]); ?>
                        <?php echo htmlspecialchars($currency); ?>
                    </td>
                </tr>

                <tr>
                    <th>Expense Records</th>
                    <td>
                        <?php echo $expenses["count"]; ?>
                    </td>
                </tr>

            </table>

        </div>

    </div>


    <!-- TOP PRODUCTS -->

    <div class="section">

        <div class="section-header">

            <h2>
                Top Selling Products
            </h2>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Barcode</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Sales</th>
                        <th>Cost</th>
                        <th>Profit</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($top_products)): ?>

                    <?php
                    $number = 1;
                    ?>

                    <?php foreach ($top_products as $product): ?>

                        <tr>

                            <td>
                                <?php echo $number++; ?>
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
                                echo number_format(
                                    $product["quantity"],
                                    0
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo money(
                                    $product["sales_amount"]
                                );
                                ?>
                                <?php
                                echo htmlspecialchars($currency);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo money(
                                    $product["cost_amount"]
                                );
                                ?>
                                <?php
                                echo htmlspecialchars($currency);
                                ?>
                            </td>

                            <td>
                                <?php
                                echo money(
                                    $product["profit"]
                                );
                                ?>
                                <?php
                                echo htmlspecialchars($currency);
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td
                            colspan="7"
                            class="empty"
                        >
                            No products sold on this date.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- CUSTOMERS AND SUPPLIERS -->

    <div class="two-columns">

        <!-- CUSTOMERS -->

        <div class="section">

            <div class="section-header">

                <h2>
                    Top Customers
                </h2>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Customer</th>
                            <th>Invoices</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($top_customers)): ?>

                        <?php foreach ($top_customers as $customer): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $customer["name"]
                                            ?: "Walk-in Customer"
                                        );
                                        ?>
                                    </strong>

                                    <?php
                                    if (!empty($customer["phone"])) {
                                        echo "<br>";
                                        echo "<small>";
                                        echo htmlspecialchars(
                                            $customer["phone"]
                                        );
                                        echo "</small>";
                                    }
                                    ?>

                                </td>

                                <td>
                                    <?php
                                    echo $customer["invoice_count"];
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo money(
                                        $customer["total_amount"]
                                    );
                                    ?>
                                    <?php
                                    echo htmlspecialchars($currency);
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="3"
                                class="empty"
                            >
                                No customers found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- SUPPLIERS -->

        <div class="section">

            <div class="section-header">

                <h2>
                    Top Suppliers
                </h2>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Supplier</th>
                            <th>Purchases</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($top_suppliers)): ?>

                        <?php foreach ($top_suppliers as $supplier): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $supplier["name"]
                                            ?: "Unknown Supplier"
                                        );
                                        ?>
                                    </strong>

                                    <?php
                                    if (!empty($supplier["phone"])) {
                                        echo "<br>";
                                        echo "<small>";
                                        echo htmlspecialchars(
                                            $supplier["phone"]
                                        );
                                        echo "</small>";
                                    }
                                    ?>

                                </td>

                                <td>
                                    <?php
                                    echo $supplier["purchase_count"];
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo money(
                                        $supplier["total_amount"]
                                    );
                                    ?>
                                    <?php
                                    echo htmlspecialchars($currency);
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="3"
                                class="empty"
                            >
                                No purchases found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- EXPENSE CATEGORIES -->

    <div class="section">

        <div class="section-header">

            <h2>
                Expenses by Category
            </h2>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Category</th>
                        <th>Records</th>
                        <th>Total Expense</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($expense_categories)): ?>

                    <?php foreach ($expense_categories as $category): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $category["category"]
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo $category["expense_count"];
                                ?>
                            </td>

                            <td>
                                <?php
                                echo money(
                                    $category["total_amount"]
                                );
                                ?>
                                <?php
                                echo htmlspecialchars($currency);
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="3"
                            class="empty"
                        >
                            No expenses recorded.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- PROFIT CALCULATION -->

    <div class="section">

        <div class="section-header">

            <h2>
                Daily Profit Calculation
            </h2>

        </div>

        <table>

            <tr>

                <th>
                    Total Sales
                </th>

                <td>
                    <?php echo money($sales["total"]); ?>
                    <?php echo htmlspecialchars($currency); ?>
                </td>

            </tr>

            <tr>

                <th>
                    Cost of Goods Sold
                </th>

                <td>
                    -
                    <?php echo money($cogs); ?>
                    <?php echo htmlspecialchars($currency); ?>
                </td>

            </tr>

            <tr>

                <th>
                    Gross Profit
                </th>

                <td>
                    <strong>
                        <?php echo money($gross_profit); ?>
                        <?php echo htmlspecialchars($currency); ?>
                    </strong>
                </td>

            </tr>

            <tr>

                <th>
                    Operating Expenses
                </th>

                <td>
                    -
                    <?php echo money($expenses["total"]); ?>
                    <?php echo htmlspecialchars($currency); ?>
                </td>

            </tr>

            <tr>

                <th>
                    Net Profit
                </th>

                <td>

                    <strong>

                        <?php
                        echo money($net_profit);
                        ?>

                        <?php
                        echo htmlspecialchars($currency);
                        ?>

                    </strong>

                </td>

            </tr>

            <tr>

                <th>
                    Profit Margin
                </th>

                <td>

                    <strong>
                        <?php
                        echo number_format(
                            $profit_margin,
                            2
                        );
                        ?>%
                    </strong>

                </td>

            </tr>

        </table>

    </div>


    <!-- FOOTER -->

    <div class="footer">

        <?php
        echo htmlspecialchars($receipt_footer);
        ?>

        <br><br>

        Generated by:
        <strong>
            <?php echo htmlspecialchars($username); ?>
        </strong>

    </div>

</div>

</body>

</html>