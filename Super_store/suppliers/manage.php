<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";
$role     = $_SESSION["role"] ?? "";

$search = trim($_GET["search"] ?? "");

$search_value = "%" . $search . "%";


/* =========================
   SUPPLIERS
========================= */

$sql = "
    SELECT
        id,
        name,
        phone,
        address,
        balance,
        created_at
    FROM suppliers
    WHERE
        name LIKE ?
        OR phone LIKE ?
        OR address LIKE ?
    ORDER BY id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $search_value,
    $search_value,
    $search_value
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/* =========================
   MESSAGES
========================= */

$success = $_GET["success"] ?? "";
$error   = $_GET["error"] ?? "";

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Suppliers - Super Store</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #1f2937;
}


/* =========================
   TOP BAR
========================= */

.topbar {
    background: #111827;
    color: white;
    padding: 17px 25px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 15px;
}

.topbar h2 {
    margin: 0;
    font-size: 21px;
}

.user-info {
    color: #d1d5db;
    font-size: 14px;
}


/* =========================
   CONTAINER
========================= */

.container {
    max-width: 1250px;
    margin: 25px auto;
    padding: 0 20px;
}


/* =========================
   PAGE HEADER
========================= */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 15px;

    margin-bottom: 20px;
}

.page-header h1 {
    margin: 0;
    font-size: 28px;
}

.buttons {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}


/* =========================
   BUTTONS
========================= */

.btn {
    display: inline-block;

    padding: 10px 15px;

    border: none;
    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;
    font-weight: bold;
}

.btn-blue {
    background: #2563eb;
    color: white;
}

.btn-green {
    background: #16a34a;
    color: white;
}

.btn-red {
    background: #dc2626;
    color: white;
}

.btn-gray {
    background: #6b7280;
    color: white;
}

.btn-dark {
    background: #111827;
    color: white;
}

.btn-small {
    padding: 7px 10px;
    font-size: 12px;
}


/* =========================
   CARD
========================= */

.card {
    background: white;

    border-radius: 12px;

    padding: 22px;

    box-shadow:
        0 3px 15px
        rgba(0,0,0,0.06);
}


/* =========================
   ALERT
========================= */

.alert {
    padding: 13px 16px;

    border-radius: 8px;

    margin-bottom: 18px;

    font-size: 14px;

    font-weight: bold;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}


/* =========================
   SEARCH
========================= */

.search-box {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-box input {
    flex: 1;

    padding: 11px 13px;

    border:
        1px solid #d1d5db;

    border-radius: 7px;

    font-size: 14px;

    outline: none;
}

.search-box input:focus {
    border-color: #2563eb;
}


/* =========================
   TABLE
========================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}

th,
td {
    padding: 13px 11px;

    border-bottom:
        1px solid #e5e7eb;

    text-align: left;

    font-size: 14px;
}

th {
    background: #f8fafc;

    font-weight: bold;

    color: #374151;
}

tbody tr:hover {
    background: #f9fafb;
}


/* =========================
   SUPPLIER NAME
========================= */

.supplier-name {
    font-weight: bold;
}

.phone {
    color: #6b7280;
    font-size: 12px;
    margin-top: 4px;
}


/* =========================
   BALANCE
========================= */

.balance {
    font-weight: bold;
}

.balance-due {
    color: #dc2626;
}

.balance-zero {
    color: #16a34a;
}


/* =========================
   ACTIONS
========================= */

.actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.actions form {
    margin: 0;
}


/* =========================
   EMPTY
========================= */

.empty {
    text-align: center;

    padding: 45px 20px;

    color: #6b7280;
}

.empty-icon {
    font-size: 40px;
    margin-bottom: 10px;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 700px) {

    .topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .search-box {
        flex-direction: column;
    }

    .search-box .btn {
        width: 100%;
        text-align: center;
    }

}

</style>

</head>


<body>


<!-- =========================
     TOP BAR
========================= -->

<div class="topbar">

    <h2>
        Super Store Management System
    </h2>

    <div class="user-info">

        <?php
        echo htmlspecialchars($username);
        ?>

        |

        <?php
        echo htmlspecialchars($role);
        ?>

    </div>

</div>


<div class="container">


<!-- =========================
     HEADER
========================= -->

<div class="page-header">

    <h1>
        Suppliers
    </h1>


    <div class="buttons">

        <a
            href="add.php"
            class="btn btn-blue"
        >
            + Add Supplier
        </a>

        <a
            href="../dashboard.php"
            class="btn btn-gray"
        >
            Dashboard
        </a>

    </div>

</div>


<!-- =========================
     ALERTS
========================= -->

<?php if ($success === "added"): ?>

    <div class="alert alert-success">
        Supplier added successfully.
    </div>

<?php elseif ($success === "updated"): ?>

    <div class="alert alert-success">
        Supplier updated successfully.
    </div>

<?php elseif ($success === "deleted"): ?>

    <div class="alert alert-success">
        Supplier deleted successfully.
    </div>

<?php endif; ?>


<?php if ($error === "not_found"): ?>

    <div class="alert alert-error">
        Supplier not found.
    </div>

<?php elseif ($error === "has_history"): ?>

    <div class="alert alert-error">
        This supplier cannot be deleted because purchase history exists.
    </div>

<?php elseif ($error === "has_balance"): ?>

    <div class="alert alert-error">
        This supplier cannot be deleted because an outstanding balance exists.
    </div>

<?php elseif ($error === "access_denied"): ?>

    <div class="alert alert-error">
        Access denied. Only Admin can perform this action.
    </div>

<?php elseif ($error === "delete_failed"): ?>

    <div class="alert alert-error">
        Supplier deletion failed.
    </div>

<?php endif; ?>


<!-- =========================
     MAIN CARD
========================= -->

<div class="card">


<!-- SEARCH -->

<form
    method="GET"
    class="search-box"
>

    <input
        type="text"
        name="search"
        value="<?php echo htmlspecialchars($search); ?>"
        placeholder="Search supplier by name, phone or address..."
    >


    <button
        type="submit"
        class="btn btn-dark"
    >
        Search
    </button>


    <?php if ($search !== ""): ?>

        <a
            href="manage.php"
            class="btn btn-gray"
        >
            Clear
        </a>

    <?php endif; ?>

</form>


<!-- TABLE -->

<div class="table-wrapper">

<table>

<thead>

<tr>

    <th>
        #
    </th>

    <th>
        Supplier
    </th>

    <th>
        Address
    </th>

    <th>
        Balance
    </th>

    <th>
        Created Date
    </th>

    <th>
        Actions
    </th>

</tr>

</thead>


<tbody>


<?php

$counter = 1;

if (mysqli_num_rows($result) > 0):

?>

<?php while ($row = mysqli_fetch_assoc($result)): ?>

<tr>


<!-- NUMBER -->

<td>

    <?php
    echo $counter++;
    ?>

</td>


<!-- SUPPLIER -->

<td>

    <div class="supplier-name">

        <?php

        echo htmlspecialchars(
            $row["name"]
        );

        ?>

    </div>


    <?php if (!empty($row["phone"])): ?>

        <div class="phone">

            <?php

            echo htmlspecialchars(
                $row["phone"]
            );

            ?>

        </div>

    <?php endif; ?>

</td>


<!-- ADDRESS -->

<td>

    <?php

    echo htmlspecialchars(
        $row["address"] ?: "-"
    );

    ?>

</td>


<!-- BALANCE -->

<td>

    <?php

    $balance =
        (float)$row["balance"];

    ?>


    <span
        class="
            balance
            <?php

            if ($balance > 0) {
                echo "balance-due";
            } else {
                echo "balance-zero";
            }

            ?>
        "
    >

        AFN

        <?php

        echo number_format(
            $balance,
            2
        );

        ?>

    </span>

</td>


<!-- CREATED -->

<td>

    <?php

    echo htmlspecialchars(
        $row["created_at"]
    );

    ?>

</td>


<!-- ACTIONS -->

<td>

<div class="actions">


<!-- EDIT -->

<a
    href="edit.php?id=<?php echo $row["id"]; ?>"
    class="btn btn-blue btn-small"
>
    Edit
</a>


<!-- DELETE -->

<?php if ($role === "Admin"): ?>

<form
    method="POST"
    action="delete.php"
    onsubmit="return confirm(
        'Are you sure you want to delete this supplier?'
    );"
>

    <input
        type="hidden"
        name="id"
        value="<?php echo $row["id"]; ?>"
    >


    <button
        type="submit"
        class="btn btn-red btn-small"
    >
        Delete
    </button>

</form>

<?php endif; ?>


</div>

</td>


</tr>

<?php endwhile; ?>


<?php else: ?>

<tr>

<td
    colspan="6"
    class="empty"
>

    <div class="empty-icon">
        🚚
    </div>

    <strong>
        No suppliers found
    </strong>

    <br>

    <span>
        Add your first supplier to start managing purchases.
    </span>

</td>

</tr>

<?php endif; ?>


</tbody>

</table>

</div>


</div>


</div>


</body>

</html>


<?php

mysqli_stmt_close($stmt);

mysqli_close($conn);

?>