<?php

session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

/* =========================
   ADMIN ONLY
========================= */

if ($user_role !== 'Admin') {
    header("Location: manage.php?error=access_denied");
    exit();
}

/* =========================
   POST ONLY
========================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage.php");
    exit();
}

/* =========================
   PAYMENT ID
========================= */

$payment_id = (int)($_POST['id'] ?? 0);

if ($payment_id <= 0) {
    header("Location: manage.php?error=invalid_payment");
    exit();
}

/* =========================
   START TRANSACTION
========================= */

mysqli_begin_transaction($conn);

try {

    /* =========================
       GET PAYMENT + LOCK
    ========================= */

    $payment_stmt = mysqli_prepare(
        $conn,
        "
        SELECT
            id,
            customer_id,
            supplier_id,
            amount,
            payment_type,
            description,
            payment_date
        FROM payments
        WHERE id = ?
        FOR UPDATE
        "
    );

    if (!$payment_stmt) {
        throw new Exception(
            "Payment query preparation failed."
        );
    }

    mysqli_stmt_bind_param(
        $payment_stmt,
        "i",
        $payment_id
    );

    mysqli_stmt_execute($payment_stmt);

    $payment_result =
        mysqli_stmt_get_result($payment_stmt);

    if (
        !$payment_result ||
        mysqli_num_rows($payment_result) === 0
    ) {
        throw new Exception(
            "Payment record not found."
        );
    }

    $payment = mysqli_fetch_assoc(
        $payment_result
    );

    $amount = (float)$payment['amount'];

    $customer_id =
        !empty($payment['customer_id'])
        ? (int)$payment['customer_id']
        : 0;

    $supplier_id =
        !empty($payment['supplier_id'])
        ? (int)$payment['supplier_id']
        : 0;

    $payment_type =
        $payment['payment_type'];


    /* =====================================================
       CUSTOMER PAYMENT
       ===================================================== */

    if ($payment_type === 'Customer Payment') {

        if ($customer_id <= 0) {
            throw new Exception(
                "Invalid customer payment record."
            );
        }

        /* =========================
           LOCK CUSTOMER
        ========================= */

        $customer_stmt = mysqli_prepare(
            $conn,
            "
            SELECT id, name, balance
            FROM customers
            WHERE id = ?
            FOR UPDATE
            "
        );

        if (!$customer_stmt) {
            throw new Exception(
                "Customer query preparation failed."
            );
        }

        mysqli_stmt_bind_param(
            $customer_stmt,
            "i",
            $customer_id
        );

        mysqli_stmt_execute($customer_stmt);

        $customer_result =
            mysqli_stmt_get_result($customer_stmt);

        if (
            !$customer_result ||
            mysqli_num_rows($customer_result) === 0
        ) {
            throw new Exception(
                "Customer associated with this payment was not found."
            );
        }

        $customer =
            mysqli_fetch_assoc($customer_result);

        $current_balance =
            (float)$customer['balance'];

        /*
        |--------------------------------------------------------------------------
        | RESTORE CUSTOMER BALANCE
        |--------------------------------------------------------------------------
        |
        | Example:
        | Before payment = 10,000
        | Payment         = 3,000
        | Current balance = 7,000
        |
        | Delete payment:
        | New balance = 7,000 + 3,000 = 10,000
        |
        */

        $new_balance =
            $current_balance + $amount;


        /* =========================
           UPDATE CUSTOMER
        ========================= */

        $update_customer = mysqli_prepare(
            $conn,
            "
            UPDATE customers
            SET balance = ?
            WHERE id = ?
            "
        );

        if (!$update_customer) {
            throw new Exception(
                "Customer balance update preparation failed."
            );
        }

        mysqli_stmt_bind_param(
            $update_customer,
            "di",
            $new_balance,
            $customer_id
        );

        if (!mysqli_stmt_execute($update_customer)) {
            throw new Exception(
                "Customer balance could not be restored."
            );
        }
    }


    /* =====================================================
       SUPPLIER PAYMENT
       ===================================================== */

    elseif ($payment_type === 'Supplier Payment') {

        if ($supplier_id <= 0) {
            throw new Exception(
                "Invalid supplier payment record."
            );
        }

        /* =========================
           LOCK SUPPLIER
        ========================= */

        $supplier_stmt = mysqli_prepare(
            $conn,
            "
            SELECT id, name, balance
            FROM suppliers
            WHERE id = ?
            FOR UPDATE
            "
        );

        if (!$supplier_stmt) {
            throw new Exception(
                "Supplier query preparation failed."
            );
        }

        mysqli_stmt_bind_param(
            $supplier_stmt,
            "i",
            $supplier_id
        );

        mysqli_stmt_execute($supplier_stmt);

        $supplier_result =
            mysqli_stmt_get_result($supplier_stmt);

        if (
            !$supplier_result ||
            mysqli_num_rows($supplier_result) === 0
        ) {
            throw new Exception(
                "Supplier associated with this payment was not found."
            );
        }

        $supplier =
            mysqli_fetch_assoc($supplier_result);

        $current_balance =
            (float)$supplier['balance'];

        /*
        |--------------------------------------------------------------------------
        | RESTORE SUPPLIER BALANCE
        |--------------------------------------------------------------------------
        */

        $new_balance =
            $current_balance + $amount;


        /* =========================
           UPDATE SUPPLIER
        ========================= */

        $update_supplier = mysqli_prepare(
            $conn,
            "
            UPDATE suppliers
            SET balance = ?
            WHERE id = ?
            "
        );

        if (!$update_supplier) {
            throw new Exception(
                "Supplier balance update preparation failed."
            );
        }

        mysqli_stmt_bind_param(
            $update_supplier,
            "di",
            $new_balance,
            $supplier_id
        );

        if (!mysqli_stmt_execute($update_supplier)) {
            throw new Exception(
                "Supplier balance could not be restored."
            );
        }
    }


    /* =====================================================
       INVALID PAYMENT TYPE
       ===================================================== */

    else {

        throw new Exception(
            "Invalid payment type."
        );
    }


    /* =========================
       DELETE PAYMENT
    ========================= */

    $delete_stmt = mysqli_prepare(
        $conn,
        "
        DELETE FROM payments
        WHERE id = ?
        "
    );

    if (!$delete_stmt) {
        throw new Exception(
            "Payment delete preparation failed."
        );
    }

    mysqli_stmt_bind_param(
        $delete_stmt,
        "i",
        $payment_id
    );

    if (!mysqli_stmt_execute($delete_stmt)) {
        throw new Exception(
            "Payment could not be deleted."
        );
    }

    /* =========================
       CHECK DELETE
    ========================= */

    if (mysqli_stmt_affected_rows($delete_stmt) <= 0) {
        throw new Exception(
            "Payment was not deleted."
        );
    }


    /* =========================
       COMMIT
    ========================= */

    mysqli_commit($conn);


    /* =========================
       SUCCESS
    ========================= */

    header(
        "Location: manage.php?success=payment_deleted"
    );

    exit();


} catch (Exception $e) {

    /* =========================
       ROLLBACK
    ========================= */

    mysqli_rollback($conn);

    /*
    |--------------------------------------------------------------------------
    | LOGICAL ERROR MESSAGE
    |--------------------------------------------------------------------------
    */

    $error_message = $e->getMessage();

    header(
        "Location: manage.php?error=" .
        urlencode($error_message)
    );

    exit();
}

?>