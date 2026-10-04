<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";

$error = "";
$success = "";

/* =========================
   GET SUPPLIER ID
========================= */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: manage.php?error=invalid_id");
    exit();
}

$id = (int) $_GET["id"];


/* =========================
   LOAD SUPPLIER
========================= */

$sql = "
    SELECT
        id,
        name,
        phone,
        address,
        balance
    FROM suppliers
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
$supplier = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$supplier) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   UPDATE SUPPLIER
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $balance = $_POST["balance"] ?? "0";

    /* Validation */

    if ($name === "") {

        $error = "Supplier name is required.";

    } elseif (strlen($name) < 2) {

        $error = "Supplier name must contain at least 2 characters.";

    } elseif (!is_numeric($balance) || $balance < 0) {

        $error = "Opening balance must be a valid number.";

    } else {

        $balance = (float) $balance;


        /* =========================
           CHECK DUPLICATE NAME
        ========================= */

        $check_sql = "
            SELECT id
            FROM suppliers
            WHERE name = ?
            AND id != ?
            LIMIT 1
        ";

        $check_stmt = mysqli_prepare($conn, $check_sql);

        if (!$check_stmt) {

            $error = "Database error.";

        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "si",
                $name,
                $id
            );

            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) > 0) {

                $error = "Another supplier with this name already exists.";

            } else {

                /* =========================
                   UPDATE SUPPLIER
                ========================= */

                $update_sql = "
                    UPDATE suppliers
                    SET
                        name = ?,
                        phone = ?,
                        address = ?,
                        balance = ?
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
                        "sssdi",
                        $name,
                        $phone,
                        $address,
                        $balance,
                        $id
                    );

                    if (mysqli_stmt_execute($update_stmt)) {

                        mysqli_stmt_close($update_stmt);
                        mysqli_stmt_close($check_stmt);

                        header(
                            "Location: manage.php?success=updated"
                        );
                        exit();

                    } else {

                        $error = "Failed to update supplier.";
                    }

                    mysqli_stmt_close($update_stmt);
                }
            }

            mysqli_stmt_close($check_stmt);
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

<title>Edit Supplier | Super Store</title>


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
   CARD HEADER
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
.form-group textarea {

    width: 100%;

    padding: 12px 14px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    outline: none;

    font-size: 15px;

    transition: 0.2s;
}


.form-group input:focus,
.form-group textarea:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, 0.1);
}


.form-group textarea {

    min-height: 110px;

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
   BALANCE INFO
========================= */

.balance-info {

    background: #fef3c7;

    border: 1px solid #fcd34d;

    padding: 12px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    color: #92400e;

    font-size: 14px;
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
   BUTTONS
========================= */

.form-actions {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 25px;

    padding-top: 20px;

    border-top: 1px solid #e5e7eb;
}


.btn {

    display: inline-block;

    padding: 11px 18px;

    border-radius: 8px;

    text-decoration: none;

    border: none;

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
     MAIN CONTAINER
========================= -->

<div class="container">

    <div class="card">


        <!-- HEADER -->

        <div class="card-header">

            <h1>
                Edit Supplier
            </h1>

            <p>
                Update supplier information and balance.
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



            <div class="balance-info">

                <strong>Note:</strong>

                Supplier balance represents the amount
                your store currently owes to this supplier.

            </div>



            <form
                method="POST"
                action=""
            >


                <!-- NAME + PHONE -->

                <div class="form-row">


                    <div class="form-group">

                        <label>
                            Supplier Name
                            <span>*</span>
                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="Enter supplier name"
                            value="<?php
                            echo htmlspecialchars(
                                $_POST["name"]
                                ?? $supplier["name"]
                            );
                            ?>"
                            required
                        >

                    </div>



                    <div class="form-group">

                        <label>
                            Phone Number
                        </label>

                        <input
                            type="text"
                            name="phone"
                            placeholder="Enter phone number"
                            value="<?php
                            echo htmlspecialchars(
                                $_POST["phone"]
                                ?? $supplier["phone"]
                            );
                            ?>"
                        >

                    </div>


                </div>



                <!-- ADDRESS -->

                <div class="form-group">

                    <label>
                        Address
                    </label>

                    <textarea
                        name="address"
                        placeholder="Enter supplier address"
                    ><?php
                    echo htmlspecialchars(
                        $_POST["address"]
                        ?? $supplier["address"]
                    );
                    ?></textarea>

                </div>



                <!-- BALANCE -->

                <div class="form-group">

                    <label>
                        Opening / Current Balance
                    </label>

                    <input
                        type="number"
                        name="balance"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        value="<?php
                        echo htmlspecialchars(
                            $_POST["balance"]
                            ?? $supplier["balance"]
                        );
                        ?>"
                    >

                </div>



                <!-- ACTIONS -->

                <div class="form-actions">


                    <a
                        href="manage.php"
                        class="btn btn-secondary"
                    >
                        ← Back to Suppliers
                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        ✓ Update Supplier
                    </button>


                </div>


            </form>

        </div>

    </div>

</div>


</body>

</html>