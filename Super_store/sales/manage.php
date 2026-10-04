<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";
$role     = $_SESSION["role"] ?? "Cashier";

$search = trim($_GET["search"] ?? "");


/* =========================
   SALES QUERY
========================= */

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
        users.name AS cashier_name

    FROM sales

    LEFT JOIN customers
        ON sales.customer_id = customers.id

    LEFT JOIN users
        ON sales.user_id = users.id

    WHERE
        sales.invoice_no LIKE ?
        OR customers.name LIKE ?
        OR users.name LIKE ?

    ORDER BY sales.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

$search_param = "%" . $search . "%";

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $search_param,
    $search_param,
    $search_param
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/* =========================
   SUCCESS / ERROR
========================= */

$success = $_GET["success"] ?? "";
$error   = $_GET["error"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sales History - Super Store</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        /* =========================
           TOPBAR
        ========================= */

        .topbar {
            background: #0f172a;
            color: white;
            min-height: 70px;
            padding: 0 25px;

            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            font-size: 22px;
            font-weight: bold;
        }

        .user-info {
            font-size: 14px;
        }

        .role {
            display: inline-block;
            background: #1e293b;
            padding: 6px 10px;
            border-radius: 6px;
            margin-left: 8px;
        }


        /* =========================
           CONTAINER
        ========================= */

        .container {
            padding: 25px;
        }


        /* =========================
           HEADER
        ========================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 27px;
        }

        .page-header p {
            margin: 6px 0 0;
            color: #64748b;
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
            border: none;
            padding: 10px 14px;
            border-radius: 7px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-blue {
            background: #2563eb;
            color: white;
        }

        .btn-dark {
            background: #0f172a;
            color: white;
        }

        .btn-green {
            background: #16a34a;
            color: white;
        }

        .btn-red {
            background: #dc2626;
            color: white;
        }

        .btn-gray {
            background: #64748b;
            color: white;
        }

        .btn-small {
            padding: 7px 9px;
            font-size: 12px;
        }


        /* =========================
           ALERTS
        ========================= */

        .alert {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }


        /* =========================
           SEARCH CARD
        ========================= */

        .search-card {
            background: white;
            padding: 18px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.07);
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            outline: none;
            font-size: 14px;
        }

        .search-input:focus {
            border-color: #2563eb;
        }


        /* =========================
           TABLE CARD
        ========================= */

        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.07);
            overflow: hidden;
        }

        .table-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-align: left;
            padding: 13px 10px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            vertical-align: middle;
        }

        tr:hover td {
            background: #f8fafc;
        }


        /* =========================
           INVOICE
        ========================= */

        .invoice {
            font-weight: bold;
            color: #2563eb;
        }


        /* =========================
           AMOUNTS
        ========================= */

        .amount {
            font-weight: 600;
            white-space: nowrap;
        }

        .paid {
            color: #16a34a;
        }

        .due {
            color: #dc2626;
            font-weight: bold;
        }

        .no-due {
            color: #16a34a;
        }


        /* =========================
           PAYMENT BADGE
        ========================= */

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
        }

        .cash {
            background: #dcfce7;
            color: #166534;
        }

        .card {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .credit {
            background: #fee2e2;
            color: #991b1b;
        }


        /* =========================
           ACTIONS
        ========================= */

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .delete-form {
            display: inline;
        }


        /* =========================
           EMPTY
        ========================= */

        .empty {
            text-align: center;
            padding: 55px 20px;
            color: #94a3b8;
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 700px) {

            .topbar {
                padding: 15px;
            }

            .brand {
                font-size: 18px;
            }

            .user-info {
                display: none;
            }

            .container {
                padding: 15px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-form {
                flex-direction: column;
            }

        }

    </style>

</head>

<body>


<!-- =========================
     TOPBAR
========================= -->

<div class="topbar">

    <div class="brand">
        🛒 Super Store
    </div>

    <div class="user-info">

        <?php echo htmlspecialchars($username); ?>

        <span class="role">
            <?php echo htmlspecialchars($role); ?>
        </span>

    </div>

</div>


<div class="container">


    <!-- =========================
         PAGE HEADER
    ========================= -->

    <div class="page-header">

        <div>

            <h1>
                Sales History
            </h1>

            <p>
                Manage and view all sales transactions.
            </p>

        </div>


        <div class="header-buttons">

            <a
                href="pos.php"
                class="btn btn-green"
            >
                + New Sale
            </a>

            <a
                href="../dashboard.php"
                class="btn btn-dark"
            >
                Dashboard
            </a>

        </div>

    </div>


    <!-- =========================
         SUCCESS MESSAGE
    ========================= -->

    <?php if ($success === "added"): ?>

        <div class="alert success">
            ✓ Sale completed successfully.
        </div>

    <?php elseif ($success === "deleted"): ?>

        <div class="alert success">
            ✓ Sale deleted successfully.
        </div>

    <?php elseif ($success === "updated"): ?>

        <div class="alert success">
            ✓ Sale updated successfully.
        </div>

    <?php endif; ?>


    <!-- =========================
         ERROR MESSAGE
    ========================= -->

    <?php if ($error !== ""): ?>

        <div class="alert danger">

            <?php

            if ($error === "not_found") {

                echo "Sale was not found.";

            } elseif ($error === "access_denied") {

                echo "Access denied. Only administrator can perform this action.";

            } elseif ($error === "has_history") {

                echo "This sale cannot be deleted because it has related records.";

            } elseif ($error === "delete_failed") {

                echo "Unable to delete the sale.";

            } elseif ($error === "invalid_id") {

                echo "Invalid sale ID.";

            } else {

                echo htmlspecialchars($error);

            }

            ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         SEARCH
    ========================= -->

    <div class="search-card">

        <form
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by invoice, customer or cashier..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button
                type="submit"
                class="btn btn-blue"
            >
                🔍 Search
            </button>

            <?php if ($search !== ""): ?>

                <a
                    href="manage.php"
                    class="btn btn-gray"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =========================
         SALES TABLE
    ========================= -->

    <div class="table-card">

        <div class="table-header">

            <h2>
                Sales Transactions
            </h2>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Invoice
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Cashier
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
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $counter = 1;

                if (mysqli_num_rows($result) > 0):

                    while ($row = mysqli_fetch_assoc($result)):

                        $payment =
                            $row["payment_method"];

                ?>

                    <tr>

                        <!-- NUMBER -->

                        <td>
                            <?php echo $counter++; ?>
                        </td>


                        <!-- INVOICE -->

                        <td>

                            <span class="invoice">

                                <?php
                                echo htmlspecialchars(
                                    $row["invoice_no"]
                                );
                                ?>

                            </span>

                        </td>


                        <!-- CUSTOMER -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $row["customer_name"]
                                ?: "Walk-in Customer"
                            );

                            ?>

                        </td>


                        <!-- CASHIER -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $row["cashier_name"]
                                ?: "Unknown"
                            );

                            ?>

                        </td>


                        <!-- SUBTOTAL -->

                        <td class="amount">

                            <?php
                            echo number_format(
                                (float)$row["subtotal"],
                                2
                            );
                            ?>

                            AFN

                        </td>


                        <!-- DISCOUNT -->

                        <td class="amount">

                            <?php
                            echo number_format(
                                (float)$row["discount"],
                                2
                            );
                            ?>

                            AFN

                        </td>


                        <!-- TOTAL -->

                        <td class="amount">

                            <?php
                            echo number_format(
                                (float)$row["total_amount"],
                                2
                            );
                            ?>

                            AFN

                        </td>


                        <!-- PAID -->

                        <td class="amount paid">

                            <?php
                            echo number_format(
                                (float)$row["paid_amount"],
                                2
                            );
                            ?>

                            AFN

                        </td>


                        <!-- DUE -->

                        <td
                            class="
                                amount
                                <?php
                                echo (
                                    (float)$row["due_amount"] > 0
                                )
                                ? "due"
                                : "no-due";
                                ?>
                            "
                        >

                            <?php
                            echo number_format(
                                (float)$row["due_amount"],
                                2
                            );
                            ?>

                            AFN

                        </td>


                        <!-- PAYMENT METHOD -->

                        <td>

                            <?php

                            if ($payment === "Cash"):

                            ?>

                                <span class="badge cash">
                                    CASH
                                </span>

                            <?php

                            elseif ($payment === "Card"):

                            ?>

                                <span class="badge card">
                                    CARD
                                </span>

                            <?php

                            else:

                            ?>

                                <span class="badge credit">
                                    CREDIT
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- DATE -->

                        <td>

                            <?php

                            echo date(
                                "Y-m-d H:i",
                                strtotime($row["sale_date"])
                            );

                            ?>

                        </td>


                        <!-- ACTIONS -->

                        <td>

                            <div class="actions">


                                <!-- VIEW -->

                                <a
                                    href="view.php?id=<?php echo $row["id"]; ?>"
                                    class="btn btn-blue btn-small"
                                >
                                    View
                                </a>


                                <!-- RECEIPT -->

                                <a
                                    href="receipt.php?id=<?php echo $row["id"]; ?>"
                                    target="_blank"
                                    class="btn btn-green btn-small"
                                >
                                    🖨 Receipt
                                </a>


                                <!-- DELETE -->

                                <?php if ($role === "Admin"): ?>

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        class="delete-form"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to delete this sale? Stock and customer balance will also be affected.'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo $row["id"]; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-red btn-small"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                <?php endif; ?>


                            </div>

                        </td>

                    </tr>

                <?php

                    endwhile;

                else:

                ?>

                    <tr>

                        <td
                            colspan="12"
                            class="empty"
                        >

                            <div class="empty-icon">
                                📋
                            </div>

                            <strong>
                                No sales found.
                            </strong>

                            <br><br>

                            <?php if ($search !== ""): ?>

                                Try another search.

                            <?php else: ?>

                                No sales transactions have been recorded yet.

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


</body>

</html>

<?php

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>