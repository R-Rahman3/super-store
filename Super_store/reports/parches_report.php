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

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $from_date)) {
    $from_date = date("Y-m-01");
}

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $to_date)) {
    $to_date = date("Y-m-d");
}

/* Reverse dates if necessary */

if ($from_date > $to_date) {
    $temp = $from_date;
    $from_date = $to_date;
    $to_date = $temp;
}


/* =========================
   PURCHASE SUMMARY
========================= */

$total_purchases = 0;
$total_paid = 0;
$total_due = 0;
$total_invoices = 0;

$sql = "
    SELECT
        COUNT(*) AS total_invoices,
        COALESCE(SUM(total_amount), 0) AS total_purchases,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due
    FROM purchases
    WHERE DATE(purchase_date) BETWEEN ? AND ?
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

        $total_purchases = (float) $summary["total_purchases"];

        $total_paid = (float) $summary["total_paid"];

        $total_due = (float) $summary["total_due"];
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   PURCHASE LIST
========================= */

$purchases = [];

$sql = "
    SELECT
        purchases.id,
        purchases.invoice_no,
        purchases.total_amount,
        purchases.paid_amount,
        purchases.due_amount,
        purchases.purchase_date,

        suppliers.name AS supplier_name,
        suppliers.phone AS supplier_phone

    FROM purchases

    LEFT JOIN suppliers
        ON purchases.supplier_id = suppliers.id

    WHERE DATE(purchases.purchase_date) BETWEEN ? AND ?

    ORDER BY purchases.id DESC
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

        $purchases[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   SUPPLIER SUMMARY
========================= */

$supplier_summary = [];

$sql = "
    SELECT
        suppliers.name AS supplier_name,
        COUNT(purchases.id) AS total_invoices,
        COALESCE(SUM(purchases.total_amount), 0) AS total_purchases,
        COALESCE(SUM(purchases.paid_amount), 0) AS total_paid,
        COALESCE(SUM(purchases.due_amount), 0) AS total_due

    FROM purchases

    INNER JOIN suppliers
        ON purchases.supplier_id = suppliers.id

    WHERE DATE(purchases.purchase_date) BETWEEN ? AND ?

    GROUP BY purchases.supplier_id

    ORDER BY total_purchases DESC

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

        $supplier_summary[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   AVERAGE PURCHASE
========================= */

$average_purchase = 0;

if ($total_invoices > 0) {

    $average_purchase =
        $total_purchases / $total_invoices;
}


/* =========================
   PURCHASE ITEMS
========================= */

$total_items = 0;

$sql = "
    SELECT
        COALESCE(SUM(purchase_items.quantity), 0) AS total_items

    FROM purchase_items

    INNER JOIN purchases
        ON purchase_items.purchase_id = purchases.id

    WHERE DATE(purchases.purchase_date) BETWEEN ? AND ?
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

    $items_summary = mysqli_fetch_assoc($result);

    if ($items_summary) {

        $total_items =
            (int) $items_summary["total_items"];
    }

    mysqli_stmt_close($stmt);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Purchase Report | Super Store</title>

<style>

/* =========================
   GLOBAL
========================= */

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

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);
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

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.card {
    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);
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

.card.purchase {
    border-left: 5px solid #8e44ad;
}

.card.paid {
    border-left: 5px solid #27ae60;
}

.card.due {
    border-left: 5px solid #c0392b;
}

.card.average {
    border-left: 5px solid #2874a6;
}


/* =========================
   GRID
========================= */

.grid {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;

    margin-bottom: 25px;
}

.panel {
    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 2px 10px rgba(0,0,0,.06);
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
    border-bottom:
        1px solid #eee;
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

.purple {
    color: #8e44ad;
}


/* =========================
   DATA TABLE
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

    border-bottom:
        1px solid #eee;

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
   BADGES
========================= */

.badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.badge-paid {
    background: #e8f8f5;

    color: #148f77;
}

.badge-due {
    background: #fdedec;

    color: #c0392b;
}

.badge-partial {
    background: #fef5e7;

    color: #d68910;
}


/* =========================
   VIEW BUTTON
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

@media(max-width:1100px) {

    .cards {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width:750px) {

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
            <?php
            echo htmlspecialchars($username);
            ?>
        </strong>

        <span>
            <?php
            echo htmlspecialchars($role);
            ?>
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

        <a href="../purchases/manage.php"
           class="active">

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


        <a href="../logout.php"
           class="logout">

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

            <h1>
                Purchase Report
            </h1>

            <p>

                Purchase report from

                <strong>
                    <?php
                    echo htmlspecialchars($from_date);
                    ?>
                </strong>

                to

                <strong>
                    <?php
                    echo htmlspecialchars($to_date);
                    ?>
                </strong>

            </p>

        </div>


        <button
            onclick="window.print()"
            class="btn btn-print no-print">

            🖨 Print Report

        </button>

    </div>


    <!-- =========================
         FILTER
    ========================= -->

    <div class="filter-box no-print">

        <form
            method="GET"
            class="filter-form">


            <div class="form-group">

                <label>
                    From Date
                </label>

                <input
                    type="date"
                    name="from_date"
                    value="<?php
                        echo htmlspecialchars($from_date);
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
                    name="to_date"
                    value="<?php
                        echo htmlspecialchars($to_date);
                    ?>"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn">

                Generate Report

            </button>

        </form>

    </div>


    <!-- =========================
         CARDS
    ========================= -->

    <div class="cards">


        <div class="card purchase">

            <div class="card-title">
                Total Purchases
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $total_purchases,
                    2
                );
                ?>

                AFN

            </div>

            <small>

                <?php
                echo number_format(
                    $total_invoices
                );
                ?>

                purchase invoices

            </small>

        </div>


        <div class="card paid">

            <div class="card-title">
                Total Paid
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $total_paid,
                    2
                );
                ?>

                AFN

            </div>

            <small>
                Paid to suppliers
            </small>

        </div>


        <div class="card due">

            <div class="card-title">
                Supplier Due
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $total_due,
                    2
                );
                ?>

                AFN

            </div>

            <small>
                Outstanding amount
            </small>

        </div>


        <div class="card average">

            <div class="card-title">
                Average Purchase
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $average_purchase,
                    2
                );
                ?>

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


        <div class="panel">

            <h2>
                Purchase Summary
            </h2>

            <table class="summary-table">


                <tr>

                    <td>
                        Purchase Invoices
                    </td>

                    <td class="purple">

                        <?php
                        echo number_format(
                            $total_invoices
                        );
                        ?>

                    </td>

                </tr>


                <tr>

                    <td>
                        Total Purchased Items
                    </td>

                    <td class="blue">

                        <?php
                        echo number_format(
                            $total_items
                        );
                        ?>

                    </td>

                </tr>


                <tr>

                    <td>
                        Total Purchases
                    </td>

                    <td class="purple">

                        <?php
                        echo number_format(
                            $total_purchases,
                            2
                        );
                        ?>

                        AFN

                    </td>

                </tr>


                <tr>

                    <td>
                        Paid Amount
                    </td>

                    <td class="green">

                        <?php
                        echo number_format(
                            $total_paid,
                            2
                        );
                        ?>

                        AFN

                    </td>

                </tr>


                <tr>

                    <td>
                        Due Amount
                    </td>

                    <td class="red">

                        <?php
                        echo number_format(
                            $total_due,
                            2
                        );
                        ?>

                        AFN

                    </td>

                </tr>

            </table>

        </div>


        <!-- SUPPLIER SUMMARY -->

        <div class="panel">

            <h2>
                Top Suppliers
            </h2>

            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>
                                Supplier
                            </th>

                            <th>
                                Invoices
                            </th>

                            <th>
                                Purchases
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php
                    if (count($supplier_summary) > 0):
                    ?>

                        <?php foreach (
                            $supplier_summary
                            as $supplier
                        ): ?>

                            <tr>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $supplier["supplier_name"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo number_format(
                                        $supplier["total_invoices"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo number_format(
                                        $supplier["total_purchases"],
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
                                colspan="3"
                                class="empty">

                                No supplier purchases found.

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =========================
         PURCHASE DETAILS
    ========================= -->

    <div class="panel">

        <h2>
            Purchase Details
        </h2>


        <div class="table-container">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Invoice
                        </th>

                        <th>
                            Supplier
                        </th>

                        <th>
                            Phone
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
                            Status
                        </th>

                        <th>
                            Date
                        </th>

                        <th class="no-print">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (count($purchases) > 0): ?>

                    <?php $number = 1; ?>


                    <?php foreach (
                        $purchases
                        as $purchase
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
                                        $purchase["invoice_no"]
                                    );
                                    ?>

                                </strong>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $purchase["supplier_name"]
                                    ?: "No Supplier"
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $purchase["supplier_phone"]
                                    ?: "-"
                                );
                                ?>

                            </td>


                            <td>

                                <strong>

                                    <?php
                                    echo number_format(
                                        $purchase["total_amount"],
                                        2
                                    );
                                    ?>

                                    AFN

                                </strong>

                            </td>


                            <td class="green">

                                <?php
                                echo number_format(
                                    $purchase["paid_amount"],
                                    2
                                );
                                ?>

                                AFN

                            </td>


                            <td class="red">

                                <?php
                                echo number_format(
                                    $purchase["due_amount"],
                                    2
                                );
                                ?>

                                AFN

                            </td>


                            <td>


                                <?php
                                $total =
                                    (float)
                                    $purchase["total_amount"];

                                $paid =
                                    (float)
                                    $purchase["paid_amount"];

                                $due =
                                    (float)
                                    $purchase["due_amount"];


                                if (
                                    $due <= 0 &&
                                    $total > 0
                                ):

                                ?>

                                    <span
                                        class="badge badge-paid">

                                        Paid

                                    </span>


                                <?php
                                elseif (
                                    $paid > 0 &&
                                    $due > 0
                                ):
                                ?>

                                    <span
                                        class="badge badge-partial">

                                        Partial

                                    </span>


                                <?php else: ?>

                                    <span
                                        class="badge badge-due">

                                        Due

                                    </span>

                                <?php endif; ?>


                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $purchase["purchase_date"]
                                );
                                ?>

                            </td>


                            <td class="no-print">

                                <a
                                    href="../purchases/edit.php?id=<?php echo (int)$purchase["id"]; ?>"
                                    class="view-btn">

                                    View / Edit

                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="10"
                            class="empty">

                            No purchases found
                            for the selected date range.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


    <!-- FOOTER -->

    <div class="report-footer">

        Purchase report generated by

        <strong>

            <?php
            echo htmlspecialchars($username);
            ?>

        </strong>

        |

        <?php
        echo date("Y-m-d H:i");
        ?>

    </div>


</div>

</body>

</html>