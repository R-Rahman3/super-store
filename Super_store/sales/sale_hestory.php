<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";
$role     = $_SESSION["role"] ?? "";


/* =========================
   SEARCH
========================= */

$search = trim($_GET["search"] ?? "");

$search_value = "%" . $search . "%";


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

        users.name AS cashier_name,
        users.username AS cashier_username

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

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $search_value,
    $search_value,
    $search_value
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/* =========================
   MESSAGES
========================= */

$success = $_GET["success"] ?? "";
$error   = $_GET["error"] ?? "";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sales History - Super Store</title>


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


        /* =========================
           TOP BAR
        ========================= */

        .topbar {

            background: #111827;

            color: white;

            padding: 17px 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;
        }


        .topbar h2 {

            margin: 0;

            font-size: 21px;
        }


        .user-info {

            font-size: 14px;

            color: #d1d5db;
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

            font-size: 28px;
        }


        .buttons {

            display: flex;

            gap: 9px;

            flex-wrap: wrap;
        }


        .btn {

            display: inline-block;

            padding: 10px 15px;

            border-radius: 7px;

            border: none;

            text-decoration: none;

            cursor: pointer;

            font-size: 14px;

            font-weight: bold;
        }


        .btn-blue {

            background: #2563eb;

            color: white;
        }


        .btn-dark {

            background: #111827;

            color: white;
        }


        .btn-green {

            background: #16a34a;

            color: white;
        }


        .btn-gray {

            background: #6b7280;

            color: white;
        }


        .btn-red {

            background: #dc2626;

            color: white;
        }


        .btn-small {

            padding: 7px 10px;

            font-size: 12px;
        }


        /* =========================
           CARD
        ========================= */

        .card {

            background: white;

            border-radius: 12px;

            padding: 22px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,0.06);
        }


        /* =========================
           ALERT
        ========================= */

        .alert {

            padding: 13px 16px;

            border-radius: 8px;

            margin-bottom: 18px;

            font-size: 14px;

            font-weight: bold;
        }


        .alert-success {

            background: #dcfce7;

            color: #166534;

            border: 1px solid #bbf7d0;
        }


        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;
        }


        /* =========================
           SEARCH
        ========================= */

        .search-box {

            display: flex;

            gap: 10px;

            margin-bottom: 20px;
        }


        .search-box input {

            flex: 1;

            padding: 11px 13px;

            border:
                1px solid #d1d5db;

            border-radius: 7px;

            font-size: 14px;

            outline: none;
        }


        .search-box input:focus {

            border-color: #2563eb;
        }


        /* =========================
           TABLE
        ========================= */

        .table-wrapper {

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 1250px;
        }


        th,
        td {

            padding: 13px 10px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;

            font-size: 13px;

            white-space: nowrap;
        }


        th {

            background: #f8fafc;

            font-weight: bold;

            color: #374151;
        }


        tbody tr:hover {

            background: #f9fafb;
        }


        .invoice {

            font-weight: bold;

            color: #2563eb;
        }


        .amount {

            text-align: right;
        }


        .due {

            color: #dc2626;

            font-weight: bold;
        }


        .paid {

            color: #16a34a;

            font-weight: bold;
        }


        /* =========================
           PAYMENT BADGES
        ========================= */

        .badge {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;
        }


        .badge-cash {

            background: #dcfce7;

            color: #166534;
        }


        .badge-card {

            background: #dbeafe;

            color: #1d4ed8;
        }


        .badge-credit {

            background: #fef3c7;

            color: #92400e;
        }


        /* =========================
           ACTIONS
        ========================= */

        .actions {

            display: flex;

            gap: 6px;

            align-items: center;
        }


        .actions form {

            margin: 0;
        }


        /* =========================
           EMPTY
        ========================= */

        .empty {

            text-align: center;

            padding: 45px 20px;

            color: #6b7280;
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

                flex-direction: column;

                align-items: flex-start;
            }


            .page-header {

                flex-direction: column;

                align-items: flex-start;
            }


            .search-box {

                flex-direction: column;
            }


            .search-box .btn {

                width: 100%;

                text-align: center;
            }

        }

    </style>

</head>


<body>


<!-- =========================
     TOP BAR
========================= -->

<div class="topbar">

    <h2>
        Super Store Management System
    </h2>


    <div class="user-info">

        <?php
        echo htmlspecialchars($username);
        ?>

        |

        <?php
        echo htmlspecialchars($role);
        ?>

    </div>

</div>



<div class="container">


    <!-- =========================
         HEADER
    ========================= -->

    <div class="page-header">

        <h1>
            Sales History
        </h1>


        <div class="buttons">

            <a
                href="pos.php"
                class="btn btn-blue"
            >
                + New Sale
            </a>


            <a
                href="../dashboard.php"
                class="btn btn-gray"
            >
                Dashboard
            </a>

        </div>

    </div>



    <!-- =========================
         ALERTS
    ========================= -->

    <?php if ($success === "added"): ?>

        <div class="alert alert-success">
            Sale added successfully.
        </div>

    <?php elseif ($success === "deleted"): ?>

        <div class="alert alert-success">
            Sale deleted successfully.
            Stock and customer balance have been updated.
        </div>

    <?php elseif ($success === "updated"): ?>

        <div class="alert alert-success">
            Sale updated successfully.
        </div>

    <?php endif; ?>


    <?php if ($error === "not_found"): ?>

        <div class="alert alert-error">
            Sale not found.
        </div>

    <?php elseif ($error === "access_denied"): ?>

        <div class="alert alert-error">
            Access denied. Only Admin can perform this action.
        </div>

    <?php elseif ($error === "invalid_id"): ?>

        <div class="alert alert-error">
            Invalid sale ID.
        </div>

    <?php elseif ($error === "has_history"): ?>

        <div class="alert alert-error">
            This sale could not be deleted because related history exists.
        </div>

    <?php elseif ($error === "delete_failed"): ?>

        <div class="alert alert-error">
            Sale deletion failed. Please try again.
        </div>

    <?php elseif ($error === "invalid_request"): ?>

        <div class="alert alert-error">
            Invalid request.
        </div>

    <?php elseif ($error !== ""): ?>

        <div class="alert alert-error">

            An unexpected error occurred.

        </div>

    <?php endif; ?>



    <!-- =========================
         MAIN CARD
    ========================= -->

    <div class="card">


        <!-- SEARCH -->

        <form
            method="GET"
            class="search-box"
        >

            <input
                type="text"
                name="search"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search by invoice, customer or cashier..."
            >


            <button
                type="submit"
                class="btn btn-dark"
            >
                Search
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



        <!-- TABLE -->

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

                        <th class="amount">
                            Subtotal
                        </th>

                        <th class="amount">
                            Discount
                        </th>

                        <th class="amount">
                            Total
                        </th>

                        <th class="amount">
                            Paid
                        </th>

                        <th class="amount">
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

                if (
                    mysqli_num_rows($result) > 0
                ):

                ?>


                    <?php
                    while (
                        $row =
                        mysqli_fetch_assoc($result)
                    ):
                    ?>


                        <tr>


                            <!-- NUMBER -->

                            <td>

                                <?php
                                echo $counter++;
                                ?>

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
                                    ?:
                                    $row["cashier_username"]
                                    ?:
                                    "Unknown"
                                );

                                ?>

                            </td>



                            <!-- SUBTOTAL -->

                            <td class="amount">

                                AFN

                                <?php

                                echo number_format(
                                    (float)$row["subtotal"],
                                    2
                                );

                                ?>

                            </td>



                            <!-- DISCOUNT -->

                            <td class="amount">

                                AFN

                                <?php

                                echo number_format(
                                    (float)$row["discount"],
                                    2
                                );

                                ?>

                            </td>



                            <!-- TOTAL -->

                            <td class="amount">

                                <strong>

                                    AFN

                                    <?php

                                    echo number_format(
                                        (float)$row["total_amount"],
                                        2
                                    );

                                    ?>

                                </strong>

                            </td>



                            <!-- PAID -->

                            <td class="amount paid">

                                AFN

                                <?php

                                echo number_format(
                                    (float)$row["paid_amount"],
                                    2
                                );

                                ?>

                            </td>



                            <!-- DUE -->

                            <td
                                class="amount
                                <?php
                                if (
                                    (float)$row["due_amount"] > 0
                                ) {
                                    echo "due";
                                }
                                ?>"
                            >

                                AFN

                                <?php

                                echo number_format(
                                    (float)$row["due_amount"],
                                    2
                                );

                                ?>

                            </td>



                            <!-- PAYMENT -->

                            <td>

                                <?php

                                $payment =
                                    $row["payment_method"];

                                if ($payment === "Cash"):

                                ?>

                                    <span
                                        class="badge badge-cash"
                                    >
                                        Cash
                                    </span>

                                <?php

                                elseif (
                                    $payment === "Card"
                                ):

                                ?>

                                    <span
                                        class="badge badge-card"
                                    >
                                        Card
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge badge-credit"
                                    >
                                        Credit
                                    </span>

                                <?php endif; ?>

                            </td>



                            <!-- DATE -->

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $row["sale_date"]
                                );

                                ?>

                            </td>



                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <!-- VIEW -->

                                    <a
                                        href="veiw.php?id=<?php echo $row["id"]; ?>"
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
                                        Receipt
                                    </a>



                                    <!-- DELETE -->

                                    <?php
                                    if ($role === "Admin"):
                                    ?>

                                        <form
                                            method="POST"
                                            action="delete.php"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this sale? Stock and customer balance will also be affected.'
                                            );"
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


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="12"
                            class="empty"
                        >

                            <div class="empty-icon">
                                🧾
                            </div>

                            <strong>
                                No sales found
                            </strong>

                            <br>

                            <span>
                                There are no sales matching your search.
                            </span>

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