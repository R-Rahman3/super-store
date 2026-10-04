<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

/* =========================
   FILTERS
========================= */

$search = trim($_GET['search'] ?? '');
$type   = trim($_GET['type'] ?? '');
$date   = trim($_GET['date'] ?? '');

/* =========================
   SUMMARY
========================= */

$total_customer_payments = 0;
$total_supplier_payments = 0;
$total_payments = 0;
$total_records = 0;

$sql_summary = "
    SELECT
        COUNT(*) AS total_records,
        COALESCE(SUM(CASE 
            WHEN payment_type = 'Customer Payment' THEN amount 
            ELSE 0 
        END), 0) AS customer_total,
        COALESCE(SUM(CASE 
            WHEN payment_type = 'Supplier Payment' THEN amount 
            ELSE 0 
        END), 0) AS supplier_total,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM payments
";

$summary_result = mysqli_query($conn, $sql_summary);

if ($summary_result) {
    $summary = mysqli_fetch_assoc($summary_result);

    $total_records = (int)$summary['total_records'];
    $total_customer_payments = (float)$summary['customer_total'];
    $total_supplier_payments = (float)$summary['supplier_total'];
    $total_payments = (float)$summary['total_amount'];
}

/* =========================
   MAIN QUERY
========================= */

$where = [];
$params = [];
$types = "";

/*
   Search Customer or Supplier name
*/
if ($search !== '') {
    $where[] = "(
        c.name LIKE ?
        OR s.name LIKE ?
        OR p.description LIKE ?
    )";

    $search_param = "%" . $search . "%";

    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;

    $types .= "sss";
}

/*
   Payment Type
*/
if ($type !== '' && in_array($type, ['Customer Payment', 'Supplier Payment'], true)) {
    $where[] = "p.payment_type = ?";
    $params[] = $type;
    $types .= "s";
}

/*
   Date
*/
if ($date !== '') {
    $where[] = "DATE(p.payment_date) = ?";
    $params[] = $date;
    $types .= "s";
}

$where_sql = "";

if (!empty($where)) {
    $where_sql = "WHERE " . implode(" AND ", $where);
}

$sql = "
    SELECT
        p.id,
        p.amount,
        p.payment_type,
        p.description,
        p.payment_date,
        p.customer_id,
        p.supplier_id,

        c.name AS customer_name,
        c.phone AS customer_phone,

        s.name AS supplier_name,
        s.phone AS supplier_phone,

        u.name AS user_name

    FROM payments p

    LEFT JOIN customers c
        ON p.customer_id = c.id

    LEFT JOIN suppliers s
        ON p.supplier_id = s.id

    LEFT JOIN users u
        ON p.user_id = u.id

    $where_sql

    ORDER BY p.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Query preparation failed: " . mysqli_error($conn));
}

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

/* =========================
   SETTINGS / CURRENCY
========================= */

$currency = "AFN";

$settings_result = mysqli_query(
    $conn,
    "SELECT currency FROM settings ORDER BY id ASC LIMIT 1"
);

if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    $settings_data = mysqli_fetch_assoc($settings_result);

    if (!empty($settings_data['currency'])) {
        $currency = $settings_data['currency'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment Management - Super Store</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #222;
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
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.07);

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 25px;
            margin-bottom: 6px;
        }

        .header p {
            color: #777;
            font-size: 14px;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* =========================
           BUTTONS
        ========================= */

        .btn {
            text-decoration: none;
            border: none;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
            transition: 0.2s;
        }

        .btn:hover {
            opacity: 0.88;
            transform: translateY(-1px);
        }

        .btn-customer {
            background: #198754;
            color: #fff;
        }

        .btn-supplier {
            background: #dc3545;
            color: #fff;
        }

        .btn-back {
            background: #343a40;
            color: #fff;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            padding: 7px 11px;
            font-size: 12px;
        }

        /* =========================
           CARDS
        ========================= */

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        .card-title {
            color: #777;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .card-value {
            font-size: 25px;
            font-weight: bold;
        }

        .customer-card {
            border-left: 5px solid #198754;
        }

        .supplier-card {
            border-left: 5px solid #dc3545;
        }

        .total-card {
            border-left: 5px solid #0d6efd;
        }

        .records-card {
            border-left: 5px solid #6f42c1;
        }

        /* =========================
           FILTER
        ========================= */

        .filter-box {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
        }

        .filter-box h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }

        .filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 10px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 7px;
            outline: none;
            background: #fff;
        }

        input:focus,
        select:focus {
            border-color: #0d6efd;
        }

        .search-btn {
            background: #0d6efd;
            color: #fff;
        }

        .clear-btn {
            background: #6c757d;
            color: #fff;
        }

        /* =========================
           TABLE
        ========================= */

        .table-box {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-header h3 {
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th {
            background: #f1f3f5;
            text-align: left;
            padding: 13px 10px;
            font-size: 13px;
            border-bottom: 2px solid #ddd;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            vertical-align: middle;
        }

        tr:hover {
            background: #fafafa;
        }

        .badge {
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .badge-customer {
            background: #d1e7dd;
            color: #0f5132;
        }

        .badge-supplier {
            background: #f8d7da;
            color: #842029;
        }

        .amount {
            font-weight: bold;
            font-size: 14px;
        }

        .customer-name {
            font-weight: bold;
        }

        .phone {
            color: #777;
            font-size: 11px;
            margin-top: 3px;
        }

        .description {
            color: #666;
            max-width: 220px;
            word-wrap: break-word;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #777;
        }

        /* =========================
           PRINT
        ========================= */

        @media print {

            body {
                background: white;
            }

            .header-buttons,
            .filter-box,
            .btn-delete,
            .print-btn {
                display: none !important;
            }

            .container {
                width: 100%;
                margin: 0;
            }

            .card,
            .table-box,
            .header {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            table {
                min-width: 0;
            }
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 600px) {

            .container {
                width: 94%;
                margin: 15px auto;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 21px;
            }

            .header-buttons {
                width: 100%;
            }

            .header-buttons .btn {
                flex: 1;
                text-align: center;
            }

        }

    </style>
</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>
            <h1>💳 Payment Management</h1>
            <p>Manage customer and supplier payments</p>
        </div>

        <div class="header-buttons">

            <a href="customer_payment.php" class="btn btn-customer">
                + Customer Payment
            </a>

            <a href="supplier_payment.php" class="btn btn-supplier">
                + Supplier Payment
            </a>

            <a href="../dashboard.php" class="btn btn-back">
                Dashboard
            </a>

        </div>

    </div>


    <!-- SUMMARY CARDS -->

    <div class="cards">

        <div class="card customer-card">
            <div class="card-title">
                Customer Payments
            </div>

            <div class="card-value">
                <?= number_format($total_customer_payments, 2) ?>
                <?= htmlspecialchars($currency) ?>
            </div>
        </div>


        <div class="card supplier-card">
            <div class="card-title">
                Supplier Payments
            </div>

            <div class="card-value">
                <?= number_format($total_supplier_payments, 2) ?>
                <?= htmlspecialchars($currency) ?>
            </div>
        </div>


        <div class="card total-card">
            <div class="card-title">
                Total Payments
            </div>

            <div class="card-value">
                <?= number_format($total_payments, 2) ?>
                <?= htmlspecialchars($currency) ?>
            </div>
        </div>


        <div class="card records-card">
            <div class="card-title">
                Payment Records
            </div>

            <div class="card-value">
                <?= number_format($total_records) ?>
            </div>
        </div>

    </div>


    <!-- FILTER -->

    <div class="filter-box">

        <h3>🔎 Search & Filter</h3>

        <form method="GET">

            <div class="filters">

                <div class="form-group">

                    <label>Search</label>

                    <input
                        type="text"
                        name="search"
                        placeholder="Customer, Supplier or Description..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Payment Type</label>

                    <select name="type">

                        <option value="">All Payments</option>

                        <option
                            value="Customer Payment"
                            <?= $type === 'Customer Payment' ? 'selected' : '' ?>
                        >
                            Customer Payment
                        </option>

                        <option
                            value="Supplier Payment"
                            <?= $type === 'Supplier Payment' ? 'selected' : '' ?>
                        >
                            Supplier Payment
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Date</label>

                    <input
                        type="date"
                        name="date"
                        value="<?= htmlspecialchars($date) ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="btn search-btn"
                >
                    Search
                </button>


                <a
                    href="manage.php"
                    class="btn clear-btn"
                >
                    Clear
                </a>

            </div>

        </form>

    </div>


    <!-- TABLE -->

    <div class="table-box">

        <div class="table-header">

            <h3>Payment Records</h3>

            <button
                onclick="window.print()"
                class="btn print-btn"
            >
                🖨 Print
            </button>

        </div>


        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>Payment Type</th>

                    <th>Customer / Supplier</th>

                    <th>Amount</th>

                    <th>Description</th>

                    <th>Added By</th>

                    <th>Date</th>

                    <?php if ($user_role === 'Admin'): ?>
                        <th>Action</th>
                    <?php endif; ?>

                </tr>

            </thead>


            <tbody>

            <?php if ($result && mysqli_num_rows($result) > 0): ?>

                <?php $counter = 1; ?>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                    <tr>

                        <td>
                            <?= $counter++ ?>
                        </td>


                        <!-- PAYMENT TYPE -->

                        <td>

                            <?php if ($row['payment_type'] === 'Customer Payment'): ?>

                                <span class="badge badge-customer">
                                    Customer Payment
                                </span>

                            <?php else: ?>

                                <span class="badge badge-supplier">
                                    Supplier Payment
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- CUSTOMER / SUPPLIER -->

                        <td>

                            <?php if ($row['payment_type'] === 'Customer Payment'): ?>

                                <div class="customer-name">
                                    <?= htmlspecialchars($row['customer_name'] ?? 'Unknown Customer') ?>
                                </div>

                                <?php if (!empty($row['customer_phone'])): ?>

                                    <div class="phone">
                                        📞 <?= htmlspecialchars($row['customer_phone']) ?>
                                    </div>

                                <?php endif; ?>

                            <?php else: ?>

                                <div class="customer-name">
                                    <?= htmlspecialchars($row['supplier_name'] ?? 'Unknown Supplier') ?>
                                </div>

                                <?php if (!empty($row['supplier_phone'])): ?>

                                    <div class="phone">
                                        📞 <?= htmlspecialchars($row['supplier_phone']) ?>
                                    </div>

                                <?php endif; ?>

                            <?php endif; ?>

                        </td>


                        <!-- AMOUNT -->

                        <td>

                            <span class="amount">
                                <?= number_format((float)$row['amount'], 2) ?>
                                <?= htmlspecialchars($currency) ?>
                            </span>

                        </td>


                        <!-- DESCRIPTION -->

                        <td>

                            <div class="description">

                                <?php
                                if (!empty($row['description'])) {
                                    echo htmlspecialchars($row['description']);
                                } else {
                                    echo '<span style="color:#aaa;">No description</span>';
                                }
                                ?>

                            </div>

                        </td>


                        <!-- USER -->

                        <td>

                            <?= htmlspecialchars($row['user_name'] ?? 'System') ?>

                        </td>


                        <!-- DATE -->

                        <td>

                            <?= date(
                                'Y-m-d H:i',
                                strtotime($row['payment_date'])
                            ) ?>

                        </td>


                        <!-- ACTION -->

                        <?php if ($user_role === 'Admin'): ?>

                            <td>

                                <form
                                    method="POST"
                                    action="delete.php"
                                    onsubmit="return confirm('Are you sure you want to delete this payment?');"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$row['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-delete"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </td>

                        <?php endif; ?>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="<?= $user_role === 'Admin' ? '8' : '7' ?>"
                        class="empty"
                    >
                        <div style="font-size:40px; margin-bottom:10px;">
                            💳
                        </div>

                        <strong>No payment records found.</strong>

                        <p style="margin-top:6px;">
                            Customer or Supplier payments will appear here.
                        </p>

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>