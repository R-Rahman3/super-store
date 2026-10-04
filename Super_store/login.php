<?php
session_start();

include "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        $sql = "SELECT id, name, username, password, role, status
                FROM users
                WHERE username = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) === 1) {

                $user = mysqli_fetch_assoc($result);

                if ($user["status"] !== "Active") {

                    $error = "Your account is inactive. Please contact administrator.";

                } elseif (password_verify($password, $user["password"])) {

                    session_regenerate_id(true);

                    $_SESSION["user_id"]  = $user["id"];
                    $_SESSION["name"]     = $user["name"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["role"]     = $user["role"];

                    header("Location: dashboard.php");
                    exit();

                } else {

                    $error = "Invalid username or password.";
                }

            } else {

                $error = "Invalid username or password.";
            }

            mysqli_stmt_close($stmt);

        } else {

            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login - Super Store</title>

<!-- Face API -->
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.login-container {
    width: 100%;
    max-width: 420px;
}

.login-box {
    background: white;
    padding: 40px;
    border-radius: 18px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.25);
}

.logo {
    width: 70px;
    height: 70px;
    margin: 0 auto 20px;
    background: #2563eb;
    color: white;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    font-weight: bold;
}

h1 {
    text-align: center;
    color: #0f172a;
    margin-bottom: 8px;
}

.subtitle {
    text-align: center;
    color: #64748b;
    margin-bottom: 30px;
    font-size: 14px;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #334155;
    font-weight: 600;
    font-size: 14px;
}

input {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    outline: none;
    font-size: 15px;
}

input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}

.login-btn {
    width: 100%;
    border: none;
    padding: 14px;
    background: #2563eb;
    color: white;
    border-radius: 9px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.login-btn:hover {
    background: #1d4ed8;
}

.face-btn {
    width: 100%;
    border: none;
    padding: 14px;
    background: #0f172a;
    color: white;
    border-radius: 9px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    margin-top: 12px;
}

.face-btn:hover {
    background: #1e293b;
}

.divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 22px 0;
    color: #94a3b8;
    font-size: 13px;
}

.divider::before,
.divider::after {
    content: "";
    height: 1px;
    background: #e2e8f0;
    flex: 1;
}

.face-area {
    display: none;
    margin-top: 20px;
    text-align: center;
}

#video {
    width: 100%;
    max-width: 300px;
    border-radius: 15px;
    background: #000;
}

.face-status {
    margin-top: 12px;
    font-size: 14px;
    color: #475569;
}

.close-camera {
    margin-top: 10px;
    border: none;
    background: #ef4444;
    color: white;
    padding: 10px 18px;
    border-radius: 8px;
    cursor: pointer;
}

.back-home {
    text-align: center;
    margin-top: 22px;
}

.back-home a {
    color: #2563eb;
    text-decoration: none;
    font-size: 14px;
}

.footer {
    text-align: center;
    color: #94a3b8;
    margin-top: 20px;
    font-size: 12px;
}

</style>

</head>

<body>

<div class="login-container">

<div class="login-box">

<div class="logo">
SS
</div>

<h1>Super Store</h1>

<p class="subtitle">
Management System Login
</p>

<?php if ($error != ""): ?>

<div class="error">
<?php echo htmlspecialchars($error); ?>
</div>

<?php endif; ?>


<!-- NORMAL LOGIN -->

<form method="POST" action="">

<div class="form-group">

<label for="username">
Username
</label>

<input
type="text"
id="username"
name="username"
placeholder="Enter your username"
value="<?php echo htmlspecialchars($_POST["username"] ?? ""); ?>"
autocomplete="username"
required
>

</div>


<div class="form-group">

<label for="password">
Password
</label>

<input
type="password"
id="password"
name="password"
placeholder="Enter your password"
autocomplete="current-password"
required
>

</div>


<button type="submit" class="login-btn">
Login
</button>

</form>


<div class="divider">
OR
</div>


<!-- FACE LOGIN BUTTON -->

<button type="button"
        class="face-btn"
        onclick="startFaceLogin()">

📷 Login with Face

</button>


<!-- CAMERA -->

<div class="face-area" id="faceArea">

<video id="video" autoplay muted playsinline></video>

<div class="face-status" id="faceStatus">
Starting camera...
</div>

<button type="button"
        class="close-camera"
        onclick="stopCamera()">

Close Camera

</button>

</div>


<div class="back-home">

<a href="index.php">
← Back to Home
</a>

</div>

</div>


<div class="footer">
© <?php echo date("Y"); ?> Super Store Management System
</div>

</div>


<script>

let stream = null;

async function startFaceLogin() {

    const username = document.getElementById("username").value.trim();

    if (username === "") {

        alert("Please enter your username first.");

        document.getElementById("username").focus();

        return;
    }

    const faceArea = document.getElementById("faceArea");
    const video = document.getElementById("video");
    const status = document.getElementById("faceStatus");

    faceArea.style.display = "block";

    status.innerText = "Loading face recognition...";

    try {

        await faceapi.nets.tinyFaceDetector.loadFromUri("models");
        await faceapi.nets.faceLandmark68Net.loadFromUri("models");
        await faceapi.nets.faceRecognitionNet.loadFromUri("models");

        status.innerText = "Opening camera...";

        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: 640,
                height: 480,
                facingMode: "user"
            },
            audio: false
        });

        video.srcObject = stream;

        status.innerText = "Look directly at the camera...";

        video.onloadedmetadata = () => {

            detectFace(username);

        };

    } catch (error) {

        console.error(error);

        status.innerText =
            "Camera or Face Recognition could not start.";

    }

}


async function detectFace(username) {

    const video = document.getElementById("video");

    const status = document.getElementById("faceStatus");

    const detection =
        await faceapi
        .detectSingleFace(
            video,
            new faceapi.TinyFaceDetectorOptions()
        )
        .withFaceLandmarks()
        .withFaceDescriptor();

    if (!detection) {

        status.innerText =
            "No face detected. Please look at the camera.";

        setTimeout(() => {

            detectFace(username);

        }, 1000);

        return;
    }


    status.innerText =
        "Face detected. Checking...";


    const descriptor =
        Array.from(detection.descriptor);


    fetch("face_login.php", {

        method: "POST",

        headers: {
            "Content-Type": "application/json"
        },

        body: JSON.stringify({

            username: username,

            descriptor: descriptor

        })

    })

    .then(response => response.json())

    .then(data => {

        if (data.success) {

            status.innerText =
                "Face verified. Logging in...";

            stopCamera();

            window.location.href =
                "dashboard.php";

        } else {

            status.innerText =
                data.message || "Face not recognized.";

            setTimeout(() => {

                detectFace(username);

            }, 2000);

        }

    })

    .catch(error => {

        console.error(error);

        status.innerText =
            "Server error. Please try again.";

    });

}


function stopCamera() {

    if (stream) {

        stream.getTracks().forEach(track => {
            track.stop();
        });

        stream = null;
    }

    document.getElementById("video").srcObject = null;

    document.getElementById("faceArea").style.display = "none";
}

</script>

</body>
</html>