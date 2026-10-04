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
   FORM SUBMIT
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

    } elseif (!is_numeric($balance) || (float)$balance < 0) {

        $error = "Balance must be a valid positive number.";

    } else {

        $balance = (float)$balance;

        /* =========================
           INSERT CUSTOMER
        ========================= */

        $sql = "
            INSERT INTO customers
            (
                name,
                phone,
                address,
                balance
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $error = "Database error: " . mysqli_error($conn);

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "sssd",
                $name,
                $phone,
                $address,
                $balance
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                header("Location: manage.php?success=added");
                exit();

            } else {

                $error = "Customer could not be added. Please try again.";

                mysqli_stmt_close($stmt);
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Add Customer | Super Store</title>

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
     MAIN CONTAINER
========================= -->

<div class="container">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <h1>
            Add Customer
        </h1>

        <p>
            Add a new customer to your store.
        </p>

    </div>


    <!-- CARD -->

    <div class="card">


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="alert">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <!-- INFO -->

        <div class="info-box">

            <strong>Customer Information:</strong>

            Add the customer's name, phone number,
            address and opening balance.

            If the customer does not owe anything,
            keep the balance as <strong>0</strong>.

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
                                $_POST["name"] ?? ""
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
                                $_POST["phone"] ?? ""
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
                        $_POST["address"] ?? ""
                    );
                ?></textarea>

            </div>


            <!-- BALANCE -->

            <div class="form-group">

                <label for="balance">

                    Opening Balance (AFN)

                </label>

                <input
                    type="number"
                    id="balance"
                    name="balance"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    value="<?php
                        echo htmlspecialchars(
                            $_POST["balance"] ?? "0"
                        );
                    ?>"
                >

                <span class="help-text">

                    Enter the amount the customer currently owes.

                    If there is no outstanding balance, enter 0.

                </span>

            </div>


            <!-- BUTTONS -->

            <div class="form-buttons">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Customer
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