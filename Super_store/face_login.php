```php
<?php

session_start();

header("Content-Type: application/json");

include "db.php";

/*
|--------------------------------------------------------------------------
| Get JSON data from JavaScript
|--------------------------------------------------------------------------
*/

$input = json_decode(file_get_contents("php://input"), true);

$username  = trim($input["username"] ?? "");
$descriptor = $input["descriptor"] ?? [];


/*
|--------------------------------------------------------------------------
| Validate input
|--------------------------------------------------------------------------
*/

if ($username === "") {

    echo json_encode([
        "success" => false,
        "message" => "Username is required."
    ]);

    exit();
}


if (!is_array($descriptor) || count($descriptor) !== 128) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid face data."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Find User
|--------------------------------------------------------------------------
*/

$sql = "SELECT id, name, username, role, status, face_descriptor
        FROM users
        WHERE username = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    echo json_encode([
        "success" => false,
        "message" => "Database error."
    ]);

    exit();
}


mysqli_stmt_bind_param($stmt, "s", $username);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


if (mysqli_num_rows($result) !== 1) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);

    exit();
}


$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| Check Account Status
|--------------------------------------------------------------------------
*/

if ($user["status"] !== "Active") {

    echo json_encode([
        "success" => false,
        "message" => "Your account is inactive."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Check Registered Face
|--------------------------------------------------------------------------
*/

if (
    empty($user["face_descriptor"]) ||
    $user["face_descriptor"] === null
) {

    echo json_encode([
        "success" => false,
        "message" => "Face is not registered for this user."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Decode Stored Face Descriptor
|--------------------------------------------------------------------------
*/

$storedDescriptor = json_decode(
    $user["face_descriptor"],
    true
);


if (
    !is_array($storedDescriptor) ||
    count($storedDescriptor) !== 128
) {

    echo json_encode([
        "success" => false,
        "message" => "Stored face data is invalid."
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| Calculate Euclidean Distance
|--------------------------------------------------------------------------
*/

$distance = 0;

for ($i = 0; $i < 128; $i++) {

    $difference =
        floatval($descriptor[$i]) -
        floatval($storedDescriptor[$i]);

    $distance += $difference * $difference;
}

$distance = sqrt($distance);


/*
|--------------------------------------------------------------------------
| Face Match Threshold
|--------------------------------------------------------------------------
|
| Lower value = more strict
| Higher value = more flexible
|
| 0.50 = strict
| 0.60 = balanced
| 0.70 = more flexible
|
*/

$threshold = 0.60;


/*
|--------------------------------------------------------------------------
| Check Face Match
|--------------------------------------------------------------------------
*/

if ($distance <= $threshold) {

    /*
    |--------------------------------------------------------------------------
    | Regenerate Session ID
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);


    /*
    |--------------------------------------------------------------------------
    | Create Login Session
    |--------------------------------------------------------------------------
    */

    $_SESSION["user_id"]  = $user["id"];
    $_SESSION["name"]     = $user["name"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["role"]     = $user["role"];

    $_SESSION["face_login"] = true;


    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "success" => true,
        "message" => "Face verified successfully.",
        "distance" => round($distance, 4)
    ]);

    exit();

}


/*
|--------------------------------------------------------------------------
| Face Does Not Match
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => false,
    "message" => "Face does not match.",
    "distance" => round($distance, 4)
]);

exit();

?>
```
