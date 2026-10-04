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
   EXPENSE SUMMARY
========================= */

$total_expenses = 0;
$total_expense_count = 0;

$sql = "
    SELECT
        COUNT(*) AS total_count,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM expenses
    WHERE DATE(expense_date) BETWEEN ? AND ?
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

        $total_expense_count =
            (int) $summary["total_count"];

        $total_expenses =
            (float) $summary["total_amount"];
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   EXPENSE BY CATEGORY
========================= */

$categories = [];

$sql = "
    SELECT
        category,
        COUNT(*) AS expense_count,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM expenses
    WHERE DATE(expense_date) BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total_amount DESC
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
        $categories[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   EXPENSE DETAILS
========================= */

$expenses = [];

$sql = "
    SELECT
        expenses.id,
        expenses.title,
        expenses.category,
        expenses.amount,
        expenses.description,
        expenses.expense_date,

        users.name AS user_name,
        users.username AS username

    FROM expenses

    LEFT JOIN users
        ON expenses.user_id = users.id

    WHERE DATE(expenses.expense_date) BETWEEN ? AND ?

    ORDER BY expenses.id DESC
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
        $expenses[] = $row;
    }

    mysqli_stmt_close($stmt);
}


/* =========================
   TOP EXPENSE CATEGORY
========================= */

$top_category = "None";
$top_category_amount = 0;

if (count($categories) > 0) {

    $top_category =
        $categories[0]["category"];

    $top_category_amount =
        (float) $categories[0]["total_amount"];
}


/* =========================
   AVERAGE EXPENSE
========================= */

$average_expense = 0;

if ($total_expense_count > 0) {

    $average_expense =
        $total_expenses / $total_expense_count;
}


/* =========================
   TODAY EXPENSE
========================= */

$today_expense = 0;

$sql = "
    SELECT
        COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE expense_date = CURDATE()
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $today_row = mysqli_fetch_assoc($result);

    if ($today_row) {
        $today_expense =
            (float) $today_row["total"];
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Expense Report | Super Store</title>

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
   BUTTON
========================= */

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

.card.total {
    border-left: 5px solid #e67e22;
}

.card.count {
    border-left: 5px solid #2874a6;
}

.card.average {
    border-left: 5px solid #8e44ad;
}

.card.today {
    border-left: 5px solid #27ae60;
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

.orange {
    color: #e67e22;
}

.blue {
    color: #2874a6;
}

.green {
    color: #27ae60;
}

.purple {
    color: #8e44ad;
}

.red {
    color: #c0392b;
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
   CATEGORY BADGE
========================= */

.category-badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    background: #fef5e7;

    color: #d68910;

    font-size: 12px;

    font-weight: bold;
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

        <a href="../purchases/manage.php">
            Purchases
        </a>

        <a href="../customers/manage.php">
            Customers
        </a>

        <a href="../suppliers/manage.php">
            Suppliers
        </a>

        <a href="../expenses/manage.php"
           class="active">

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
                Expense Report
            </h1>

            <p>

                Expense report from

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


        <div class="card total">

            <div class="card-title">
                Total Expenses
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $total_expenses,
                    2
                );
                ?>

                AFN

            </div>

            <small>
                Selected period
            </small>

        </div>


        <div class="card count">

            <div class="card-title">
                Expense Records
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $total_expense_count
                );
                ?>

            </div>

            <small>
                Total transactions
            </small>

        </div>


        <div class="card average">

            <div class="card-title">
                Average Expense
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $average_expense,
                    2
                );
                ?>

                AFN

            </div>

            <small>
                Per transaction
            </small>

        </div>


        <div class="card today">

            <div class="card-title">
                Today's Expenses
            </div>

            <div class="card-value">

                <?php
                echo number_format(
                    $today_expense,
                    2
                );
                ?>

                AFN

            </div>

            <small>
                Current day
            </small>

        </div>

    </div>


    <!-- =========================
         SUMMARY
    ========================= -->

    <div class="grid">


        <!-- EXPENSE SUMMARY -->

        <div class="panel">

            <h2>
                Expense Summary
            </h2>


            <table class="summary-table">


                <tr>

                    <td>
                        Total Expense Records
                    </td>

                    <td class="blue">

                        <?php
                        echo number_format(
                            $total_expense_count
                        );
                        ?>

                    </td>

                </tr>


                <tr>

                    <td>
                        Total Expenses
                    </td>

                    <td class="orange">

                        <?php
                        echo number_format(
                            $total_expenses,
                            2
                        );
                        ?>

                        AFN

                    </td>

                </tr>


                <tr>

                    <td>
                        Average Expense
                    </td>

                    <td class="purple">

                        <?php
                        echo number_format(
                            $average_expense,
                            2
                        );
                        ?>

                        AFN

                    </td>

                </tr>


                <tr>

                    <td>
                        Today's Expenses
                    </td>

                    <td class="green">

                        <?php
                        echo number_format(
                            $today_expense,
                            2
                        );
                        ?>

                        AFN

                    </td>

                </tr>


                <tr>

                    <td>
                        Highest Expense Category
                    </td>

                    <td class="red">

                        <?php
                        echo htmlspecialchars(
                            $top_category
                        );
                        ?>

                    </td>

                </tr>


                <tr>

                    <td>
                        Category Amount
                    </td>

                    <td class="orange">

                        <?php
                        echo number_format(
                            $top_category_amount,
                            2
                        );
                        ?>

                        AFN

                    </td>

                </tr>

            </table>

        </div>


        <!-- CATEGORY REPORT -->

        <div class="panel">

            <h2>
                Expenses by Category
            </h2>


            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>
                                Category
                            </th>

                            <th>
                                Records
                            </th>

                            <th>
                                Total
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        count($categories) > 0
                    ): ?>


                        <?php foreach (
                            $categories
                            as $category
                        ): ?>


                            <tr>

                                <td>

                                    <span
                                        class="category-badge">

                                        <?php
                                        echo htmlspecialchars(
                                            $category["category"]
                                        );
                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php
                                    echo number_format(
                                        $category["expense_count"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <strong>

                                        <?php
                                        echo number_format(
                                            $category["total_amount"],
                                            2
                                        );
                                        ?>

                                        AFN

                                    </strong>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="3"
                                class="empty">

                                No expense categories
                                found.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =========================
         EXPENSE DETAILS
    ========================= -->

    <div class="panel">

        <h2>
            Expense Details
        </h2>


        <div class="table-container">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Title
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            Added By
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


                <?php if (
                    count($expenses) > 0
                ): ?>


                    <?php $number = 1; ?>


                    <?php foreach (
                        $expenses
                        as $expense
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
                                        $expense["title"]
                                    );
                                    ?>

                                </strong>

                            </td>


                            <td>

                                <span
                                    class="category-badge">

                                    <?php
                                    echo htmlspecialchars(
                                        $expense["category"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td class="orange">

                                <strong>

                                    <?php
                                    echo number_format(
                                        $expense["amount"],
                                        2
                                    );
                                    ?>

                                    AFN

                                </strong>

                            </td>


                            <td>

                                <?php

                                $description =
                                    $expense["description"]
                                    ?: "-";

                                echo htmlspecialchars(
                                    $description
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $expense["user_name"]
                                    ?: $expense["username"]
                                    ?: "Unknown"
                                );

                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $expense["expense_date"]
                                );
                                ?>

                            </td>


                            <td class="no-print">

                                <a
                                    href="../expenses/edit.php?id=<?php echo (int)$expense["id"]; ?>"
                                    class="btn"
                                    style="padding:7px 11px;font-size:12px;">

                                    Edit

                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            class="empty">

                            No expenses found
                            for the selected date range.

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

        Expense report generated by

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