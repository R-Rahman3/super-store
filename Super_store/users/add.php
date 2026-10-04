<?php

session_start();

/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "Admin") {
    die("Access Denied. Only Admin can add users.");
}

/* =========================
   DATABASE
========================= */

require_once "../db.php";

/* =========================
   USER INFO
========================= */

$username = $_SESSION["username"] ?? "Admin";

/* =========================
   FORM VARIABLES
========================= */

$name = "";
$new_username = "";
$role = "Cashier";
$status = "Active";
$error = "";

/* =========================
   FORM SUBMIT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $new_username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $role = $_POST["role"] ?? "Cashier";
    $status = $_POST["status"] ?? "Active";

    /* =========================
       VALIDATION
    ========================= */

    if ($name === "") {
        $error = "Please enter the user's full name.";
    }

    elseif (strlen($name) < 2) {
        $error = "Name must contain at least 2 characters.";
    }

    elseif ($new_username === "") {
        $error = "Please enter a username.";
    }

    elseif (!preg_match('/^[A-Za-z0-9_]+$/', $new_username)) {
        $error = "Username can contain only letters, numbers and underscore.";
    }

    elseif (strlen($new_username) < 3) {
        $error = "Username must contain at least 3 characters.";
    }

    elseif ($password === "") {
        $error = "Please enter a password.";
    }

    elseif (strlen($password) < 6) {
        $error = "Password must contain at least 6 characters.";
    }

    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }

    elseif (!in_array($role, ["Admin", "Manager", "Cashier"], true)) {
        $error = "Invalid user role.";
    }

    elseif (!in_array($status, ["Active", "Inactive"], true)) {
        $error = "Invalid user status.";
    }

    /* =========================
       CHECK USERNAME
    ========================= */

    if ($error === "") {

        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE username = ? LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check,
            "s",
            $new_username
        );

        mysqli_stmt_execute($check);

        $result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($result) > 0) {
            $error = "This username already exists. Please choose another username.";
        }

        mysqli_stmt_close($check);
    }

    /* =========================
       CREATE USER
    ========================= */

    if ($error === "") {

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users
            (name, username, password, role, status)
            VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $name,
            $new_username,
            $hashed_password,
            $role,
            $status
        );

        if (mysqli_stmt_execute($stmt)) {

            mysqli_stmt_close($stmt);

            header("Location: manage.php?success=added");
            exit();

        } else {

            $error = "Failed to create user. Please try again.";
        }

        mysqli_stmt_close($stmt);
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

    <title>Add User - Super Store</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;
            color: #1f2937;
        }

        .topbar {
            background: #111827;
            color: white;
            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow: 0 3px 12px rgba(0,0,0,.12);
        }

        .topbar h1 {
            font-size: 22px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-badge {
            background: #1f2937;
            border: 1px solid #374151;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
        }

        .dashboard-btn {
            color: white;
            text-decoration: none;
            background: #2563eb;
            padding: 9px 15px;
            border-radius: 7px;
            font-size: 14px;
        }

        .dashboard-btn:hover {
            background: #1d4ed8;
        }

        .container {
            max-width: 900px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h2 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #6b7280;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 30px;

            box-shadow:
                0 4px 20px rgba(0,0,0,.07);
        }

        .alert {
            padding: 14px 17px;
            border-radius: 8px;
            margin-bottom: 22px;

            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;

            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
            color: #374151;
        }

        input,
        select {
            width: 100%;
            padding: 12px 14px;

            border: 1px solid #d1d5db;
            border-radius: 8px;

            font-size: 15px;
            outline: none;

            background: white;
        }

        input:focus,
        select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37,99,235,.10);
        }

        .help-text {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
        }

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 75px;
        }

        .show-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);

            border: none;
            background: transparent;

            color: #2563eb;
            font-size: 13px;
            font-weight: 600;

            cursor: pointer;
        }

        .form-actions {
            margin-top: 28px;
            padding-top: 22px;

            border-top: 1px solid #e5e7eb;

            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn {
            border: none;
            border-radius: 8px;

            padding: 12px 20px;

            font-size: 14px;
            font-weight: 600;

            cursor: pointer;
            text-decoration: none;

            display: inline-block;
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .btn-save {
            background: #2563eb;
            color: white;
        }

        .btn-save:hover {
            background: #1d4ed8;
        }

        .info-box {
            margin-top: 25px;
            padding: 17px;

            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 9px;

            color: #1e40af;
            font-size: 13px;
            line-height: 1.6;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }

        @media (max-width: 700px) {

            .topbar {
                padding: 15px 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .topbar-right {
                width: 100%;
                justify-content: space-between;
            }

            .container {
                margin: 25px auto;
            }

            .card {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }

    </style>

</head>

<body>

    <!-- TOPBAR -->

    <div class="topbar">

        <h1>
            Super Store Management System
        </h1>

        <div class="topbar-right">

            <div class="user-badge">
                Admin: <?php echo htmlspecialchars($username); ?>
            </div>

            <a
                href="../dashboard.php"
                class="dashboard-btn"
            >
                Dashboard
            </a>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="container">

        <div class="page-header">

            <h2>
                Add New User
            </h2>

            <p>
                Create a new system user and assign their access role.
            </p>

        </div>


        <div class="card">

            <?php if ($error !== ""): ?>

                <div class="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                autocomplete="off"
            >

                <div class="form-grid">

                    <!-- NAME -->

                    <div class="form-group">

                        <label for="name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?php echo htmlspecialchars($name); ?>"
                            placeholder="Enter full name"
                            maxlength="100"
                            required
                        >

                    </div>


                    <!-- USERNAME -->

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?php echo htmlspecialchars($new_username); ?>"
                            placeholder="Enter username"
                            maxlength="50"
                            autocomplete="off"
                            required
                        >

                        <div class="help-text">
                            Use letters, numbers and underscore only.
                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="password-box">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword('password', this)"
                            >
                                Show
                            </button>

                        </div>

                        <div class="help-text">
                            Minimum 6 characters.
                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <div class="password-box">

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword('confirm_password', this)"
                            >
                                Show
                            </button>

                        </div>

                    </div>


                    <!-- ROLE -->

                    <div class="form-group">

                        <label for="role">
                            User Role
                        </label>

                        <select
                            id="role"
                            name="role"
                            required
                        >

                            <option
                                value="Admin"
                                <?php echo ($role === "Admin") ? "selected" : ""; ?>
                            >
                                Admin
                            </option>

                            <option
                                value="Manager"
                                <?php echo ($role === "Manager") ? "selected" : ""; ?>
                            >
                                Manager
                            </option>

                            <option
                                value="Cashier"
                                <?php echo ($role === "Cashier") ? "selected" : ""; ?>
                            >
                                Cashier
                            </option>

                        </select>

                        <div class="help-text">
                            Select the user's system access level.
                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label for="status">
                            Account Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            required
                        >

                            <option
                                value="Active"
                                <?php echo ($status === "Active") ? "selected" : ""; ?>
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?php echo ($status === "Inactive") ? "selected" : ""; ?>
                            >
                                Inactive
                            </option>

                        </select>

                        <div class="help-text">
                            Inactive users cannot log in.
                        </div>

                    </div>

                </div>


                <!-- INFO -->

                <div class="info-box">

                    <strong>
                        Security Information
                    </strong>

                    The password will be securely encrypted using
                    PHP's password hashing system before it is saved
                    in the database. Never store plain-text passwords.

                </div>


                <!-- ACTIONS -->

                <div class="form-actions">

                    <a
                        href="manage.php"
                        class="btn btn-cancel"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-save"
                    >
                        Create User
                    </button>

                </div>

            </form>

        </div>

    </div>


    <script>

        function togglePassword(id, button) {

            const input = document.getElementById(id);

            if (input.type === "password") {

                input.type = "text";
                button.textContent = "Hide";

            } else {

                input.type = "password";
                button.textContent = "Show";

            }

        }

    </script>

</body>

</html>