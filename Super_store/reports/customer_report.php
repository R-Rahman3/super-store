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
   FILTERS
========================= */

$search = trim($_GET["search"] ?? "");
$customer_id = (int)($_GET["customer_id"] ?? 0);

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

/* Validate dates */

$from_obj = DateTime::createFromFormat("Y-m-d", $from);
$to_obj   = DateTime::createFromFormat("Y-m-d", $to);

if (!$from_obj || $from_obj->format("Y-m-d") !== $from) {
    $from = date("Y-m-01");
}

if (!$to_obj || $to_obj->format("Y-m-d") !== $to) {
    $to = date("Y-m-d");
}

if ($from > $to) {
    $temp = $from;
    $from = $to;
    $to = $temp;
}

/* =========================
   HELPER
========================= */

function money($amount)
{
    return number_format((float)$amount, 2);
}

/* =========================
   STORE SETTINGS
========================= */

$store_name = "My Super Store";
$store_phone = "";
$store_address = "";
$currency = "AFN";
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

    $receipt_footer =
        $settings["receipt_footer"] ??
        $receipt_footer;
}


/* =========================
   CUSTOMER DROPDOWN
========================= */

$customers = [];

$result = mysqli_query(
    $conn,
    "
    SELECT
        id,
        name,
        phone,
        balance
    FROM customers
    ORDER BY name ASC
    "
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
}


/* =========================
   BUILD CUSTOMER FILTER
========================= */

$customer_condition = "";
$customer_params = [];
$customer_types = "";

if ($customer_id > 0) {

    $customer_condition .=
        " AND sales.customer_id = ? ";

    $customer_params[] = $customer_id;
    $customer_types .= "i";
}


/* =========================
   SEARCH CONDITION
========================= */

if ($search !== "") {

    $customer_condition .=
        " AND (
            customers.name LIKE ?
            OR customers.phone LIKE ?
            OR customers.address LIKE ?
        ) ";

    $search_like = "%" . $search . "%";

    $customer_params[] = $search_like;
    $customer_params[] = $search_like;
    $customer_params[] = $search_like;

    $customer_types .= "sss";
}


/* =========================
   CUSTOMER SALES SUMMARY
========================= */

$total_customers = 0;
$total_invoices = 0;
$total_sales = 0;
$total_paid = 0;
$total_due = 0;

$sql = "
    SELECT

        COUNT(DISTINCT customers.id)
        AS total_customers,

        COUNT(DISTINCT sales.id)
        AS total_invoices,

        COALESCE(
            SUM(sales.total_amount),
            0
        ) AS total_sales,

        COALESCE(
            SUM(sales.paid_amount),
            0
        ) AS total_paid,

        COALESCE(
            SUM(sales.due_amount),
            0
        ) AS total_due

    FROM customers

    INNER JOIN sales
        ON sales.customer_id = customers.id

    WHERE
        DATE(sales.sale_date)
        BETWEEN ? AND ?

        $customer_condition
";

$stmt = mysqli_prepare($conn, $sql);

$types = "ss" . $customer_types;

$params = [$from, $to];

foreach ($customer_params as $param) {
    $params[] = $param;
}

mysqli_stmt_bind_param(
    $stmt,
    $types,
    ...$params
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {

    $total_customers =
        (int)$row["total_customers"];

    $total_invoices =
        (int)$row["total_invoices"];

    $total_sales =
        (float)$row["total_sales"];

    $total_paid =
        (float)$row["total_paid"];

    $total_due =
        (float)$row["total_due"];
}

mysqli_stmt_close($stmt);


/* =========================
   AVERAGE SALE
========================= */

$average_sale = 0;

if ($total_invoices > 0) {

    $average_sale =
        $total_sales / $total_invoices;
}


/* =========================
   CUSTOMER PERFORMANCE
========================= */

$customer_report = [];

$sql = "
    SELECT

        customers.id,
        customers.name,
        customers.phone,
        customers.address,
        customers.balance,

        COUNT(sales.id)
        AS invoice_count,

        COALESCE(
            SUM(sales.total_amount),
            0
        ) AS total_sales,

        COALESCE(
            SUM(sales.paid_amount),
            0
        ) AS total_paid,

        COALESCE(
            SUM(sales.due_amount),
            0
        ) AS total_due

    FROM customers

    INNER JOIN sales
        ON sales.customer_id = customers.id

    WHERE
        DATE(sales.sale_date)
        BETWEEN ? AND ?

        $customer_condition

    GROUP BY
        customers.id,
        customers.name,
        customers.phone,
        customers.address,
        customers.balance

    ORDER BY total_sales DESC
";

$stmt = mysqli_prepare($conn, $sql);

$types = "ss" . $customer_types;

$params = [$from, $to];

foreach ($customer_params as $param) {
    $params[] = $param;
}

mysqli_stmt_bind_param(
    $stmt,
    $types,
    ...$params
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $customer_report[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   SALES DETAILS
========================= */

$sales_details = [];

$sql = "
    SELECT

        sales.id,
        sales.invoice_no,

        customers.name
        AS customer_name,

        customers.phone
        AS customer_phone,

        sales.subtotal,
        sales.discount,
        sales.total_amount,
        sales.paid_amount,
        sales.due_amount,
        sales.payment_method,
        sales.sale_date

    FROM sales

    LEFT JOIN customers
        ON sales.customer_id = customers.id

    WHERE
        DATE(sales.sale_date)
        BETWEEN ? AND ?

        $customer_condition

    ORDER BY sales.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

$types = "ss" . $customer_types;

$params = [$from, $to];

foreach ($customer_params as $param) {
    $params[] = $param;
}

mysqli_stmt_bind_param(
    $stmt,
    $types,
    ...$params
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $sales_details[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   TOP CUSTOMER
========================= */

$top_customer = null;

if (!empty($customer_report)) {
    $top_customer = $customer_report[0];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Customer Report -
    <?php echo htmlspecialchars($store_name); ?>
</title>

<style>

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

    padding: 25px;

    border-radius: 15px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.06);

    display: flex;

    justify-content:
        space-between;

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
   FILTER
========================= */

.filter-box {

    background: white;

    padding: 22px;

    border-radius: 15px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.05);
}

.filter-form {

    display: grid;

    grid-template-columns:
        1.2fr
        1fr
        1fr
        1fr
        auto;

    gap: 15px;

    align-items: end;
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

.form-group input,
.form-group select {

    padding: 11px;

    border:
        1px solid #d1d5db;

    border-radius: 8px;

    font-size: 14px;

    background: white;
}

.btn-filter {

    background: #16a34a;

    color: white;
}


/* =========================
   STORE INFO
========================= */

.store-info {

    background: white;

    padding: 22px;

    border-radius: 15px;

    text-align: center;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.05);
}

.store-info h2 {
    margin: 0 0 7px;
}

.store-info p {
    margin: 4px 0;
    color: #6b7280;
}


/* =========================
   CARDS
========================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(210px, 1fr)
        );

    gap: 18px;

    margin-bottom: 25px;
}

.card {

    background: white;

    padding: 22px;

    border-radius: 15px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.05);
}

.card-title {

    color: #6b7280;

    font-size: 14px;

    margin-bottom: 10px;
}

.card-value {

    font-size: 24px;

    font-weight: bold;
}

.card-small {

    margin-top: 7px;

    color: #6b7280;

    font-size: 13px;
}

.blue {
    border-left: 5px solid #2563eb;
}

.green {
    border-left: 5px solid #16a34a;
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


/* =========================
   SECTION
========================= */

.section {

    background: white;

    padding: 22px;

    border-radius: 15px;

    margin-bottom: 22px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.05);
}

.section-header {

    display: flex;

    justify-content:
        space-between;

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

    border-collapse:
        collapse;
}

table th {

    background: #f3f4f6;

    text-align: left;

    padding: 13px;

    font-size: 13px;

    white-space: nowrap;
}

table td {

    padding: 13px;

    border-bottom:
        1px solid #e5e7eb;

    font-size: 14px;
}

table tr:hover {

    background: #fafafa;
}


/* =========================
   BADGES
========================= */

.badge {

    padding:
        5px 9px;

    border-radius:
        20px;

    font-size:
        12px;

    font-weight:
        bold;
}

.badge-paid {

    background:
        #dcfce7;

    color:
        #166534;
}

.badge-partial {

    background:
        #fef3c7;

    color:
        #92400e;
}

.badge-due {

    background:
        #fee2e2;

    color:
        #991b1b;
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

        border:
            1px solid #ddd;
    }

    .section {

        break-inside: avoid;
    }

    table {

        font-size: 11px;
    }
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 1000px) {

    .filter-form {

        grid-template-columns:
            1fr 1fr;
    }
}

@media (max-width: 700px) {

    .header {

        flex-direction: column;

        align-items:
            flex-start;
    }

    .filter-form {

        grid-template-columns: 1fr;
    }

    .form-group input,
    .form-group select {

        width: 100%;
    }
}

</style>

</head>

<body>

<div class="container">


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <div>

        <h1>
            Customer Report
        </h1>

        <p>

            From
            <strong>
                <?php
                echo date(
                    "d M Y",
                    strtotime($from)
                );
                ?>
            </strong>

            to

            <strong>
                <?php
                echo date(
                    "d M Y",
                    strtotime($to)
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


<!-- =========================
     FILTER
========================= -->

<div class="filter-box">

<form
    method="GET"
    class="filter-form"
>

    <div class="form-group">

        <label>
            Search Customer
        </label>

        <input
            type="text"
            name="search"
            value="<?php
                echo htmlspecialchars($search);
            ?>"
            placeholder="Name, phone or address"
        >

    </div>


    <div class="form-group">

        <label>
            Customer
        </label>

        <select name="customer_id">

            <option value="0">
                All Customers
            </option>

            <?php foreach ($customers as $customer): ?>

                <option
                    value="<?php
                        echo $customer["id"];
                    ?>"
                    <?php
                    echo
                        $customer_id ==
                        $customer["id"]
                        ? "selected"
                        : "";
                    ?>
                >

                    <?php
                    echo htmlspecialchars(
                        $customer["name"]
                    );
                    ?>

                    <?php
                    if (!empty($customer["phone"])) {
                        echo " - ";
                        echo htmlspecialchars(
                            $customer["phone"]
                        );
                    }
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="form-group">

        <label>
            From Date
        </label>

        <input
            type="date"
            name="from"
            value="<?php
                echo htmlspecialchars($from);
            ?>"
            required
        >

    </div>


    <div class="form-group">

        <label>
            To Date
        </label>

        <input
            type="date"
            name="to"
            value="<?php
                echo htmlspecialchars($to);
            ?>"
            required
        >

    </div>


    <button
        type="submit"
        class="btn btn-filter"
    >
        Apply Filter
    </button>

</form>

</div>


<!-- =========================
     STORE INFO
========================= -->

<div class="store-info">

    <h2>
        <?php
        echo htmlspecialchars($store_name);
        ?>
    </h2>

    <?php if (!empty($store_address)): ?>

        <p>
            <?php
            echo htmlspecialchars(
                $store_address
            );
            ?>
        </p>

    <?php endif; ?>

    <?php if (!empty($store_phone)): ?>

        <p>
            Phone:
            <?php
            echo htmlspecialchars(
                $store_phone
            );
            ?>
        </p>

    <?php endif; ?>

    <p>
        Customer Sales Report
    </p>

</div>


<!-- =========================
     SUMMARY CARDS
========================= -->

<div class="cards">


    <div class="card blue">

        <div class="card-title">
            Customers
        </div>

        <div class="card-value">

            <?php
            echo $total_customers;
            ?>

        </div>

        <div class="card-small">
            Customers with sales
        </div>

    </div>


    <div class="card blue">

        <div class="card-title">
            Total Invoices
        </div>

        <div class="card-value">

            <?php
            echo $total_invoices;
            ?>

        </div>

        <div class="card-small">
            Sales invoices
        </div>

    </div>


    <div class="card purple">

        <div class="card-title">
            Total Sales
        </div>

        <div class="card-value">

            <?php
            echo money($total_sales);
            ?>

            <?php
            echo htmlspecialchars($currency);
            ?>

        </div>

        <div class="card-small">
            Customer sales
        </div>

    </div>


    <div class="card green">

        <div class="card-title">
            Total Paid
        </div>

        <div class="card-value">

            <?php
            echo money($total_paid);
            ?>

            <?php
            echo htmlspecialchars($currency);
            ?>

        </div>

        <div class="card-small">
            Amount received
        </div>

    </div>


    <div class="card red">

        <div class="card-title">
            Total Due
        </div>

        <div class="card-value">

            <?php
            echo money($total_due);
            ?>

            <?php
            echo htmlspecialchars($currency);
            ?>

        </div>

        <div class="card-small">
            Outstanding from sales
        </div>

    </div>


    <div class="card orange">

        <div class="card-title">
            Average Sale
        </div>

        <div class="card-value">

            <?php
            echo money($average_sale);
            ?>

            <?php
            echo htmlspecialchars($currency);
            ?>

        </div>

        <div class="card-small">
            Average invoice value
        </div>

    </div>

</div>


<!-- =========================
     TOP CUSTOMER
========================= -->

<?php if ($top_customer): ?>

<div class="section">

    <div class="section-header">

        <h2>
            Top Customer
        </h2>

    </div>

    <div class="cards">

        <div class="card blue">

            <div class="card-title">
                Customer
            </div>

            <div class="card-value">

                <?php
                echo htmlspecialchars(
                    $top_customer["name"]
                );
                ?>

            </div>

            <div class="card-small">

                <?php
                echo htmlspecialchars(
                    $top_customer["phone"]
                    ?: "No phone"
                );
                ?>

            </div>

        </div>


        <div class="card purple">

            <div class="card-title">
                Sales
            </div>

            <div class="card-value">

                <?php
                echo money(
                    $top_customer["total_sales"]
                );
                ?>

                <?php
                echo htmlspecialchars($currency);
                ?>

            </div>

        </div>


        <div class="card green">

            <div class="card-title">
                Paid
            </div>

            <div class="card-value">

                <?php
                echo money(
                    $top_customer["total_paid"]
                );
                ?>

                <?php
                echo htmlspecialchars($currency);
                ?>

            </div>

        </div>


        <div class="card red">

            <div class="card-title">
                Due
            </div>

            <div class="card-value">

                <?php
                echo money(
                    $top_customer["total_due"]
                );
                ?>

                <?php
                echo htmlspecialchars($currency);
                ?>

            </div>

        </div>

    </div>

</div>

<?php endif; ?>


<!-- =========================
     CUSTOMER PERFORMANCE
========================= -->

<div class="section">

    <div class="section-header">

        <h2>
            Customer Performance
        </h2>

    </div>

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>
                        Customer
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        Invoices
                    </th>

                    <th>
                        Total Sales
                    </th>

                    <th>
                        Paid
                    </th>

                    <th>
                        Period Due
                    </th>

                    <th>
                        Current Balance
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (!empty($customer_report)): ?>

                <?php
                $number = 1;
                ?>

                <?php foreach (
                    $customer_report
                    as $customer
                ): ?>

                    <tr>

                        <td>
                            <?php
                            echo $number++;
                            ?>
                        </td>


                        <td>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $customer["name"]
                                );
                                ?>

                            </strong>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $customer["phone"]
                                ?: "-"
                            );

                            ?>

                        </td>


                        <td>

                            <?php
                            echo $customer[
                                "invoice_count"
                            ];
                            ?>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $customer["total_sales"]
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $currency
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $customer["total_paid"]
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $currency
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $customer["total_due"]
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $currency
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $customer["balance"]
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $currency
                            );
                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="8"
                        class="empty"
                    >
                        No customer sales found
                        for the selected filters.
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

<div class="section">

    <div class="section-header">

        <h2>
            Customer Sales Details
        </h2>

    </div>

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>
                        Invoice
                    </th>

                    <th>
                        Customer
                    </th>

                    <th>
                        Subtotal
                    </th>

                    <th>
                        Discount
                    </th>

                    <th>
                        Total
                    </th>

                    <th>
                        Paid
                    </th>

                    <th>
                        Due
                    </th>

                    <th>
                        Payment
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (!empty($sales_details)): ?>

                <?php
                $number = 1;
                ?>

                <?php foreach (
                    $sales_details
                    as $sale
                ): ?>

                    <tr>

                        <td>
                            <?php
                            echo $number++;
                            ?>
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

                            <?php
                            if (
                                !empty(
                                    $sale["customer_phone"]
                                )
                            ) {

                                echo "<br>";

                                echo "<small>";

                                echo htmlspecialchars(
                                    $sale[
                                        "customer_phone"
                                    ]
                                );

                                echo "</small>";
                            }
                            ?>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $sale["subtotal"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $sale["discount"]
                            );
                            ?>

                        </td>


                        <td>

                            <strong>

                                <?php
                                echo money(
                                    $sale["total_amount"]
                                );
                                ?>

                                <?php
                                echo htmlspecialchars(
                                    $currency
                                );
                                ?>

                            </strong>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $sale["paid_amount"]
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $currency
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo money(
                                $sale["due_amount"]
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $currency
                            );
                            ?>

                        </td>


                        <td>

                            <?php

                            $payment =
                                $sale[
                                    "payment_method"
                                ];

                            if ($payment === "Cash") {

                                echo
                                    '<span class="badge badge-paid">
                                    Cash
                                    </span>';

                            } elseif (
                                $payment === "Card"
                            ) {

                                echo
                                    '<span class="badge badge-paid">
                                    Card
                                    </span>';

                            } else {

                                echo
                                    '<span class="badge badge-due">
                                    Credit
                                    </span>';
                            }

                            ?>

                        </td>


                        <td>

                            <?php
                            echo date(
                                "d M Y H:i",
                                strtotime(
                                    $sale["sale_date"]
                                )
                            );
                            ?>

                        </td>


                        <td>

                            <a
                                href="../sales/view.php?id=<?php
                                    echo $sale["id"];
                                ?>"
                                class="btn"
                                style="
                                    background:#2563eb;
                                    color:white;
                                    padding:7px 12px;
                                    font-size:12px;
                                "
                            >
                                View
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="11"
                        class="empty"
                    >
                        No sales found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================
     REPORT SUMMARY
========================= -->

<div class="section">

    <div class="section-header">

        <h2>
            Customer Account Summary
        </h2>

    </div>

    <table>

        <tr>

            <th>
                Total Customer Sales
            </th>

            <td>

                <strong>

                    <?php
                    echo money($total_sales);
                    ?>

                    <?php
                    echo htmlspecialchars(
                        $currency
                    );
                    ?>

                </strong>

            </td>

        </tr>


        <tr>

            <th>
                Total Amount Paid
            </th>

            <td>

                <?php
                echo money($total_paid);
                ?>

                <?php
                echo htmlspecialchars(
                    $currency
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Total Outstanding Due
            </th>

            <td>

                <strong>

                    <?php
                    echo money($total_due);
                    ?>

                    <?php
                    echo htmlspecialchars(
                        $currency
                    );
                    ?>

                </strong>

            </td>

        </tr>


        <tr>

            <th>
                Average Customer Sale
            </th>

            <td>

                <?php
                echo money($average_sale);
                ?>

                <?php
                echo htmlspecialchars(
                    $currency
                );
                ?>

            </td>

        </tr>


        <tr>

            <th>
                Report Period
            </th>

            <td>

                <?php
                echo date(
                    "d M Y",
                    strtotime($from)
                );
                ?>

                -

                <?php
                echo date(
                    "d M Y",
                    strtotime($to)
                );
                ?>

            </td>

        </tr>

    </table>

</div>


<!-- =========================
     FOOTER
========================= -->

<div class="footer">

    <?php
    echo htmlspecialchars(
        $receipt_footer
    );
    ?>

    <br><br>

    Generated by:

    <strong>

        <?php
        echo htmlspecialchars(
            $username
        );
        ?>

    </strong>

</div>


</div>

</body>

</html>