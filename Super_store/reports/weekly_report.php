<?php
session_start();

require_once "../db.php";

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

/* =========================
   DATE
========================= */

$selected_date = isset($_GET['date']) && $_GET['date'] !== ''
    ? $_GET['date']
    : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

/*
   Selected date is the last day of the report.
   Report period = selected date and previous 6 days.
*/

$end_date = $selected_date;
$start_date = date('Y-m-d', strtotime($end_date . ' -6 days'));

/* =========================
   SETTINGS
========================= */

$store_name = "My Super Store";
$currency = "AFN";
$store_phone = "";
$store_address = "";
$store_logo = "";

$settings_query = mysqli_query(
    $conn,
    "SELECT store_name, phone, address, currency, logo
     FROM settings
     ORDER BY id ASC
     LIMIT 1"
);

if ($settings_query && mysqli_num_rows($settings_query) > 0) {
    $settings = mysqli_fetch_assoc($settings_query);

    $store_name = $settings['store_name'] ?: "My Super Store";
    $currency = $settings['currency'] ?: "AFN";
    $store_phone = $settings['phone'] ?: "";
    $store_address = $settings['address'] ?: "";
    $store_logo = $settings['logo'] ?: "";
}

/* =========================
   HELPERS
========================= */

function money($amount, $currency)
{
    return number_format((float)$amount, 2) . " " . htmlspecialchars($currency);
}

/* =========================
   MAIN SUMMARY
========================= */

/* Sales */
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS invoices,
        COALESCE(SUM(subtotal),0) AS subtotal,
        COALESCE(SUM(discount),0) AS discount,
        COALESCE(SUM(total_amount),0) AS sales,
        COALESCE(SUM(paid_amount),0) AS paid,
        COALESCE(SUM(due_amount),0) AS due
     FROM sales
     WHERE DATE(sale_date) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$sales_summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$invoice_count = (int)$sales_summary['invoices'];
$subtotal = (float)$sales_summary['subtotal'];
$total_discount = (float)$sales_summary['discount'];
$total_sales = (float)$sales_summary['sales'];
$total_paid = (float)$sales_summary['paid'];
$total_due = (float)$sales_summary['due'];


/* Purchases */
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS invoices,
        COALESCE(SUM(total_amount),0) AS purchases,
        COALESCE(SUM(paid_amount),0) AS paid,
        COALESCE(SUM(due_amount),0) AS due
     FROM purchases
     WHERE DATE(purchase_date) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$purchase_summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$purchase_count = (int)$purchase_summary['invoices'];
$total_purchases = (float)$purchase_summary['purchases'];
$total_purchase_paid = (float)$purchase_summary['paid'];
$total_purchase_due = (float)$purchase_summary['due'];


/* Expenses */
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS records,
        COALESCE(SUM(amount),0) AS expenses
     FROM expenses
     WHERE expense_date BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$expense_summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$expense_count = (int)$expense_summary['records'];
$total_expenses = (float)$expense_summary['expenses'];


/* =========================
   COGS
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(si.quantity * si.purchase_price),0) AS cogs
     FROM sale_items si
     INNER JOIN sales s ON s.id = si.sale_id
     WHERE DATE(s.sale_date) BETWEEN ? AND ?"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$cogs_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$total_cogs = (float)$cogs_result['cogs'];


/* =========================
   PROFIT
========================= */

$gross_profit = $total_sales - $total_cogs;
$net_profit = $gross_profit - $total_expenses;

$profit_margin = $total_sales > 0
    ? ($net_profit / $total_sales) * 100
    : 0;


/* =========================
   PAYMENT METHODS
========================= */

$payment_methods = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        payment_method,
        COUNT(*) AS invoices,
        COALESCE(SUM(total_amount),0) AS amount
     FROM sales
     WHERE DATE(sale_date) BETWEEN ? AND ?
     GROUP BY payment_method
     ORDER BY amount DESC"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $payment_methods[] = $row;
}


/* =========================
   DAILY SALES & PROFIT
========================= */

$daily_data = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        DATE(s.sale_date) AS report_date,
        COUNT(DISTINCT s.id) AS invoices,
        COALESCE(SUM(s.total_amount),0) AS sales,
        COALESCE(SUM(
            si.quantity * si.purchase_price
        ),0) AS cogs
     FROM sales s
     LEFT JOIN sale_items si ON si.sale_id = s.id
     WHERE DATE(s.sale_date) BETWEEN ? AND ?
     GROUP BY DATE(s.sale_date)
     ORDER BY report_date ASC"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $day_sales = (float)$row['sales'];
    $day_cogs = (float)$row['cogs'];
    $day_profit = $day_sales - $day_cogs;

    $daily_data[] = [
        'date' => $row['report_date'],
        'invoices' => (int)$row['invoices'],
        'sales' => $day_sales,
        'cogs' => $day_cogs,
        'profit' => $day_profit
    ];
}


/* =========================
   TOP SELLING PRODUCTS
========================= */

$top_products = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.name,
        p.barcode,
        SUM(si.quantity) AS quantity,
        SUM(si.total) AS sales,
        SUM(si.quantity * si.purchase_price) AS cogs,
        SUM(
            si.total -
            (si.quantity * si.purchase_price)
        ) AS profit
     FROM sale_items si
     INNER JOIN sales s ON s.id = si.sale_id
     INNER JOIN products p ON p.id = si.product_id
     WHERE DATE(s.sale_date) BETWEEN ? AND ?
     GROUP BY p.id, p.name, p.barcode
     ORDER BY quantity DESC
     LIMIT 10"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $top_products[] = $row;
}


/* =========================
   TOP CUSTOMERS
========================= */

$top_customers = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COALESCE(c.name, 'Walk-in Customer') AS customer_name,
        COUNT(s.id) AS invoices,
        COALESCE(SUM(s.total_amount),0) AS total_sales,
        COALESCE(SUM(s.paid_amount),0) AS paid,
        COALESCE(SUM(s.due_amount),0) AS due
     FROM sales s
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE DATE(s.sale_date) BETWEEN ? AND ?
     GROUP BY s.customer_id, c.name
     ORDER BY total_sales DESC
     LIMIT 10"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $top_customers[] = $row;
}


/* =========================
   EXPENSE BY CATEGORY
========================= */

$expense_categories = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        COALESCE(category,'Other') AS category,
        COUNT(*) AS records,
        COALESCE(SUM(amount),0) AS amount
     FROM expenses
     WHERE expense_date BETWEEN ? AND ?
     GROUP BY category
     ORDER BY amount DESC"
);

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $expense_categories[] = $row;
}


/* =========================
   BEST DAY
========================= */

$best_day = null;

foreach ($daily_data as $day) {

    if ($best_day === null || $day['sales'] > $best_day['sales']) {
        $best_day = $day;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Weekly Report - <?php echo htmlspecialchars($store_name); ?></title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #222;
}

.container {
    width: 95%;
    max-width: 1450px;
    margin: 25px auto;
}

/* HEADER */

.header {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.store-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.store-logo {
    width: 70px;
    height: 70px;
    object-fit: contain;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.store-info h1 {
    margin: 0 0 7px;
    font-size: 26px;
}

.store-info p {
    margin: 3px 0;
    color: #666;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    background: #1f6feb;
    color: #fff;
}

.btn:hover {
    opacity: .9;
}

.btn-dark {
    background: #343a40;
}

/* FILTER */

.filter-box {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.filter-form {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-weight: 600;
    font-size: 14px;
}

.form-group input {
    padding: 10px 12px;
    border: 1px solid #ccc;
    border-radius: 7px;
    min-width: 200px;
}

/* PERIOD */

.period {
    background: #fff;
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    border-left: 5px solid #1f6feb;
}

.period h2 {
    margin: 0 0 5px;
}

.period p {
    margin: 0;
    color: #666;
}

/* CARDS */

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.card-title {
    color: #666;
    font-size: 14px;
    margin-bottom: 8px;
}

.card-value {
    font-size: 25px;
    font-weight: bold;
}

.card-small {
    margin-top: 7px;
    font-size: 13px;
    color: #777;
}

/* REPORT GRID */

.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.section {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    margin-bottom: 20px;
}

.section h2 {
    margin: 0 0 18px;
    font-size: 20px;
}

/* TABLE */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th,
td {
    padding: 11px 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
    font-size: 14px;
}

th {
    background: #f7f8fa;
    font-weight: 700;
}

.text-right {
    text-align: right;
}

.total-row td {
    font-weight: bold;
    background: #f7f8fa;
}

/* BADGE */

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #eef2f7;
    font-size: 12px;
    font-weight: 600;
}

/* PROFIT */

.profit-positive {
    font-weight: bold;
}

.profit-negative {
    font-weight: bold;
}

/* EMPTY */

.empty {
    text-align: center;
    padding: 30px;
    color: #777;
}

/* FOOTER */

.footer {
    text-align: center;
    color: #777;
    padding: 20px;
}

/* PRINT */

@media print {

    body {
        background: #fff;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .header,
    .filter-box,
    .period,
    .section,
    .card {
        box-shadow: none;
    }

    .header-actions,
    .filter-box {
        display: none !important;
    }

    .cards {
        grid-template-columns: repeat(4, 1fr);
    }

    .grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .section {
        break-inside: avoid;
    }

    @page {
        size: A4 landscape;
        margin: 10mm;
    }
}

/* RESPONSIVE */

@media (max-width: 1000px) {

    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 600px) {

    .container {
        width: 94%;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .store-info {
        align-items: flex-start;
    }

    .store-logo {
        width: 55px;
        height: 55px;
    }

    .store-info h1 {
        font-size: 21px;
    }

    .form-group input {
        min-width: 100%;
    }

    .filter-form {
        display: block;
    }

    .form-group {
        margin-bottom: 10px;
    }

    .filter-form .btn {
        width: 100%;
    }
}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div class="store-info">

            <?php if (!empty($store_logo) && file_exists("../" . $store_logo)): ?>

                <img
                    src="../<?php echo htmlspecialchars($store_logo); ?>"
                    class="store-logo"
                    alt="Store Logo"
                >

            <?php endif; ?>

            <div>

                <h1>
                    <?php echo htmlspecialchars($store_name); ?>
                </h1>

                <?php if ($store_phone): ?>
                    <p>
                        Phone:
                        <?php echo htmlspecialchars($store_phone); ?>
                    </p>
                <?php endif; ?>

                <?php if ($store_address): ?>
                    <p>
                        <?php echo htmlspecialchars($store_address); ?>
                    </p>
                <?php endif; ?>

            </div>

        </div>

        <div class="header-actions">

            <a href="index.php" class="btn btn-dark">
                Reports
            </a>

            <button onclick="window.print()" class="btn">
                Print Report
            </button>

        </div>

    </div>


    <!-- DATE FILTER -->

    <div class="filter-box">

        <form method="GET" class="filter-form">

            <div class="form-group">

                <label>
                    Report End Date
                </label>

                <input
                    type="date"
                    name="date"
                    value="<?php echo htmlspecialchars($end_date); ?>"
                    required
                >

            </div>

            <button type="submit" class="btn">
                Generate Report
            </button>

        </form>

    </div>


    <!-- PERIOD -->

    <div class="period">

        <h2>
            Weekly Report
        </h2>

        <p>
            Period:
            <strong>
                <?php echo date("d M Y", strtotime($start_date)); ?>
            </strong>

            to

            <strong>
                <?php echo date("d M Y", strtotime($end_date)); ?>
            </strong>

            — 7 Days
        </p>

    </div>


    <!-- MAIN CARDS -->

    <div class="cards">

        <div class="card">

            <div class="card-title">
                Weekly Sales
            </div>

            <div class="card-value">
                <?php echo money($total_sales, $currency); ?>
            </div>

            <div class="card-small">
                <?php echo $invoice_count; ?> invoices
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Weekly Purchases
            </div>

            <div class="card-value">
                <?php echo money($total_purchases, $currency); ?>
            </div>

            <div class="card-small">
                <?php echo $purchase_count; ?> purchase invoices
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Weekly Expenses
            </div>

            <div class="card-value">
                <?php echo money($total_expenses, $currency); ?>
            </div>

            <div class="card-small">
                <?php echo $expense_count; ?> expense records
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Net Profit
            </div>

            <div class="card-value">
                <?php echo money($net_profit, $currency); ?>
            </div>

            <div class="card-small">
                Margin:
                <?php echo number_format($profit_margin, 2); ?>%
            </div>

        </div>

    </div>


    <!-- FINANCIAL SUMMARY -->

    <div class="grid">

        <div class="section">

            <h2>
                Financial Summary
            </h2>

            <div class="table-wrapper">

                <table>

                    <tr>
                        <td>Gross Sales</td>
                        <td class="text-right">
                            <?php echo money($subtotal, $currency); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Total Discount</td>
                        <td class="text-right">
                            <?php echo money($total_discount, $currency); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Net Sales</td>
                        <td class="text-right">
                            <?php echo money($total_sales, $currency); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Cost of Goods Sold (COGS)</td>
                        <td class="text-right">
                            <?php echo money($total_cogs, $currency); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Gross Profit</td>
                        <td class="text-right">
                            <?php echo money($gross_profit, $currency); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Total Expenses</td>
                        <td class="text-right">
                            <?php echo money($total_expenses, $currency); ?>
                        </td>
                    </tr>

                    <tr class="total-row">

                        <td>
                            Net Profit
                        </td>

                        <td class="text-right">
                            <?php echo money($net_profit, $currency); ?>
                        </td>

                    </tr>

                </table>

            </div>

        </div>


        <!-- PAYMENT METHODS -->

        <div class="section">

            <h2>
                Payment Methods
            </h2>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Method</th>
                            <th>Invoices</th>
                            <th class="text-right">
                                Amount
                            </th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($payment_methods) > 0): ?>

                        <?php foreach ($payment_methods as $payment): ?>

                            <tr>

                                <td>
                                    <span class="badge">
                                        <?php echo htmlspecialchars($payment['payment_method']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php echo (int)$payment['invoices']; ?>
                                </td>

                                <td class="text-right">
                                    <?php
                                    echo money(
                                        $payment['amount'],
                                        $currency
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty">
                                No payment records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- DAILY PERFORMANCE -->

    <div class="section">

        <h2>
            Daily Sales & Profit
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Date</th>

                        <th>Invoices</th>

                        <th class="text-right">
                            Sales
                        </th>

                        <th class="text-right">
                            COGS
                        </th>

                        <th class="text-right">
                            Profit
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($daily_data) > 0): ?>

                    <?php foreach ($daily_data as $day): ?>

                        <tr>

                            <td>
                                <?php
                                echo date(
                                    "D, d M Y",
                                    strtotime($day['date'])
                                );
                                ?>
                            </td>

                            <td>
                                <?php echo $day['invoices']; ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $day['sales'],
                                    $currency
                                );
                                ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $day['cogs'],
                                    $currency
                                );
                                ?>
                            </td>

                            <td class="text-right profit-positive">
                                <?php
                                echo money(
                                    $day['profit'],
                                    $currency
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty">
                            No sales found for this week.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- BEST DAY -->

    <?php if ($best_day): ?>

    <div class="section">

        <h2>
            Best Sales Day
        </h2>

        <table>

            <tr>

                <td>
                    Best Day
                </td>

                <td class="text-right">
                    <strong>
                        <?php
                        echo date(
                            "D, d M Y",
                            strtotime($best_day['date'])
                        );
                        ?>
                    </strong>
                </td>

            </tr>

            <tr>

                <td>
                    Sales
                </td>

                <td class="text-right">
                    <?php
                    echo money(
                        $best_day['sales'],
                        $currency
                    );
                    ?>
                </td>

            </tr>

            <tr>

                <td>
                    Profit
                </td>

                <td class="text-right">
                    <?php
                    echo money(
                        $best_day['profit'],
                        $currency
                    );
                    ?>
                </td>

            </tr>

        </table>

    </div>

    <?php endif; ?>


    <!-- TOP PRODUCTS -->

    <div class="section">

        <h2>
            Top Selling Products
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Product</th>

                        <th>Barcode</th>

                        <th>Quantity</th>

                        <th class="text-right">
                            Sales
                        </th>

                        <th class="text-right">
                            Profit
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($top_products) > 0): ?>

                    <?php $number = 1; ?>

                    <?php foreach ($top_products as $product): ?>

                        <tr>

                            <td>
                                <?php echo $number++; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $product['name']
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $product['barcode']
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    $product['quantity'],
                                    2
                                );
                                ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $product['sales'],
                                    $currency
                                );
                                ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $product['profit'],
                                    $currency
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No product sales found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- TOP CUSTOMERS -->

    <div class="section">

        <h2>
            Top Customers
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Customer</th>

                        <th>Invoices</th>

                        <th class="text-right">
                            Sales
                        </th>

                        <th class="text-right">
                            Paid
                        </th>

                        <th class="text-right">
                            Due
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($top_customers) > 0): ?>

                    <?php $number = 1; ?>

                    <?php foreach ($top_customers as $customer): ?>

                        <tr>

                            <td>
                                <?php echo $number++; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $customer['customer_name']
                                    );
                                    ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo $customer['invoices']; ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $customer['total_sales'],
                                    $currency
                                );
                                ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $customer['paid'],
                                    $currency
                                );
                                ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $customer['due'],
                                    $currency
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No customer sales found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- EXPENSE CATEGORIES -->

    <div class="section">

        <h2>
            Expenses by Category
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>Category</th>

                        <th>Records</th>

                        <th class="text-right">
                            Amount
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($expense_categories) > 0): ?>

                    <?php foreach ($expense_categories as $expense): ?>

                        <tr>

                            <td>
                                <span class="badge">
                                    <?php
                                    echo htmlspecialchars(
                                        $expense['category']
                                    );
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php echo $expense['records']; ?>
                            </td>

                            <td class="text-right">
                                <?php
                                echo money(
                                    $expense['amount'],
                                    $currency
                                );
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="3" class="empty">
                            No expenses found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ACCOUNT BALANCES -->

    <div class="grid">

        <div class="section">

            <h2>
                Sales Payment Summary
            </h2>

            <table>

                <tr>
                    <td>Total Paid</td>

                    <td class="text-right">
                        <?php
                        echo money(
                            $total_paid,
                            $currency
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Total Customer Due</td>

                    <td class="text-right">
                        <?php
                        echo money(
                            $total_due,
                            $currency
                        );
                        ?>
                    </td>
                </tr>

            </table>

        </div>


        <div class="section">

            <h2>
                Purchase Payment Summary
            </h2>

            <table>

                <tr>
                    <td>Total Paid to Suppliers</td>

                    <td class="text-right">
                        <?php
                        echo money(
                            $total_purchase_paid,
                            $currency
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Total Supplier Due</td>

                    <td class="text-right">
                        <?php
                        echo money(
                            $total_purchase_due,
                            $currency
                        );
                        ?>
                    </td>
                </tr>

            </table>

        </div>

    </div>


    <!-- FOOTER -->

    <div class="footer">

        Weekly Report generated on
        <?php echo date("d M Y H:i"); ?>

        <br>

        <?php
        echo htmlspecialchars(
            "Store: " . $store_name
        );
        ?>

    </div>

</div>

</body>
</html>