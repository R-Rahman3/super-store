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
   DATE FILTER
========================= */

$from_date = $_GET["from"] ?? date("Y-m-01");
$to_date   = $_GET["to"] ?? date("Y-m-d");

$error = "";

/* Validate dates */
$from_check = DateTime::createFromFormat("Y-m-d", $from_date);
$to_check   = DateTime::createFromFormat("Y-m-d", $to_date);

if (
    !$from_check ||
    !$to_check ||
    $from_check->format("Y-m-d") !== $from_date ||
    $to_check->format("Y-m-d") !== $to_date
) {
    $from_date = date("Y-m-01");
    $to_date   = date("Y-m-d");
}

if ($from_date > $to_date) {
    $error = "From date cannot be greater than To date.";

    $temp = $from_date;
    $from_date = $to_date;
    $to_date = $temp;
}

/* =========================
   SALES
========================= */

/*
   Total Sales:
   sales.total_amount already includes discount.
*/

$sql_sales = "
    SELECT
        COUNT(*) AS total_invoices,
        COALESCE(SUM(subtotal), 0) AS subtotal,
        COALESCE(SUM(discount), 0) AS discount,
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
";

$stmt_sales = mysqli_prepare($conn, $sql_sales);

if (!$stmt_sales) {
    die("Sales Query Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt_sales,
    "ss",
    $from_date,
    $to_date
);

mysqli_stmt_execute($stmt_sales);

$result_sales = mysqli_stmt_get_result($stmt_sales);

$sales_data = mysqli_fetch_assoc($result_sales);

mysqli_stmt_close($stmt_sales);

$total_invoices = (int)($sales_data["total_invoices"] ?? 0);
$subtotal       = (float)($sales_data["subtotal"] ?? 0);
$total_discount = (float)($sales_data["discount"] ?? 0);
$total_sales    = (float)($sales_data["total_sales"] ?? 0);
$total_paid     = (float)($sales_data["total_paid"] ?? 0);
$total_due      = (float)($sales_data["total_due"] ?? 0);


/* =========================
   COST OF GOODS SOLD
========================= */

/*
   COGS =
   Quantity Sold × Purchase Price
*/

$sql_cogs = "
    SELECT
        COALESCE(SUM(si.quantity * si.purchase_price), 0) AS cogs,
        COALESCE(SUM(si.quantity), 0) AS total_items
    FROM sale_items si
    INNER JOIN sales s
        ON si.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
";

$stmt_cogs = mysqli_prepare($conn, $sql_cogs);

if (!$stmt_cogs) {
    die("COGS Query Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt_cogs,
    "ss",
    $from_date,
    $to_date
);

mysqli_stmt_execute($stmt_cogs);

$result_cogs = mysqli_stmt_get_result($stmt_cogs);

$cogs_data = mysqli_fetch_assoc($result_cogs);

mysqli_stmt_close($stmt_cogs);

$cogs = (float)($cogs_data["cogs"] ?? 0);
$total_items = (float)($cogs_data["total_items"] ?? 0);


/* =========================
   GROSS PROFIT
========================= */

/*
   Gross Profit =
   Total Sales - COGS
*/

$gross_profit = $total_sales - $cogs;


/* =========================
   EXPENSES
========================= */

$sql_expenses = "
    SELECT
        COUNT(*) AS expense_records,
        COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";

$stmt_expenses = mysqli_prepare($conn, $sql_expenses);

if (!$stmt_expenses) {
    die("Expense Query Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt_expenses,
    "ss",
    $from_date,
    $to_date
);

mysqli_stmt_execute($stmt_expenses);

$result_expenses = mysqli_stmt_get_result($stmt_expenses);

$expense_data = mysqli_fetch_assoc($result_expenses);

mysqli_stmt_close($stmt_expenses);

$expense_records = (int)($expense_data["expense_records"] ?? 0);
$total_expenses  = (float)($expense_data["total_expenses"] ?? 0);


/* =========================
   NET PROFIT
========================= */

/*
   Net Profit =
   Gross Profit - Expenses
*/

$net_profit = $gross_profit - $total_expenses;


/* =========================
   PROFIT MARGIN
========================= */

$profit_margin = 0;

if ($total_sales > 0) {
    $profit_margin = ($net_profit / $total_sales) * 100;
}


/* =========================
   PAYMENT METHOD SUMMARY
========================= */

$payment_summary = [
    "Cash" => 0,
    "Card" => 0,
    "Credit" => 0
];

$sql_payment = "
    SELECT
        payment_method,
        COUNT(*) AS invoice_count,
        COALESCE(SUM(total_amount), 0) AS amount
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY amount DESC
";

$stmt_payment = mysqli_prepare($conn, $sql_payment);

if ($stmt_payment) {

    mysqli_stmt_bind_param(
        $stmt_payment,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt_payment);

    $result_payment = mysqli_stmt_get_result($stmt_payment);

    while ($row = mysqli_fetch_assoc($result_payment)) {

        $method = $row["payment_method"];

        if (isset($payment_summary[$method])) {
            $payment_summary[$method] = (float)$row["amount"];
        }
    }

    mysqli_stmt_close($stmt_payment);
}


/* =========================
   DAILY PROFIT
========================= */

$sql_daily = "
    SELECT
        DATE(s.sale_date) AS sale_day,
        COUNT(DISTINCT s.id) AS invoices,
        COALESCE(SUM(s.total_amount), 0) AS sales,
        COALESCE(SUM(si.quantity * si.purchase_price), 0) AS cogs
    FROM sales s
    LEFT JOIN sale_items si
        ON s.id = si.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY DATE(s.sale_date)
    ORDER BY sale_day DESC
";

$stmt_daily = mysqli_prepare($conn, $sql_daily);

$daily_data = [];

if ($stmt_daily) {

    mysqli_stmt_bind_param(
        $stmt_daily,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt_daily);

    $result_daily = mysqli_stmt_get_result($stmt_daily);

    while ($row = mysqli_fetch_assoc($result_daily)) {

        $day_sales = (float)$row["sales"];
        $day_cogs  = (float)$row["cogs"];

        $daily_data[] = [
            "day" => $row["sale_day"],
            "invoices" => (int)$row["invoices"],
            "sales" => $day_sales,
            "cogs" => $day_cogs,
            "profit" => $day_sales - $day_cogs
        ];
    }

    mysqli_stmt_close($stmt_daily);
}


/* =========================
   TOP PROFIT PRODUCTS
========================= */

$sql_products = "
    SELECT
        p.name,
        p.barcode,
        COALESCE(SUM(si.quantity), 0) AS quantity_sold,
        COALESCE(
            SUM(
                si.quantity *
                (si.selling_price - si.purchase_price)
            ),
            0
        ) AS product_profit
    FROM sale_items si
    INNER JOIN sales s
        ON si.sale_id = s.id
    INNER JOIN products p
        ON si.product_id = p.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY si.product_id, p.name, p.barcode
    ORDER BY product_profit DESC
    LIMIT 10
";

$stmt_products = mysqli_prepare($conn, $sql_products);

$products_data = [];

if ($stmt_products) {

    mysqli_stmt_bind_param(
        $stmt_products,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt_products);

    $result_products = mysqli_stmt_get_result($stmt_products);

    while ($row = mysqli_fetch_assoc($result_products)) {
        $products_data[] = $row;
    }

    mysqli_stmt_close($stmt_products);
}


/* =========================
   EXPENSE BY CATEGORY
========================= */

$sql_expense_category = "
    SELECT
        category,
        COUNT(*) AS records,
        COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total DESC
";

$stmt_expense_category = mysqli_prepare(
    $conn,
    $sql_expense_category
);

$expense_categories = [];

if ($stmt_expense_category) {

    mysqli_stmt_bind_param(
        $stmt_expense_category,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt_expense_category);

    $result_expense_category =
        mysqli_stmt_get_result($stmt_expense_category);

    while ($row = mysqli_fetch_assoc($result_expense_category)) {
        $expense_categories[] = $row;
    }

    mysqli_stmt_close($stmt_expense_category);
}


/* =========================
   HELPER
========================= */

function money($amount)
{
    return number_format((float)$amount, 2);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Profit Report - Super Store</title>

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
    max-width: 1450px;
    margin: 25px auto;
    padding: 0 20px;
}

/* =========================
   TOP BAR
========================= */

.top-bar {
    background: white;
    padding: 18px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
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

.field input {
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-size: 14px;
}

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

.btn-secondary {
    background: #475569;
    color: white;
}

.btn-success {
    background: #059669;
    color: white;
}

.btn:hover {
    opacity: .9;
}

/* =========================
   SUMMARY CARDS
========================= */

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
}

.card-title {
    color: #64748b;
    font-size: 13px;
    margin-bottom: 9px;
}

.card-value {
    font-size: 25px;
    font-weight: bold;
}

.card small {
    display: block;
    margin-top: 7px;
    color: #94a3b8;
}

.sales {
    border-left: 5px solid #2563eb;
}

.cogs {
    border-left: 5px solid #f59e0b;
}

.gross {
    border-left: 5px solid #10b981;
}

.expense {
    border-left: 5px solid #ef4444;
}

.net {
    border-left: 5px solid #7c3aed;
}

.margin {
    border-left: 5px solid #0891b2;
}

.paid {
    border-left: 5px solid #16a34a;
}

.due {
    border-left: 5px solid #dc2626;
}

/* =========================
   PROFIT OVERVIEW
========================= */

.profit-box {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
}

.profit-box h2 {
    margin-top: 0;
    font-size: 20px;
}

.profit-row {
    display: flex;
    justify-content: space-between;
    padding: 13px 0;
    border-bottom: 1px solid #e5e7eb;
}

.profit-row:last-child {
    border-bottom: none;
}

.profit-label {
    color: #475569;
}

.profit-value {
    font-weight: bold;
}

.net-profit-row {
    margin-top: 10px;
    padding: 18px 0;
    font-size: 20px;
}

/* =========================
   TWO COLUMNS
========================= */

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
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
    min-width: 600px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
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

.profit-positive {
    color: #059669;
    font-weight: bold;
}

.profit-negative {
    color: #dc2626;
    font-weight: bold;
}

/* =========================
   ERROR
========================= */

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 15px;
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
        border-bottom: 2px solid #111827;
    }

    .header-left p,
    .header-right {
        color: #333;
    }

    .top-bar,
    .btn,
    .no-print {
        display: none !important;
    }

    .container {
        max-width: none;
        margin: 0;
        padding: 0;
    }

    .cards {
        grid-template-columns: repeat(4, 1fr);
    }

    .card,
    .section,
    .profit-box {
        box-shadow: none;
        border: 1px solid #ddd;
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
        grid-template-columns: repeat(2, 1fr);
    }

    .two-columns {
        grid-template-columns: 1fr;
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

        <h1>Profit & Loss Report</h1>

        <p>
            Super Store Management System
        </p>

    </div>

    <div class="header-right">

        <strong>
            <?php echo htmlspecialchars($username); ?>
        </strong>

        <?php echo htmlspecialchars($role); ?>

    </div>

</div>


<div class="container">


<!-- =========================
     PRINT HEADER
========================= -->

<div class="print-only">

    <h2>Profit & Loss Report</h2>

    <p>
        Period:
        <?php echo htmlspecialchars($from_date); ?>
        to
        <?php echo htmlspecialchars($to_date); ?>
    </p>

</div>


<!-- =========================
     FILTER
========================= -->

<div class="top-bar no-print">

    <form method="GET" class="filter-form">

        <div class="field">

            <label>From Date</label>

            <input
                type="date"
                name="from"
                value="<?php echo htmlspecialchars($from_date); ?>"
            >

        </div>


        <div class="field">

            <label>To Date</label>

            <input
                type="date"
                name="to"
                value="<?php echo htmlspecialchars($to_date); ?>"
            >

        </div>


        <button
            type="submit"
            class="btn btn-primary"
        >
            Generate Report
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


<?php if ($error): ?>

<div class="error">
    <?php echo htmlspecialchars($error); ?>
</div>

<?php endif; ?>


<!-- =========================
     PERIOD
========================= -->

<div style="margin-bottom:15px; color:#64748b;">

    Report Period:
    <strong>
        <?php echo htmlspecialchars($from_date); ?>
    </strong>

    to

    <strong>
        <?php echo htmlspecialchars($to_date); ?>
    </strong>

</div>


<!-- =========================
     SUMMARY CARDS
========================= -->

<div class="cards">


    <div class="card sales">

        <div class="card-title">
            TOTAL SALES
        </div>

        <div class="card-value">
            AFN <?php echo money($total_sales); ?>
        </div>

        <small>
            <?php echo $total_invoices; ?> invoices
        </small>

    </div>


    <div class="card cogs">

        <div class="card-title">
            COST OF GOODS SOLD
        </div>

        <div class="card-value">
            AFN <?php echo money($cogs); ?>
        </div>

        <small>
            <?php echo money($total_items); ?> items sold
        </small>

    </div>


    <div class="card gross">

        <div class="card-title">
            GROSS PROFIT
        </div>

        <div class="card-value profit-positive">
            AFN <?php echo money($gross_profit); ?>
        </div>

        <small>
            Before expenses
        </small>

    </div>


    <div class="card expense">

        <div class="card-title">
            TOTAL EXPENSES
        </div>

        <div class="card-value">
            AFN <?php echo money($total_expenses); ?>
        </div>

        <small>
            <?php echo $expense_records; ?> expense records
        </small>

    </div>


    <div class="card net">

        <div class="card-title">
            NET PROFIT
        </div>

        <div class="card-value
            <?php echo $net_profit >= 0
                ? 'profit-positive'
                : 'profit-negative'; ?>">

            AFN <?php echo money($net_profit); ?>

        </div>

        <small>
            After expenses
        </small>

    </div>


    <div class="card margin">

        <div class="card-title">
            PROFIT MARGIN
        </div>

        <div class="card-value">

            <?php echo number_format($profit_margin, 2); ?>%

        </div>

        <small>
            Net profit / sales
        </small>

    </div>


    <div class="card paid">

        <div class="card-title">
            TOTAL PAID
        </div>

        <div class="card-value">

            AFN <?php echo money($total_paid); ?>

        </div>

        <small>
            Payments received
        </small>

    </div>


    <div class="card due">

        <div class="card-title">
            CUSTOMER DUE
        </div>

        <div class="card-value">

            AFN <?php echo money($total_due); ?>

        </div>

        <small>
            Outstanding sales
        </small>

    </div>

</div>


<!-- =========================
     PROFIT CALCULATION
========================= -->

<div class="profit-box">

    <h2>
        Profit Calculation
    </h2>


    <div class="profit-row">

        <span class="profit-label">
            Gross Sales / Subtotal
        </span>

        <span class="profit-value">
            AFN <?php echo money($subtotal); ?>
        </span>

    </div>


    <div class="profit-row">

        <span class="profit-label">
            Less: Discount
        </span>

        <span class="profit-value">
            AFN <?php echo money($total_discount); ?>
        </span>

    </div>


    <div class="profit-row">

        <span class="profit-label">
            Net Sales
        </span>

        <span class="profit-value">
            AFN <?php echo money($total_sales); ?>
        </span>

    </div>


    <div class="profit-row">

        <span class="profit-label">
            Less: Cost of Goods Sold
        </span>

        <span class="profit-value">
            AFN <?php echo money($cogs); ?>
        </span>

    </div>


    <div class="profit-row">

        <span class="profit-label">
            Gross Profit
        </span>

        <span class="profit-value profit-positive">
            AFN <?php echo money($gross_profit); ?>
        </span>

    </div>


    <div class="profit-row">

        <span class="profit-label">
            Less: Operating Expenses
        </span>

        <span class="profit-value">
            AFN <?php echo money($total_expenses); ?>
        </span>

    </div>


    <div class="profit-row net-profit-row">

        <span>
            <strong>NET PROFIT / LOSS</strong>
        </span>

        <span
            class="<?php echo $net_profit >= 0
                ? 'profit-positive'
                : 'profit-negative'; ?>"
        >

            AFN <?php echo money($net_profit); ?>

        </span>

    </div>

</div>


<!-- =========================
     PAYMENT + EXPENSE
========================= -->

<div class="two-columns">


    <!-- PAYMENT SUMMARY -->

    <div class="section">

        <h2>
            Payment Method Summary
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Payment Method
                    </th>

                    <th class="text-right">
                        Amount
                    </th>

                </tr>

            </thead>

            <tbody>

                <tr>

                    <td>
                        Cash
                    </td>

                    <td class="text-right">
                        AFN <?php echo money($payment_summary["Cash"]); ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Card
                    </td>

                    <td class="text-right">
                        AFN <?php echo money($payment_summary["Card"]); ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Credit
                    </td>

                    <td class="text-right">
                        AFN <?php echo money($payment_summary["Credit"]); ?>
                    </td>

                </tr>

            </tbody>

        </table>

    </div>


    <!-- EXPENSE CATEGORY -->

    <div class="section">

        <h2>
            Expenses by Category
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Category
                    </th>

                    <th>
                        Records
                    </th>

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
                        <?php
                        echo htmlspecialchars(
                            $expense["category"]
                        );
                        ?>
                    </td>

                    <td>
                        <?php echo $expense["records"]; ?>
                    </td>

                    <td class="text-right">
                        AFN
                        <?php echo money($expense["total"]); ?>
                    </td>

                </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="3">
                        No expenses found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================
     TOP PROFIT PRODUCTS
========================= -->

<div class="section" style="margin-bottom:20px;">

    <h2>
        Top Profitable Products
    </h2>

    <table>

        <thead>

            <tr>

                <th>#</th>

                <th>
                    Product
                </th>

                <th>
                    Barcode
                </th>

                <th class="text-right">
                    Quantity Sold
                </th>

                <th class="text-right">
                    Profit
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (count($products_data) > 0): ?>

            <?php $number = 1; ?>

            <?php foreach ($products_data as $product): ?>

            <tr>

                <td>
                    <?php echo $number++; ?>
                </td>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $product["name"]
                    );
                    ?>
                </td>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $product["barcode"]
                    );
                    ?>
                </td>

                <td class="text-right">
                    <?php
                    echo number_format(
                        (float)$product["quantity_sold"],
                        2
                    );
                    ?>
                </td>

                <td class="text-right profit-positive">

                    AFN
                    <?php
                    echo money(
                        $product["product_profit"]
                    );
                    ?>

                </td>

            </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td colspan="5">
                    No product sales found for this period.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =========================
     DAILY PROFIT
========================= -->

<div class="section" style="margin-bottom:30px;">

    <h2>
        Daily Sales & Profit
    </h2>

    <table>

        <thead>

            <tr>

                <th>
                    Date
                </th>

                <th>
                    Invoices
                </th>

                <th class="text-right">
                    Sales
                </th>

                <th class="text-right">
                    COGS
                </th>

                <th class="text-right">
                    Gross Profit
                </th>

            </tr>

        </thead>

        <tbody>

        <?php if (count($daily_data) > 0): ?>

            <?php foreach ($daily_data as $day): ?>

            <tr>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $day["day"]
                    );
                    ?>
                </td>

                <td>
                    <?php echo $day["invoices"]; ?>
                </td>

                <td class="text-right">

                    AFN
                    <?php
                    echo money(
                        $day["sales"]
                    );
                    ?>

                </td>

                <td class="text-right">

                    AFN
                    <?php
                    echo money(
                        $day["cogs"]
                    );
                    ?>

                </td>

                <td class="text-right profit-positive">

                    AFN
                    <?php
                    echo money(
                        $day["profit"]
                    );
                    ?>

                </td>

            </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td colspan="5">
                    No sales found for this period.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =========================
     FOOTER
========================= -->

<div
    style="
        text-align:center;
        color:#94a3b8;
        font-size:12px;
        padding:20px;
    "
>

    Generated by Super Store Management System

</div>


</div>

</body>

</html>