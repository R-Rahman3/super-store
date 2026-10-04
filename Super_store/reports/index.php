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

/* =========================
   SALES REPORT
========================= */

$total_sales = 0;
$total_paid = 0;
$total_due = 0;
$total_discount = 0;

$sql = "
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due,
        COALESCE(SUM(discount), 0) AS total_discount
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$sales_summary = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$total_sales = (float) $sales_summary["total_sales"];
$total_paid = (float) $sales_summary["total_paid"];
$total_due = (float) $sales_summary["total_due"];
$total_discount = (float) $sales_summary["total_discount"];


/* =========================
   PURCHASE REPORT
========================= */

$total_purchases = 0;
$total_purchase_paid = 0;
$total_purchase_due = 0;

$sql = "
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_purchases,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(due_amount), 0) AS total_due
    FROM purchases
    WHERE purchase_date BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$purchase_summary = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$total_purchases = (float) $purchase_summary["total_purchases"];
$total_purchase_paid = (float) $purchase_summary["total_paid"];
$total_purchase_due = (float) $purchase_summary["total_due"];


/* =========================
   EXPENSE REPORT
========================= */

$total_expenses = 0;

$sql = "
    SELECT
        COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$expense_summary = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$total_expenses = (float) $expense_summary["total_expenses"];


/* =========================
   PROFIT CALCULATION
========================= */

$total_cost = 0;

$sql = "
    SELECT
        COALESCE(SUM(quantity * purchase_price), 0) AS total_cost
    FROM sale_items
    INNER JOIN sales
        ON sale_items.sale_id = sales.id
    WHERE sales.sale_date BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$cost_summary = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$total_cost = (float) $cost_summary["total_cost"];

/*
    Gross Profit:
    Sales - Cost - Discount
*/

$gross_profit = $total_sales - $total_cost - $total_discount;

/*
    Net Profit:
    Gross Profit - Expenses
*/

$net_profit = $gross_profit - $total_expenses;


/* =========================
   SALES COUNT
========================= */

$sales_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

$sales_count = (int) $row["total"];

mysqli_stmt_close($stmt);


/* =========================
   PURCHASE COUNT
========================= */

$purchase_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM purchases
    WHERE purchase_date BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

$purchase_count = (int) $row["total"];

mysqli_stmt_close($stmt);


/* =========================
   EXPENSE COUNT
========================= */

$expense_count = 0;

$sql = "
    SELECT COUNT(*) AS total
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

$expense_count = (int) $row["total"];

mysqli_stmt_close($stmt);


/* =========================
   PAYMENT METHOD REPORT
========================= */

$cash_sales = 0;
$card_sales = 0;
$credit_sales = 0;

$sql = "
    SELECT
        payment_method,
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
    GROUP BY payment_method
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
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


/* =========================
   TOP SELLING PRODUCTS
========================= */

$top_products = [];

$sql = "
    SELECT
        products.name,
        SUM(sale_items.quantity) AS total_quantity,
        SUM(sale_items.total) AS total_sales
    FROM sale_items
    INNER JOIN sales
        ON sale_items.sale_id = sales.id
    INNER JOIN products
        ON sale_items.product_id = products.id
    WHERE sales.sale_date BETWEEN ? AND ?
    GROUP BY sale_items.product_id
    ORDER BY total_quantity DESC
    LIMIT 10
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $top_products[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   EXPENSE BY CATEGORY
========================= */

$expense_categories = [];

$sql = "
    SELECT
        category,
        SUM(amount) AS total
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $from_date, $to_date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $expense_categories[] = $row;
}

mysqli_stmt_close($stmt);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reports | Super Store</title>

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

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: #17202a;
    color: white;
    padding: 25px 15px;
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
    margin-top: 5px;
}

.filter-box {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.filter-box form {
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
    font-size: 25px;
    font-weight: bold;
}

.sales {
    border-left: 5px solid #2874a6;
}

.purchases {
    border-left: 5px solid #8e44ad;
}

.expenses {
    border-left: 5px solid #e67e22;
}

.profit {
    border-left: 5px solid #27ae60;
}

.card small {
    color: #888;
    display: block;
    margin-top: 7px;
}

.section-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

.panel {
    background: white;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.panel h2 {
    font-size: 19px;
    margin-bottom: 18px;
}

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

.table-container {
    overflow-x: auto;
}

table.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.data-table th {
    background: #f8f9f9;
}

.empty {
    text-align: center;
    padding: 20px;
    color: #888;
}

@media(max-width: 1100px) {

    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

    .section-grid {
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

    .filter-box form {
        display: block;
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group input,
    .btn {
        width: 100%;
    }
}

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

}

</style>

</head>

<body>


<!-- SIDEBAR -->

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

        <a href="index.php" class="active">
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


<!-- MAIN -->

<div class="main">

    <div class="header">

        <div>

            <h1>Financial Reports</h1>

            <p>
                Business performance from
                <strong><?php echo htmlspecialchars($from_date); ?></strong>
                to
                <strong><?php echo htmlspecialchars($to_date); ?></strong>
            </p>

        </div>

        <button
            onclick="window.print()"
            class="btn btn-print no-print"
        >
            🖨 Print Report
        </button>

    </div>


    <!-- FILTER -->

    <div class="filter-box no-print">

        <form method="GET">

            <div class="form-group">

                <label>From Date</label>

                <input
                    type="date"
                    name="from_date"
                    value="<?php echo htmlspecialchars($from_date); ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>To Date</label>

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


    <!-- MAIN CARDS -->

    <div class="cards">

        <div class="card sales">

            <div class="card-title">
                Total Sales
            </div>

            <div class="card-value">
                <?php echo number_format($total_sales, 2); ?> AFN
            </div>

            <small>
                <?php echo $sales_count; ?> sales
            </small>

        </div>


        <div class="card purchases">

            <div class="card-title">
                Total Purchases
            </div>

            <div class="card-value">
                <?php echo number_format($total_purchases, 2); ?> AFN
            </div>

            <small>
                <?php echo $purchase_count; ?> purchases
            </small>

        </div>


        <div class="card expenses">

            <div class="card-title">
                Total Expenses
            </div>

            <div class="card-value">
                <?php echo number_format($total_expenses, 2); ?> AFN
            </div>

            <small>
                <?php echo $expense_count; ?> expenses
            </small>

        </div>


        <div class="card profit">

            <div class="card-title">
                Net Profit
            </div>

            <div class="card-value">
                <?php echo number_format($net_profit, 2); ?> AFN
            </div>

            <small>
                Gross Profit:
                <?php echo number_format($gross_profit, 2); ?> AFN
            </small>

        </div>

    </div>


    <!-- FINANCIAL SUMMARY -->

    <div class="section-grid">

        <div class="panel">

            <h2>Financial Summary</h2>

            <table class="summary-table">

                <tr>
                    <td>Total Sales</td>
                    <td class="blue">
                        <?php echo number_format($total_sales, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Sales Discount</td>
                    <td class="orange">
                        <?php echo number_format($total_discount, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Cost of Goods Sold</td>
                    <td class="red">
                        <?php echo number_format($total_cost, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Gross Profit</td>
                    <td class="green">
                        <?php echo number_format($gross_profit, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Operating Expenses</td>
                    <td class="red">
                        <?php echo number_format($total_expenses, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td><strong>Net Profit</strong></td>
                    <td class="green">
                        <strong>
                            <?php echo number_format($net_profit, 2); ?> AFN
                        </strong>
                    </td>
                </tr>

            </table>

        </div>


        <!-- PAYMENT SUMMARY -->

        <div class="panel">

            <h2>Payment Method Summary</h2>

            <table class="summary-table">

                <tr>
                    <td>Cash Sales</td>

                    <td class="green">
                        <?php echo number_format($cash_sales, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Card Sales</td>

                    <td class="blue">
                        <?php echo number_format($card_sales, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Credit Sales</td>

                    <td class="red">
                        <?php echo number_format($credit_sales, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Total Paid</td>

                    <td>
                        <?php echo number_format($total_paid, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Total Customer Due</td>

                    <td class="red">
                        <?php echo number_format($total_due, 2); ?> AFN
                    </td>
                </tr>

            </table>

        </div>

    </div>


    <!-- PURCHASE SUMMARY -->

    <div class="section-grid">

        <div class="panel">

            <h2>Purchase Summary</h2>

            <table class="summary-table">

                <tr>
                    <td>Total Purchases</td>

                    <td>
                        <?php echo number_format($total_purchases, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Paid to Suppliers</td>

                    <td class="green">
                        <?php echo number_format($total_purchase_paid, 2); ?> AFN
                    </td>
                </tr>

                <tr>
                    <td>Supplier Due</td>

                    <td class="red">
                        <?php echo number_format($total_purchase_due, 2); ?> AFN
                    </td>
                </tr>

            </table>

        </div>


        <!-- EXPENSE CATEGORY -->

        <div class="panel">

            <h2>Expenses by Category</h2>

            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>
                            <th>Category</th>
                            <th>Total</th>
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
                                    <?php
                                    echo number_format(
                                        $expense["total"],
                                        2
                                    );
                                    ?>
                                    AFN
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="2" class="empty">
                                No expenses found.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- TOP PRODUCTS -->

    <div class="panel">

        <h2>Top Selling Products</h2>

        <div class="table-container">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Product</th>

                        <th>Quantity Sold</th>

                        <th>Total Sales</th>

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
                                <?php
                                echo htmlspecialchars(
                                    $product["name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    $product["total_quantity"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo number_format(
                                    $product["total_sales"],
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
                            No sales found for this period.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <br>

    <div style="text-align:center;color:#888;">

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