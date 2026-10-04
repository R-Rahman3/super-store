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
    die("Access Denied. Only Admin can edit users.");
}

/* =========================
   DATABASE
========================= */

require_once "../db.php";

/* =========================
   ADMIN INFO
========================= */

$admin_username = $_SESSION["username"] ?? "Admin";

/* =========================
   GET USER ID
========================= */

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: manage.php?error=not_found");
    exit();
}

/* =========================
   LOAD USER
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        id,
        name,
        username,
        role,
        status
     FROM users
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    header("Location: manage.php?error=not_found");
    exit();
}

/* =========================
   FORM VARIABLES
========================= */

$name = $user["name"];
$username = $user["username"];
$role = $user["role"];
$status = $user["status"];

$error = "";

/* =========================
   FORM SUBMIT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $username = trim($_POST["username"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    $role = $_POST["role"] ?? "";
    $status = $_POST["status"] ?? "";

    /* =========================
       VALIDATION
    ========================= */

    if ($name === "") {

        $error = "Please enter the user's full name.";

    } elseif (strlen($name) < 2) {

        $error = "Name must contain at least 2 characters.";

    } elseif ($username === "") {

        $error = "Please enter a username.";

    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {

        $error = "Username can contain only letters, numbers and underscore.";

    } elseif (strlen($username) < 3) {

        $error = "Username must contain at least 3 characters.";

    } elseif (!in_array($role, ["Admin", "Manager", "Cashier"], true)) {

        $error = "Invalid user role.";

    } elseif (!in_array($status, ["Active", "Inactive"], true)) {

        $error = "Invalid user status.";

    }

    /* =========================
       PASSWORD VALIDATION
       Only if entered
    ========================= */

    if ($error === "" && $password !== "") {

        if (strlen($password) < 6) {

            $error = "New password must contain at least 6 characters.";

        } elseif ($password !== $confirm_password) {

            $error = "Passwords do not match.";

        }
    }

    /* =========================
       CHECK USERNAME
    ========================= */

    if ($error === "") {

        $check = mysqli_prepare(
            $conn,
            "SELECT id
             FROM users
             WHERE username = ?
             AND id != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check,
            "si",
            $username,
            $id
        );

        mysqli_stmt_execute($check);

        $check_result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_result) > 0) {
            $error = "This username already exists. Please choose another username.";
        }

        mysqli_stmt_close($check);
    }

    /* =========================
       UPDATE USER
    ========================= */

    if ($error === "") {

        /*
         * If password is empty:
         * keep old password.
         */

        if ($password === "") {

            $update = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET
                    name = ?,
                    username = ?,
                    role = ?,
                    status = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update,
                "ssssi",
                $name,
                $username,
                $role,
                $status,
                $id
            );

        } else {

            /*
             * New password entered:
             * hash it securely.
             */

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $update = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET
                    name = ?,
                    username = ?,
                    password = ?,
                    role = ?,
                    status = ?
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update,
                "sssssi",
                $name,
                $username,
                $hashed_password,
                $role,
                $status,
                $id
            );
        }

        if (mysqli_stmt_execute($update)) {

            mysqli_stmt_close($update);

            /*
             * If the admin edited his own account,
             * update the current session username/name.
             */

            if ((int)$_SESSION["user_id"] === (int)$id) {

                $_SESSION["name"] = $name;
                $_SESSION["username"] = $username;
                $_SESSION["role"] = $role;

            }

            header("Location: manage.php?success=updated");
            exit();

        } else {

            $error = "Failed to update user. Please try again.";

            mysqli_stmt_close($update);
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

    <title>Edit User - Super Store</title>

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

        /* =========================
           TOPBAR
        ========================= */

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

        /* =========================
           CONTAINER
        ========================= */

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

        /* =========================
           CARD
        ========================= */

        .card {
            background: white;

            border-radius: 14px;

            padding: 30px;

            box-shadow:
                0 4px 20px rgba(0,0,0,.07);
        }

        /* =========================
           ALERT
        ========================= */

        .alert {
            padding: 14px 17px;

            border-radius: 8px;

            margin-bottom: 22px;

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;

            font-size: 14px;
        }

        /* =========================
           FORM
        ========================= */

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

        /* =========================
           PASSWORD
        ========================= */

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

        /* =========================
           CURRENT USER
        ========================= */

        .current-user {
            margin-bottom: 22px;

            padding: 14px 17px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1e40af;

            border-radius: 8px;

            font-size: 14px;
        }

        /* =========================
           INFO BOX
        ========================= */

        .info-box {
            margin-top: 25px;

            padding: 17px;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 9px;

            color: #475569;

            font-size: 13px;

            line-height: 1.6;
        }

        .info-box strong {
            display: block;

            margin-bottom: 5px;

            color: #334155;
        }

        /* =========================
           ACTIONS
        ========================= */

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

        /* =========================
           RESPONSIVE
        ========================= */

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


    <!-- =========================
         TOPBAR
    ========================= -->

    <div class="topbar">

        <h1>
            Super Store Management System
        </h1>

        <div class="topbar-right">

            <div class="user-badge">

                Admin:
                <?php echo htmlspecialchars($admin_username); ?>

            </div>

            <a
                href="../dashboard.php"
                class="dashboard-btn"
            >
                Dashboard
            </a>

        </div>

    </div>


    <!-- =========================
         CONTENT
    ========================= -->

    <div class="container">

        <div class="page-header">

            <h2>
                Edit User
            </h2>

            <p>
                Update the user's account information and access permissions.
            </p>

        </div>


        <div class="card">


            <?php if ($error !== ""): ?>

                <div class="alert">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>


            <?php if ((int)$_SESSION["user_id"] === (int)$id): ?>

                <div class="current-user">

                    <strong>Current Account:</strong>

                    You are editing your own account.
                    Changes to your name, username and role will
                    immediately update your current session.

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
                            value="<?php echo htmlspecialchars($username); ?>"
                            placeholder="Enter username"
                            maxlength="50"
                            autocomplete="off"
                            required
                        >

                        <div class="help-text">
                            Letters, numbers and underscore only.
                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">
                            New Password
                        </label>

                        <div class="password-box">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Leave empty to keep current password"
                                autocomplete="new-password"
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
                            Leave empty if you do not want to change the password.
                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <div class="password-box">

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm new password"
                                autocomplete="new-password"
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
                        Password Security
                    </strong>

                    If you enter a new password, it will be securely
                    hashed using PHP's password hashing system.
                    If you leave the password fields empty, the existing
                    password will remain unchanged.

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
                        Update User
                    </button>

                </div>

            </form>

        </div>

    </div>


    <!-- =========================
         JAVASCRIPT
    ========================= -->

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