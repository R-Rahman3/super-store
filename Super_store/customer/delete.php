<?php

session_start();

include "../db.php";

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

/* =========================
   ADMIN ONLY
========================= */

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
    header("Location: manage.php?error=access_denied");
    exit();
}

/* =========================
   POST ONLY
========================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage.php");
    exit();
}


/* =========================
   GET CUSTOMER ID
========================= */

$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   CHECK CUSTOMER
========================= */

$check_sql = "
    SELECT
        id,
        name,
        balance
    FROM customers
    WHERE id = ?
    LIMIT 1
";

$check_stmt = mysqli_prepare(
    $conn,
    $check_sql
);

if (!$check_stmt) {
    header("Location: manage.php?error=delete_failed");
    exit();
}

mysqli_stmt_bind_param(
    $check_stmt,
    "i",
    $id
);

mysqli_stmt_execute($check_stmt);

$result = mysqli_stmt_get_result(
    $check_stmt
);

if (mysqli_num_rows($result) !== 1) {

    mysqli_stmt_close($check_stmt);

    header("Location: manage.php?error=not_found");
    exit();
}

$customer = mysqli_fetch_assoc($result);

mysqli_stmt_close($check_stmt);


/* =========================
   CHECK SALES HISTORY
========================= */

$history_sql = "
    SELECT id
    FROM sales
    WHERE customer_id = ?
    LIMIT 1
";

$history_stmt = mysqli_prepare(
    $conn,
    $history_sql
);

if (!$history_stmt) {
    header("Location: manage.php?error=delete_failed");
    exit();
}

mysqli_stmt_bind_param(
    $history_stmt,
    "i",
    $id
);

mysqli_stmt_execute($history_stmt);

$history_result = mysqli_stmt_get_result(
    $history_stmt
);


/*
   Do not delete a customer that has
   sales history.
*/

if (mysqli_num_rows($history_result) > 0) {

    mysqli_stmt_close($history_stmt);

    header("Location: manage.php?error=has_history");
    exit();
}

mysqli_stmt_close($history_stmt);


/* =========================
   CHECK CUSTOMER BALANCE
========================= */

/*
   If customer still owes money,
   do not allow deletion.
*/

if ((float)$customer["balance"] > 0) {

    header("Location: manage.php?error=has_balance");
    exit();
}


/* =========================
   DELETE CUSTOMER
========================= */

$delete_sql = "
    DELETE FROM customers
    WHERE id = ?
";

$delete_stmt = mysqli_prepare(
    $conn,
    $delete_sql
);

if (!$delete_stmt) {
    header("Location: ../manage.php?error=delete_failed");
    exit();
}

mysqli_stmt_bind_param(
    $delete_stmt,
    "i",
    $id
);

if (mysqli_stmt_execute($delete_stmt)) {

    if (mysqli_stmt_affected_rows($delete_stmt) === 1) {

        mysqli_stmt_close($delete_stmt);

        header("Location: manage.php?success=deleted");
        exit();

    } else {

        mysqli_stmt_close($delete_stmt);

        header("Location: ../manage.php?error=delete_failed");
        exit();
    }

} else {

    mysqli_stmt_close($delete_stmt);

    header("Location: manage.php?error=delete_failed");
    exit();
}

?>