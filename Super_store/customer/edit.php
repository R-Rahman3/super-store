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

$error = "";

/* =========================
   GET CUSTOMER ID
========================= */

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: manage.php?error=not_found");
    exit();
}


/* =========================
   GET CUSTOMER
========================= */

$sql = "
    SELECT
        id,
        name,
        phone,
        address,
        balance
    FROM customers
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

if (mysqli_num_rows($result) !== 1) {

    mysqli_stmt_close($stmt);

    header("Location: manage.php?error=not_found");
    exit();
}

$customer = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================
   UPDATE CUSTOMER
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name    = trim($_POST["name"] ?? "");
    $phone   = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $balance = trim($_POST["balance"] ?? "0");


    /* =========================
       VALIDATION
    ========================= */

    if ($name === "") {

        $error = "Customer name is required.";

    } elseif (strlen($name) < 2) {

        $error = "Customer name must be at least 2 characters.";

    } elseif (!is_numeric($balance)) {

        $error = "Balance must be a valid number.";

    } elseif ((float)$balance < 0) {

        $error = "Balance cannot be negative.";

    } else {

        $balance = (float)$balance;


        /* =========================
           UPDATE DATABASE
        ========================= */

        $update_sql = "
            UPDATE customers
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

            $error = "Database error: " .
                     mysqli_error($conn);

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

                header(
                    "Location: manage.php?success=updated"
                );

                exit();

            } else {

                $error =
                    "Customer could not be updated. Please try again.";

                mysqli_stmt_close($update_stmt);
            }
        }
    }

    /*
       Keep submitted values in the form
       when there is an error.
    */

    $customer["name"]    = $name;
    $customer["phone"]   = $phone;
    $customer["address"] = $address;
    $customer["balance"] = $balance;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Customer | Super Store</title>

<style>

/* =========================
   RESET
========================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* =========================
   BODY
========================= */

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

    max-width: 900px;

    margin: 40px auto;

    padding: 0 20px;
}


/* =========================
   PAGE HEADER
========================= */

.page-header {

    margin-bottom: 25px;
}

.page-header h1 {

    font-size: 28px;

    margin-bottom: 7px;
}

.page-header p {

    color: #777;

    font-size: 14px;
}


/* =========================
   CARD
========================= */

.card {

    background: #ffffff;

    border-radius: 12px;

    padding: 30px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.06);
}


/* =========================
   ALERT
========================= */

.alert {

    background: #fee2e2;

    color: #991b1b;

    padding: 14px 16px;

    border-radius: 7px;

    margin-bottom: 20px;

    font-size: 14px;
}


/* =========================
   INFO BOX
========================= */

.info-box {

    background: #eff6ff;

    border-left: 4px solid #2563eb;

    padding: 14px 16px;

    margin-bottom: 25px;

    border-radius: 5px;

    color: #1e40af;

    font-size: 13px;

    line-height: 1.6;
}


/* =========================
   FORM
========================= */

.form-group {

    margin-bottom: 20px;
}

.form-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;
}

label {

    display: block;

    margin-bottom: 7px;

    font-size: 14px;

    font-weight: 600;

    color: #374151;
}

.required {

    color: #dc2626;
}

input,
textarea {

    width: 100%;

    padding: 12px 13px;

    border: 1px solid #d1d5db;

    border-radius: 7px;

    font-size: 14px;

    outline: none;

    transition: 0.2s;
}

input:focus,
textarea:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37,99,235,0.1);
}

textarea {

    min-height: 110px;

    resize: vertical;
}

.help-text {

    display: block;

    margin-top: 6px;

    color: #888;

    font-size: 12px;
}


/* =========================
   BUTTONS
========================= */

.form-buttons {

    display: flex;

    gap: 10px;

    margin-top: 25px;

    padding-top: 20px;

    border-top: 1px solid #eee;
}

.btn {

    border: none;

    padding: 12px 20px;

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

.btn-secondary {

    background: #64748b;

    color: white;
}

.btn-primary:hover,
.btn-secondary:hover {

    opacity: 0.9;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 700px) {

    .header {

        padding: 15px;

        flex-direction: column;

        align-items: flex-start;

        gap: 8px;
    }

    .container {

        margin: 25px auto;

        padding: 0 15px;
    }

    .card {

        padding: 20px;
    }

    .form-row {

        grid-template-columns: 1fr;

        gap: 0;
    }

    .form-buttons {

        flex-direction: column;
    }

    .form-buttons .btn {

        width: 100%;

        text-align: center;
    }

}

</style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <h2>
        Super Store Management System
    </h2>

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


    <!-- PAGE HEADER -->

    <div class="page-header">

        <h1>
            Edit Customer
        </h1>

        <p>
            Update customer information and balance.
        </p>

    </div>


    <!-- CARD -->

    <div class="card">


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="alert">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- INFO -->

        <div class="info-box">

            <strong>Update Customer:</strong>

            Change the customer's information below
            and click <strong>Update Customer</strong>
            to save the changes.

        </div>


        <!-- FORM -->

        <form method="POST">


            <!-- NAME + PHONE -->

            <div class="form-row">


                <!-- NAME -->

                <div class="form-group">

                    <label for="name">

                        Customer Name

                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter customer name"
                        value="<?php
                            echo htmlspecialchars(
                                $customer["name"]
                            );
                        ?>"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label for="phone">

                        Phone Number

                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        placeholder="Enter phone number"
                        value="<?php
                            echo htmlspecialchars(
                                $customer["phone"] ?? ""
                            );
                        ?>"
                    >

                </div>

            </div>


            <!-- ADDRESS -->

            <div class="form-group">

                <label for="address">

                    Address

                </label>

                <textarea
                    id="address"
                    name="address"
                    placeholder="Enter customer address"
                ><?php
                    echo htmlspecialchars(
                        $customer["address"] ?? ""
                    );
                ?></textarea>

            </div>


            <!-- BALANCE -->

            <div class="form-group">

                <label for="balance">

                    Balance (AFN)

                </label>

                <input
                    type="number"
                    id="balance"
                    name="balance"
                    min="0"
                    step="0.01"
                    value="<?php
                        echo htmlspecialchars(
                            $customer["balance"] ?? "0"
                        );
                    ?>"
                >

                <span class="help-text">

                    Enter the customer's current outstanding
                    balance.

                </span>

            </div>


            <!-- BUTTONS -->

            <div class="form-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Customer
                </button>

                <a
                    href="manage.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>


        </form>

    </div>

</div>

</body>

</html>