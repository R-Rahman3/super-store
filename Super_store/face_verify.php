```php
<?php

session_start();

include "db.php";

header("Content-Type: application/json");

/*
|--------------------------------------------------------------------------
| Only POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Get Face Descriptor
|--------------------------------------------------------------------------
*/

$descriptor = $_POST["descriptor"] ?? "";

if ($descriptor === "") {

    echo json_encode([
        "success" => false,
        "message" => "Face data was not received."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Validate JSON
|--------------------------------------------------------------------------
*/

$inputDescriptor = json_decode($descriptor, true);

if (
    !is_array($inputDescriptor) ||
    count($inputDescriptor) === 0
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid face data."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Get Active Users With Registered Faces
|--------------------------------------------------------------------------
*/

$sql = "SELECT id, name, username, role, status, face_descriptor
        FROM users
        WHERE status = 'Active'
        AND face_descriptor IS NOT NULL
        AND face_descriptor != ''";

$result = mysqli_query($conn, $sql);

if (!$result) {

    echo json_encode([
        "success" => false,
        "message" => "Database error."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Compare Face Descriptor
|--------------------------------------------------------------------------
|
| The actual face similarity calculation is done in JavaScript
| using face-api.js.
|
| PHP receives the detected user ID after comparison.
|
|--------------------------------------------------------------------------
*/

$user_id = intval($_POST["user_id"] ?? 0);

if ($user_id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "User was not identified."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Verify User
|--------------------------------------------------------------------------
*/

$sql = "SELECT id, name, username, role, status
        FROM users
        WHERE id = ?
        AND status = 'Active'
        AND face_descriptor IS NOT NULL
        AND face_descriptor != ''
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error."
    ]);

    exit();
}

mysqli_stmt_bind_param($stmt, "i", $user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) !== 1) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "success" => false,
        "message" => "Face not registered or user is inactive."
    ]);

    exit();
}

$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| Create Secure Login Session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION["user_id"]  = $user["id"];
$_SESSION["name"]     = $user["name"];
$_SESSION["username"] = $user["username"];
$_SESSION["role"]     = $user["role"];


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Face verified successfully.",
    "redirect" => "dashboard.php"
]);

exit();

?>
```
