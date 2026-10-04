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

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "";


/* =========================
   SEARCH
========================= */

$search = trim($_GET["search"] ?? "");

$search_like = "%" . $search . "%";


/* =========================
   EXPENSES QUERY
========================= */

$sql = "
    SELECT
        expenses.id,
        expenses.title,
        expenses.category,
        expenses.amount,
        expenses.description,
        expenses.expense_date,

        users.name AS user_name,
        users.username AS username

    FROM expenses

    LEFT JOIN users
        ON expenses.user_id = users.id

    WHERE
        expenses.title LIKE ?
        OR expenses.category LIKE ?
        OR expenses.description LIKE ?
        OR users.name LIKE ?

    ORDER BY expenses.id DESC
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "ssss",
    $search_like,
    $search_like,
    $search_like,
    $search_like
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/* =========================
   TOTAL EXPENSE
========================= */

$total_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_expense
    FROM expenses
";

$total_result = mysqli_query($conn, $total_sql);

$total_row = mysqli_fetch_assoc($total_result);

$total_expense = (float) $total_row["total_expense"];


/* =========================
   TODAY EXPENSE
========================= */

$today_sql = "
    SELECT COALESCE(SUM(amount), 0) AS today_expense
    FROM expenses
    WHERE expense_date = CURDATE()
";

$today_result = mysqli_query($conn, $today_sql);

$today_row = mysqli_fetch_assoc($today_result);

$today_expense = (float) $today_row["today_expense"];


/* =========================
   SUCCESS / ERROR MESSAGES
========================= */

$success = $_GET["success"] ?? "";
$error = $_GET["error"] ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Expenses Management | Super Store</title>


<style>

/* =========================
   RESET
========================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* =========================
   BODY
========================= */

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
}


/* =========================
   TOPBAR
========================= */

.topbar {
    background: #1f2937;
    color: white;

    padding: 16px 30px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar h2 {
    font-size: 21px;
}

.user-info {
    font-size: 14px;
}


/* =========================
   CONTAINER
========================= */

.container {
    width: 100%;
    max-width: 1250px;

    margin: 30px auto;

    padding: 0 20px;
}


/* =========================
   PAGE HEADER
========================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}

.page-header h1 {

    font-size: 26px;

    color: #111827;

    margin-bottom: 5px;
}

.page-header p {

    color: #6b7280;

    font-size: 14px;
}


/* =========================
   BUTTON
========================= */

.btn {

    display: inline-block;

    padding: 11px 17px;

    border-radius: 8px;

    border: none;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;

    font-weight: 600;
}

.btn-primary {

    background: #2563eb;

    color: white;
}

.btn-primary:hover {

    background: #1d4ed8;
}

.btn-secondary {

    background: #e5e7eb;

    color: #374151;
}

.btn-secondary:hover {

    background: #d1d5db;
}


/* =========================
   STAT CARDS
========================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;

    margin-bottom: 25px;
}

.stat-card {

    background: white;

    padding: 22px;

    border-radius: 12px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);
}

.stat-title {

    color: #6b7280;

    font-size: 14px;

    margin-bottom: 8px;
}

.stat-value {

    font-size: 25px;

    font-weight: 700;

    color: #dc2626;
}


/* =========================
   SEARCH CARD
========================= */

.search-card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    margin-bottom: 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);
}

.search-form {

    display: flex;

    gap: 10px;
}

.search-form input {

    flex: 1;

    padding: 12px 14px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    outline: none;

    font-size: 14px;
}

.search-form input:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37,99,235,0.1);
}


/* =========================
   MESSAGE
========================= */

.alert {

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
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
   TABLE CARD
========================= */

.table-card {

    background: white;

    border-radius: 12px;

    overflow: hidden;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);
}

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}

thead {

    background: #f9fafb;
}

th {

    text-align: left;

    padding: 15px;

    font-size: 13px;

    color: #374151;

    border-bottom:
        1px solid #e5e7eb;
}

td {

    padding: 14px 15px;

    font-size: 14px;

    border-bottom:
        1px solid #f0f0f0;

    vertical-align: middle;
}

tbody tr:hover {

    background: #f9fafb;
}


/* =========================
   AMOUNT
========================= */

.amount {

    color: #dc2626;

    font-weight: 700;
}


/* =========================
   CATEGORY
========================= */

.category {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    background: #f3f4f6;

    color: #374151;

    font-size: 12px;

    font-weight: 600;
}


/* =========================
   ACTIONS
========================= */

.actions {

    display: flex;

    gap: 7px;

    align-items: center;
}

.btn-edit {

    background: #eff6ff;

    color: #1d4ed8;

    padding: 7px 11px;

    border-radius: 6px;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;
}

.btn-edit:hover {

    background: #dbeafe;
}

.btn-delete {

    background: #fee2e2;

    color: #b91c1c;

    padding: 7px 11px;

    border-radius: 6px;

    border: none;

    cursor: pointer;

    font-size: 12px;

    font-weight: 600;
}

.btn-delete:hover {

    background: #fecaca;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding: 50px;

    color: #6b7280;

    font-size: 15px;
}


/* =========================
   FOOTER ACTION
========================= */

.bottom-actions {

    margin-top: 20px;

    display: flex;

    gap: 10px;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 700px) {

    .topbar {

        padding: 15px;
    }

    .topbar h2 {

        font-size: 18px;
    }

    .user-info {

        font-size: 12px;
    }

    .container {

        padding: 0 12px;

        margin-top: 20px;
    }

    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .page-header .btn {

        width: 100%;

        text-align: center;
    }

    .stats {

        grid-template-columns: 1fr;
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
     TOPBAR
========================= -->

<div class="topbar">

    <h2>
        Super Store Management
    </h2>

    <div class="user-info">

        User:

        <strong>
            <?php
            echo htmlspecialchars($username);
            ?>
        </strong>

    </div>

</div>



<!-- =========================
     MAIN
========================= -->

<div class="container">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <h1>
                Expenses Management
            </h1>

            <p>
                Manage and track all store expenses.
            </p>

        </div>


        <a
            href="add.php"
            class="btn btn-primary"
        >
            + Add Expense
        </a>

    </div>



    <!-- =========================
         MESSAGES
    ========================= -->

    <?php if ($success === "added"): ?>

        <div class="alert alert-success">
            Expense added successfully.
        </div>

    <?php elseif ($success === "updated"): ?>

        <div class="alert alert-success">
            Expense updated successfully.
        </div>

    <?php elseif ($success === "deleted"): ?>

        <div class="alert alert-success">
            Expense deleted successfully.
        </div>

    <?php endif; ?>


    <?php if ($error === "not_found"): ?>

        <div class="alert alert-error">
            Expense was not found.
        </div>

    <?php elseif ($error === "access_denied"): ?>

        <div class="alert alert-error">
            Access denied. Only Admin can delete expenses.
        </div>

    <?php elseif ($error === "invalid_request"): ?>

        <div class="alert alert-error">
            Invalid request.
        </div>

    <?php elseif ($error === "delete_failed"): ?>

        <div class="alert alert-error">
            Failed to delete expense.
        </div>

    <?php endif; ?>



    <!-- =========================
         STATISTICS
    ========================= -->

    <div class="stats">

        <div class="stat-card">

            <div class="stat-title">
                Total Expenses
            </div>

            <div class="stat-value">

                <?php
                echo number_format(
                    $total_expense,
                    2
                );
                ?>

                AFN

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Today's Expenses
            </div>

            <div class="stat-value">

                <?php
                echo number_format(
                    $today_expense,
                    2
                );
                ?>

                AFN

            </div>

        </div>

    </div>



    <!-- =========================
         SEARCH
    ========================= -->

    <div class="search-card">

        <form
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search by title, category, description or user..."
                value="<?php
                echo htmlspecialchars($search);
                ?>"
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>

            <a
                href="manage.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        </form>

    </div>



    <!-- =========================
         TABLE
    ========================= -->

    <div class="table-card">

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Title</th>

                        <th>Category</th>

                        <th>Amount</th>

                        <th>Description</th>

                        <th>Date</th>

                        <th>Added By</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $counter = 1;

                if (mysqli_num_rows($result) > 0):

                    while ($row = mysqli_fetch_assoc($result)):

                ?>

                    <tr>

                        <td>
                            <?php
                            echo $counter++;
                            ?>
                        </td>


                        <td>

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $row["title"]
                                );
                                ?>
                            </strong>

                        </td>


                        <td>

                            <span class="category">

                                <?php
                                echo htmlspecialchars(
                                    $row["category"]
                                );
                                ?>

                            </span>

                        </td>


                        <td>

                            <span class="amount">

                                <?php
                                echo number_format(
                                    (float)$row["amount"],
                                    2
                                );
                                ?>

                                AFN

                            </span>

                        </td>


                        <td>

                            <?php

                            $description =
                                $row["description"];

                            if (
                                strlen($description) > 50
                            ) {

                                echo htmlspecialchars(
                                    substr(
                                        $description,
                                        0,
                                        50
                                    )
                                ) . "...";

                            } else {

                                echo htmlspecialchars(
                                    $description
                                );
                            }

                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $row["expense_date"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $row["user_name"]
                                ?? $row["username"]
                                ?? "Unknown"
                            );

                            ?>

                        </td>


                        <td>

                            <div class="actions">


                                <!-- EDIT -->

                                <a
                                    href="edit.php?id=<?php echo $row["id"]; ?>"
                                    class="btn-edit"
                                >
                                    Edit
                                </a>



                                <!-- DELETE -->

                                <?php if ($role === "Admin"): ?>

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        onsubmit="return confirm('Are you sure you want to delete this expense?');"
                                        style="display:inline;"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php
                                            echo $row["id"];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-delete"
                                        >
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

                        <td
                            colspan="8"
                            class="empty"
                        >
                            No expenses found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>



    <!-- =========================
         BOTTOM ACTIONS
    ========================= -->

    <div class="bottom-actions">

        <a
            href="../dashboard.php"
            class="btn btn-secondary"
        >
            ← Dashboard
        </a>

        <a
            href="add.php"
            class="btn btn-primary"
        >
            + Add Expense
        </a>

    </div>


</div>


</body>
</html>