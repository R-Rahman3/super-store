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


/* =========================
   SEARCH
========================= */

$search = "";

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}


/* =========================
   GET CATEGORIES
========================= */

$sql = "
    SELECT
        categories.category_id,
        categories.name,
        categories.description,
        categories.created_at,
        COUNT(products.id) AS product_count
    FROM categories
    LEFT JOIN products
        ON products.category_id = categories.category_id
    WHERE
        categories.name LIKE ?
        OR categories.description LIKE ?
    GROUP BY
        categories.category_id,
        categories.name,
        categories.description,
        categories.created_at
    ORDER BY
        categories.category_id DESC
";

$stmt = mysqli_prepare($conn, $sql);

$search_value = "%" . $search . "%";

mysqli_stmt_bind_param(
    $stmt,
    "ss",
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

<title>Manage Categories - Super Store</title>


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

    max-width: 1200px;

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

    flex-wrap: wrap;

    gap: 15px;

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

    margin-left: 5px;
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

    min-width: 750px;
}


thead {

    background: #1f2937;

    color: white;
}


th {

    padding: 14px 12px;

    text-align: left;

    font-size: 13px;
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
   PRODUCT COUNT
========================= */

.product-count {

    display: inline-block;

    background: #e7f1ff;

    color: #0d6efd;

    padding: 5px 10px;

    border-radius: 20px;

    font-weight: bold;

    font-size: 12px;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding: 45px;

    color: #777;

    font-size: 16px;
}


/* =========================
   DESCRIPTION
========================= */

.description {

    max-width: 350px;

    line-height: 1.5;

    color: #666;
}


/* =========================
   ACTION FORM
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

    <h2>Categories Management</h2>

    <div>

        <a href="add.php" class="btn btn-add">
            + Add Category
        </a>

        <a href="../../dashboard.php" class="btn btn-dashboard">
            Dashboard
        </a>

    </div>

</div>



<!-- =========================
     SUCCESS MESSAGE
========================= -->

<?php if (isset($_GET["success"])): ?>

    <div class="alert alert-success">

        <?php

        if ($_GET["success"] == "added") {

            echo "Category added successfully.";

        }

        elseif ($_GET["success"] == "updated") {

            echo "Category updated successfully.";

        }

        elseif ($_GET["success"] == "deleted") {

            echo "Category deleted successfully.";

        }

        ?>

    </div>

<?php endif; ?>



<!-- =========================
     ERROR MESSAGE
========================= -->

<?php if (isset($_GET["error"])): ?>

    <div class="alert alert-danger">

        <?php

        if ($_GET["error"] == "has_products") {

            echo "This category cannot be deleted because it contains products.";

        }

        elseif ($_GET["error"] == "not_found") {

            echo "Category not found.";

        }

        elseif ($_GET["error"] == "delete_failed") {

            echo "Category could not be deleted.";

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
            placeholder="Search category name or description..."
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
     CATEGORY TABLE
========================= -->

<div class="table-container">

<table>

<thead>

<tr>

    <th>#</th>

    <th>Category Name</th>

    <th>Description</th>

    <th>Products</th>

    <th>Created Date</th>

    <th>Actions</th>

</tr>

</thead>


<tbody>


<?php

$count = 1;

if (mysqli_num_rows($result) > 0):

    while ($category = mysqli_fetch_assoc($result)):

?>


<tr>


<!-- Number -->

<td>

    <?php echo $count++; ?>

</td>


<!-- Category Name -->

<td>

    <strong>

        <?php

        echo htmlspecialchars(
            $category["name"]
        );

        ?>

    </strong>

</td>


<!-- Description -->

<td>

    <div class="description">

        <?php

        if (!empty($category["description"])) {

            echo nl2br(
                htmlspecialchars(
                    $category["description"]
                )
            );

        } else {

            echo "<span style='color:#999;'>No description</span>";

        }

        ?>

    </div>

</td>


<!-- Product Count -->

<td>

    <span class="product-count">

        <?php

        echo (int)$category["product_count"];

        ?>

        Products

    </span>

</td>


<!-- Created Date -->

<td>

    <?php

    echo date(
        "Y-m-d",
        strtotime($category["created_at"])
    );

    ?>

</td>


<!-- Actions -->

<td>

    <a
        href="edit.php?id=<?php echo $category["category_id"]; ?>"
        class="btn btn-edit"
    >
        Edit
    </a>


    <?php if ($_SESSION["role"] === "Admin"): ?>

        <form
            method="POST"
            action="delete.php"
            class="action-form"
            onsubmit="return confirm('Are you sure you want to delete this category?');"
        >

            <input
                type="hidden"
                name="id"
                value="<?php echo $category["category_id"]; ?>"
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
    colspan="6"
    class="empty"
>

    No categories found.

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