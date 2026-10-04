<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";


/* =========================
   SALE ID
========================= */

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    die("Invalid Receipt ID.");
}


/* =========================
   STORE SETTINGS
========================= */

$store_name = "Super Store";
$store_phone = "";
$store_address = "";
$currency = "AFN";
$receipt_footer = "Thank you for shopping with us!";

$settings_sql = "
    SELECT
        store_name,
        phone,
        address,
        currency,
        receipt_footer
    FROM settings
    ORDER BY id DESC
    LIMIT 1
";

$settings_result = mysqli_query($conn, $settings_sql);

if ($settings_result && mysqli_num_rows($settings_result) > 0) {

    $settings = mysqli_fetch_assoc($settings_result);

    $store_name = $settings["store_name"] ?: $store_name;
    $store_phone = $settings["phone"] ?? "";
    $store_address = $settings["address"] ?? "";
    $currency = $settings["currency"] ?: $currency;
    $receipt_footer = $settings["receipt_footer"] ?: $receipt_footer;
}


/* =========================
   SALE
========================= */

$sql = "
    SELECT
        sales.*,
        customers.name AS customer_name,
        customers.phone AS customer_phone,
        users.name AS cashier_name
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
    die("Database Error.");
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {
    die("Receipt not found.");
}

$sale = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================
   SALE ITEMS
========================= */

$item_sql = "
    SELECT
        sale_items.quantity,
        sale_items.selling_price,
        sale_items.total,
        products.name AS product_name,
        products.unit
    FROM sale_items

    INNER JOIN products
        ON sale_items.product_id = products.id

    WHERE sale_items.sale_id = ?

    ORDER BY sale_items.id ASC
";

$item_stmt = mysqli_prepare($conn, $item_sql);

if (!$item_stmt) {
    die("Database Error.");
}

mysqli_stmt_bind_param($item_stmt, "i", $id);
mysqli_stmt_execute($item_stmt);

$items_result = mysqli_stmt_get_result($item_stmt);


/* =========================
   AMOUNTS
========================= */

$subtotal = (float)$sale["subtotal"];
$discount = (float)$sale["discount"];
$total = (float)$sale["total_amount"];
$paid = (float)$sale["paid_amount"];
$due = (float)$sale["due_amount"];

$change = 0;

if ($paid > $total) {
    $change = $paid - $total;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
Receipt - <?php echo htmlspecialchars($sale["invoice_no"]); ?>
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    padding: 20px;

    background: #eeeeee;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #000;

}


/* =========================
   RECEIPT
========================= */

.receipt {

    width: 80mm;

    max-width: 100%;

    margin: auto;

    background: white;

    padding: 10px;

}


/* =========================
   HEADER
========================= */

.header {

    text-align: center;

    padding-bottom: 10px;

    border-bottom: 1px dashed #000;

}

.store-name {

    font-size: 21px;

    font-weight: bold;

    margin-bottom: 5px;

}

.store-info {

    font-size: 11px;

    line-height: 1.5;

}


/* =========================
   TITLE
========================= */

.receipt-title {

    text-align: center;

    font-size: 14px;

    font-weight: bold;

    margin: 10px 0;

}


/* =========================
   INFO
========================= */

.info {

    font-size: 11px;

    margin-bottom: 10px;

}

.info-row {

    display: flex;

    justify-content: space-between;

    margin-bottom: 4px;

}

.info-row span:first-child {

    font-weight: bold;

}


/* =========================
   TABLE
========================= */

table {

    width: 100%;

    border-collapse: collapse;

    font-size: 10px;

}

thead {

    border-top: 1px dashed #000;

    border-bottom: 1px dashed #000;

}

th {

    padding: 5px 2px;

    text-align: left;

}

td {

    padding: 5px 2px;

    border-bottom: 1px dotted #aaa;

}

.right {

    text-align: right;

}

.center {

    text-align: center;

}


/* =========================
   SUMMARY
========================= */

.summary {

    margin-top: 8px;

    padding-top: 7px;

    border-top: 1px dashed #000;

    font-size: 11px;

}

.summary-row {

    display: flex;

    justify-content: space-between;

    margin: 5px 0;

}

.grand-total {

    font-size: 15px;

    font-weight: bold;

    padding: 7px 0;

    border-top: 1px dashed #000;

    border-bottom: 1px dashed #000;

}


/* =========================
   PAYMENT
========================= */

.payment {

    font-size: 11px;

    margin-top: 8px;

}

.payment-row {

    display: flex;

    justify-content: space-between;

    margin: 5px 0;

}


/* =========================
   FOOTER
========================= */

.footer {

    text-align: center;

    border-top: 1px dashed #000;

    margin-top: 12px;

    padding-top: 10px;

    font-size: 10px;

    line-height: 1.5;

}


/* =========================
   BUTTONS
========================= */

.buttons {

    width: 80mm;

    max-width: 100%;

    margin: 20px auto;

    display: flex;

    gap: 8px;

}

.buttons button,
.buttons a {

    flex: 1;

    border: none;

    padding: 10px;

    border-radius: 5px;

    text-decoration: none;

    text-align: center;

    cursor: pointer;

    font-size: 12px;

}

.print {

    background: #2563eb;

    color: white;

}

.back {

    background: #64748b;

    color: white;

}


/* =========================
   PRINT
========================= */

@media print {

    body {

        background: white;

        padding: 0;

    }

    .receipt {

        width: 80mm;

        padding: 5px;

        margin: 0;

    }

    .buttons {

        display: none;

    }

    @page {

        size: 80mm auto;

        margin: 0;

    }

}

</style>

</head>


<body>


<div class="receipt">


    <!-- STORE -->

    <div class="header">

        <div class="store-name">

            <?php

            echo htmlspecialchars($store_name);

            ?>

        </div>


        <?php if ($store_address != ""): ?>

            <div class="store-info">

                <?php

                echo htmlspecialchars($store_address);

                ?>

            </div>

        <?php endif; ?>


        <?php if ($store_phone != ""): ?>

            <div class="store-info">

                Phone:

                <?php

                echo htmlspecialchars($store_phone);

                ?>

            </div>

        <?php endif; ?>

    </div>


    <!-- TITLE -->

    <div class="receipt-title">

        SALES RECEIPT

    </div>


    <!-- SALE INFORMATION -->

    <div class="info">


        <div class="info-row">

            <span>
                Invoice:
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $sale["invoice_no"]
                );

                ?>

            </span>

        </div>


        <div class="info-row">

            <span>
                Date:
            </span>

            <span>

                <?php

                echo date(
                    "Y-m-d H:i",
                    strtotime($sale["sale_date"])
                );

                ?>

            </span>

        </div>


        <div class="info-row">

            <span>
                Cashier:
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $sale["cashier_name"]
                    ?: "Unknown"
                );

                ?>

            </span>

        </div>


        <div class="info-row">

            <span>
                Customer:
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $sale["customer_name"]
                    ?: "Walk-in"
                );

                ?>

            </span>

        </div>


        <?php if (!empty($sale["customer_phone"])): ?>

            <div class="info-row">

                <span>
                    Phone:
                </span>

                <span>

                    <?php

                    echo htmlspecialchars(
                        $sale["customer_phone"]
                    );

                    ?>

                </span>

            </div>

        <?php endif; ?>


    </div>


    <!-- PRODUCTS -->

    <table>

        <thead>

            <tr>

                <th>
                    Item
                </th>

                <th class="center">
                    Qty
                </th>

                <th class="right">
                    Price
                </th>

                <th class="right">
                    Total
                </th>

            </tr>

        </thead>


        <tbody>


        <?php

        while ($item = mysqli_fetch_assoc($items_result)):

        ?>

            <tr>

                <td>

                    <?php

                    echo htmlspecialchars(
                        $item["product_name"]
                    );

                    ?>

                </td>


                <td class="center">

                    <?php

                    echo number_format(
                        (float)$item["quantity"],
                        2
                    );

                    ?>

                </td>


                <td class="right">

                    <?php

                    echo number_format(
                        (float)$item["selling_price"],
                        2
                    );

                    ?>

                </td>


                <td class="right">

                    <?php

                    echo number_format(
                        (float)$item["total"],
                        2
                    );

                    ?>

                </td>

            </tr>

        <?php endwhile; ?>


        </tbody>

    </table>


    <!-- SUMMARY -->

    <div class="summary">


        <div class="summary-row">

            <span>
                Subtotal
            </span>

            <span>

                <?php

                echo number_format(
                    $subtotal,
                    2
                );

                ?>

                <?php echo htmlspecialchars($currency); ?>

            </span>

        </div>


        <?php if ($discount > 0): ?>

            <div class="summary-row">

                <span>
                    Discount
                </span>

                <span>

                    -

                    <?php

                    echo number_format(
                        $discount,
                        2
                    );

                    ?>

                    <?php echo htmlspecialchars($currency); ?>

                </span>

            </div>

        <?php endif; ?>


        <div class="summary-row grand-total">

            <span>
                TOTAL
            </span>

            <span>

                <?php

                echo number_format(
                    $total,
                    2
                );

                ?>

                <?php echo htmlspecialchars($currency); ?>

            </span>

        </div>


    </div>


    <!-- PAYMENT -->

    <div class="payment">


        <div class="payment-row">

            <span>
                Payment Method
            </span>

            <strong>

                <?php

                echo htmlspecialchars(
                    $sale["payment_method"]
                );

                ?>

            </strong>

        </div>


        <div class="payment-row">

            <span>
                Paid
            </span>

            <span>

                <?php

                echo number_format(
                    $paid,
                    2
                );

                ?>

                <?php echo htmlspecialchars($currency); ?>

            </span>

        </div>


        <?php if ($change > 0): ?>

            <div class="payment-row">

                <span>
                    Change
                </span>

                <span>

                    <?php

                    echo number_format(
                        $change,
                        2
                    );

                    ?>

                    <?php echo htmlspecialchars($currency); ?>

                </span>

            </div>

        <?php endif; ?>


        <?php if ($due > 0): ?>

            <div class="payment-row">

                <span>
                    Due
                </span>

                <strong>

                    <?php

                    echo number_format(
                        $due,
                        2
                    );

                    ?>

                    <?php echo htmlspecialchars($currency); ?>

                </strong>

            </div>

        <?php endif; ?>


    </div>


    <!-- FOOTER -->

    <div class="footer">

        <?php

        echo htmlspecialchars(
            $receipt_footer
        );

        ?>

        <br>

        <strong>
            Thank You!
        </strong>

    </div>


</div>


<!-- BUTTONS -->

<div class="buttons">

    <button
        class="print"
        onclick="window.print();"
    >

        🖨 Print Receipt

    </button>


    <a
        href="manage.php"
        class="back"
    >

        ← Sales History

    </a>

</div>


<script>

window.onload = function () {

    // Uncomment this line if you want
    // the print dialog to open automatically.

    // window.print();

};

</script>


</body>

</html>


<?php

mysqli_stmt_close($item_stmt);

mysqli_close($conn);

?>