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
   SALE ID
========================= */

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    header("Location: manage.php?error=invalid_id");
    exit();
}


/* =========================
   GET SALE
========================= */

$sql = "
    SELECT
        sales.id,
        sales.invoice_no,
        sales.customer_id,
        sales.user_id,
        sales.subtotal,
        sales.discount,
        sales.total_amount,
        sales.paid_amount,
        sales.due_amount,
        sales.payment_method,
        sales.sale_date,

        customers.name AS customer_name,
        customers.phone AS customer_phone,
        customers.address AS customer_address,

        users.name AS cashier_name,
        users.username AS cashier_username

    FROM sales

    LEFT JOIN customers
        ON sales.customer_id = customers.id

    LEFT JOIN users
        ON sales.user_id = users.id

    WHERE sales.id = ?

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {

    mysqli_stmt_close($stmt);

    header("Location: manage.php?error=not_found");
    exit();
}

$sale = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================
   GET SALE ITEMS
========================= */

$items_sql = "
    SELECT
        sale_items.id,
        sale_items.product_id,
        sale_items.quantity,
        sale_items.selling_price,
        sale_items.purchase_price,
        sale_items.total,

        products.name AS product_name,
        products.barcode,
        products.unit

    FROM sale_items

    INNER JOIN products
        ON sale_items.product_id = products.id

    WHERE sale_items.sale_id = ?

    ORDER BY sale_items.id ASC
";

$items_stmt = mysqli_prepare(
    $conn,
    $items_sql
);

if (!$items_stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $items_stmt,
    "i",
    $id
);

mysqli_stmt_execute($items_stmt);

$items_result =
    mysqli_stmt_get_result($items_stmt);


/* =========================
   PROFIT CALCULATION
========================= */

$total_profit = 0;

while ($item = mysqli_fetch_assoc($items_result)) {

    $quantity =
        (float)$item["quantity"];

    $selling_price =
        (float)$item["selling_price"];

    $purchase_price =
        (float)$item["purchase_price"];

    $total_profit +=
        ($selling_price - $purchase_price)
        * $quantity;
}


/*
 * Re-run query because the first
 * result has been consumed.
 */

mysqli_stmt_close($items_stmt);


$items_stmt = mysqli_prepare(
    $conn,
    $items_sql
);

mysqli_stmt_bind_param(
    $items_stmt,
    "i",
    $id
);

mysqli_stmt_execute($items_stmt);

$items_result =
    mysqli_stmt_get_result($items_stmt);


/* =========================
   VALUES
========================= */

$subtotal =
    (float)$sale["subtotal"];

$discount =
    (float)$sale["discount"];

$total =
    (float)$sale["total_amount"];

$paid =
    (float)$sale["paid_amount"];

$due =
    (float)$sale["due_amount"];

$change =
    max($paid - $total, 0);


/* =========================
   PAYMENT BADGE
========================= */

$payment_class = "cash";

if ($sale["payment_method"] === "Card") {
    $payment_class = "card";
}

if ($sale["payment_method"] === "Credit") {
    $payment_class = "credit";
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        View Sale -
        <?php echo htmlspecialchars($sale["invoice_no"]); ?>
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

            max-width: 1250px;

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


        .btn-dark {

            background: #111827;

            color: white;
        }


        .btn-blue {

            background: #2563eb;

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


        /* =========================
           CARDS
        ========================= */

        .card {

            background: white;

            border-radius: 12px;

            padding: 22px;

            margin-bottom: 20px;

            box-shadow:
                0 3px 15px
                rgba(0,0,0,0.06);
        }


        .card-title {

            margin: 0 0 18px;

            font-size: 19px;
        }


        /* =========================
           SALE INFO
        ========================= */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;
        }


        .info-box {

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 9px;

            padding: 15px;
        }


        .info-label {

            font-size: 12px;

            color: #6b7280;

            margin-bottom: 6px;

            text-transform: uppercase;
        }


        .info-value {

            font-size: 16px;

            font-weight: bold;

            word-break: break-word;
        }


        /* =========================
           CUSTOMER
        ========================= */

        .customer-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;
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

            min-width: 850px;
        }


        th,
        td {

            padding: 13px 11px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;

            font-size: 14px;
        }


        th {

            background: #f8fafc;

            font-weight: bold;
        }


        td.number,
        th.number {

            text-align: right;
        }


        .product-name {

            font-weight: bold;
        }


        .barcode {

            color: #6b7280;

            font-size: 12px;

            margin-top: 4px;
        }


        /* =========================
           PAYMENT BADGES
        ========================= */

        .badge {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }


        .badge.cash {

            background: #dcfce7;

            color: #166534;
        }


        .badge.card {

            background: #dbeafe;

            color: #1d4ed8;
        }


        .badge.credit {

            background: #fef3c7;

            color: #92400e;
        }


        /* =========================
           SUMMARY
        ========================= */

        .summary-wrapper {

            display: flex;

            justify-content: flex-end;
        }


        .summary {

            width: 430px;

            max-width: 100%;
        }


        .summary-row {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            padding: 11px 0;

            border-bottom:
                1px solid #e5e7eb;
        }


        .summary-row.total {

            font-size: 21px;

            font-weight: bold;

            padding: 15px 0;
        }


        .summary-row.due {

            color: #dc2626;

            font-weight: bold;
        }


        .summary-row.change {

            color: #16a34a;

            font-weight: bold;
        }


        /* =========================
           PROFIT
        ========================= */

        .profit-box {

            margin-top: 20px;

            background: #ecfdf5;

            border: 1px solid #bbf7d0;

            border-radius: 10px;

            padding: 16px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .profit-label {

            font-weight: bold;

            color: #166534;
        }


        .profit-value {

            font-size: 20px;

            font-weight: bold;

            color: #15803d;
        }


        /* =========================
           FOOTER
        ========================= */

        .footer-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 20px;
        }


        /* =========================
           PRINT
        ========================= */

        @media print {

            body {

                background: white;
            }


            .topbar,
            .page-header,
            .footer-actions {

                display: none;
            }


            .container {

                max-width: 100%;

                margin: 0;

                padding: 0;
            }


            .card {

                box-shadow: none;

                border: none;

                padding: 10px;

                margin: 0 0 10px;
            }


            .profit-box {

                border: 1px solid #ddd;
            }

        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .info-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .customer-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 650px) {

            .topbar {

                flex-direction: column;

                align-items: flex-start;
            }


            .page-header {

                flex-direction: column;

                align-items: flex-start;
            }


            .info-grid,
            .customer-grid {

                grid-template-columns: 1fr;
            }


            .footer-actions {

                flex-direction: column;
            }


            .footer-actions .btn {

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
         PAGE HEADER
    ========================= -->

    <div class="page-header">

        <div>

            <h1>
                Sale Details
            </h1>

        </div>


        <div class="buttons">

            <a
                href="manage.php"
                class="btn btn-dark"
            >
                Sales History
            </a>


            <a
                href="pos.php"
                class="btn btn-blue"
            >
                New Sale
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
         SALE INFORMATION
    ========================= -->

    <div class="card">

        <h3 class="card-title">
            Sale Information
        </h3>


        <div class="info-grid">


            <div class="info-box">

                <div class="info-label">
                    Invoice Number
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $sale["invoice_no"]
                    );
                    ?>

                </div>

            </div>



            <div class="info-box">

                <div class="info-label">
                    Sale Date
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $sale["sale_date"]
                    );
                    ?>

                </div>

            </div>



            <div class="info-box">

                <div class="info-label">
                    Cashier
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $sale["cashier_name"]
                        ?: $sale["cashier_username"]
                        ?: "Unknown"
                    );

                    ?>

                </div>

            </div>



            <div class="info-box">

                <div class="info-label">
                    Payment Method
                </div>

                <div class="info-value">

                    <span
                        class="badge <?php echo $payment_class; ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $sale["payment_method"]
                        );
                        ?>

                    </span>

                </div>

            </div>


        </div>

    </div>



    <!-- =========================
         CUSTOMER INFORMATION
    ========================= -->

    <div class="card">

        <h3 class="card-title">
            Customer Information
        </h3>


        <div class="customer-grid">


            <div class="info-box">

                <div class="info-label">
                    Customer Name
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $sale["customer_name"]
                        ?: "Walk-in Customer"
                    );

                    ?>

                </div>

            </div>



            <div class="info-box">

                <div class="info-label">
                    Phone
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $sale["customer_phone"]
                        ?: "-"
                    );

                    ?>

                </div>

            </div>



            <div class="info-box">

                <div class="info-label">
                    Address
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $sale["customer_address"]
                        ?: "-"
                    );

                    ?>

                </div>

            </div>


        </div>

    </div>



    <!-- =========================
         PRODUCTS
    ========================= -->

    <div class="card">

        <h3 class="card-title">
            Products
        </h3>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Product
                        </th>

                        <th>
                            Barcode
                        </th>

                        <th class="number">
                            Quantity
                        </th>

                        <th class="number">
                            Selling Price
                        </th>

                        <th class="number">
                            Total
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php

                    $counter = 1;

                    if (
                        mysqli_num_rows(
                            $items_result
                        ) > 0
                    ):

                    ?>


                        <?php
                        while (
                            $item =
                            mysqli_fetch_assoc(
                                $items_result
                            )
                        ):
                        ?>


                            <tr>

                                <td>

                                    <?php
                                    echo $counter++;
                                    ?>

                                </td>


                                <td>

                                    <div class="product-name">

                                        <?php

                                        echo htmlspecialchars(
                                            $item["product_name"]
                                        );

                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="barcode">

                                        <?php

                                        echo htmlspecialchars(
                                            $item["barcode"]
                                            ?: "-"
                                        );

                                        ?>

                                    </div>

                                </td>


                                <td class="number">

                                    <?php

                                    echo number_format(
                                        (float)$item["quantity"],
                                        2
                                    );

                                    ?>

                                    <?php

                                    if (
                                        !empty(
                                            $item["unit"]
                                        )
                                    ) {

                                        echo " ";

                                        echo htmlspecialchars(
                                            $item["unit"]
                                        );

                                    }

                                    ?>

                                </td>


                                <td class="number">

                                    AFN

                                    <?php

                                    echo number_format(
                                        (float)$item["selling_price"],
                                        2
                                    );

                                    ?>

                                </td>


                                <td class="number">

                                    <strong>

                                        AFN

                                        <?php

                                        echo number_format(
                                            (float)$item["total"],
                                            2
                                        );

                                        ?>

                                    </strong>

                                </td>

                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="6"
                                style="
                                    text-align:center;
                                    padding:30px;
                                    color:#6b7280;
                                "
                            >

                                No sale items found.

                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>


        <!-- =========================
             SUMMARY
        ========================= -->

        <div class="summary-wrapper">

            <div class="summary">


                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <strong>

                        AFN

                        <?php

                        echo number_format(
                            $subtotal,
                            2
                        );

                        ?>

                    </strong>

                </div>



                <div class="summary-row">

                    <span>
                        Discount
                    </span>

                    <strong>

                        AFN

                        <?php

                        echo number_format(
                            $discount,
                            2
                        );

                        ?>

                    </strong>

                </div>



                <div class="summary-row total">

                    <span>
                        Grand Total
                    </span>

                    <strong>

                        AFN

                        <?php

                        echo number_format(
                            $total,
                            2
                        );

                        ?>

                    </strong>

                </div>



                <div class="summary-row">

                    <span>
                        Paid Amount
                    </span>

                    <strong>

                        AFN

                        <?php

                        echo number_format(
                            $paid,
                            2
                        );

                        ?>

                    </strong>

                </div>



                <?php if ($change > 0): ?>

                    <div class="summary-row change">

                        <span>
                            Change
                        </span>

                        <strong>

                            AFN

                            <?php

                            echo number_format(
                                $change,
                                2
                            );

                            ?>

                        </strong>

                    </div>

                <?php endif; ?>



                <?php if ($due > 0): ?>

                    <div class="summary-row due">

                        <span>
                            Due Amount
                        </span>

                        <strong>

                            AFN

                            <?php

                            echo number_format(
                                $due,
                                2
                            );

                            ?>

                        </strong>

                    </div>

                <?php endif; ?>


            </div>

        </div>



        <!-- =========================
             PROFIT
        ========================= -->

        <div class="profit-box">

            <div class="profit-label">

                Estimated Profit

            </div>


            <div class="profit-value">

                AFN

                <?php

                echo number_format(
                    $total_profit,
                    2
                );

                ?>

            </div>

        </div>


    </div>



    <!-- =========================
         ACTIONS
    ========================= -->

    <div class="footer-actions">


        <a
            href="manage.php"
            class="btn btn-gray"
        >
            Back to Sales
        </a>


        <a
            href="receipt.php?id=<?php echo $id; ?>"
            target="_blank"
            class="btn btn-green"
        >
            Print Receipt
        </a>


        <button
            type="button"
            class="btn btn-blue"
            onclick="window.print()"
        >
            Print Details
        </button>


    </div>


</div>


</body>

</html>

<?php

mysqli_stmt_close($items_stmt);

mysqli_close($conn);

?>