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
   SEARCH
========================= */

$search = "";

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}


/* =========================
   GET PRODUCTS
========================= */

$sql = "
    SELECT 
        products.*,
        categories.name AS category_name
    FROM products
    LEFT JOIN categories 
        ON products.category_id = categories.category_id
    WHERE 
        products.name LIKE ?
        OR products.barcode LIKE ?
        OR categories.name LIKE ?
    ORDER BY products.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

$search_value = "%" . $search . "%";

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $search_value,
    $search_value,
    $search_value
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Products - Super Store</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #333;
}


/* =========================
   HEADER
========================= */

.header {

    background: #1f2937;

    color: white;

    padding: 18px 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 10px;
}


.header h1 {
    font-size: 24px;
}


.user-info {
    font-size: 14px;
}


.user-info span {
    font-weight: bold;
}


/* =========================
   CONTAINER
========================= */

.container {

    width: 95%;

    max-width: 1400px;

    margin: 25px auto;
}


/* =========================
   TOP BAR
========================= */

.top-bar {

    background: white;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 20px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    flex-wrap: wrap;

    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}


.top-bar h2 {
    font-size: 21px;
}


/* =========================
   BUTTONS
========================= */

.btn {

    display: inline-block;

    text-decoration: none;

    border: none;

    padding: 9px 14px;

    border-radius: 6px;

    cursor: pointer;

    font-size: 14px;

    transition: 0.2s;

}


.btn-add {

    background: #198754;

    color: white;
}


.btn-add:hover {
    background: #157347;
}


.btn-dashboard {

    background: #6c757d;

    color: white;
}


.btn-dashboard:hover {
    background: #5c636a;
}


.btn-edit {

    background: #0d6efd;

    color: white;

    margin-right: 5px;
}


.btn-edit:hover {
    background: #0b5ed7;
}


.btn-delete {

    background: #dc3545;

    color: white;

    padding: 8px 12px;
}


.btn-delete:hover {
    background: #bb2d3b;
}


/* =========================
   SEARCH
========================= */

.search-box {

    background: white;

    padding: 18px;

    border-radius: 10px;

    margin-bottom: 20px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}


.search-form {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


.search-input {

    flex: 1;

    min-width: 250px;

    padding: 11px 13px;

    border: 1px solid #ddd;

    border-radius: 6px;

    font-size: 14px;

    outline: none;
}


.search-input:focus {

    border-color: #0d6efd;
}


.btn-search {

    background: #0d6efd;

    color: white;
}


.btn-search:hover {

    background: #0b5ed7;
}


.btn-clear {

    background: #6c757d;

    color: white;
}


/* =========================
   ALERTS
========================= */

.alert {

    padding: 13px 16px;

    border-radius: 7px;

    margin-bottom: 20px;

    font-size: 14px;
}


.alert-success {

    background: #d1e7dd;

    color: #0f5132;

    border: 1px solid #badbcc;
}


.alert-danger {

    background: #f8d7da;

    color: #842029;

    border: 1px solid #f5c2c7;
}


/* =========================
   TABLE
========================= */

.table-container {

    background: white;

    border-radius: 10px;

    overflow-x: auto;

    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1100px;
}


thead {

    background: #1f2937;

    color: white;
}


th {

    padding: 14px 12px;

    text-align: left;

    font-size: 13px;

    white-space: nowrap;
}


td {

    padding: 13px 12px;

    border-bottom: 1px solid #eee;

    font-size: 14px;

    vertical-align: middle;
}


tbody tr:hover {

    background: #f8f9fa;
}


/* =========================
   STOCK STATUS
========================= */

.stock-normal {

    color: #198754;

    font-weight: bold;
}


.stock-low {

    color: #fd7e14;

    font-weight: bold;
}


.stock-out {

    color: #dc3545;

    font-weight: bold;
}


/* =========================
   BADGES
========================= */

.badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}


.badge-normal {

    background: #d1e7dd;

    color: #0f5132;
}


.badge-low {

    background: #fff3cd;

    color: #664d03;
}


.badge-out {

    background: #f8d7da;

    color: #842029;
}


/* =========================
   EXPIRY
========================= */

.expired {

    color: #dc3545;

    font-weight: bold;
}


.expiring {

    color: #fd7e14;

    font-weight: bold;
}


.no-expiry {

    color: #777;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding: 40px;

    color: #777;

    font-size: 16px;
}


/* =========================
   ACTIONS
========================= */

.action-form {

    display: inline;
}


/* =========================
   RESPONSIVE
========================= */

@media(max-width: 768px) {

    .header {

        padding: 15px;

    }

    .header h1 {

        font-size: 20px;

    }

    .container {

        width: 97%;

        margin-top: 15px;

    }

    .top-bar {

        align-items: stretch;

    }

    .top-bar h2 {

        margin-bottom: 5px;

    }

}

</style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <h1>Super Store Management</h1>

    <div class="user-info">

        Welcome,

        <span>
            <?php echo htmlspecialchars($_SESSION["username"]); ?>
        </span>

        |

        <span>
            <?php echo htmlspecialchars($_SESSION["role"]); ?>
        </span>

    </div>

</div>



<div class="container">


<!-- =========================
     TOP BAR
========================= -->

<div class="top-bar">

    <h2>Products Management</h2>

    <div>

        <a href="add.php" class="btn btn-add">
            + Add Product
        </a>

        <a href="../dashboard.php" class="btn btn-dashboard">
            Dashboard
        </a>

    </div>

</div>



<!-- =========================
     ALERTS
========================= -->

<?php if (isset($_GET["success"])): ?>

    <div class="alert alert-success">

        <?php

        if ($_GET["success"] == "added") {
            echo "Product added successfully.";
        }

        elseif ($_GET["success"] == "updated") {
            echo "Product updated successfully.";
        }

        elseif ($_GET["success"] == "deleted") {
            echo "Product deleted successfully.";
        }

        ?>

    </div>

<?php endif; ?>



<?php if (isset($_GET["error"])): ?>

    <div class="alert alert-danger">

        <?php

        if ($_GET["error"] == "has_history") {

            echo "This product cannot be deleted because it has sales or purchase history.";

        }

        elseif ($_GET["error"] == "not_found") {

            echo "Product not found.";

        }

        elseif ($_GET["error"] == "delete_failed") {

            echo "Product could not be deleted.";

        }

        ?>

    </div>

<?php endif; ?>



<!-- =========================
     SEARCH
========================= -->

<div class="search-box">

    <form method="GET" class="search-form">

        <input

            type="text"

            name="search"

            class="search-input"

            placeholder="Search by product name, barcode or category..."

            value="<?php echo htmlspecialchars($search); ?>"

        >

        <button
            type="submit"
            class="btn btn-search"
        >
            Search
        </button>


        <?php if ($search != ""): ?>

            <a
                href="manage.php"
                class="btn btn-clear"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

</div>



<!-- =========================
     PRODUCTS TABLE
========================= -->

<div class="table-container">

<table>

<thead>

<tr>

    <th>#</th>

    <th>Barcode</th>

    <th>Product Name</th>

    <th>Category</th>

    <th>Purchase Price</th>

    <th>Selling Price</th>

    <th>Stock</th>

    <th>Unit</th>

    <th>Expiry Date</th>

    <th>Actions</th>

</tr>

</thead>


<tbody>


<?php

$count = 1;

if (mysqli_num_rows($result) > 0):


while ($product = mysqli_fetch_assoc($result)):

?>


<tr>


<!-- Number -->

<td>
    <?php echo $count++; ?>
</td>


<!-- Barcode -->

<td>

    <?php

    if (!empty($product["barcode"])) {

        echo htmlspecialchars($product["barcode"]);

    } else {

        echo "<span class='no-expiry'>N/A</span>";

    }

    ?>

</td>


<!-- Product Name -->

<td>

    <strong>

        <?php

        echo htmlspecialchars($product["name"]);

        ?>

    </strong>

</td>


<!-- Category -->

<td>

    <?php

    if (!empty($product["name"])) {

        echo htmlspecialchars($product["name"]);

    } else {

        echo "<span class='no-expiry'>No Category</span>";

    }

    ?>

</td>


<!-- Purchase Price -->

<td>

    <?php

    echo number_format(
        $product["purchase_price"],
        2
    );

    ?>

    AFN

</td>


<!-- Selling Price -->

<td>

    <?php

    echo number_format(
        $product["selling_price"],
        2
    );

    ?>

    AFN

</td>


<!-- Stock -->

<td>

<?php

$stock = (float)$product["stock"];

$min_stock = (float)$product["min_stock"];


if ($stock <= 0) {

    echo '<span class="badge badge-out">Out of Stock</span>';

}

elseif ($stock <= $min_stock) {

    echo '<span class="badge badge-low">Low Stock</span>';

}

else {

    echo '<span class="badge badge-normal">In Stock</span>';

}

?>

<br>

<strong>

    <?php

    echo number_format(
        $stock,
        2
    );

    ?>

</strong>

</td>


<!-- Unit -->

<td>

    <?php

    echo htmlspecialchars(
        $product["unit"]
    );

    ?>

</td>


<!-- Expiry -->

<td>

<?php

if (!empty($product["expiry_date"])) {

    $expiry_date = strtotime(
        $product["expiry_date"]
    );

    $today = strtotime(date("Y-m-d"));

    $days_left = floor(
        ($expiry_date - $today) / 86400
    );


    echo date(
        "Y-m-d",
        $expiry_date
    );


    if ($days_left < 0) {

        echo '<br><span class="expired">Expired</span>';

    }

    elseif ($days_left <= 30) {

        echo '<br><span class="expiring">'
            . $days_left .
            ' days left</span>';

    }

} else {

    echo '<span class="no-expiry">No Expiry</span>';

}

?>

</td>


<!-- ACTIONS -->

<td>

    <a
        href="edit.php?id=<?php echo $product["id"]; ?>"
        class="btn btn-edit"
    >
        Edit
    </a>


    <?php if ($_SESSION["role"] === "Admin"): ?>

        <form
            method="POST"
            action="delete.php"
            class="action-form"
            onsubmit="return confirm('Are you sure you want to delete this product?');"
        >

            <input
                type="hidden"
                name="id"
                value="<?php echo $product["id"]; ?>"
            >


            <button
                type="submit"
                class="btn btn-delete"
            >
                Delete
            </button>

        </form>

    <?php endif; ?>


</td>


</tr>


<?php

endwhile;

else:

?>


<tr>

<td
    colspan="10"
    class="empty"
>

    No products found.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>

</div>


</div>


</body>

</html>


<?php

mysqli_stmt_close($stmt);

?>