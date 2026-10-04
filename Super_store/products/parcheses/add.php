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
$role     = $_SESSION["role"] ?? "";


/* =========================
   LOAD SUPPLIERS
========================= */

$suppliers = [];

$supplier_sql = "SELECT id, name FROM suppliers ORDER BY name ASC";
$supplier_result = mysqli_query($conn, $supplier_sql);

if ($supplier_result) {
    while ($supplier = mysqli_fetch_assoc($supplier_result)) {
        $suppliers[] = $supplier;
    }
}


/* =========================
   LOAD PRODUCTS
========================= */

$products = [];

$product_sql = "
    SELECT
        id,
        barcode,
        name,
        purchase_price,
        stock,
        unit
    FROM products
    ORDER BY name ASC
";

$product_result = mysqli_query($conn, $product_sql);

if ($product_result) {
    while ($product = mysqli_fetch_assoc($product_result)) {
        $products[] = $product;
    }
}


/* =========================
   SAVE PURCHASE
========================= */

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $supplier_id = !empty($_POST["supplier_id"])
        ? (int)$_POST["supplier_id"]
        : null;

    $invoice_no = trim($_POST["invoice_no"] ?? "");

    $paid_amount = (float)($_POST["paid_amount"] ?? 0);

    $purchase_date = $_POST["purchase_date"] ?? date("Y-m-d");


    /* =========================
       PRODUCTS
    ========================= */

    $product_ids = $_POST["product_id"] ?? [];
    $quantities  = $_POST["quantity"] ?? [];
    $prices      = $_POST["purchase_price"] ?? [];


    if ($invoice_no === "") {

        $error = "Please enter invoice number.";

    } elseif (empty($product_ids)) {

        $error = "Please add at least one product.";

    } elseif ($paid_amount < 0) {

        $error = "Paid amount cannot be negative.";

    } else {

        $items = [];
        $total_amount = 0;

        for ($i = 0; $i < count($product_ids); $i++) {

            $product_id = (int)($product_ids[$i] ?? 0);
            $quantity   = (float)($quantities[$i] ?? 0);
            $price      = (float)($prices[$i] ?? 0);

            if ($product_id <= 0) {
                continue;
            }

            if ($quantity <= 0) {
                $error = "Quantity must be greater than zero.";
                break;
            }

            if ($price < 0) {
                $error = "Purchase price cannot be negative.";
                break;
            }

            $item_total = $quantity * $price;

            $items[] = [
                "product_id" => $product_id,
                "quantity"   => $quantity,
                "price"      => $price,
                "total"      => $item_total
            ];

            $total_amount += $item_total;
        }


        if ($error === "" && empty($items)) {
            $error = "Please add valid products.";
        }


        if ($error === "" && $paid_amount > $total_amount) {
            $error = "Paid amount cannot be greater than total amount.";
        }


        /* =========================
           DATABASE TRANSACTION
        ========================= */

        if ($error === "") {

            $due_amount = $total_amount - $paid_amount;

            mysqli_begin_transaction($conn);

            try {

                /* =========================
                   CHECK INVOICE
                ========================= */

                $check_invoice_sql = "
                    SELECT id
                    FROM purchases
                    WHERE invoice_no = ?
                    LIMIT 1
                ";

                $check_invoice_stmt = mysqli_prepare(
                    $conn,
                    $check_invoice_sql
                );

                if (!$check_invoice_stmt) {
                    throw new Exception("Invoice check failed.");
                }

                mysqli_stmt_bind_param(
                    $check_invoice_stmt,
                    "s",
                    $invoice_no
                );

                mysqli_stmt_execute($check_invoice_stmt);

                $invoice_result = mysqli_stmt_get_result(
                    $check_invoice_stmt
                );

                if (mysqli_num_rows($invoice_result) > 0) {

                    throw new Exception(
                        "This invoice number already exists."
                    );
                }

                mysqli_stmt_close($check_invoice_stmt);


                /* =========================
                   INSERT PURCHASE
                ========================= */

                $purchase_sql = "
                    INSERT INTO purchases
                    (
                        supplier_id,
                        invoice_no,
                        total_amount,
                        paid_amount,
                        due_amount,
                        purchase_date
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ";

                $purchase_stmt = mysqli_prepare(
                    $conn,
                    $purchase_sql
                );

                if (!$purchase_stmt) {
                    throw new Exception(
                        "Purchase insert failed."
                    );
                }

                mysqli_stmt_bind_param(
                    $purchase_stmt,
                    "isddds",
                    $supplier_id,
                    $invoice_no,
                    $total_amount,
                    $paid_amount,
                    $due_amount,
                    $purchase_date
                );

                if (!mysqli_stmt_execute($purchase_stmt)) {
                    throw new Exception(
                        "Could not save purchase."
                    );
                }

                $purchase_id = mysqli_insert_id($conn);

                mysqli_stmt_close($purchase_stmt);


                /* =========================
                   PREPARE ITEM INSERT
                ========================= */

                $item_sql = "
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

                $item_stmt = mysqli_prepare(
                    $conn,
                    $item_sql
                );

                if (!$item_stmt) {
                    throw new Exception(
                        "Purchase item preparation failed."
                    );
                }


                /* =========================
                   PREPARE STOCK UPDATE
                ========================= */

                $stock_sql = "
                    UPDATE products
                    SET stock = stock + ?,
                        purchase_price = ?
                    WHERE id = ?
                ";

                $stock_stmt = mysqli_prepare(
                    $conn,
                    $stock_sql
                );

                if (!$stock_stmt) {
                    throw new Exception(
                        "Stock update preparation failed."
                    );
                }


                /* =========================
                   INSERT ITEMS + STOCK
                ========================= */

                foreach ($items as $item) {

                    $product_id = $item["product_id"];
                    $quantity   = $item["quantity"];
                    $price      = $item["price"];
                    $item_total = $item["total"];


                    /* Insert purchase item */

                    mysqli_stmt_bind_param(
                        $item_stmt,
                        "iiddd",
                        $purchase_id,
                        $product_id,
                        $quantity,
                        $price,
                        $item_total
                    );

                    if (!mysqli_stmt_execute($item_stmt)) {

                        throw new Exception(
                            "Could not save purchase item."
                        );
                    }


                    /* Increase stock */

                    mysqli_stmt_bind_param(
                        $stock_stmt,
                        "ddi",
                        $quantity,
                        $price,
                        $product_id
                    );

                    if (!mysqli_stmt_execute($stock_stmt)) {

                        throw new Exception(
                            "Could not update product stock."
                        );
                    }

                    if (mysqli_stmt_affected_rows($stock_stmt) === 0) {

                        throw new Exception(
                            "Product not found."
                        );
                    }
                }


                mysqli_stmt_close($item_stmt);
                mysqli_stmt_close($stock_stmt);


                /* =========================
                   SUPPLIER BALANCE
                ========================= */

                if ($supplier_id !== null) {

                    $supplier_balance_sql = "
                        UPDATE suppliers
                        SET balance = balance + ?
                        WHERE id = ?
                    ";

                    $supplier_balance_stmt = mysqli_prepare(
                        $conn,
                        $supplier_balance_sql
                    );

                    if (!$supplier_balance_stmt) {
                        throw new Exception(
                            "Supplier balance update failed."
                        );
                    }

                    /*
                     * Only unpaid amount is added
                     * to supplier balance.
                     */

                    mysqli_stmt_bind_param(
                        $supplier_balance_stmt,
                        "di",
                        $due_amount,
                        $supplier_id
                    );

                    if (!mysqli_stmt_execute(
                        $supplier_balance_stmt
                    )) {

                        throw new Exception(
                            "Could not update supplier balance."
                        );
                    }

                    mysqli_stmt_close(
                        $supplier_balance_stmt
                    );
                }


                /* =========================
                   COMMIT
                ========================= */

                mysqli_commit($conn);

                header(
                    "Location: manage.php?success=added"
                );

                exit();


            } catch (Exception $e) {

                mysqli_rollback($conn);

                $error = $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>New Purchase - Super Store</title>

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

/* =========================
   CONTAINER
========================= */

.container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

/* =========================
   PAGE HEADER
========================= */

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 25px;

    gap: 15px;
    flex-wrap: wrap;
}

.page-title h1 {
    color: #0f172a;
    font-size: 28px;
    margin-bottom: 5px;
}

.page-title p {
    color: #64748b;
    font-size: 14px;
}

.buttons {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 11px 17px;
    border-radius: 8px;
    text-decoration: none;

    border: none;
    cursor: pointer;

    font-size: 14px;
    font-weight: 600;
}

.btn-secondary {
    background: #475569;
    color: white;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

/* =========================
   ERROR
========================= */

.error {
    background: #fee2e2;
    color: #b91c1c;

    border: 1px solid #fecaca;

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;
}

/* =========================
   CARD
========================= */

.card {
    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow: 0 3px 15px rgba(0,0,0,0.06);

    margin-bottom: 20px;
}

.card-title {
    font-size: 18px;
    font-weight: bold;

    margin-bottom: 20px;

    color: #0f172a;
}

/* =========================
   FORM
========================= */

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

    margin-bottom: 7px;

    color: #334155;
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

/* =========================
   PRODUCTS TABLE
========================= */

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

    padding: 13px;

    text-align: left;

    font-size: 13px;

    color: #475569;

    border-bottom:
        1px solid #e2e8f0;
}

.products-table td {
    padding: 10px;

    border-bottom:
        1px solid #e2e8f0;
}

.products-table select,
.products-table input {
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

/* =========================
   SUMMARY
========================= */

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

    color: #0f172a;

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
    background: #16a34a;

    color: white;

    border: none;

    padding: 14px 25px;

    border-radius: 8px;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;
}

.save-btn:hover {
    background: #15803d;
}

/* =========================
   RESPONSIVE
========================= */

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


<!-- =========================
     MAIN
========================= -->

<div class="container">


    <div class="page-header">

        <div class="page-title">

            <h1>New Purchase</h1>

            <p>
                Add products purchased from supplier
            </p>

        </div>


        <div class="buttons">

            <a
                href="manage.php"
                class="btn btn-secondary"
            >
                ← Purchases
            </a>

        </div>

    </div>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <form method="POST" id="purchaseForm">


        <!-- =========================
             PURCHASE INFORMATION
        ========================= -->

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
                        placeholder="e.g. PUR-1001"
                        value="<?php
                        echo htmlspecialchars(
                            $_POST["invoice_no"] ?? ""
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
                                    isset(
                                        $_POST["supplier_id"]
                                    ) &&
                                    $_POST["supplier_id"]
                                    == $supplier["id"]
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
                            $_POST["purchase_date"]
                            ?? date("Y-m-d")
                        );
                        ?>"
                        required
                    >

                </div>


            </div>

        </div>


        <!-- =========================
             PRODUCTS
        ========================= -->

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
                                                    $product[
                                                        "barcode"
                                                    ]
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

                        </tr>

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


        <!-- =========================
             PAYMENT
        ========================= -->

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
                            $_POST["paid_amount"] ?? "0"
                        );
                        ?>"
                        min="0"
                        step="0.01"
                    >

                </div>

            </div>


            <div class="summary">

                <div class="summary-box">

                    <div class="summary-row">

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
                    Save Purchase
                </button>

            </div>

        </div>


    </form>

</div>


<script>

/* =========================
   PRODUCT DATA
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
   PRODUCT PRICE
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
   CALCULATE TOTAL
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
            event.target.id === "paidAmount"
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
   INITIAL CALCULATION
========================= */

updateTotals();

</script>

</body>

</html>