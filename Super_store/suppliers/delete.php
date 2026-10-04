<?php
session_start();

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
    header("Location: manage.php?error=invalid_request");
    exit();
}


include "../db.php";


/* =========================
   GET SUPPLIER ID
========================= */

if (!isset($_POST["id"]) || !is_numeric($_POST["id"])) {
    header("Location: manage.php?error=invalid_id");
    exit();
}

$id = (int) $_POST["id"];

if ($id <= 0) {
    header("Location: manage.php?error=invalid_id");
    exit();
}


/* =========================
   CHECK SUPPLIER
========================= */

$sql = "
    SELECT
        id,
        name,
        balance
    FROM suppliers
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    header("Location: manage.php?error=delete_failed");
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$supplier = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$supplier) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   CHECK PURCHASE HISTORY
========================= */

$history_sql = "
    SELECT id
    FROM purchases
    WHERE supplier_id = ?
    LIMIT 1
";

$history_stmt = mysqli_prepare($conn, $history_sql);

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
mysqli_stmt_store_result($history_stmt);

$has_history = mysqli_stmt_num_rows($history_stmt) > 0;

mysqli_stmt_close($history_stmt);


/*
   Supplier cannot be deleted if
   purchase history exists.
*/

if ($has_history) {
    header("Location: manage.php?error=has_history");
    exit();
}


/* =========================
   CHECK BALANCE
========================= */

$balance = (float) $supplier["balance"];


/*
   If store still owes money
   to supplier, don't delete.
*/

if ($balance > 0) {
    header("Location: manage.php?error=has_balance");
    exit();
}


/* =========================
   DELETE SUPPLIER
========================= */

$delete_sql = "
    DELETE FROM suppliers
    WHERE id = ?
";

$delete_stmt = mysqli_prepare($conn, $delete_sql);

if (!$delete_stmt) {
    header("Location: manage.php?error=delete_failed");
    exit();
}

mysqli_stmt_bind_param(
    $delete_stmt,
    "i",
    $id
);


if (mysqli_stmt_execute($delete_stmt)) {

    mysqli_stmt_close($delete_stmt);
    mysqli_close($conn);

    header("Location: manage.php?success=deleted");
    exit();

} else {

    mysqli_stmt_close($delete_stmt);
    mysqli_close($conn);

    header("Location: manage.php?error=delete_failed");
    exit();
}

?>