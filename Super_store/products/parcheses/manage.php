<?php
session_start();

include "../../db.php";

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "";

/* =========================
   SEARCH
========================= */

$search = trim($_GET["search"] ?? "");

$search_like = "%" . $search . "%";

/* =========================
   PURCHASES QUERY
========================= */

$sql = "
    SELECT
        purchases.id,
        purchases.invoice_no,
        purchases.total_amount,
        purchases.paid_amount,
        purchases.due_amount,
        purchases.purchase_date,
        suppliers.name AS supplier_name
    FROM purchases
    LEFT JOIN suppliers
        ON purchases.supplier_id = suppliers.id
    WHERE
        purchases.invoice_no LIKE ?
        OR suppliers.name LIKE ?
    ORDER BY purchases.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $search_like,
    $search_like
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Purchases - Super Store</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f1f5f9;
            color: #1e293b;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            background: #0f172a;
            color: white;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
        }

        .user-info {
            font-size: 14px;
            color: #cbd5e1;
        }

        .user-info span {
            color: white;
            font-weight: bold;
        }

        /* =========================
           MAIN
        ========================= */

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .page-title h1 {
            font-size: 28px;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .page-title p {
            color: #64748b;
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 11px 17px;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #475569;
            color: white;
        }

        .btn-secondary:hover {
            background: #334155;
        }

        /* =========================
           SEARCH
        ========================= */

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-form input {
            flex: 1;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }

        .search-form input:focus {
            border-color: #2563eb;
        }

        .search-btn {
            background: #0f172a;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .clear-btn {
            background: #e2e8f0;
            color: #334155;
            padding: 12px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        /* =========================
           TABLE
        ========================= */

        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-header h2 {
            font-size: 18px;
            color: #0f172a;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            text-align: left;
            padding: 14px 15px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #334155;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .invoice {
            font-weight: bold;
            color: #2563eb;
        }

        .amount {
            font-weight: 600;
        }

        .paid {
            color: #15803d;
            font-weight: 600;
        }

        .due {
            color: #dc2626;
            font-weight: 600;
        }

        .supplier {
            font-weight: 600;
            color: #334155;
        }

        /* =========================
           ACTION BUTTONS
        ========================= */

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 7px 11px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }

        .view {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .edit {
            background: #fef3c7;
            color: #b45309;
        }

        .delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* =========================
           EMPTY
        ========================= */

        .empty {
            text-align: center;
            padding: 50px 20px;
            color: #64748b;
        }

        .empty-icon {
            font-size: 45px;
            margin-bottom: 15px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 700px) {

            .header {
                padding: 0 15px;
            }

            .container {
                margin: 20px auto;
                padding: 0 12px;
            }

            .page-title h1 {
                font-size: 23px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-btn,
            .clear-btn {
                text-align: center;
            }

        }

    </style>

</head>

<body>

<!-- =========================
     HEADER
========================= -->

<div class="header">

    <div class="logo">
        Super Store
    </div>

    <div class="user-info">

        Welcome,
        <span>
            <?php echo htmlspecialchars($username); ?>
        </span>

        |
        
        <span>
            <?php echo htmlspecialchars($role); ?>
        </span>

    </div>

</div>


<!-- =========================
     MAIN
========================= -->

<div class="container">

    <div class="page-header">

        <div class="page-title">

            <h1>Purchases</h1>

            <p>
                Manage store purchases and supplier transactions
            </p>

        </div>


        <div class="actions">

            <a href="../../dashboard.php" class="btn btn-secondary">
                Dashboard
            </a>

            <a href="add.php" class="btn btn-primary">
                + New Purchase
            </a>

        </div>

    </div>


    <!-- =========================
         SEARCH
    ========================= -->

    <div class="search-box">

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search by invoice number or supplier name..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit" class="search-btn">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a href="manage.php" class="clear-btn">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =========================
         TABLE
    ========================= -->

    <div class="table-card">

        <div class="table-header">

            <h2>
                Purchase Records
            </h2>

        </div>


        <div class="table-responsive">

            <?php if (mysqli_num_rows($result) > 0): ?>

                <table>

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Invoice No</th>

                            <th>Supplier</th>

                            <th>Total Amount</th>

                            <th>Paid Amount</th>

                            <th>Due Amount</th>

                            <th>Purchase Date</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php
                    $counter = 1;

                    while ($row = mysqli_fetch_assoc($result)):
                    ?>

                        <tr>

                            <td>
                                <?php echo $counter++; ?>
                            </td>


                            <td>

                                <span class="invoice">

                                    <?php
                                    echo htmlspecialchars(
                                        $row["invoice_no"]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span class="supplier">

                                    <?php
                                    echo htmlspecialchars(
                                        $row["supplier_name"] ?? "Walk-in Supplier"
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <span class="amount">

                                    <?php
                                    echo number_format(
                                        (float)$row["total_amount"],
                                        2
                                    );
                                    ?>

                                    AFN

                                </span>

                            </td>


                            <td>

                                <span class="paid">

                                    <?php
                                    echo number_format(
                                        (float)$row["paid_amount"],
                                        2
                                    );
                                    ?>

                                    AFN

                                </span>

                            </td>


                            <td>

                                <span class="due">

                                    <?php
                                    echo number_format(
                                        (float)$row["due_amount"],
                                        2
                                    );
                                    ?>

                                    AFN

                                </span>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime($row["purchase_date"])
                                );
                                ?>

                            </td>


                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="view.php?id=<?php echo (int)$row["id"]; ?>"
                                        class="action-btn view"
                                    >
                                        View
                                    </a>


                                    <a
                                        href="edit.php?id=<?php echo (int)$row["id"]; ?>"
                                        class="action-btn edit"
                                    >
                                        Edit
                                    </a>


                                    <?php if ($role === "Admin"): ?>

                                        <form
                                            method="POST"
                                            action="delete.php"
                                            style="display:inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this purchase? Product stock will also be affected.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int)$row["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn delete"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    <div class="empty-icon">
                        🛒
                    </div>

                    <h3>
                        No Purchase Records Found
                    </h3>

                    <p>
                        <?php
                        if ($search !== "") {
                            echo "No purchase matched your search.";
                        } else {
                            echo "You have not added any purchases yet.";
                        }
                        ?>
                    </p>

                    <br>

                    <a
                        href="add.php"
                        class="btn btn-primary"
                    >
                        + Add First Purchase
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


</body>

</html>

<?php

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>