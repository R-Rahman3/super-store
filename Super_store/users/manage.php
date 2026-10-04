<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$role = $_SESSION["role"] ?? "";

if ($role !== "Admin") {
    die("Access Denied. Only Admin can manage users.");
}

require_once "../db.php";

$username = $_SESSION["username"] ?? "Admin";

/* =========================
   SEARCH
========================= */

$search = trim($_GET["search"] ?? "");

/* =========================
   SUCCESS / ERROR
========================= */

$success = $_GET["success"] ?? "";
$error   = $_GET["error"] ?? "";


/* =========================
   USERS QUERY
========================= */

$users = [];

if ($search !== "") {

    $search_like = "%" . $search . "%";

    $stmt = mysqli_prepare($conn, "
        SELECT
            id,
            name,
            username,
            role,
            status,
            created_at
        FROM users
        WHERE
            name LIKE ?
            OR username LIKE ?
            OR role LIKE ?
        ORDER BY id DESC
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $search_like,
        $search_like,
        $search_like
    );

} else {

    $stmt = mysqli_prepare($conn, "
        SELECT
            id,
            name,
            username,
            role,
            status,
            created_at
        FROM users
        ORDER BY id DESC
    ");
}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================
   USER STATISTICS
========================= */

$total_users = 0;
$active_users = 0;
$inactive_users = 0;
$admins = 0;
$managers = 0;
$cashiers = 0;

$result = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_users,
        SUM(status = 'Active') AS active_users,
        SUM(status = 'Inactive') AS inactive_users,
        SUM(role = 'Admin') AS admins,
        SUM(role = 'Manager') AS managers,
        SUM(role = 'Cashier') AS cashiers
    FROM users
");

if ($result && $row = mysqli_fetch_assoc($result)) {

    $total_users =
        (int)$row["total_users"];

    $active_users =
        (int)$row["active_users"];

    $inactive_users =
        (int)$row["inactive_users"];

    $admins =
        (int)$row["admins"];

    $managers =
        (int)$row["managers"];

    $cashiers =
        (int)$row["cashiers"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    User Management - Super Store
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f6f9;

    color: #1f2937;
}

.container {
    width: 95%;
    max-width: 1450px;
    margin: 30px auto;
}


/* =========================
   HEADER
========================= */

.header {

    background: white;

    padding: 25px;

    border-radius: 15px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.06);

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 20px;
}

.header h1 {

    margin: 0 0 8px;

    font-size: 28px;
}

.header p {

    margin: 0;

    color: #6b7280;
}

.header-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}


/* =========================
   BUTTONS
========================= */

.btn {

    border: none;

    text-decoration: none;

    padding: 11px 17px;

    border-radius: 8px;

    cursor: pointer;

    font-weight: bold;

    display: inline-block;

    font-size: 14px;
}

.btn-dashboard {

    background: #111827;

    color: white;
}

.btn-add {

    background: #16a34a;

    color: white;
}

.btn-edit {

    background: #2563eb;

    color: white;

    padding: 7px 12px;

    font-size: 12px;
}

.btn-delete {

    background: #dc2626;

    color: white;

    padding: 7px 12px;

    font-size: 12px;
}


/* =========================
   ALERTS
========================= */

.alert {

    padding: 14px 18px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-weight: bold;
}

.alert-success {

    background: #dcfce7;

    color: #166534;

    border:
        1px solid #86efac;
}

.alert-error {

    background: #fee2e2;

    color: #991b1b;

    border:
        1px solid #fca5a5;
}


/* =========================
   STATISTICS
========================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(190px, 1fr)
        );

    gap: 18px;

    margin-bottom: 25px;
}

.card {

    background: white;

    padding: 22px;

    border-radius: 15px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.05);
}

.card-title {

    color: #6b7280;

    font-size: 14px;

    margin-bottom: 10px;
}

.card-value {

    font-size: 27px;

    font-weight: bold;
}

.card-small {

    margin-top: 7px;

    color: #6b7280;

    font-size: 13px;
}

.card-blue {

    border-left:
        5px solid #2563eb;
}

.card-green {

    border-left:
        5px solid #16a34a;
}

.card-red {

    border-left:
        5px solid #dc2626;
}

.card-purple {

    border-left:
        5px solid #7c3aed;
}

.card-orange {

    border-left:
        5px solid #f59e0b;
}


/* =========================
   SEARCH BOX
========================= */

.search-box {

    background: white;

    padding: 20px;

    border-radius: 15px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.05);
}

.search-form {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.search-form input {

    flex: 1;

    min-width: 250px;

    padding: 12px;

    border:
        1px solid #d1d5db;

    border-radius: 8px;

    font-size: 14px;
}

.btn-search {

    background: #2563eb;

    color: white;
}

.btn-clear {

    background: #6b7280;

    color: white;
}


/* =========================
   TABLE SECTION
========================= */

.section {

    background: white;

    padding: 22px;

    border-radius: 15px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.05);

    margin-bottom: 25px;
}

.section-header {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 18px;
}

.section-header h2 {

    margin: 0;

    font-size: 20px;
}

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse:
        collapse;
}

table th {

    background: #f3f4f6;

    padding: 13px;

    text-align: left;

    font-size: 13px;

    white-space: nowrap;
}

table td {

    padding: 13px;

    border-bottom:
        1px solid #e5e7eb;

    font-size: 14px;
}

table tr:hover {

    background: #fafafa;
}


/* =========================
   BADGES
========================= */

.badge {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;
}

.active {

    background: #dcfce7;

    color: #166534;
}

.inactive {

    background: #fee2e2;

    color: #991b1b;
}

.admin {

    background: #ede9fe;

    color: #6d28d9;
}

.manager {

    background: #dbeafe;

    color: #1d4ed8;
}

.cashier {

    background: #fef3c7;

    color: #92400e;
}


/* =========================
   USER AVATAR
========================= */

.user-info {

    display: flex;

    align-items: center;

    gap: 12px;
}

.avatar {

    width: 40px;

    height: 40px;

    border-radius: 50%;

    background: #2563eb;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;

    font-size: 16px;
}

.user-name {

    font-weight: bold;
}

.user-username {

    font-size: 12px;

    color: #6b7280;
}


/* =========================
   EMPTY
========================= */

.empty {

    text-align: center;

    padding: 30px;

    color: #6b7280;
}


/* =========================
   FOOTER
========================= */

.footer {

    text-align: center;

    padding: 25px;

    color: #6b7280;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 700px) {

    .header {

        flex-direction: column;

        align-items:
            flex-start;
    }

    .search-form {

        flex-direction: column;
    }

    .search-form input {

        width: 100%;

        min-width: 0;
    }

    .header-actions {

        width: 100%;
    }

    .btn {

        text-align: center;
    }
}

</style>

</head>

<body>

<div class="container">


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <div>

        <h1>
            User Management
        </h1>

        <p>
            Manage system users,
            roles and account status.
        </p>

    </div>

    <div class="header-actions">

        <a
            href="../dashboard.php"
            class="btn btn-dashboard"
        >
            Dashboard
        </a>

        <a
            href="add.php"
            class="btn btn-add"
        >
            + Add User
        </a>

    </div>

</div>


<!-- =========================
     ALERTS
========================= -->

<?php if ($success === "added"): ?>

    <div class="alert alert-success">
        User added successfully.
    </div>

<?php elseif ($success === "updated"): ?>

    <div class="alert alert-success">
        User updated successfully.
    </div>

<?php elseif ($success === "deleted"): ?>

    <div class="alert alert-success">
        User deleted successfully.
    </div>

<?php elseif ($success === "status_changed"): ?>

    <div class="alert alert-success">
        User status changed successfully.
    </div>

<?php endif; ?>


<?php if ($error === "not_found"): ?>

    <div class="alert alert-error">
        User not found.
    </div>

<?php elseif ($error === "access_denied"): ?>

    <div class="alert alert-error">
        Access denied.
    </div>

<?php elseif ($error === "cannot_delete_self"): ?>

    <div class="alert alert-error">
        You cannot delete your own account.
    </div>

<?php elseif ($error === "has_history"): ?>

    <div class="alert alert-error">
        This user has related records and
        cannot be deleted.
    </div>

<?php elseif ($error === "delete_failed"): ?>

    <div class="alert alert-error">
        User could not be deleted.
    </div>

<?php endif; ?>


<!-- =========================
     STATISTICS
========================= -->

<div class="cards">


    <div class="card card-blue">

        <div class="card-title">
            Total Users
        </div>

        <div class="card-value">
            <?php
            echo $total_users;
            ?>
        </div>

        <div class="card-small">
            All registered users
        </div>

    </div>


    <div class="card card-green">

        <div class="card-title">
            Active Users
        </div>

        <div class="card-value">
            <?php
            echo $active_users;
            ?>
        </div>

        <div class="card-small">
            Currently active
        </div>

    </div>


    <div class="card card-red">

        <div class="card-title">
            Inactive Users
        </div>

        <div class="card-value">
            <?php
            echo $inactive_users;
            ?>
        </div>

        <div class="card-small">
            Disabled accounts
        </div>

    </div>


    <div class="card card-purple">

        <div class="card-title">
            Administrators
        </div>

        <div class="card-value">
            <?php
            echo $admins;
            ?>
        </div>

        <div class="card-small">
            Full system access
        </div>

    </div>


    <div class="card card-blue">

        <div class="card-title">
            Managers
        </div>

        <div class="card-value">
            <?php
            echo $managers;
            ?>
        </div>

        <div class="card-small">
            Management users
        </div>

    </div>


    <div class="card card-orange">

        <div class="card-title">
            Cashiers
        </div>

        <div class="card-value">
            <?php
            echo $cashiers;
            ?>
        </div>

        <div class="card-small">
            POS users
        </div>

    </div>

</div>


<!-- =========================
     SEARCH
========================= -->

<div class="search-box">

    <form
        method="GET"
        class="search-form"
    >

        <input
            type="text"
            name="search"
            value="<?php
                echo htmlspecialchars($search);
            ?>"
            placeholder="Search by name, username or role..."
        >

        <button
            type="submit"
            class="btn btn-search"
        >
            Search
        </button>

        <a
            href="manage.php"
            class="btn btn-clear"
        >
            Clear
        </a>

    </form>

</div>


<!-- =========================
     USERS TABLE
========================= -->

<div class="section">

    <div class="section-header">

        <h2>
            System Users
        </h2>

        <span>
            <?php
            echo count($users);
            ?>
            users
        </span>

    </div>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        User
                    </th>

                    <th>
                        Role
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Created
                    </th>

                    <th>
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($users)): ?>

                <?php
                $number = 1;
                ?>

                <?php foreach ($users as $user): ?>

                    <tr>

                        <td>
                            <?php
                            echo $number++;
                            ?>
                        </td>


                        <!-- USER -->

                        <td>

                            <div class="user-info">

                                <div class="avatar">

                                    <?php

                                    echo strtoupper(
                                        substr(
                                            $user["name"],
                                            0,
                                            1
                                        )
                                    );

                                    ?>

                                </div>

                                <div>

                                    <div class="user-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $user["name"]
                                        );
                                        ?>

                                    </div>

                                    <div class="user-username">

                                        @<?php
                                        echo htmlspecialchars(
                                            $user["username"]
                                        );
                                        ?>

                                    </div>

                                </div>

                            </div>

                        </td>


                        <!-- ROLE -->

                        <td>

                            <?php

                            if (
                                $user["role"]
                                === "Admin"
                            ) {

                                echo
                                '<span class="badge admin">
                                    Admin
                                </span>';

                            } elseif (
                                $user["role"]
                                === "Manager"
                            ) {

                                echo
                                '<span class="badge manager">
                                    Manager
                                </span>';

                            } else {

                                echo
                                '<span class="badge cashier">
                                    Cashier
                                </span>';
                            }

                            ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?php

                            if (
                                $user["status"]
                                === "Active"
                            ) {

                                echo
                                '<span class="badge active">
                                    Active
                                </span>';

                            } else {

                                echo
                                '<span class="badge inactive">
                                    Inactive
                                </span>';
                            }

                            ?>

                        </td>


                        <!-- CREATED -->

                        <td>

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $user["created_at"]
                                )
                            );

                            ?>

                        </td>


                        <!-- ACTIONS -->

                        <td>

                            <a
                                href="edit.php?id=<?php
                                    echo $user["id"];
                                ?>"
                                class="btn btn-edit"
                            >
                                Edit
                            </a>


                            <?php

                            if (
                                $user["id"]
                                != $_SESSION["user_id"]
                            ):

                            ?>

                                <form
                                    method="POST"
                                    action="delete.php"
                                    style="
                                        display:inline;
                                    "
                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to delete this user?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php
                                            echo $user["id"];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-delete"
                                    >
                                        Delete
                                    </button>

                                </form>

                            <?php else: ?>

                                <span
                                    style="
                                        color:#6b7280;
                                        font-size:12px;
                                    "
                                >
                                    Current User
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="empty"
                    >
                        No users found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================
     FOOTER
========================= -->

<div class="footer">

    Logged in as:

    <strong>
        <?php
        echo htmlspecialchars($username);
        ?>
    </strong>

    — Admin User Management

</div>


</div>

</body>

</html>