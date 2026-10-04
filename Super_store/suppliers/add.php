<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $balance = $_POST["balance"] ?? 0;

    // Validation
    if ($name === "") {
        $error = "Supplier name is required.";
    } elseif (strlen($name) < 2) {
        $error = "Supplier name must contain at least 2 characters.";
    } elseif (!is_numeric($balance) || $balance < 0) {
        $error = "Opening balance must be a valid positive number.";
    } else {

        $balance = (float)$balance;

        // Check duplicate supplier
        $check_sql = "
            SELECT id
            FROM suppliers
            WHERE name = ?
            LIMIT 1
        ";

        $check_stmt = mysqli_prepare($conn, $check_sql);

        if (!$check_stmt) {
            $error = "Database error.";
        } else {

            mysqli_stmt_bind_param(
                $check_stmt,
                "s",
                $name
            );

            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) > 0) {

                $error = "A supplier with this name already exists.";

            } else {

                // Insert supplier
                $sql = "
                    INSERT INTO suppliers
                    (
                        name,
                        phone,
                        address,
                        balance
                    )
                    VALUES (?, ?, ?, ?)
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
                        mysqli_stmt_close($check_stmt);

                        header("Location: manage.php?success=added");
                        exit();

                    } else {

                        $error = "Failed to add supplier.";
                    }

                    mysqli_stmt_close($stmt);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Supplier | Super Store</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
}

/* Topbar */

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

/* Container */

.container {
    width: 100%;
    max-width: 850px;
    margin: 40px auto;
    padding: 0 20px;
}

/* Card */

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.08);
    overflow: hidden;
}

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

/* Form */

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
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.form-group textarea {
    min-height: 110px;
    resize: vertical;
}

/* Two columns */

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Balance info */

.balance-info {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #92400e;
    font-size: 14px;
}

/* Error */

.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

/* Buttons */

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

/* Responsive */

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

<!-- Topbar -->

<div class="topbar">

    <h2>Super Store Management</h2>

    <div class="user-info">
        User: <strong>
            <?php echo htmlspecialchars($username); ?>
        </strong>
    </div>

</div>


<div class="container">

    <div class="card">

        <div class="card-header">

            <h1>Add New Supplier</h1>

            <p>
                Enter supplier information and opening balance.
            </p>

        </div>


        <div class="form">

            <?php if ($error !== ""): ?>

                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>


            <div class="balance-info">
                <strong>Note:</strong>
                Opening Balance represents the amount your store already owes
                to this supplier.
            </div>


            <form method="POST" action="">

                <div class="form-row">

                    <div class="form-group">

                        <label>
                            Supplier Name <span>*</span>
                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="Enter supplier name"
                            value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
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
                            value="<?php echo htmlspecialchars($_POST["phone"] ?? ""); ?>"
                        >

                    </div>

                </div>


                <div class="form-group">

                    <label>
                        Address
                    </label>

                    <textarea
                        name="address"
                        placeholder="Enter supplier address"
                    ><?php echo htmlspecialchars($_POST["address"] ?? ""); ?></textarea>

                </div>


                <div class="form-group">

                    <label>
                        Opening Balance
                    </label>

                    <input
                        type="number"
                        name="balance"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        value="<?php echo htmlspecialchars($_POST["balance"] ?? "0"); ?>"
                    >

                </div>


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
                        + Add Supplier
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>