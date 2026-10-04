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

/* Only Admin can delete purchase */
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
    header("Location: manage.php?error=access_denied");
    exit();
}

/* Only POST request allowed */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage.php");
    exit();
}

/* =========================
   GET PURCHASE ID
========================= */

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: manage.php?error=not_found");
    exit();
}

/* =========================
   START TRANSACTION
========================= */

mysqli_begin_transaction($conn);

try {

    /* =========================
       GET PURCHASE
    ========================= */

    $purchase_sql = "
        SELECT
            id,
            supplier_id,
            total_amount,
            paid_amount,
            due_amount
        FROM purchases
        WHERE id = ?
        LIMIT 1
    ";

    $purchase_stmt = mysqli_prepare($conn, $purchase_sql);

    if (!$purchase_stmt) {
        throw new Exception("Purchase query failed.");
    }

    mysqli_stmt_bind_param(
        $purchase_stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($purchase_stmt);

    $purchase_result = mysqli_stmt_get_result($purchase_stmt);

    if (mysqli_num_rows($purchase_result) !== 1) {
        mysqli_stmt_close($purchase_stmt);
        throw new Exception("Purchase not found.");
    }

    $purchase = mysqli_fetch_assoc($purchase_result);

    mysqli_stmt_close($purchase_stmt);


    /* =========================
       GET PURCHASE ITEMS
    ========================= */

    $items_sql = "
        SELECT
            product_id,
            quantity
        FROM purchase_items
        WHERE purchase_id = ?
    ";

    $items_stmt = mysqli_prepare($conn, $items_sql);

    if (!$items_stmt) {
        throw new Exception("Purchase items query failed.");
    }

    mysqli_stmt_bind_param(
        $items_stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($items_stmt);

    $items_result = mysqli_stmt_get_result($items_stmt);

    $items = [];

    while ($item = mysqli_fetch_assoc($items_result)) {
        $items[] = $item;
    }

    mysqli_stmt_close($items_stmt);


    /* =========================
       CHECK STOCK
    ========================= */

    /*
       Before deleting the purchase, check whether
       the current stock is enough to reverse the purchase.

       Example:
       Purchase quantity = 10
       Current stock = 6

       This means some of those 10 products may already
       have been sold, so deleting the purchase could
       create negative stock.
    */

    $check_stock_sql = "
        SELECT
            id,
            name,
            stock
        FROM products
        WHERE id = ?
        LIMIT 1
    ";

    $check_stock_stmt = mysqli_prepare($conn, $check_stock_sql);

    if (!$check_stock_stmt) {
        throw new Exception("Stock check failed.");
    }

    foreach ($items as $item) {

        $product_id = (int)$item["product_id"];
        $quantity = (float)$item["quantity"];

        mysqli_stmt_bind_param(
            $check_stock_stmt,
            "i",
            $product_id
        );

        mysqli_stmt_execute($check_stock_stmt);

        $stock_result = mysqli_stmt_get_result($check_stock_stmt);

        if (mysqli_num_rows($stock_result) !== 1) {
            throw new Exception("Product not found.");
        }

        $product = mysqli_fetch_assoc($stock_result);

        if ((float)$product["stock"] < $quantity) {

            throw new Exception(
                "Cannot delete purchase because stock for product '" .
                $product["name"] .
                "' is already used/sold."
            );
        }
    }

    mysqli_stmt_close($check_stock_stmt);


    /* =========================
       REMOVE STOCK
    ========================= */

    $remove_stock_sql = "
        UPDATE products
        SET stock = stock - ?
        WHERE id = ?
    ";

    $remove_stock_stmt = mysqli_prepare(
        $conn,
        $remove_stock_sql
    );

    if (!$remove_stock_stmt) {
        throw new Exception("Stock update failed.");
    }

    foreach ($items as $item) {

        $product_id = (int)$item["product_id"];
        $quantity = (float)$item["quantity"];

        mysqli_stmt_bind_param(
            $remove_stock_stmt,
            "di",
            $quantity,
            $product_id
        );

        if (!mysqli_stmt_execute($remove_stock_stmt)) {
            throw new Exception("Failed to update product stock.");
        }
    }

    mysqli_stmt_close($remove_stock_stmt);


    /* =========================
       RESTORE SUPPLIER BALANCE
    ========================= */

    $supplier_id = $purchase["supplier_id"];
    $due_amount = (float)$purchase["due_amount"];

    if (!empty($supplier_id) && $due_amount > 0) {

        $supplier_sql = "
            UPDATE suppliers
            SET balance = balance - ?
            WHERE id = ?
        ";

        $supplier_stmt = mysqli_prepare(
            $conn,
            $supplier_sql
        );

        if (!$supplier_stmt) {
            throw new Exception("Supplier balance update failed.");
        }

        mysqli_stmt_bind_param(
            $supplier_stmt,
            "di",
            $due_amount,
            $supplier_id
        );

        if (!mysqli_stmt_execute($supplier_stmt)) {
            throw new Exception("Failed to update supplier balance.");
        }

        mysqli_stmt_close($supplier_stmt);
    }


    /* =========================
       DELETE PURCHASE ITEMS
    ========================= */

    $delete_items_sql = "
        DELETE FROM purchase_items
        WHERE purchase_id = ?
    ";

    $delete_items_stmt = mysqli_prepare(
        $conn,
        $delete_items_sql
    );

    if (!$delete_items_stmt) {
        throw new Exception("Could not delete purchase items.");
    }

    mysqli_stmt_bind_param(
        $delete_items_stmt,
        "i",
        $id
    );

    if (!mysqli_stmt_execute($delete_items_stmt)) {
        throw new Exception("Failed to delete purchase items.");
    }

    mysqli_stmt_close($delete_items_stmt);


    /* =========================
       DELETE PURCHASE
    ========================= */

    $delete_purchase_sql = "
        DELETE FROM purchases
        WHERE id = ?
    ";

    $delete_purchase_stmt = mysqli_prepare(
        $conn,
        $delete_purchase_sql
    );

    if (!$delete_purchase_stmt) {
        throw new Exception("Could not delete purchase.");
    }

    mysqli_stmt_bind_param(
        $delete_purchase_stmt,
        "i",
        $id
    );

    if (!mysqli_stmt_execute($delete_purchase_stmt)) {
        throw new Exception("Failed to delete purchase.");
    }

    if (mysqli_stmt_affected_rows($delete_purchase_stmt) !== 1) {
        throw new Exception("Purchase was not deleted.");
    }

    mysqli_stmt_close($delete_purchase_stmt);


    /* =========================
       COMMIT
    ========================= */

    mysqli_commit($conn);

    header("Location: manage.php?success=deleted");
    exit();

} catch (Exception $e) {

    /* =========================
       ROLLBACK
    ========================= */

    mysqli_rollback($conn);

    /*
       For development/testing:
       Show the error.

       Later, for production, we can
       replace this with a simple error code.
    */

    header(
        "Location: manage.php?error=" .
        urlencode($e->getMessage())
    );

    exit();
}

?>