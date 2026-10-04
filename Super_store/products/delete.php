```php
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

if ($_SESSION["role"] !== "Admin") {
    die("Access Denied");
}


/* =========================
   CHECK REQUEST
========================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: manage.php");
    exit();
}


/* =========================
   CHECK PRODUCT ID
========================= */

if (!isset($_POST["id"]) || !is_numeric($_POST["id"])) {
    header("Location: manage.php");
    exit();
}

$product_id = intval($_POST["id"]);


/* =========================
   CHECK PRODUCT EXISTS
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, name
     FROM products
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $product_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$product = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$product) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   CHECK SALES HISTORY
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id
     FROM sale_items
     WHERE product_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $product_id
);

mysqli_stmt_execute($stmt);

$sale_result = mysqli_stmt_get_result($stmt);

$has_sales = mysqli_num_rows($sale_result) > 0;

mysqli_stmt_close($stmt);


/* =========================
   CHECK PURCHASE HISTORY
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id
     FROM purchase_items
     WHERE product_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $product_id
);

mysqli_stmt_execute($stmt);

$purchase_result = mysqli_stmt_get_result($stmt);

$has_purchases = mysqli_num_rows($purchase_result) > 0;

mysqli_stmt_close($stmt);


/* =========================
   DO NOT DELETE HISTORY
========================= */

if ($has_sales || $has_purchases) {

    header("Location: manage.php?error=has_history");
    exit();
}


/* =========================
   DELETE PRODUCT
========================= */

$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM products
     WHERE id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $product_id
);


if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header("Location: manage.php?success=deleted");
    exit();

} else {

    mysqli_stmt_close($stmt);

    header("Location: manage.php?error=delete_failed");
    exit();
}

?>
```
