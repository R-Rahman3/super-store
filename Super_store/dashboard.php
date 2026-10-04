<?php
session_start();

include "db.php";
require_once "language.php";



/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["username"];
$user_role = $_SESSION["role"];


/* =========================
   DASHBOARD STATISTICS
========================= */

// Today's sales
$today_sales = 0;

$sql = "SELECT COALESCE(SUM(total_amount), 0) AS total
        FROM sales
        WHERE DATE(sale_date) = CURDATE()";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $today_sales = $row["total"];
}


// Total sales
$total_sales = 0;

$sql = "SELECT COALESCE(SUM(total_amount), 0) AS total
        FROM sales";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $total_sales = $row["total"];
}


// Total products
$total_products = 0;

$sql = "SELECT COUNT(*) AS total
        FROM products";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $total_products = $row["total"];
}


// Low stock products
$low_stock = 0;

$sql = "SELECT COUNT(*) AS total
        FROM products
        WHERE stock <= min_stock";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $low_stock = $row["total"];
}


// Total customers
$total_customers = 0;

$sql = "SELECT COUNT(*) AS total
        FROM customers";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $total_customers = $row["total"];
}


// Total suppliers
$total_suppliers = 0;

$sql = "SELECT COUNT(*) AS total
        FROM suppliers";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $total_suppliers = $row["total"];
}


// Today's expenses
$today_expenses = 0;

$sql = "SELECT COALESCE(SUM(amount), 0) AS total
        FROM expenses
        WHERE DATE(expense_date) = CURDATE()";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $today_expenses = $row["total"];
}


// Today's profit
$today_profit = 0;

$sql = "SELECT 
        COALESCE(SUM((selling_price - purchase_price) * quantity), 0) AS profit
        FROM sale_items
        WHERE DATE(
            (SELECT sale_date
             FROM sales
             WHERE sales.id = sale_items.sale_id)
        ) = CURDATE()";

$result = mysqli_query($conn, $sql);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $today_profit = $row["profit"] - $today_expenses;
}

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION["language"] === "ps" ? "ps" : "en" ?>" dir="<?= $_SESSION["language"] === "ps" ? "rtl" : "ltr" ?>">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Super Store Dashboard</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f1f5f9;
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

    background: #0f172a;
    color: white;

    padding: 20px;

    overflow-y: auto;
}

.logo {
    text-align: center;
    margin-bottom: 30px;
}

.logo h2 {
    color: #38bdf8;
}

.logo p {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 5px;
}

.menu-title {
    color: #64748b;
    font-size: 12px;
    margin: 20px 0 8px;
    text-transform: uppercase;
}

.sidebar a {
    display: block;

    text-decoration: none;
    color: #cbd5e1;

    padding: 11px 12px;

    border-radius: 6px;

    margin-bottom: 4px;

    transition: 0.2s;
}

.sidebar a:hover {
    background: #1e293b;
    color: white;
}

.sidebar a.active {
    background: #2563eb;
    color: white;
}


/* =========================
   MAIN
========================= */

.main {
    margin-left: 250px;
    padding: 25px;
}


/* =========================
   TOPBAR
========================= */

.topbar {
    background: white;

    padding: 18px 22px;

    border-radius: 10px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 25px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.topbar h1 {
    font-size: 24px;
    color: #0f172a;
}

.user-info {
    text-align: right;
}

.user-info strong {
    color: #0f172a;
}

.user-info span {
    display: block;
    color: #64748b;
    font-size: 13px;
}


/* =========================
   CARDS
========================= */

.cards {
    display: grid;

    grid-template-columns:
    repeat(auto-fit, minmax(220px, 1fr));

    gap: 18px;

    margin-bottom: 25px;
}

.card {
    background: white;

    padding: 20px;

    border-radius: 10px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.05);

    border-left: 4px solid #2563eb;
}

.card h3 {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 10px;
}

.card .number {
    font-size: 25px;
    font-weight: bold;
    color: #0f172a;
}

.card p {
    color: #94a3b8;
    font-size: 12px;
    margin-top: 7px;
}

.card.warning {
    border-left-color: #f59e0b;
}

.card.success {
    border-left-color: #16a34a;
}

.card.danger {
    border-left-color: #dc2626;
}


/* =========================
   QUICK ACTIONS
========================= */

.section {
    background: white;

    padding: 22px;

    border-radius: 10px;

    margin-bottom: 25px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.section h2 {
    font-size: 18px;
    margin-bottom: 18px;
    color: #0f172a;
}

.actions {
    display: grid;

    grid-template-columns:
    repeat(auto-fit, minmax(160px, 1fr));

    gap: 12px;
}

.actions a {
    text-decoration: none;

    background: #f8fafc;

    padding: 15px;

    border-radius: 8px;

    color: #334155;

    text-align: center;

    border: 1px solid #e2e8f0;
}

.actions a:hover {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 768px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
    }

}

@media (max-width: 600px) {

    .sidebar {
        position: relative;

        width: 100%;

        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .user-info {
        text-align: left;
    }

}


/* =========================
   LANGUAGE / RTL
========================= */

body.rtl {
    direction: rtl;
}

body.rtl .sidebar {
    left: auto;
    right: 0;
}

body.rtl .main {
    margin-left: 0;
    margin-right: 250px;
}

body.rtl .card {
    border-left: none;
    border-right: 4px solid #2563eb;
}

body.rtl .card.warning {
    border-right-color: #f59e0b;
}

body.rtl .card.success {
    border-right-color: #16a34a;
}

body.rtl .card.danger {
    border-right-color: #dc2626;
}

body.rtl .user-info {
    text-align: left;
}

.language-box {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
}

.language-box a {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 6px;
    background: #1e293b;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 12px;
}

.language-box a:hover {
    background: #2563eb;
    color: white;
}

@media (max-width: 768px) {
    body.rtl .main {
        margin-left: 0;
        margin-right: 210px;
    }
}

@media (max-width: 600px) {
    body.rtl .main {
        margin-right: 0;
    }

    body.rtl .user-info {
        text-align: right;
    }
}

</style>

</head>

<body class="<?= $_SESSION["language"] === "ps" ? "rtl" : "" ?>">


<!-- =========================
     SIDEBAR
========================= -->

<div class="sidebar">

    <div class="logo">

        <h2>SUPER STORE</h2>

        <p>Management System</p>

    </div>


    <div class="menu-title">
        <?= __('dashboard') ?>
    </div>

    <a href="dashboard.php" class="active">
        <?= __('dashboard') ?>
    </a>
    

    <div class="menu-title">
        <?= __('sales') ?>
    </div>

    <a href="sales/pos.php">
        <?= __('new_sale') ?>
    </a>

    <a href="sales/manage.php">
        <?= __('sales_history') ?>
    </a>


    <div class="menu-title">
        <?= __('stock') ?>
    </div>

    <a href="products/manage.php">
        <?= __('products') ?>
    </a>

    <a href="products/categories/manage.php">
        <?= __('categories') ?>
    </a>

      <a href="stock/manage.php">
        <?= __('stock') ?>
    </a>

     <a href="payment/manage.php">
        <?= __('payments') ?>
    </a>


    <div class="menu-title">
        <?= __('purchases') ?>
    </div>

    <a href="products/parcheses/manage.php">
        <?= __('purchases') ?>
    </a>


    <div class="menu-title">
        <?= __('customers') ?> / <?= __('suppliers') ?>
    </div>

    <a href="customer/manage.php">
        <?= __('customers') ?>
    </a>

    <a href="suppliers/manage.php">
        <?= __('suppliers') ?>
    </a>


    <div class="menu-title">
        <?= __('expenses') ?> / <?= __('payments') ?>
    </div>

    <a href="expense/manage.php">
        <?= __('expenses') ?>
    </a>
      <div class="menu-title">
       Reports
      </div>
    <a href="reports/index.php">
          
        Reports
    </a>
    <a href="reports/customer_report.php">
        <?= __('customer_report') ?>
    </a>
    <a href="reports/daily_report.php">
        <?= __('daily_report') ?>
    </a>
    <a href="reports/weekly_report.php">
        <?= __('weekly_report') ?>
    </a>
    <a href="reports/monthly_report.php">
        <?= __('monthly_report') ?>
    </a>
    <a href="reports/expense_report.php">
        <?= __('expense_report') ?>
    </a>
    <a href="reports/parches_report.php">
        <?= __('purchase_report') ?>
    </a>
    <a href="reports/profit_report.php">
        <?= __('profit_report') ?>
    </a>
    <a href="reports/Sale_report.php">
        <?= __('sales_report') ?>
    </a>
    <a href="reports/stock_report.php">
        <?= __('stock_report') ?>
    </a>
    <a href="reports/supplier_report.php">
        <?= __('supplier_report') ?>
    </a>


    <?php if ($user_role == "Admin"): ?>

        <div class="menu-title">
            <?= __('users') ?> / <?= __('settings') ?>
        </div>

        <a href="users/manage.php">
            Users
        </a>

        <a href="settings.php">
            <?= __('settings') ?>
        </a>
         <div class="menu-title">
           Languages
         </div>
         <div class="language-box">
            <a href="language.php?lang=en">🇬🇧 <?= __('english') ?></a>
            <a href="language.php?lang=ps">🇦🇫 <?= __('pashto') ?></a>
         </div>

    <?php endif; ?>


    <div class="menu-title">
        <?= __('user') ?>
    </div>

    <a href="logout.php">
        <?= __('logout') ?>
    </a>

    <a href="backup/index.php">
        <?= __('backup') ?>
    </a>

</div>


<!-- =========================
     MAIN CONTENT
========================= -->

<div class="main">


    <div class="topbar">

        <div>

            <h1><?= __('dashboard') ?></h1>

            <p style="color:#64748b;">
                <?= $_SESSION["language"] === "ps" ? "د سوپر سټور د مدیریت سیستم ته ښه راغلاست" : "Welcome to Super Store Management System" ?><br>
                د مغازی لپازه بشپړ سیستم
            </p>

        </div>


        <div class="user-info">

            <strong>
                <?php echo htmlspecialchars($user_name); ?>
            </strong>

            <span>
                <?php echo htmlspecialchars($user_role); ?>
            </span>

        </div>

    </div>


    <!-- =========================
         STATISTICS
    ========================= -->

    <div class="cards">


        <div class="card success">

            <h3><?= __('today_sales') ?></h3>

            <div class="number">
                <?php echo number_format($today_sales, 2); ?> AFN
            </div>

            <p><?= $_SESSION["language"] === "ps" ? "د نن ورځې خرڅلاو" : "Sales made today" ?></p>

        </div>


        <div class="card">

            <h3><?= __('total_sales') ?></h3>

            <div class="number">
                <?php echo number_format($total_sales, 2); ?> AFN
            </div>

            <p><?= $_SESSION["language"] === "ps" ? "د ټولو وختونو خرڅلاو" : "All time sales" ?></p>

        </div>


        <div class="card success">

            <h3><?= __('today_profit') ?></h3>

            <div class="number">
                <?php echo number_format($today_profit, 2); ?> AFN
            </div>

            <p><?= $_SESSION["language"] === "ps" ? "د نن ورځې اټکلي ګټه" : "Estimated today's profit" ?></p>

        </div>


        <div class="card danger">

            <h3><?= __('today_expenses') ?></h3>

            <div class="number">
                <?php echo number_format($today_expenses, 2); ?> AFN
            </div>

            <p><?= $_SESSION["language"] === "ps" ? "د نن ورځې مصارف" : "Expenses today" ?></p>

        </div>


        <div class="card">

            <h3><?= __('total_products') ?></h3>

            <div class="number">
                <?php echo number_format($total_products); ?>
            </div>

            <p><?= $_SESSION["language"] === "ps" ? "په سیستم کې محصولات" : "Products in system" ?></p>

        </div>


        <div class="card warning">

            <h3><?= __('low_stock') ?></h3>

            <div class="number">
                <?php echo number_format($low_stock); ?>
            </div>

            <p><?= $_SESSION["language"] === "ps" ? "د بیا اخیستلو اړتیا لرونکي محصولات" : "Products need restocking" ?></p>

        </div>


        <div class="card">

            <h3><?= __('customers') ?></h3>

            <div class="number">
                <?php echo number_format($total_customers); ?>
            </div>

            <p><?= __('total_customers') ?></p>

        </div>


        <div class="card">

            <h3><?= __('suppliers') ?></h3>

            <div class="number">
                <?php echo number_format($total_suppliers); ?>
            </div>

            <p><?= __('total_suppliers') ?></p>

        </div>

    </div>


    <!-- =========================
         QUICK ACTIONS
    ========================= -->

    <div class="section">

        <h2><?= $_SESSION["language"] === "ps" ? "چټک کارونه" : "Quick Actions" ?></h2>

        <div class="actions">

            <a href="sales/pos.php">
                🛒 <?= __('new_sale') ?>
            </a>

            <a href="products/add.php">
                ➕ <?= __('add') ?> <?= __('product') ?>
            </a>

            <a href="purchases/add.php">
                📦 <?= __('purchase') ?>
            </a>

            <a href="customers/add.php">
                👤 <?= __('add') ?> <?= __('customer') ?>
            </a>

            <a href="suppliers/add.php">
                🚚 <?= __('add') ?> <?= __('supplier') ?>
            </a>

            <a href="expenses/add.php">
                💰 <?= __('add') ?> <?= __('expense') ?>
            </a>

        </div>

    </div>


</div>

</body>
</html>