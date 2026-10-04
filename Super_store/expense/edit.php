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

$error = "";


/* =========================
   GET EXPENSE ID
========================= */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: manage.php?error=invalid_id");
    exit();
}

$id = (int) $_GET["id"];

if ($id <= 0) {
    header("Location: manage.php?error=invalid_id");
    exit();
}


/* =========================
   LOAD EXPENSE
========================= */

$sql = "
    SELECT
        id,
        title,
        category,
        amount,
        description,
        expense_date,
        user_id
    FROM expenses
    WHERE id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$expense = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$expense) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   UPDATE EXPENSE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $amount = $_POST["amount"] ?? "";
    $description = trim($_POST["description"] ?? "");
    $expense_date = $_POST["expense_date"] ?? "";


    /* =========================
       VALIDATION
    ========================= */

    if ($title === "") {

        $error = "Expense title is required.";

    } elseif (strlen($title) < 2) {

        $error = "Expense title must contain at least 2 characters.";

    } elseif ($category === "") {

        $error = "Please select an expense category.";

    } elseif (!is_numeric($amount) || $amount <= 0) {

        $error = "Amount must be greater than 0.";

    } elseif ($expense_date === "") {

        $error = "Expense date is required.";

    } else {

        $amount = (float) $amount;


        /* =========================
           UPDATE
        ========================= */

        $update_sql = "
            UPDATE expenses
            SET
                title = ?,
                category = ?,
                amount = ?,
                description = ?,
                expense_date = ?
            WHERE id = ?
        ";

        $update_stmt = mysqli_prepare(
            $conn,
            $update_sql
        );

        if (!$update_stmt) {

            $error = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $update_stmt,
                "ssdssi",
                $title,
                $category,
                $amount,
                $description,
                $expense_date,
                $id
            );


            if (mysqli_stmt_execute($update_stmt)) {

                mysqli_stmt_close($update_stmt);
                mysqli_close($conn);

                header(
                    "Location: manage.php?success=updated"
                );

                exit();

            } else {

                $error = "Failed to update expense.";

                mysqli_stmt_close($update_stmt);
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Edit Expense | Super Store</title>


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
    max-width: 850px;

    margin: 40px auto;

    padding: 0 20px;
}


/* =========================
   CARD
========================= */

.card {
    background: white;

    border-radius: 12px;

    box-shadow:
        0 4px 18px rgba(0, 0, 0, 0.08);

    overflow: hidden;
}


/* =========================
   HEADER
========================= */

.card-header {
    padding: 22px 25px;

    border-bottom: 1px solid #e5e7eb;
}

.card-header h1 {
    font-size: 24px;

    color: #111827;

    margin-bottom: 6px;
}

.card-header p {
    color: #6b7280;

    font-size: 14px;
}


/* =========================
   FORM
========================= */

.form {
    padding: 28px 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;

    margin-bottom: 8px;

    font-weight: 600;

    color: #374151;
}

.form-group label span {
    color: #dc2626;
}


.form-group input,
.form-group select,
.form-group textarea {

    width: 100%;

    padding: 12px 14px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    outline: none;

    font-size: 15px;

    background: white;

    transition: 0.2s;
}


.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, 0.1);
}


.form-group textarea {

    min-height: 120px;

    resize: vertical;
}


/* =========================
   TWO COLUMNS
========================= */

.form-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;
}


/* =========================
   INFO BOX
========================= */

.info-box {

    background: #eff6ff;

    border: 1px solid #bfdbfe;

    color: #1e40af;

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 22px;

    font-size: 14px;

    line-height: 1.5;
}


/* =========================
   ERROR
========================= */

.error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;

    padding: 13px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
}


/* =========================
   ACTIONS
========================= */

.form-actions {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 25px;

    padding-top: 20px;

    border-top: 1px solid #e5e7eb;
}


/* =========================
   BUTTONS
========================= */

.btn {

    display: inline-block;

    padding: 11px 18px;

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
   RESPONSIVE
========================= */

@media (max-width: 650px) {

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
        margin: 20px auto;
        padding: 0 12px;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .form {
        padding: 20px 17px;
    }

    .card-header {
        padding: 20px 17px;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 10px;
        align-items: stretch;
    }

    .btn {
        width: 100%;
        text-align: center;
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

    <div class="card">


        <!-- HEADER -->

        <div class="card-header">

            <h1>
                Edit Expense
            </h1>

            <p>
                Update the selected expense information.
            </p>

        </div>



        <!-- FORM -->

        <div class="form">


            <?php if ($error !== ""): ?>

                <div class="error">

                    <?php
                    echo htmlspecialchars($error);
                    ?>

                </div>

            <?php endif; ?>


            <div class="info-box">

                <strong>Important:</strong>

                Changing the amount or date will also
                affect your financial reports.

            </div>



            <form method="POST" action="">


                <!-- TITLE + CATEGORY -->

                <div class="form-row">


                    <div class="form-group">

                        <label>
                            Expense Title
                            <span>*</span>
                        </label>

                        <input
                            type="text"
                            name="title"
                            placeholder="Example: Shop Rent"
                            value="<?php
                            echo htmlspecialchars(
                                $_POST["title"]
                                ?? $expense["title"]
                            );
                            ?>"
                            required
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Category
                            <span>*</span>
                        </label>

                        <select
                            name="category"
                            required
                        >

                            <option value="">
                                Select Category
                            </option>


                            <option
                                value="Rent"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Rent"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Rent
                            </option>


                            <option
                                value="Electricity"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Electricity"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Electricity
                            </option>


                            <option
                                value="Water"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Water"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Water
                            </option>


                            <option
                                value="Salary"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Salary"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Salary
                            </option>


                            <option
                                value="Transportation"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Transportation"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Transportation
                            </option>


                            <option
                                value="Maintenance"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Maintenance"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Maintenance
                            </option>


                            <option
                                value="Office"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Office"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Office
                            </option>


                            <option
                                value="Other"
                                <?php
                                echo (
                                    ($_POST["category"]
                                    ?? $expense["category"])
                                    === "Other"
                                )
                                ? "selected"
                                : "";
                                ?>
                            >
                                Other
                            </option>

                        </select>

                    </div>

                </div>



                <!-- AMOUNT + DATE -->

                <div class="form-row">


                    <div class="form-group">

                        <label>
                            Amount (AFN)
                            <span>*</span>
                        </label>

                        <input
                            type="number"
                            name="amount"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            value="<?php
                            echo htmlspecialchars(
                                $_POST["amount"]
                                ?? $expense["amount"]
                            );
                            ?>"
                            required
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Expense Date
                            <span>*</span>
                        </label>

                        <input
                            type="date"
                            name="expense_date"
                            value="<?php
                            echo htmlspecialchars(
                                $_POST["expense_date"]
                                ?? $expense["expense_date"]
                            );
                            ?>"
                            required
                        >

                    </div>

                </div>



                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter additional information..."
                    ><?php
                    echo htmlspecialchars(
                        $_POST["description"]
                        ?? $expense["description"]
                    );
                    ?></textarea>

                </div>



                <!-- ACTIONS -->

                <div class="form-actions">

                    <a
                        href="manage.php"
                        class="btn btn-secondary"
                    >
                        ← Back to Expenses
                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        ✓ Update Expense
                    </button>

                </div>


            </form>

        </div>

    </div>

</div>


</body>

</html>