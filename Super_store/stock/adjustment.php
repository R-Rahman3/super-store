<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? '';

if ($user_role !== "Admin" && $user_role !== "Manager") {
    header("Location: manage.php?error=access_denied");
    exit();
}

/*
|--------------------------------------------------------------------------
| PRODUCT ID
|--------------------------------------------------------------------------
*/

$product_id = intval($_GET['id'] ?? $_POST['product_id'] ?? 0);

if ($product_id<=0) {
    header("Location: manage.php?error=invalid_product");
    exit();
}

/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| GET PRODUCT
|--------------------------------------------------------------------------
*/

$product = null;

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        p.id,
        p.barcode,
        p.name,
        p.stock,
        p.min_stock,
        p.unit,
        p.purchase_price,
        p.selling_price,
        p.expiry_date,
        c.name AS category_name
     FROM products p
     LEFT JOIN categories c
        ON p.category_id = c.category_id
     WHERE p.id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($result) {
    $product = mysqli_fetch_assoc($result);
}

mysqli_stmt_close($stmt);

if (!$product) {
    header("Location: manage.php?error=product_not_found");
    exit();
}

/*
|--------------------------------------------------------------------------
| SAVE ADJUSTMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $adjustment_type = trim($_POST['adjustment_type'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $allowed_types = [
        "add",
        "remove",
        "set"
    ];

    if (!in_array($adjustment_type, $allowed_types, true)) {

        $error = "Invalid adjustment type.";

    } elseif ($quantity === '' || !is_numeric($quantity)) {

        $error = "Please enter a valid quantity.";

    } elseif ((float)$quantity < 0) {

        $error = "Quantity cannot be negative.";

    } elseif ($reason === '') {

        $error = "Please enter a reason for this adjustment.";

    } elseif (strlen($reason) > 255) {

        $error = "Reason must not exceed 255 characters.";

    } else {

        $quantity = (float)$quantity;

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        mysqli_begin_transaction($conn);

        try {

            /*
            |--------------------------------------------------------------------------
            | LOCK PRODUCT
            |--------------------------------------------------------------------------
            */

            $lock_stmt = mysqli_prepare(
                $conn,
                "SELECT stock
                 FROM products
                 WHERE id = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $lock_stmt,
                "i",
                $product_id
            );

            mysqli_stmt_execute($lock_stmt);

            $lock_result = mysqli_stmt_get_result($lock_stmt);

            $locked_product = mysqli_fetch_assoc($lock_result);

            mysqli_stmt_close($lock_stmt);

            if (!$locked_product) {
                throw new Exception("Product not found.");
            }

            $stock_before = (float)$locked_product['stock'];

            /*
            |--------------------------------------------------------------------------
            | CALCULATE NEW STOCK
            |--------------------------------------------------------------------------
            */

            if ($adjustment_type === "add") {

                $stock_after = $stock_before + $quantity;

                $movement_type = "Adjustment Add";

            } elseif ($adjustment_type === "remove") {

                if ($quantity > $stock_before) {
                    throw new Exception(
                        "You cannot remove more stock than available."
                    );
                }

                $stock_after = $stock_before - $quantity;

                $movement_type = "Adjustment Remove";

            } else {

                $stock_after = $quantity;

                $movement_type = "Adjustment Set";
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE PRODUCT STOCK
            |--------------------------------------------------------------------------
            */

            $update_stmt = mysqli_prepare(
                $conn,
                "UPDATE products
                 SET stock = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update_stmt,
                "di",
                $stock_after,
                $product_id
            );

            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception(
                    "Failed to update product stock."
                );
            }

            mysqli_stmt_close($update_stmt);

            /*
            |--------------------------------------------------------------------------
            | SAVE STOCK MOVEMENT
            |--------------------------------------------------------------------------
            */

            $movement_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO stock_movements
                (
                    product_id,
                    user_id,
                    type,
                    quantity,
                    stock_before,
                    stock_after,
                    reference_id,
                    reason
                )
                VALUES (?, ?, ?, ?, ?, ?, NULL, ?)"
            );

            mysqli_stmt_bind_param(
                $movement_stmt,
                "iisddds",
                $product_id,
                $user_id,
                $movement_type,
                $quantity,
                $stock_before,
                $stock_after,
                $reason
            );

            if (!mysqli_stmt_execute($movement_stmt)) {
                throw new Exception(
                    "Failed to save stock movement."
                );
            }

            mysqli_stmt_close($movement_stmt);

            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            mysqli_commit($conn);

            header(
                "Location: manage.php?success=adjusted"
            );

            exit();

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Stock Adjustment - Super Store</title>

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

.header {
    background: white;
    border-bottom: 1px solid #ddd;
    padding: 18px 25px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 15px;
}

.header h1 {
    font-size: 24px;
    margin-bottom: 5px;
}

.header p {
    color: #777;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-back {
    background: #343a40;
    color: white;
}

.btn-save {
    background: #007bff;
    color: white;
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.container {
    width: 94%;
    max-width: 900px;
    margin: 35px auto;
}

/*
|--------------------------------------------------------------------------
| ALERTS
|--------------------------------------------------------------------------
*/

.alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/*
|--------------------------------------------------------------------------
| PRODUCT CARD
|--------------------------------------------------------------------------
*/

.product-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;

    box-shadow: 0 3px 15px rgba(0,0,0,0.07);
}

.product-title {
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 8px;
}

.product-barcode {
    color: #777;
    font-family: monospace;
}

.product-info {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.info-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.info-label {
    color: #777;
    font-size: 12px;
    margin-bottom: 6px;
}

.info-value {
    font-size: 18px;
    font-weight: bold;
}

/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-card {
    background: white;
    border-radius: 12px;
    padding: 25px;

    box-shadow: 0 3px 15px rgba(0,0,0,0.07);
}

.form-card h2 {
    font-size: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 7px;
}

.form-control {
    width: 100%;
    padding: 12px;

    border: 1px solid #ccc;
    border-radius: 7px;

    font-size: 14px;
    outline: none;
}

.form-control:focus {
    border-color: #007bff;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

/*
|--------------------------------------------------------------------------
| TYPE OPTIONS
|--------------------------------------------------------------------------
*/

.type-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.type-option {
    position: relative;
}

.type-option input {
    position: absolute;
    opacity: 0;
}

.type-option label {
    display: block;
    padding: 15px;

    border: 2px solid #ddd;
    border-radius: 8px;

    text-align: center;
    cursor: pointer;

    transition: 0.2s;
}

.type-option input:checked + label {
    border-color: #007bff;
    background: #eef6ff;
    color: #007bff;
}

.add-label {
    color: #198754;
}

.remove-label {
    color: #dc3545;
}

.set-label {
    color: #6f42c1;
}

/*
|--------------------------------------------------------------------------
| PREVIEW
|--------------------------------------------------------------------------
*/

.preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 20px;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.preview-item {
    text-align: center;
}

.preview-label {
    color: #777;
    font-size: 12px;
    margin-bottom: 5px;
}

.preview-value {
    font-size: 20px;
    font-weight: bold;
}

/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .product-info {
        grid-template-columns: repeat(2, 1fr);
    }

    .type-options {
        grid-template-columns: 1fr;
    }

    .preview-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 450px) {

    .container {
        width: 92%;
    }

    .product-info {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .btn {
        width: 100%;
        text-align: center;
    }

}

</style>

</head>

<body>

<!-- HEADER -->

<div class="header">

    <div>

        <h1>📦 Stock Adjustment</h1>

        <p>
            Add, remove or set product stock
        </p>

    </div>

    <div class="header-actions">

        <a
            href="manage.php"
            class="btn btn-back"
        >
            ← Back to Stock
        </a>

    </div>

</div>


<div class="container">

    <?php if ($error): ?>

        <div class="alert alert-error">

            ❌ <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- PRODUCT INFORMATION -->

    <div class="product-card">

        <div class="product-title">

            <?= htmlspecialchars($product['name']) ?>

        </div>

        <div class="product-barcode">

            Barcode:
            <?= htmlspecialchars($product['barcode']) ?>

        </div>


        <div class="product-info">

            <div class="info-box">

                <div class="info-label">
                    Current Stock
                </div>

                <div class="info-value">
                    <?= number_format(
                        (float)$product['stock'],
                        2
                    ) ?>
                    <?= htmlspecialchars($product['unit']) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Minimum Stock
                </div>

                <div class="info-value">
                    <?= number_format(
                        (float)$product['min_stock'],
                        2
                    ) ?>
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Purchase Price
                </div>

                <div class="info-value">
                    <?= number_format(
                        (float)$product['purchase_price'],
                        2
                    ) ?>
                    AFN
                </div>

            </div>


            <div class="info-box">

                <div class="info-label">
                    Selling Price
                </div>

                <div class="info-value">
                    <?= number_format(
                        (float)$product['selling_price'],
                        2
                    ) ?>
                    AFN
                </div>

            </div>

        </div>

    </div>


    <!-- FORM -->

    <div class="form-card">

        <h2>
            Adjust Stock
        </h2>


        <form
            method="POST"
            action=""
            onsubmit="return confirmAdjustment();"
        >

            <input
                type="hidden"
                name="product_id"
                value="<?= $product['id'] ?>"
            >


            <!-- ADJUSTMENT TYPE -->

            <div class="form-group">

                <label>
                    Adjustment Type
                </label>

                <div class="type-options">

                    <div class="type-option">

                        <input
                            type="radio"
                            id="add"
                            name="adjustment_type"
                            value="add"
                            checked
                            onchange="calculateStock()"
                        >

                        <label
                            for="add"
                            class="add-label"
                        >
                            ➕ Add Stock
                        </label>

                    </div>


                    <div class="type-option">

                        <input
                            type="radio"
                            id="remove"
                            name="adjustment_type"
                            value="remove"
                            onchange="calculateStock()"
                        >

                        <label
                            for="remove"
                            class="remove-label"
                        >
                            ➖ Remove Stock
                        </label>

                    </div>


                    <div class="type-option">

                        <input
                            type="radio"
                            id="set"
                            name="adjustment_type"
                            value="set"
                            onchange="calculateStock()"
                        >

                        <label
                            for="set"
                            class="set-label"
                        >
                            🔄 Set Stock
                        </label>

                    </div>

                </div>

            </div>


            <!-- QUANTITY -->

            <div class="form-group">

                <label for="quantity">
                    Quantity
                </label>

                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    class="form-control"
                    min="0"
                    step="0.01"
                    placeholder="Enter quantity"
                    required
                    oninput="calculateStock()"
                >

            </div>


            <!-- PREVIEW -->

            <div class="preview">

                <div class="preview-grid">

                    <div class="preview-item">

                        <div class="preview-label">
                            Current Stock
                        </div>

                        <div class="preview-value">

                            <span id="currentStock">
                                <?= number_format(
                                    (float)$product['stock'],
                                    2
                                ) ?>
                            </span>

                        </div>

                    </div>


                    <div class="preview-item">

                        <div class="preview-label">
                            Adjustment
                        </div>

                        <div class="preview-value">

                            <span id="adjustmentValue">
                                0.00
                            </span>

                        </div>

                    </div>


                    <div class="preview-item">

                        <div class="preview-label">
                            New Stock
                        </div>

                        <div class="preview-value">

                            <span id="newStock">
                                <?= number_format(
                                    (float)$product['stock'],
                                    2
                                ) ?>
                            </span>

                        </div>

                    </div>

                </div>

            </div>


            <!-- REASON -->

            <div class="form-group">

                <label for="reason">
                    Reason
                </label>

                <textarea
                    name="reason"
                    id="reason"
                    class="form-control"
                    maxlength="255"
                    placeholder="Example: Damaged items, physical stock count, new stock found..."
                    required
                ></textarea>

            </div>


            <!-- BUTTONS -->

            <div class="form-actions">

                <a
                    href="manage.php"
                    class="btn btn-cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-save"
                >
                    💾 Save Adjustment
                </button>

            </div>

        </form>

    </div>

</div>


<script>

const currentStock =
    <?= (float)$product['stock'] ?>;


/*
|--------------------------------------------------------------------------
| CALCULATE NEW STOCK
|--------------------------------------------------------------------------
*/

function calculateStock() {

    const quantityInput =
        document.getElementById("quantity");

    const quantity =
        parseFloat(quantityInput.value) || 0;

    const type =
        document.querySelector(
            'input[name="adjustment_type"]:checked'
        ).value;

    let newStock = currentStock;

    let adjustment = quantity;


    if (type === "add") {

        newStock =
            currentStock + quantity;

        adjustment =
            quantity;

    } else if (type === "remove") {

        newStock =
            currentStock - quantity;

        adjustment =
            -quantity;

    } else if (type === "set") {

        newStock =
            quantity;

        adjustment =
            quantity - currentStock;
    }


    if (newStock < 0) {
        newStock = 0;
    }


    document.getElementById(
        "adjustmentValue"
    ).textContent =
        adjustment.toFixed(2);


    document.getElementById(
        "newStock"
    ).textContent =
        newStock.toFixed(2);
}


/*
|--------------------------------------------------------------------------
| CONFIRM
|--------------------------------------------------------------------------
*/

function confirmAdjustment() {

    const quantity =
        parseFloat(
            document.getElementById("quantity").value
        ) || 0;

    if (quantity < 0) {

        alert("Quantity cannot be negative.");

        return false;
    }


    const type =
        document.querySelector(
            'input[name="adjustment_type"]:checked'
        ).value;


    let message =
        "Are you sure you want to save this stock adjustment?";


    if (type === "add") {

        message =
            "Are you sure you want to ADD " +
            quantity +
            " units to stock?";

    } else if (type === "remove") {

        message =
            "Are you sure you want to REMOVE " +
            quantity +
            " units from stock?";

    } else if (type === "set") {

        message =
            "Are you sure you want to SET stock to " +
            quantity +
            " units?";
    }


    return confirm(message);
}

</script>

</body>

</html>