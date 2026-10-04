<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}


/* =========================
   ADMIN ONLY
========================= */

$role = $_SESSION["role"] ?? "";

if ($role !== "Admin") {
    header("Location: manage.php?error=access_denied");
    exit();
}


/* =========================
   POST ONLY
========================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage.php?error=invalid_request");
    exit();
}


include "../db.php";


/* =========================
   SALE ID
========================= */

$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    header("Location: manage.php?error=invalid_id");
    exit();
}


/* =========================
   START TRANSACTION
========================= */

mysqli_begin_transaction($conn);

try {

    /* =========================
       GET SALE
    ========================= */

    $sale_sql = "
        SELECT
            id,
            invoice_no,
            customer_id,
            due_amount
        FROM sales
        WHERE id = ?
        LIMIT 1
    ";

    $sale_stmt = mysqli_prepare($conn, $sale_sql);

    if (!$sale_stmt) {
        throw new Exception("Unable to prepare sale query.");
    }

    mysqli_stmt_bind_param(
        $sale_stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($sale_stmt);

    $sale_result =
        mysqli_stmt_get_result($sale_stmt);

    if (mysqli_num_rows($sale_result) !== 1) {

        mysqli_stmt_close($sale_stmt);

        throw new Exception("not_found");
    }

    $sale =
        mysqli_fetch_assoc($sale_result);

    mysqli_stmt_close($sale_stmt);


    /* =========================
       GET SALE ITEMS
    ========================= */

    $items_sql = "
        SELECT
            id,
            product_id,
            quantity
        FROM sale_items
        WHERE sale_id = ?
    ";

    $items_stmt =
        mysqli_prepare($conn, $items_sql);

    if (!$items_stmt) {
        throw new Exception(
            "Unable to prepare items query."
        );
    }

    mysqli_stmt_bind_param(
        $items_stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($items_stmt);

    $items_result =
        mysqli_stmt_get_result($items_stmt);


    if (mysqli_num_rows($items_result) === 0) {

        mysqli_stmt_close($items_stmt);

        throw new Exception(
            "has_history"
        );
    }


    /* =========================
       PREPARE STOCK UPDATE
    ========================= */

    $stock_sql = "
        UPDATE products
        SET stock = stock + ?
        WHERE id = ?
    ";

    $stock_stmt =
        mysqli_prepare($conn, $stock_sql);

    if (!$stock_stmt) {

        mysqli_stmt_close($items_stmt);

        throw new Exception(
            "Unable to prepare stock query."
        );
    }


    /* =========================
       RESTORE STOCK
    ========================= */

    while ($item = mysqli_fetch_assoc($items_result)) {

        $product_id =
            (int)$item["product_id"];

        $quantity =
            (float)$item["quantity"];


        if ($product_id <= 0 || $quantity <= 0) {

            mysqli_stmt_close($stock_stmt);
            mysqli_stmt_close($items_stmt);

            throw new Exception(
                "Invalid sale item."
            );
        }


        /* Check product exists */

        $check_product_sql = "
            SELECT id
            FROM products
            WHERE id = ?
            LIMIT 1
        ";

        $check_product_stmt =
            mysqli_prepare(
                $conn,
                $check_product_sql
            );

        if (!$check_product_stmt) {

            mysqli_stmt_close($stock_stmt);
            mysqli_stmt_close($items_stmt);

            throw new Exception(
                "Unable to check product."
            );
        }

        mysqli_stmt_bind_param(
            $check_product_stmt,
            "i",
            $product_id
        );

        mysqli_stmt_execute(
            $check_product_stmt
        );

        $product_result =
            mysqli_stmt_get_result(
                $check_product_stmt
            );


        if (
            mysqli_num_rows(
                $product_result
            ) !== 1
        ) {

            mysqli_stmt_close(
                $check_product_stmt
            );

            mysqli_stmt_close(
                $stock_stmt
            );

            mysqli_stmt_close(
                $items_stmt
            );

            throw new Exception(
                "Product not found."
            );
        }

        mysqli_stmt_close(
            $check_product_stmt
        );


        /* Restore stock */

        mysqli_stmt_bind_param(
            $stock_stmt,
            "di",
            $quantity,
            $product_id
        );

        if (
            !mysqli_stmt_execute(
                $stock_stmt
            )
        ) {

            mysqli_stmt_close(
                $stock_stmt
            );

            mysqli_stmt_close(
                $items_stmt
            );

            throw new Exception(
                "Stock restoration failed."
            );
        }

    }


    mysqli_stmt_close($stock_stmt);
    mysqli_stmt_close($items_stmt);


    /* =========================
       RESTORE CUSTOMER BALANCE
       =========================
       
       If this was a credit sale,
       remove the sale's due amount
       from customer's balance.
    ========================= */

    $customer_id =
        !empty($sale["customer_id"])
        ? (int)$sale["customer_id"]
        : null;

    $due_amount =
        (float)$sale["due_amount"];


    if (
        $customer_id !== null &&
        $due_amount > 0
    ) {

        /* Check customer */

        $customer_check_sql = "
            SELECT
                id,
                balance
            FROM customers
            WHERE id = ?
            LIMIT 1
        ";

        $customer_check_stmt =
            mysqli_prepare(
                $conn,
                $customer_check_sql
            );

        if (!$customer_check_stmt) {

            throw new Exception(
                "Unable to check customer."
            );
        }

        mysqli_stmt_bind_param(
            $customer_check_stmt,
            "i",
            $customer_id
        );

        mysqli_stmt_execute(
            $customer_check_stmt
        );

        $customer_result =
            mysqli_stmt_get_result(
                $customer_check_stmt
            );


        if (
            mysqli_num_rows(
                $customer_result
            ) !== 1
        ) {

            mysqli_stmt_close(
                $customer_check_stmt
            );

            throw new Exception(
                "Customer not found."
            );
        }

        mysqli_stmt_close(
            $customer_check_stmt
        );


        /* Remove due from balance */

        $customer_sql = "
            UPDATE customers
            SET balance = balance - ?
            WHERE id = ?
        ";

        $customer_stmt =
            mysqli_prepare(
                $conn,
                $customer_sql
            );

        if (!$customer_stmt) {

            throw new Exception(
                "Unable to update customer balance."
            );
        }

        mysqli_stmt_bind_param(
            $customer_stmt,
            "di",
            $due_amount,
            $customer_id
        );

        if (
            !mysqli_stmt_execute(
                $customer_stmt
            )
        ) {

            mysqli_stmt_close(
                $customer_stmt
            );

            throw new Exception(
                "Customer balance update failed."
            );
        }

        mysqli_stmt_close(
            $customer_stmt
        );
    }


    /* =========================
       DELETE SALE ITEMS
    ========================= */

    $delete_items_sql = "
        DELETE FROM sale_items
        WHERE sale_id = ?
    ";

    $delete_items_stmt =
        mysqli_prepare(
            $conn,
            $delete_items_sql
        );

    if (!$delete_items_stmt) {

        throw new Exception(
            "Unable to delete sale items."
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

        mysqli_stmt_close(
            $delete_items_stmt
        );

        throw new Exception(
            "Unable to delete sale items."
        );
    }

    mysqli_stmt_close(
        $delete_items_stmt
    );


    /* =========================
       DELETE SALE
    ========================= */

    $delete_sale_sql = "
        DELETE FROM sales
        WHERE id = ?
    ";

    $delete_sale_stmt =
        mysqli_prepare(
            $conn,
            $delete_sale_sql
        );

    if (!$delete_sale_stmt) {

        throw new Exception(
            "Unable to delete sale."
        );
    }

    mysqli_stmt_bind_param(
        $delete_sale_stmt,
        "i",
        $id
    );

    if (
        !mysqli_stmt_execute(
            $delete_sale_stmt
        )
    ) {

        mysqli_stmt_close(
            $delete_sale_stmt
        );

        throw new Exception(
            "Unable to delete sale."
        );
    }


    if (
        mysqli_stmt_affected_rows(
            $delete_sale_stmt
        ) !== 1
    ) {

        mysqli_stmt_close(
            $delete_sale_stmt
        );

        throw new Exception(
            "Sale was not deleted."
        );
    }

    mysqli_stmt_close(
        $delete_sale_stmt
    );


    /* =========================
       COMMIT
    ========================= */

    mysqli_commit($conn);

    mysqli_close($conn);

    header(
        "Location: manage.php?success=deleted"
    );

    exit();


} catch (Exception $e) {

    /* =========================
       ROLLBACK
    ========================= */

    mysqli_rollback($conn);

    mysqli_close($conn);


    $error =
        $e->getMessage();


    if ($error === "not_found") {

        header(
            "Location: manage.php?error=not_found"
        );

    } elseif ($error === "has_history") {

        header(
            "Location: manage.php?error=has_history"
        );

    } else {

        header(
            "Location: manage.php?error=delete_failed"
        );
    }

    exit();

}

?>