<?php
session_start();

include "../../db.php";

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION["username"] ?? "User";
$role     = $_SESSION["role"] ?? "";

$error = "";

/* =========================
   GET PURCHASE ID
========================= */

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   LOAD SUPPLIERS
========================= */

$suppliers = [];

$supplier_result = mysqli_query(
    $conn,
    "SELECT id, name FROM suppliers ORDER BY name ASC"
);

if ($supplier_result) {
    while ($row = mysqli_fetch_assoc($supplier_result)) {
        $suppliers[] = $row;
    }
}


/* =========================
   LOAD PRODUCTS
========================= */

$products = [];

$product_result = mysqli_query(
    $conn,
    "
    SELECT
        id,
        barcode,
        name,
        purchase_price,
        stock,
        unit
    FROM products
    ORDER BY name ASC
    "
);

if ($product_result) {
    while ($row = mysqli_fetch_assoc($product_result)) {
        $products[] = $row;
    }
}


/* =========================
   LOAD PURCHASE
========================= */

$purchase_sql = "
    SELECT
        id,
        supplier_id,
        invoice_no,
        total_amount,
        paid_amount,
        due_amount,
        purchase_date
    FROM purchases
    WHERE id = ?
    LIMIT 1
";

$purchase_stmt = mysqli_prepare($conn, $purchase_sql);

if (!$purchase_stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $purchase_stmt,
    "i",
    $id
);

mysqli_stmt_execute($purchase_stmt);

$purchase_result = mysqli_stmt_get_result(
    $purchase_stmt
);

if (mysqli_num_rows($purchase_result) !== 1) {
    mysqli_stmt_close($purchase_stmt);

    header("Location: manage.php?error=not_found");
    exit();
}

$purchase = mysqli_fetch_assoc(
    $purchase_result
);

mysqli_stmt_close($purchase_stmt);


/* =========================
   LOAD OLD ITEMS
========================= */

$old_items = [];

$item_sql = "
    SELECT
        purchase_items.id,
        purchase_items.product_id,
        purchase_items.quantity,
        purchase_items.purchase_price,
        purchase_items.total,
        products.name AS product_name,
        products.barcode
    FROM purchase_items
    INNER JOIN products
        ON purchase_items.product_id = products.id
    WHERE purchase_items.purchase_id = ?
    ORDER BY purchase_items.id ASC
";

$item_stmt = mysqli_prepare(
    $conn,
    $item_sql
);

if (!$item_stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $item_stmt,
    "i",
    $id
);

mysqli_stmt_execute($item_stmt);

$item_result = mysqli_stmt_get_result(
    $item_stmt
);

while ($item = mysqli_fetch_assoc($item_result)) {
    $old_items[] = $item;
}

mysqli_stmt_close($item_stmt);


/* =========================
   UPDATE PURCHASE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $supplier_id = !empty($_POST["supplier_id"])
        ? (int)$_POST["supplier_id"]
        : null;

    $invoice_no = trim(
        $_POST["invoice_no"] ?? ""
    );

    $paid_amount = (float)(
        $_POST["paid_amount"] ?? 0
    );

    $purchase_date = $_POST["purchase_date"]
        ?? date("Y-m-d");

    $product_ids = $_POST["product_id"] ?? [];
    $quantities  = $_POST["quantity"] ?? [];
    $prices      = $_POST["purchase_price"] ?? [];


    /* =========================
       VALIDATION
    ========================= */

    if ($invoice_no === "") {

        $error = "Please enter invoice number.";

    } elseif (empty($product_ids)) {

        $error = "Please add at least one product.";

    } elseif ($paid_amount < 0) {

        $error = "Paid amount cannot be negative.";

    } else {

        $items = [];
        $total_amount = 0;

        for (
            $i = 0;
            $i < count($product_ids);
            $i++
        ) {

            $product_id = (int)(
                $product_ids[$i] ?? 0
            );

            $quantity = (float)(
                $quantities[$i] ?? 0
            );

            $price = (float)(
                $prices[$i] ?? 0
            );


            if ($product_id <= 0) {
                continue;
            }

            if ($quantity <= 0) {

                $error =
                    "Quantity must be greater than zero.";

                break;
            }

            if ($price < 0) {

                $error =
                    "Purchase price cannot be negative.";

                break;
            }


            $item_total =
                $quantity * $price;

            $items[] = [
                "product_id" => $product_id,
                "quantity"   => $quantity,
                "price"      => $price,
                "total"      => $item_total
            ];

            $total_amount += $item_total;
        }


        if (
            $error === "" &&
            empty($items)
        ) {

            $error =
                "Please add valid products.";
        }


        if (
            $error === "" &&
            $paid_amount > $total_amount
        ) {

            $error =
                "Paid amount cannot be greater than total amount.";
        }
    }


    /* =========================
       TRANSACTION
    ========================= */

    if ($error === "") {

        $due_amount =
            $total_amount - $paid_amount;

        mysqli_begin_transaction($conn);

        try {

            /* =========================
               CHECK INVOICE
            ========================= */

            $check_sql = "
                SELECT id
                FROM purchases
                WHERE invoice_no = ?
                AND id != ?
                LIMIT 1
            ";

            $check_stmt = mysqli_prepare(
                $conn,
                $check_sql
            );

            if (!$check_stmt) {
                throw new Exception(
                    "Invoice check failed."
                );
            }

            mysqli_stmt_bind_param(
                $check_stmt,
                "si",
                $invoice_no,
                $id
            );

            mysqli_stmt_execute($check_stmt);

            $check_result =
                mysqli_stmt_get_result(
                    $check_stmt
                );

            if (
                mysqli_num_rows(
                    $check_result
                ) > 0
            ) {

                throw new Exception(
                    "This invoice number already exists."
                );
            }

            mysqli_stmt_close(
                $check_stmt
            );


            /* =========================
               REMOVE OLD STOCK
            ========================= */

            $remove_stock_sql = "
                UPDATE products
                SET stock = stock - ?
                WHERE id = ?
            ";

            $remove_stock_stmt =
                mysqli_prepare(
                    $conn,
                    $remove_stock_sql
                );

            if (!$remove_stock_stmt) {
                throw new Exception(
                    "Could not prepare stock adjustment."
                );
            }


            foreach ($old_items as $old_item) {

                $old_quantity =
                    (float)$old_item["quantity"];

                $old_product_id =
                    (int)$old_item["product_id"];


                mysqli_stmt_bind_param(
                    $remove_stock_stmt,
                    "di",
                    $old_quantity,
                    $old_product_id
                );

                if (
                    !mysqli_stmt_execute(
                        $remove_stock_stmt
                    )
                ) {

                    throw new Exception(
                        "Could not remove old stock."
                    );
                }
            }

            mysqli_stmt_close(
                $remove_stock_stmt
            );


            /* =========================
               SUPPLIER OLD BALANCE
               REMOVE OLD DUE
            ========================= */

            if (
                !empty(
                    $purchase["supplier_id"]
                )
            ) {

                $old_supplier_id =
                    (int)$purchase["supplier_id"];

                $old_due =
                    (float)$purchase["due_amount"];


                $supplier_remove_sql = "
                    UPDATE suppliers
                    SET balance = balance - ?
                    WHERE id = ?
                ";

                $supplier_remove_stmt =
                    mysqli_prepare(
                        $conn,
                        $supplier_remove_sql
                    );

                if (!$supplier_remove_stmt) {
                    throw new Exception(
                        "Could not adjust old supplier balance."
                    );
                }

                mysqli_stmt_bind_param(
                    $supplier_remove_stmt,
                    "di",
                    $old_due,
                    $old_supplier_id
                );

                if (
                    !mysqli_stmt_execute(
                        $supplier_remove_stmt
                    )
                ) {

                    throw new Exception(
                        "Could not remove old supplier balance."
                    );
                }

                mysqli_stmt_close(
                    $supplier_remove_stmt
                );
            }


            /* =========================
               UPDATE PURCHASE
            ========================= */

            $update_sql = "
                UPDATE purchases
                SET
                    supplier_id = ?,
                    invoice_no = ?,
                    total_amount = ?,
                    paid_amount = ?,
                    due_amount = ?,
                    purchase_date = ?
                WHERE id = ?
            ";

            $update_stmt =
                mysqli_prepare(
                    $conn,
                    $update_sql
                );

            if (!$update_stmt) {
                throw new Exception(
                    "Purchase update failed."
                );
            }

            mysqli_stmt_bind_param(
                $update_stmt,
                "isdddsi",
                $supplier_id,
                $invoice_no,
                $total_amount,
                $paid_amount,
                $due_amount,
                $purchase_date,
                $id
            );

            if (
                !mysqli_stmt_execute(
                    $update_stmt
                )
            ) {

                throw new Exception(
                    "Could not update purchase."
                );
            }

            mysqli_stmt_close(
                $update_stmt
            );


            /* =========================
               DELETE OLD ITEMS
            ========================= */

            $delete_items_sql = "
                DELETE FROM purchase_items
                WHERE purchase_id = ?
            ";

            $delete_items_stmt =
                mysqli_prepare(
                    $conn,
                    $delete_items_sql
                );

            if (!$delete_items_stmt) {
                throw new Exception(
                    "Could not delete old items."
                );
            }

            mysqli_stmt_bind_param(
                $delete_items_stmt,
                "i",
                $id
            );

            if (
                !mysqli_stmt_execute(
                    $delete_items_stmt
                )
            ) {

                throw new Exception(
                    "Could not remove old purchase items."
                );
            }

            mysqli_stmt_close(
                $delete_items_stmt
            );


            /* =========================
               INSERT NEW ITEMS
            ========================= */

            $insert_item_sql = "
                INSERT INTO purchase_items
                (
                    purchase_id,
                    product_id,
                    quantity,
                    purchase_price,
                    total
                )
                VALUES (?, ?, ?, ?, ?)
            ";

            $insert_item_stmt =
                mysqli_prepare(
                    $conn,
                    $insert_item_sql
                );

            if (!$insert_item_stmt) {
                throw new Exception(
                    "Could not prepare purchase items."
                );
            }


            /* =========================
               ADD NEW STOCK
            ========================= */

            $add_stock_sql = "
                UPDATE products
                SET
                    stock = stock + ?,
                    purchase_price = ?
                WHERE id = ?
            ";

            $add_stock_stmt =
                mysqli_prepare(
                    $conn,
                    $add_stock_sql
                );

            if (!$add_stock_stmt) {
                throw new Exception(
                    "Could not prepare stock update."
                );
            }


            foreach ($items as $item) {

                $product_id =
                    $item["product_id"];

                $quantity =
                    $item["quantity"];

                $price =
                    $item["price"];

                $item_total =
                    $item["total"];


                /* Insert item */

                mysqli_stmt_bind_param(
                    $insert_item_stmt,
                    "iiddd",
                    $id,
                    $product_id,
                    $quantity,
                    $price,
                    $item_total
                );

                if (
                    !mysqli_stmt_execute(
                        $insert_item_stmt
                    )
                ) {

                    throw new Exception(
                        "Could not save new purchase item."
                    );
                }


                /* Add stock */

                mysqli_stmt_bind_param(
                    $add_stock_stmt,
                    "ddi",
                    $quantity,
                    $price,
                    $product_id
                );

                if (
                    !mysqli_stmt_execute(
                        $add_stock_stmt
                    )
                ) {

                    throw new Exception(
                        "Could not update new stock."
                    );
                }

                if (
                    mysqli_stmt_affected_rows(
                        $add_stock_stmt
                    ) === 0
                ) {

                    throw new Exception(
                        "Selected product was not found."
                    );
                }
            }


            mysqli_stmt_close(
                $insert_item_stmt
            );

            mysqli_stmt_close(
                $add_stock_stmt
            );


            /* =========================
               ADD NEW SUPPLIER BALANCE
            ========================= */

            if ($supplier_id !== null) {

                $supplier_add_sql = "
                    UPDATE suppliers
                    SET balance = balance + ?
                    WHERE id = ?
                ";

                $supplier_add_stmt =
                    mysqli_prepare(
                        $conn,
                        $supplier_add_sql
                    );

                if (!$supplier_add_stmt) {
                    throw new Exception(
                        "Could not prepare supplier balance."
                    );
                }

                mysqli_stmt_bind_param(
                    $supplier_add_stmt,
                    "di",
                    $due_amount,
                    $supplier_id
                );

                if (
                    !mysqli_stmt_execute(
                        $supplier_add_stmt
                    )
                ) {

                    throw new Exception(
                        "Could not update supplier balance."
                    );
                }

                mysqli_stmt_close(
                    $supplier_add_stmt
                );
            }


            /* =========================
               COMMIT
            ========================= */

            mysqli_commit($conn);

            header(
                "Location: manage.php?success=updated"
            );

            exit();


        } catch (Exception $e) {

            mysqli_rollback($conn);

            $error =
                $e->getMessage();
        }
    }
}


/* =========================
   FORM DATA AFTER ERROR
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $form_invoice_no =
        $_POST["invoice_no"] ?? "";

    $form_supplier_id =
        $_POST["supplier_id"] ?? "";

    $form_paid =
        $_POST["paid_amount"] ?? "0";

    $form_date =
        $_POST["purchase_date"]
        ?? date("Y-m-d");

    $form_product_ids =
        $_POST["product_id"] ?? [];

    $form_quantities =
        $_POST["quantity"] ?? [];

    $form_prices =
        $_POST["purchase_price"] ?? [];

} else {

    $form_invoice_no =
        $purchase["invoice_no"];

    $form_supplier_id =
        $purchase["supplier_id"];

    $form_paid =
        $purchase["paid_amount"];

    $form_date =
        $purchase["purchase_date"];

    $form_product_ids = [];

    $form_quantities = [];

    $form_prices = [];

    foreach ($old_items as $item) {

        $form_product_ids[] =
            $item["product_id"];

        $form_quantities[] =
            $item["quantity"];

        $form_prices[] =
            $item["purchase_price"];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Purchase - Super Store</title>

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

/* HEADER */

.header {
    height: 70px;
    background: #0f172a;
    color: white;

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
    color: #cbd5e1;
    font-size: 14px;
}

.user-info span {
    color: white;
    font-weight: bold;
}

/* CONTAINER */

.container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

/* PAGE HEADER */

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

.btn {
    display: inline-block;

    padding: 11px 17px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;
}

.btn-secondary {
    background: #475569;
    color: white;
}

/* ERROR */

.error {
    background: #fee2e2;
    color: #b91c1c;

    border: 1px solid #fecaca;

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}

/* CARD */

.card {
    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.06);

    margin-bottom: 20px;
}

.card-title {
    font-size: 18px;

    font-weight: bold;

    color: #0f172a;

    margin-bottom: 20px;
}

/* FORM */

.form-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;
}

.form-group {
    display: flex;

    flex-direction: column;
}

.form-group label {
    font-size: 13px;

    font-weight: 600;

    color: #334155;

    margin-bottom: 7px;
}

input,
select {
    width: 100%;

    padding: 12px 13px;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    outline: none;

    font-size: 14px;

    background: white;
}

input:focus,
select:focus {
    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,0.10);
}

/* TABLE */

.table-wrapper {
    overflow-x: auto;
}

.products-table {
    width: 100%;

    border-collapse: collapse;

    min-width: 900px;
}

.products-table th {
    background: #f8fafc;

    color: #475569;

    font-size: 13px;

    text-align: left;

    padding: 13px;

    border-bottom:
        1px solid #e2e8f0;
}

.products-table td {
    padding: 10px;

    border-bottom:
        1px solid #e2e8f0;
}

.products-table input,
.products-table select {
    min-width: 130px;
}

.row-total {
    font-weight: bold;
}

.remove-btn {
    background: #fee2e2;

    color: #b91c1c;

    border: none;

    padding: 8px 12px;

    border-radius: 6px;

    cursor: pointer;

    font-weight: 600;
}

.add-row-btn {
    margin-top: 15px;

    background: #0f172a;

    color: white;

    border: none;

    padding: 10px 15px;

    border-radius: 7px;

    cursor: pointer;

    font-weight: 600;
}

/* SUMMARY */

.summary {
    display: flex;

    justify-content: flex-end;

    margin-top: 25px;
}

.summary-box {
    width: 380px;
}

.summary-row {
    display: flex;

    justify-content: space-between;

    padding: 10px 0;

    border-bottom:
        1px solid #e2e8f0;

    font-size: 14px;
}

.summary-row.total {
    font-size: 20px;

    font-weight: bold;

    border-bottom: none;

    padding-top: 18px;
}

.summary-row.due {
    color: #dc2626;

    font-weight: bold;
}

.save-area {
    display: flex;

    justify-content: flex-end;

    margin-top: 20px;
}

.save-btn {
    background: #2563eb;

    color: white;

    border: none;

    padding: 14px 25px;

    border-radius: 8px;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;
}

.save-btn:hover {
    background: #1d4ed8;
}

/* RESPONSIVE */

@media (max-width: 900px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .summary-box {
        width: 100%;
    }
}

@media (max-width: 600px) {

    .header {
        padding: 0 15px;
    }

    .container {
        padding: 0 12px;
    }

    .card {
        padding: 18px;
    }

    .page-title h1 {
        font-size: 23px;
    }
}

</style>

</head>

<body>


<!-- HEADER -->

<div class="header">

    <div class="logo">
        Super Store
    </div>

    <div class="user-info">

        Welcome,

        <span>
            <?php
            echo htmlspecialchars($username);
            ?>
        </span>

        |

        <span>
            <?php
            echo htmlspecialchars($role);
            ?>
        </span>

    </div>

</div>


<div class="container">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                Edit Purchase
            </h1>

            <p>
                Update purchase information and products
            </p>

        </div>


        <a
            href="manage.php"
            class="btn btn-secondary"
        >
            ← Purchases
        </a>

    </div>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <form method="POST">


        <!-- PURCHASE INFORMATION -->

        <div class="card">

            <div class="card-title">
                Purchase Information
            </div>


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Invoice Number *
                    </label>

                    <input
                        type="text"
                        name="invoice_no"
                        value="<?php
                        echo htmlspecialchars(
                            $form_invoice_no
                        );
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Supplier
                    </label>

                    <select name="supplier_id">

                        <option value="">
                            -- Select Supplier --
                        </option>

                        <?php foreach (
                            $suppliers as $supplier
                        ): ?>

                            <option
                                value="<?php
                                echo (int)$supplier["id"];
                                ?>"
                                <?php

                                if (
                                    (string)$form_supplier_id
                                    ===
                                    (string)$supplier["id"]
                                ) {
                                    echo "selected";
                                }

                                ?>
                            >

                                <?php
                                echo htmlspecialchars(
                                    $supplier["name"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Purchase Date *
                    </label>

                    <input
                        type="date"
                        name="purchase_date"
                        value="<?php
                        echo htmlspecialchars(
                            $form_date
                        );
                        ?>"
                        required
                    >

                </div>


            </div>

        </div>


        <!-- PRODUCTS -->

        <div class="card">

            <div class="card-title">
                Purchase Products
            </div>


            <div class="table-wrapper">

                <table
                    class="products-table"
                    id="productsTable"
                >

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Purchase Price
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody id="productRows">

                    <?php

                    $row_count =
                        count($form_product_ids);

                    if ($row_count === 0) {

                        $row_count = 1;

                        $form_product_ids = [""];
                        $form_quantities = [1];
                        $form_prices = [0];
                    }

                    for (
                        $i = 0;
                        $i < $row_count;
                        $i++
                    ):

                        $selected_product =
                            $form_product_ids[$i]
                            ?? "";

                        $quantity =
                            $form_quantities[$i]
                            ?? 1;

                        $price =
                            $form_prices[$i]
                            ?? 0;

                    ?>

                        <tr class="product-row">

                            <td>

                                <select
                                    name="product_id[]"
                                    class="product-select"
                                    required
                                >

                                    <option value="">
                                        -- Select Product --
                                    </option>

                                    <?php foreach (
                                        $products as $product
                                    ): ?>

                                        <option
                                            value="<?php
                                            echo (int)$product["id"];
                                            ?>"
                                            data-price="<?php
                                            echo htmlspecialchars(
                                                $product[
                                                    "purchase_price"
                                                ]
                                            );
                                            ?>"
                                            <?php

                                            if (
                                                (string)
                                                $selected_product
                                                ===
                                                (string)
                                                $product["id"]
                                            ) {
                                                echo "selected";
                                            }

                                            ?>
                                        >

                                            <?php
                                            echo htmlspecialchars(
                                                $product["name"]
                                            );
                                            ?>

                                            <?php

                                            if (
                                                $product["barcode"]
                                                !== ""
                                            ) {

                                                echo " - ";

                                                echo htmlspecialchars(
                                                    $product["barcode"]
                                                );
                                            }

                                            ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <td>

                                <input
                                    type="number"
                                    name="quantity[]"
                                    class="quantity"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $quantity
                                    );
                                    ?>"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >

                            </td>


                            <td>

                                <input
                                    type="number"
                                    name="purchase_price[]"
                                    class="purchase-price"
                                    value="<?php
                                    echo htmlspecialchars(
                                        $price
                                    );
                                    ?>"
                                    min="0"
                                    step="0.01"
                                    required
                                >

                            </td>


                            <td>

                                <span class="row-total">
                                    0.00
                                </span>

                                AFN

                            </td>


                            <td>

                                <button
                                    type="button"
                                    class="remove-btn"
                                    onclick="removeRow(this)"
                                >
                                    Remove
                                </button>

                            </td>

                        </tr>

                    <?php endfor; ?>

                    </tbody>

                </table>

            </div>


            <button
                type="button"
                class="add-row-btn"
                onclick="addRow()"
            >
                + Add Product
            </button>

        </div>


        <!-- PAYMENT -->

        <div class="card">

            <div class="card-title">
                Payment
            </div>


            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Paid Amount
                    </label>

                    <input
                        type="number"
                        name="paid_amount"
                        id="paidAmount"
                        value="<?php
                        echo htmlspecialchars(
                            $form_paid
                        );
                        ?>"
                        min="0"
                        step="0.01"
                    >

                </div>

            </div>


            <div class="summary">

                <div class="summary-box">


                    <div class="summary-row total">

                        <span>
                            Total Amount
                        </span>

                        <strong>

                            <span id="grandTotal">
                                0.00
                            </span>

                            AFN

                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Paid Amount
                        </span>

                        <strong>

                            <span id="displayPaid">
                                0.00
                            </span>

                            AFN

                        </strong>

                    </div>


                    <div class="summary-row due">

                        <span>
                            Due Amount
                        </span>

                        <strong>

                            <span id="dueAmount">
                                0.00
                            </span>

                            AFN

                        </strong>

                    </div>


                </div>

            </div>


            <div class="save-area">

                <button
                    type="submit"
                    class="save-btn"
                >
                    Update Purchase
                </button>

            </div>

        </div>


    </form>

</div>


<script>

/* =========================
   PRODUCT OPTIONS
========================= */

const productOptions =
    document.querySelector(
        ".product-select"
    ).innerHTML;


/* =========================
   ADD ROW
========================= */

function addRow() {

    const tbody =
        document.getElementById(
            "productRows"
        );

    const row =
        document.createElement("tr");

    row.className =
        "product-row";

    row.innerHTML = `

        <td>

            <select
                name="product_id[]"
                class="product-select"
                required
            >

                ${productOptions}

            </select>

        </td>

        <td>

            <input
                type="number"
                name="quantity[]"
                class="quantity"
                value="1"
                min="0.01"
                step="0.01"
                required
            >

        </td>

        <td>

            <input
                type="number"
                name="purchase_price[]"
                class="purchase-price"
                value="0"
                min="0"
                step="0.01"
                required
            >

        </td>

        <td>

            <span class="row-total">
                0.00
            </span>

            AFN

        </td>

        <td>

            <button
                type="button"
                class="remove-btn"
                onclick="removeRow(this)"
            >
                Remove
            </button>

        </td>

    `;

    tbody.appendChild(row);

    updateTotals();
}


/* =========================
   REMOVE ROW
========================= */

function removeRow(button) {

    const rows =
        document.querySelectorAll(
            ".product-row"
        );

    if (rows.length <= 1) {

        alert(
            "At least one product is required."
        );

        return;
    }

    button
        .closest(".product-row")
        .remove();

    updateTotals();
}


/* =========================
   PRODUCT CHANGE
========================= */

document.addEventListener(
    "change",
    function(event) {

        if (
            event.target.classList.contains(
                "product-select"
            )
        ) {

            const option =
                event.target.options[
                    event.target.selectedIndex
                ];

            const price =
                option.dataset.price || 0;

            const row =
                event.target.closest(
                    ".product-row"
                );

            const priceInput =
                row.querySelector(
                    ".purchase-price"
                );

            priceInput.value = price;

            updateTotals();
        }

    }
);


/* =========================
   INPUT CHANGE
========================= */

document.addEventListener(
    "input",
    function(event) {

        if (
            event.target.classList.contains(
                "quantity"
            ) ||

            event.target.classList.contains(
                "purchase-price"
            ) ||

            event.target.id ===
                "paidAmount"
        ) {

            updateTotals();
        }

    }
);


/* =========================
   UPDATE TOTALS
========================= */

function updateTotals() {

    let grandTotal = 0;

    const rows =
        document.querySelectorAll(
            ".product-row"
        );


    rows.forEach(function(row) {

        const quantity =
            parseFloat(
                row.querySelector(
                    ".quantity"
                ).value
            ) || 0;

        const price =
            parseFloat(
                row.querySelector(
                    ".purchase-price"
                ).value
            ) || 0;

        const total =
            quantity * price;

        row.querySelector(
            ".row-total"
        ).textContent =
            total.toFixed(2);

        grandTotal += total;

    });


    const paid =
        parseFloat(
            document.getElementById(
                "paidAmount"
            ).value
        ) || 0;

    let due =
        grandTotal - paid;

    if (due < 0) {
        due = 0;
    }


    document.getElementById(
        "grandTotal"
    ).textContent =
        grandTotal.toFixed(2);


    document.getElementById(
        "displayPaid"
    ).textContent =
        paid.toFixed(2);


    document.getElementById(
        "dueAmount"
    ).textContent =
        due.toFixed(2);
}


/* =========================
   INITIAL
========================= */

updateTotals();

</script>

</body>

</html>