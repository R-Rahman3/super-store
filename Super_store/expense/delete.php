<?php
session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";


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


/* =========================
   VALIDATE ID
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
   CHECK EXPENSE EXISTS
========================= */

$sql = "
    SELECT
        id,
        title,
        amount
    FROM expenses
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    header("Location: manage.php?error=delete_failed");
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$expense = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$expense) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   DELETE EXPENSE
========================= */

$delete_sql = "
    DELETE FROM expenses
    WHERE id = ?
";

$delete_stmt = mysqli_prepare(
    $conn,
    $delete_sql
);

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