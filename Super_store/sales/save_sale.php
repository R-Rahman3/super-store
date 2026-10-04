<?php

session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: pos.php");
    exit();
}

/* =========================
   DATABASE
========================= */

require_once "../db.php";

/* =========================
   USER
========================= */

$user_id = (int) $_SESSION["user_id"];

/* =========================
   GET POST DATA
========================= */

$invoice_no = trim($_POST["invoice_no"] ?? "");

$customer_id = isset($_POST["customer_id"])
    ? (int) $_POST["customer_id"]
    : 0;

$discount = isset($_POST["discount"])
    ? (float) $_POST["discount"]
    : 0;

$paid_amount = isset($_POST["paid_amount"])
    ? (float) $_POST["paid_amount"]
    : 0;

$payment_method = trim(
    $_POST["payment_method"] ?? "Cash"
);

$product_ids = $_POST["product_id"] ?? [];
$quantities = $_POST["quantity"] ?? [];

/* =========================
   VALIDATION
========================= */

if ($invoice_no === "") {
    die("Invalid invoice number.");
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $invoice_no)) {
    die("Invalid invoice number format.");
}

if ($discount < 0) {
    die("Discount cannot be negative.");
}

if ($paid_amount < 0) {
    die("Paid amount cannot be negative.");
}

$allowed_payment_methods = [
    "Cash",
    "Card",
    "Credit"
];

if (!in_array($payment_method, $allowed_payment_methods, true)) {
    die("Invalid payment method.");
}

if (!is_array($product_ids) || !is_array($quantities)) {
    die("Invalid sale items.");
}

if (count($product_ids) === 0) {
    die("No products were added to the sale.");
}

if (count($product_ids) !== count($quantities)) {
    die("Invalid product and quantity data.");
}

/* =========================
   CUSTOMER VALIDATION
========================= */

if ($customer_id > 0) {

    $customer_check = mysqli_prepare(
        $conn,
        "SELECT id
         FROM customers
         WHERE id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $customer_check,
        "i",
        $customer_id
    );

    mysqli_stmt_execute($customer_check);

    $customer_result = mysqli_stmt_get_result(
        $customer_check
    );

    if (mysqli_num_rows($customer_result) === 0) {

        mysqli_stmt_close($customer_check);

        die("Selected customer does not exist.");
    }

    mysqli_stmt_close($customer_check);
}

/* =========================
   PAYMENT BASIC RULES
========================= */

if ($payment_method === "Credit" && $customer_id <= 0) {
    die("A customer is required for credit sales.");
}

if ($payment_method === "Credit" && $paid_amount < 0) {
    die("Invalid credit payment.");
}

if ($payment_method === "Card" && $paid_amount < 0) {
    die("Invalid card payment.");
}

/* =========================
   START TRANSACTION
========================= */

mysqli_begin_transaction($conn);

try {

    /* =========================
       CHECK INVOICE
    ========================= */

    $invoice_check = mysqli_prepare(
        $conn,
        "SELECT id
         FROM sales
         WHERE invoice_no = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $invoice_check,
        "s",
        $invoice_no
    );

    mysqli_stmt_execute($invoice_check);

    $invoice_result = mysqli_stmt_get_result(
        $invoice_check
    );

    if (mysqli_num_rows($invoice_result) > 0) {

        mysqli_stmt_close($invoice_check);

        throw new Exception(
            "Invoice number already exists."
        );
    }

    mysqli_stmt_close($invoice_check);


    /* =========================
       PREPARE PRODUCT QUERY
    ========================= */

    /*
     * IMPORTANT:
     * We do NOT trust selling_price
     * sent from pos.php.
     *
     * Prices come directly from database.
     */

    $product_stmt = mysqli_prepare(
        $conn,
        "SELECT
            id,
            name,
            selling_price,
            purchase_price,
            stock
         FROM products
         WHERE id = ?
         FOR UPDATE"
    );


    /* =========================
       PREPARE INSERT SALE ITEM
    ========================= */

    $item_stmt = mysqli_prepare(
        $conn,
        "INSERT INTO sale_items
        (
            sale_id,
            product_id,
            quantity,
            selling_price,
            purchase_price,
            total
        )
        VALUES (?, ?, ?, ?, ?, ?)"
    );


    /* =========================
       PREPARE STOCK UPDATE
    ========================= */

    $stock_stmt = mysqli_prepare(
        $conn,
        "UPDATE products
         SET stock = stock - ?
         WHERE id = ?"
    );


    /* =========================
       PROCESS CART
    ========================= */

    $subtotal = 0;

    $items = [];


    for ($i = 0; $i < count($product_ids); $i++) {

        $product_id = (int) $product_ids[$i];

        $quantity = (float) $quantities[$i];


        /* =========================
           ITEM VALIDATION
        ========================= */

        if ($product_id <= 0) {
            throw new Exception(
                "Invalid product selected."
            );
        }

        if ($quantity <= 0) {
            throw new Exception(
                "Product quantity must be greater than zero."
            );
        }

        /*
         * Super Store stock is normally
         * sold in whole pieces.
         */

        if (floor($quantity) != $quantity) {
            throw new Exception(
                "Product quantity must be a whole number."
            );
        }

        $quantity = (int) $quantity;


        /* =========================
           GET REAL PRODUCT DATA
        ========================= */

        mysqli_stmt_bind_param(
            $product_stmt,
            "i",
            $product_id
        );

        mysqli_stmt_execute($product_stmt);

        $product_result = mysqli_stmt_get_result(
            $product_stmt
        );

        $product = mysqli_fetch_assoc(
            $product_result
        );


        if (!$product) {
            throw new Exception(
                "Product ID {$product_id} was not found."
            );
        }


        /* =========================
           CHECK STOCK
        ========================= */

        $current_stock = (int) $product["stock"];

        if ($quantity > $current_stock) {

            throw new Exception(
                "Not enough stock for product: " .
                $product["name"] .
                ". Available stock: " .
                $current_stock
            );
        }


        /* =========================
           DATABASE PRICES
        ========================= */

        $selling_price = (float)
            $product["selling_price"];

        $purchase_price = (float)
            $product["purchase_price"];


        if ($selling_price < 0) {
            throw new Exception(
                "Invalid selling price for product: " .
                $product["name"]
            );
        }

        if ($purchase_price < 0) {
            throw new Exception(
                "Invalid purchase price for product: " .
                $product["name"]
            );
        }


        /* =========================
           CALCULATE ITEM TOTAL
        ========================= */

        $item_total =
            $quantity * $selling_price;

        $subtotal += $item_total;


        /* =========================
           STORE ITEM
        ========================= */

        $items[] = [
            "product_id" => $product_id,
            "quantity" => $quantity,
            "selling_price" => $selling_price,
            "purchase_price" => $purchase_price,
            "total" => $item_total
        ];
    }


    mysqli_stmt_close($product_stmt);


    /* =========================
       CHECK SUBTOTAL
    ========================= */

    if ($subtotal <= 0) {
        throw new Exception(
            "Sale total must be greater than zero."
        );
    }


    /* =========================
       CHECK DISCOUNT
    ========================= */

    if ($discount > $subtotal) {
        throw new Exception(
            "Discount cannot be greater than subtotal."
        );
    }


    /* =========================
       CALCULATE TOTAL
    ========================= */

    $total_amount =
        $subtotal - $discount;


    if ($total_amount <= 0) {
        throw new Exception(
            "Final sale amount must be greater than zero."
        );
    }


    /* =========================
       PAYMENT RULES
    ========================= */

    $due_amount = 0;
    $change_amount = 0;


    /* =========================
       CASH
    ========================= */

    if ($payment_method === "Cash") {

        if ($paid_amount < $total_amount) {

            throw new Exception(
                "Cash payment must be equal to or greater than the total amount."
            );
        }

        $due_amount = 0;

        $change_amount =
            $paid_amount - $total_amount;
    }


    /* =========================
       CARD
    ========================= */

    elseif ($payment_method === "Card") {

        if ($paid_amount != $total_amount) {

            throw new Exception(
                "Card payment must be exactly equal to the total amount."
            );
        }

        $due_amount = 0;

        $change_amount = 0;
    }


    /* =========================
       CREDIT
    ========================= */

    elseif ($payment_method === "Credit") {

        if ($paid_amount > $total_amount) {

            throw new Exception(
                "Credit sale payment cannot be greater than the total amount."
            );
        }

        $due_amount =
            $total_amount - $paid_amount;

        $change_amount = 0;
    }


    /* =========================
       INSERT SALE
    ========================= */

    $sale_stmt = mysqli_prepare(
        $conn,
        "INSERT INTO sales
        (
            invoice_no,
            customer_id,
            user_id,
            subtotal,
            discount,
            total_amount,
            paid_amount,
            due_amount,
            payment_method
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );


    mysqli_stmt_bind_param(
        $sale_stmt,
        "siiddddds",
        $invoice_no,
        $customer_id,
        $user_id,
        $subtotal,
        $discount,
        $total_amount,
        $paid_amount,
        $due_amount,
        $payment_method
    );


    if (!mysqli_stmt_execute($sale_stmt)) {

        throw new Exception(
            "Failed to save sale."
        );
    }


    $sale_id = mysqli_insert_id($conn);

    mysqli_stmt_close($sale_stmt);


    /* =========================
       INSERT SALE ITEMS
       + REDUCE STOCK
    ========================= */

    foreach ($items as $item) {

        $product_id =
            $item["product_id"];

        $quantity =
            $item["quantity"];

        $selling_price =
            $item["selling_price"];

        $purchase_price =
            $item["purchase_price"];

        $item_total =
            $item["total"];


        /* =========================
           INSERT ITEM
        ========================= */

        mysqli_stmt_bind_param(
            $item_stmt,
            "iiiddd",
            $sale_id,
            $product_id,
            $quantity,
            $selling_price,
            $purchase_price,
            $item_total
        );


        if (!mysqli_stmt_execute($item_stmt)) {

            throw new Exception(
                "Failed to save sale item."
            );
        }


        /* =========================
           REDUCE STOCK
        ========================= */

        mysqli_stmt_bind_param(
            $stock_stmt,
            "ii",
            $quantity,
            $product_id
        );


        if (!mysqli_stmt_execute($stock_stmt)) {

            throw new Exception(
                "Failed to update product stock."
            );
        }
    }


    mysqli_stmt_close($item_stmt);
    mysqli_stmt_close($stock_stmt);


    /* =========================
       UPDATE CUSTOMER BALANCE
       FOR CREDIT/DUE
    ========================= */

    if ($due_amount > 0) {

        if ($customer_id <= 0) {

            throw new Exception(
                "Customer is required when there is an outstanding amount."
            );
        }


        $customer_balance_stmt = mysqli_prepare(
            $conn,
            "UPDATE customers
             SET balance = balance + ?
             WHERE id = ?"
        );


        mysqli_stmt_bind_param(
            $customer_balance_stmt,
            "di",
            $due_amount,
            $customer_id
        );


        if (
            !mysqli_stmt_execute(
                $customer_balance_stmt
            )
        ) {

            throw new Exception(
                "Failed to update customer balance."
            );
        }


        if (mysqli_stmt_affected_rows(
            $customer_balance_stmt
        ) !== 1) {

            throw new Exception(
                "Customer balance could not be updated."
            );
        }


        mysqli_stmt_close(
            $customer_balance_stmt
        );
    }


    /* =========================
       COMMIT
    ========================= */

    mysqli_commit($conn);


    /* =========================
       SUCCESS REDIRECT
    ========================= */

    /*
     * change is passed only for
     * Cash transactions.
     */

    $change_for_url =
        number_format(
            $change_amount,
            2,
            ".",
            ""
        );


    header(
        "Location: view.php?id=" .
        $sale_id .
        "&success=1&change=" .
        urlencode($change_for_url)
    );

    exit();


} catch (Throwable $e) {

    /* =========================
       ROLLBACK
    ========================= */

    mysqli_rollback($conn);


    /*
     * User-friendly error.
     *
     * Do not expose SQL/database
     * technical details.
     */

    $error_message =
        $e->getMessage();


    /*
     * Redirect back to POS with
     * the error message.
     */

    header(
        "Location: pos.php?error=" .
        urlencode($error_message)
    );

    exit();
}

?>