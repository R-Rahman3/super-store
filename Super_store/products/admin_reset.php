<?php

include "../db.php";

$username = "admin";
$new_password = "password";

$hash = password_hash($new_password, PASSWORD_DEFAULT);

$sql = "UPDATE users 
        SET password = ?, status = 'Active'
        WHERE username = ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "ss", $hash, $username);

if (mysqli_stmt_execute($stmt)) {

    if (mysqli_stmt_affected_rows($stmt) > 0) {

        echo "<h2 style='color:green;'>Admin Password Reset Successfully!</h2>";
        echo "<p><b>Username:</b> admin</p>";
        echo "<p><b>Password:</b> password</p>";
        echo "<p><a href='login.php'>Go to Login</a></p>";

    } else {

        echo "<h2 style='color:red;'>Admin user was not found.</h2>";
        echo "<p>Please check that username is exactly: <b>admin</b></p>";
    }

} else {

    echo "<h2 style='color:red;'>Password Reset Failed!</h2>";
    echo "<p>" . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>