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

$username = $_SESSION["username"] ?? "User";
$role     = $_SESSION["role"] ?? "";

/* =========================
   SEARCH
========================= */

$search = trim($_GET["search"] ?? "");

$search_like = "%" . $search . "%";

/* =========================
   GET CUSTOMERS
========================= */

$sql = "
    SELECT
        id,
        name,
        phone,
        address,
        balance,
        created_at
    FROM customers
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
    $search_like,
    $search_like,
    $search_like
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customers | Super Store</title>

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
    background: #ffffff;
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ddd;
}

.header h2 {
    font-size: 22px;
}

.user-info {
    color: #666;
    font-size: 14px;
}

.user-info strong {
    color: #222;
}

/* =========================
   CONTAINER
========================= */

.container {
    padding: 30px;
}

/* =========================
   TOP BAR
========================= */

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 15px;
    flex-wrap: wrap;
}

.page-title h1 {
    font-size: 27px;
    margin-bottom: 5px;
}

.page-title p {
    color: #777;
    font-size: 14px;
}

/* =========================
   BUTTONS
========================= */

.buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    border: none;
    padding: 11px 17px;
    border-radius: 7px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    display: inline-block;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-success {
    background: #16a34a;
    color: white;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}

/* =========================
   SEARCH
========================= */

.search-box {
    background: white;
    padding: 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    flex: 1;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-size: 14px;
    outline: none;
}

.search-form input:focus {
    border-color: #2563eb;
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
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

/* =========================
   TABLE
========================= */

.table-container {
    background: white;
    border-radius: 10px;
    overflow-x: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

thead {
    background: #f8fafc;
}

th,
td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

th {
    color: #475569;
    font-weight: 600;
}

tbody tr:hover {
    background: #f8fafc;
}

/* =========================
   BALANCE
========================= */

.balance-due {
    color: #dc2626;
    font-weight: bold;
}

.balance-clear {
    color: #16a34a;
    font-weight: bold;
}

/* =========================
   ACTIONS
========================= */

.actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.actions .btn {
    padding: 7px 11px;
    font-size: 12px;
}

.delete-form {
    display: inline;
}

/* =========================
   EMPTY
========================= */

.empty {
    text-align: center;
    padding: 50px 20px;
    color: #777;
}

.empty h3 {
    margin-bottom: 8px;
    color: #444;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 768px) {

    .header {
        padding: 15px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .container {
        padding: 15px;
    }

    .top-bar {
        align-items: flex-start;
        flex-direction: column;
    }

    .search-form {
        flex-direction: column;
    }

    .search-form .btn {
        width: 100%;
    }

}

</style>

</head>

<body>

<!-- =========================
     HEADER
========================= -->

<div class="header">

    <h2>Super Store Management System</h2>

    <div class="user-info">

        Welcome,
        <strong>
            <?php echo htmlspecialchars($username); ?>
        </strong>

        |
        <?php echo htmlspecialchars($role); ?>

    </div>

</div>


<!-- =========================
     MAIN
========================= -->

<div class="container">

    <div class="top-bar">

        <div class="page-title">

            <h1>Customers</h1>

            <p>
                Manage store customers and their balances.
            </p>

        </div>

        <div class="buttons">

            <a href="../dashboard.php"
               class="btn btn-secondary">
                Dashboard
            </a>

            <a href="add.php"
               class="btn btn-primary">
                + Add Customer
            </a>

        </div>

    </div>


    <!-- =========================
         SUCCESS MESSAGES
    ========================= -->

    <?php if (isset($_GET["success"])): ?>

        <?php if ($_GET["success"] === "added"): ?>

            <div class="alert alert-success">
                Customer added successfully.
            </div>

        <?php elseif ($_GET["success"] === "updated"): ?>

            <div class="alert alert-success">
                Customer updated successfully.
            </div>

        <?php elseif ($_GET["success"] === "deleted"): ?>

            <div class="alert alert-success">
                Customer deleted successfully.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <!-- =========================
         ERROR MESSAGES
    ========================= -->

    <?php if (isset($_GET["error"])): ?>

        <div class="alert alert-error">

            <?php

            $error = $_GET["error"];

            if ($error === "not_found") {

                echo "Customer not found.";

            } elseif ($error === "has_history") {

                echo "This customer cannot be deleted because sales history exists.";

            } elseif ($error === "delete_failed") {

                echo "Customer could not be deleted.";

            } elseif ($error === "access_denied") {

                echo "Access denied. Only Admin can delete customers.";

            } else {

                echo htmlspecialchars($error);

            }

            ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         SEARCH
    ========================= -->

    <div class="search-box">

        <form method="GET"
              class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search customer by name, phone or address..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit"
                    class="btn btn-primary">
                Search
            </button>

            <a href="manage.php"
               class="btn btn-secondary">
                Clear
            </a>

        </form>

    </div>


    <!-- =========================
         CUSTOMER TABLE
    ========================= -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>Customer Name</th>

                    <th>Phone</th>

                    <th>Address</th>

                    <th>Balance</th>

                    <th>Created Date</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php

            $counter = 1;

            if (mysqli_num_rows($result) > 0):

                while ($row = mysqli_fetch_assoc($result)):

                    $balance = (float)$row["balance"];

            ?>

                <tr>

                    <td>
                        <?php echo $counter++; ?>
                    </td>

                    <td>
                        <strong>
                            <?php
                            echo htmlspecialchars($row["name"]);
                            ?>
                        </strong>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $row["phone"] ?? "-"
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            $row["address"] ?? "-"
                        );
                        ?>
                    </td>

                    <td>

                        <?php if ($balance > 0): ?>

                            <span class="balance-due">

                                <?php
                                echo number_format(
                                    $balance,
                                    2
                                );
                                ?>

                                AFN

                            </span>

                        <?php else: ?>

                            <span class="balance-clear">
                                0.00 AFN
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php

                        echo date(
                            "Y-m-d",
                            strtotime($row["created_at"])
                        );

                        ?>

                    </td>

                    <td>

                        <div class="actions">

                            <a
                                href="edit.php?id=<?php echo (int)$row["id"]; ?>"
                                class="btn btn-warning">
                                Edit
                            </a>


                            <?php if ($role === "Admin"): ?>

                                <form
                                    method="POST"
                                    action="delete.php"
                                    class="delete-form"
                                    onsubmit="return confirm('Are you sure you want to delete this customer?');"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int)$row["id"]; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-danger">
                                        Delete
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </td>

                </tr>

            <?php

                endwhile;

            else:

            ?>

                <tr>

                    <td colspan="7">

                        <div class="empty">

                            <h3>No Customers Found</h3>

                            <p>
                                <?php
                                if ($search !== "") {
                                    echo "No customer matches your search.";
                                } else {
                                    echo "You have not added any customers yet.";
                                }
                                ?>
                            </p>

                        </div>

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