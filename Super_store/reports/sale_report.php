<?php
session_start();

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

$from_date = $_GET["from_date"] ?? date("Y-m-01");
$to_date   = $_GET["to_date"] ?? date("Y-m-d");

/* Validate dates */

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $from_date)) {
    $from_date = date("Y-m-01");
}

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $to_date)) {
    $to_date = date("Y-m-d");
}

/* Prevent reversed dates */

if ($from_date > $to_date) {
    $temp = $from_date;
    $from_date = $to_date;
    $to_date = $temp;
}


/* =========================
   SALES SUMMARY
========================= */

$total_sales = 0;
$total_paid = 0;
$total_due = 0;
$total_discount = 0;
$total_subtotal = 0;
$total_invoices = 0;

$sql = "
    SELECT
        COUNT(*) AS total_invoices,
        COALESCE(SUM(subtotal), 0) AS total_subtotal,
        COALESCE(SUM(discount), 0) AS total_discount,
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $summary = mysqli_fetch_assoc($result);

    if ($summary) {

        $total_invoices = (int) $summary["total_invoices"];
        $total_subtotal = (float) $summary["total_subtotal"];
        $total_discount = (float) $summary["total_discount"];
        $total_sales = (float) $summary["total_sales"];
        $total_paid = (float) $summary["total_paid"];
        $total_due = (float) $summary["total_due"];
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   PAYMENT METHOD SUMMARY
========================= */

$cash_sales = 0;
$card_sales = 0;
$credit_sales = 0;

$sql = "
    SELECT
        payment_method,
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE DATE(sale_date) BETWEEN ? AND ?
    GROUP BY payment_method
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {

        if ($row["payment_method"] === "Cash") {
            $cash_sales = (float) $row["total"];
        }

        if ($row["payment_method"] === "Card") {
            $card_sales = (float) $row["total"];
        }

        if ($row["payment_method"] === "Credit") {
            $credit_sales = (float) $row["total"];
        }
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   SALES LIST
========================= */

$sales = [];

$sql = "
    SELECT
        sales.id,
        sales.invoice_no,
        sales.subtotal,
        sales.discount,
        sales.total_amount,
        sales.paid_amount,
        sales.due_amount,
        sales.payment_method,
        sales.sale_date,

        customers.name AS customer_name,

        users.name AS user_name,
        users.username AS username

    FROM sales

    LEFT JOIN customers
        ON sales.customer_id = customers.id

    LEFT JOIN users
        ON sales.user_id = users.id

    WHERE DATE(sales.sale_date) BETWEEN ? AND ?

    ORDER BY sales.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $sales[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   TOP CUSTOMERS
========================= */

$top_customers = [];

$sql = "
    SELECT
        customers.name,
        COUNT(sales.id) AS total_invoices,
        SUM(sales.total_amount) AS total_sales
    FROM sales

    INNER JOIN customers
        ON sales.customer_id = customers.id

    WHERE DATE(sales.sale_date) BETWEEN ? AND ?

    GROUP BY sales.customer_id

    ORDER BY total_sales DESC

    LIMIT 10
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $from_date,
        $to_date
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $top_customers[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   TODAY / PERIOD AVERAGE
========================= */

$average_sale = 0;

if ($total_invoices > 0) {
    $average_sale = $total_sales / $total_invoices;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sales Report | Super Store</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #222;
}


/* =========================
   SIDEBAR
========================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: #17202a;
    color: white;
    padding: 25px 15px;
    overflow-y: auto;
}

.logo {
    font-size: 23px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 30px;
}

.user-box {
    background: #212f3d;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.user-box strong {
    display: block;
    margin-bottom: 5px;
}

.user-box span {
    color: #bdc3c7;
    font-size: 13px;
}

.menu a {
    display: block;
    color: #d5d8dc;
    text-decoration: none;
    padding: 12px 15px;
    margin-bottom: 5px;
    border-radius: 8px;
}

.menu a:hover,
.menu a.active {
    background: #2874a6;
    color: white;
}

.logout {
    color: #f1948a !important;
}


/* =========================
   MAIN
========================= */

.main {
    margin-left: 250px;
    padding: 30px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    font-size: 28px;
}

.header p {
    color: #777;
    margin-top: 6px;
}


/* =========================
   FILTER
========================= */

.filter-box {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.filter-form {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-group label {
    font-size: 13px;
    font-weight: bold;
}

.form-group input {
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 7px;
    min-width: 180px;
}

.btn {
    padding: 11px 18px;
    border: none;
    border-radius: 7px;
    background: #2874a6;
    color: white;
    text-decoration: none;
    cursor: pointer;
}

.btn:hover {
    opacity: .9;
}

.btn-print {
    background: #27ae60;
}


/* =========================
   CARDS
========================= */

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.card-title {
    color: #777;
    font-size: 14px;
    margin-bottom: 10px;
}

.card-value {
    font-size: 24px;
    font-weight: bold;
}

.card small {
    display: block;
    color: #888;
    margin-top: 7px;
}

.card.sales {
    border-left: 5px solid #2874a6;
}

.card.paid {
    border-left: 5px solid #27ae60;
}

.card.due {
    border-left: 5px solid #c0392b;
}

.card.average {
    border-left: 5px solid #8e44ad;
}


/* =========================
   PANELS
========================= */

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

.panel {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.panel h2 {
    font-size: 19px;
    margin-bottom: 18px;
}


/* =========================
   SUMMARY TABLE
========================= */

.summary-table {
    width: 100%;
    border-collapse: collapse;
}

.summary-table tr {
    border-bottom: 1px solid #eee;
}

.summary-table td {
    padding: 12px 5px;
}

.summary-table td:last-child {
    text-align: right;
    font-weight: bold;
}

.green {
    color: #27ae60;
}

.red {
    color: #c0392b;
}

.blue {
    color: #2874a6;
}

.orange {
    color: #e67e22;
}

.purple {
    color: #8e44ad;
}


/* =========================
   TABLE
========================= */

.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
    white-space: nowrap;
}

.data-table th {
    background: #f8f9f9;
    font-size: 13px;
}

.data-table td {
    font-size: 14px;
}

.data-table tbody tr:hover {
    background: #fafafa;
}


/* =========================
   PAYMENT BADGES
========================= */

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.badge-cash {
    background: #e8f8f5;
    color: #148f77;
}

.badge-card {
    background: #ebf5fb;
    color: #2874a6;
}

.badge-credit {
    background: #fdedec;
    color: #c0392b;
}


/* =========================
   ACTION BUTTON
========================= */

.view-btn {
    background: #2874a6;
    color: white;
    padding: 7px 11px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
}

.view-btn:hover {
    opacity: .85;
}


/* =========================
   EMPTY
========================= */

.empty {
    text-align: center;
    padding: 25px !important;
    color: #888;
}


/* =========================
   FOOTER
========================= */

.report-footer {
    text-align: center;
    color: #888;
    margin-top: 25px;
    font-size: 13px;
}


/* =========================
   RESPONSIVE
========================= */

@media(max-width: 1100px) {

    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 750px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .header {
        display: block;
    }

    .header .btn {
        margin-top: 15px;
    }

    .filter-form {
        display: block;
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group input,
    .filter-form .btn {
        width: 100%;
    }
}


/* =========================
   PRINT
========================= */

@media print {

    .sidebar,
    .filter-box,
    .no-print {
        display: none !important;
    }

    .main {
        margin: 0;
        padding: 10px;
    }

    body {
        background: white;
    }

    .card,
    .panel {
        box-shadow: none;
        border: 1px solid #ddd;
    }

    .data-table th,
    .data-table td {
        font-size: 10px;
        padding: 7px;
    }

}

</style>

</head>

<body>


<!-- =========================
     SIDEBAR
========================= -->

<div class="sidebar">

    <div class="logo">
        🛒 Super Store
    </div>

    <div class="user-box">

        <strong>
            <?php echo htmlspecialchars($username); ?>
        </strong>

        <span>
            <?php echo htmlspecialchars($role); ?>
        </span>

    </div>

    <div class="menu">

        <a href="../dashboard.php">
            Dashboard
        </a>

        <a href="../sales/pos.php">
            POS / New Sale
        </a>

        <a href="../sales/manage.php">
            Sales History
        </a>

        <a href="../products/manage.php">
            Products
        </a>

        <a href="../categories/manage.php">
            Categories
        </a>

        <a href="../purchases/manage.php">
            Purchases
        </a>

        <a href="../customers/manage.php">
            Customers
        </a>

        <a href="../suppliers/manage.php">
            Suppliers
        </a>

        <a href="../expenses/manage.php">
            Expenses
        </a>

        <a href="index.php">
            Reports
        </a>

        <?php if ($role === "Admin"): ?>

            <a href="../users/manage.php">
                Users
            </a>

            <a href="../settings.php">
                Settings
            </a>

        <?php endif; ?>

        <a href="../logout.php" class="logout">
            Logout
        </a>

    </div>

</div>


<!-- =========================
     MAIN
========================= -->

<div class="main">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>Sales Report</h1>

            <p>
                Sales report from
                <strong>
                    <?php echo htmlspecialchars($from_date); ?>
                </strong>
                to
                <strong>
                    <?php echo htmlspecialchars($to_date); ?>
                </strong>
            </p>

        </div>

        <button
            onclick="window.print()"
            class="btn btn-print no-print"
        >
            🖨 Print Report
        </button>

    </div>


    <!-- =========================
         FILTER
    ========================= -->

    <div class="filter-box no-print">

        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-group">

                <label>
                    From Date
                </label>

                <input
                    type="date"
                    name="from_date"
                    value="<?php echo htmlspecialchars($from_date); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    To Date
                </label>

                <input
                    type="date"
                    name="to_date"
                    value="<?php echo htmlspecialchars($to_date); ?>"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                Generate Report
            </button>

        </form>

    </div>


    <!-- =========================
         MAIN CARDS
    ========================= -->

    <div class="cards">


        <div class="card sales">

            <div class="card-title">
                Total Sales
            </div>

            <div class="card-value">
                <?php echo number_format($total_sales, 2); ?>
                AFN
            </div>

            <small>
                <?php echo $total_invoices; ?> invoices
            </small>

        </div>


        <div class="card paid">

            <div class="card-title">
                Total Paid
            </div>

            <div class="card-value">
                <?php echo number_format($total_paid, 2); ?>
                AFN
            </div>

            <small>
                Received from sales
            </small>

        </div>


        <div class="card due">

            <div class="card-title">
                Customer Due
            </div>

            <div class="card-value">
                <?php echo number_format($total_due, 2); ?>
                AFN
            </div>

            <small>
                Outstanding credit
            </small>

        </div>


        <div class="card average">

            <div class="card-title">
                Average Sale
            </div>

            <div class="card-value">
                <?php echo number_format($average_sale, 2); ?>
                AFN
            </div>

            <small>
                Per invoice
            </small>

        </div>

    </div>


    <!-- =========================
         SUMMARY
    ========================= -->

    <div class="grid">


        <!-- SALES SUMMARY -->

        <div class="panel">

            <h2>
                Sales Summary
            </h2>

            <table class="summary-table">

                <tr>

                    <td>
                        Number of Invoices
                    </td>

                    <td class="blue">
                        <?php echo number_format($total_invoices); ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Subtotal
                    </td>

                    <td>
                        <?php echo number_format($total_subtotal, 2); ?>
                        AFN
                    </td>

                </tr>


                <tr>

                    <td>
                        Total Discount
                    </td>

                    <td class="orange">
                        <?php echo number_format($total_discount, 2); ?>
                        AFN
                    </td>

                </tr>


                <tr>

                    <td>
                        Total Sales
                    </td>

                    <td class="blue">
                        <?php echo number_format($total_sales, 2); ?>
                        AFN
                    </td>

                </tr>


                <tr>

                    <td>
                        Total Paid
                    </td>

                    <td class="green">
                        <?php echo number_format($total_paid, 2); ?>
                        AFN
                    </td>

                </tr>


                <tr>

                    <td>
                        Total Due
                    </td>

                    <td class="red">
                        <?php echo number_format($total_due, 2); ?>
                        AFN
                    </td>

                </tr>

            </table>

        </div>


        <!-- PAYMENT METHOD -->

        <div class="panel">

            <h2>
                Payment Method
            </h2>

            <table class="summary-table">

                <tr>

                    <td>
                        💵 Cash Sales
                    </td>

                    <td class="green">
                        <?php echo number_format($cash_sales, 2); ?>
                        AFN
                    </td>

                </tr>


                <tr>

                    <td>
                        💳 Card Sales
                    </td>

                    <td class="blue">
                        <?php echo number_format($card_sales, 2); ?>
                        AFN
                    </td>

                </tr>


                <tr>

                    <td>
                        📋 Credit Sales
                    </td>

                    <td class="red">
                        <?php echo number_format($credit_sales, 2); ?>
                        AFN
                    </td>

                </tr>


                <tr>

                    <td>
                        Total
                    </td>

                    <td class="purple">
                        <?php echo number_format($total_sales, 2); ?>
                        AFN
                    </td>

                </tr>

            </table>

        </div>

    </div>


    <!-- =========================
         TOP CUSTOMERS
    ========================= -->

    <div class="panel" style="margin-bottom:25px;">

        <h2>
            Top Customers
        </h2>

        <div class="table-container">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Customer</th>

                        <th>Invoices</th>

                        <th>Total Sales</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($top_customers) > 0): ?>

                    <?php $customer_number = 1; ?>

                    <?php foreach ($top_customers as $customer): ?>

                        <tr>

                            <td>
                                <?php echo $customer_number++; ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $customer["name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    $customer["total_invoices"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    $customer["total_sales"],
                                    2
                                );
                                ?>
                                AFN
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="4"
                            class="empty"
                        >
                            No customer sales found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =========================
         SALES DETAILS
    ========================= -->

    <div class="panel">

        <h2>
            Sales Details
        </h2>

        <div class="table-container">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Invoice</th>

                        <th>Customer</th>

                        <th>Subtotal</th>

                        <th>Discount</th>

                        <th>Total</th>

                        <th>Paid</th>

                        <th>Due</th>

                        <th>Payment</th>

                        <th>Cashier</th>

                        <th>Date</th>

                        <th class="no-print">
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($sales) > 0): ?>

                    <?php $number = 1; ?>

                    <?php foreach ($sales as $sale): ?>

                        <tr>

                            <td>
                                <?php echo $number++; ?>
                            </td>


                            <td>

                                <strong>
                                    <?php
                                    echo htmlspecialchars(
                                        $sale["invoice_no"]
                                    );
                                    ?>
                                </strong>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $sale["customer_name"]
                                    ?: "Walk-in Customer"
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo number_format(
                                    $sale["subtotal"],
                                    2
                                );
                                ?>

                                AFN

                            </td>


                            <td class="orange">

                                <?php
                                echo number_format(
                                    $sale["discount"],
                                    2
                                );
                                ?>

                                AFN

                            </td>


                            <td>

                                <strong>

                                    <?php
                                    echo number_format(
                                        $sale["total_amount"],
                                        2
                                    );
                                    ?>

                                    AFN

                                </strong>

                            </td>


                            <td class="green">

                                <?php
                                echo number_format(
                                    $sale["paid_amount"],
                                    2
                                );
                                ?>

                                AFN

                            </td>


                            <td class="red">

                                <?php
                                echo number_format(
                                    $sale["due_amount"],
                                    2
                                );
                                ?>

                                AFN

                            </td>


                            <td>

                                <?php if ($sale["payment_method"] === "Cash"): ?>

                                    <span class="badge badge-cash">
                                        Cash
                                    </span>

                                <?php elseif ($sale["payment_method"] === "Card"): ?>

                                    <span class="badge badge-card">
                                        Card
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-credit">
                                        Credit
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $sale["user_name"]
                                    ?: $sale["username"]
                                    ?: "Unknown"
                                );

                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $sale["sale_date"]
                                );
                                ?>

                            </td>


                            <td class="no-print">

                                <a
                                    href="../sales/view.php?id=<?php echo (int) $sale["id"]; ?>"
                                    class="view-btn"
                                >
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="12"
                            class="empty"
                        >
                            No sales found for the selected date range.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =========================
         FOOTER
    ========================= -->

    <div class="report-footer">

        Report generated by
        <strong>
            <?php echo htmlspecialchars($username); ?>
        </strong>

        |
        <?php echo date("Y-m-d H:i"); ?>

    </div>

</div>


</body>

</html>