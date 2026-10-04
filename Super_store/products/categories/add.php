<?php

session_start();

include "../../db.php";


/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}


/* =========================
   VARIABLES
========================= */

$name = "";
$description = "";

$error = "";


/* =========================
   FORM SUBMIT
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");


    /* =========================
       VALIDATION
    ========================= */

    if ($name === "") {

        $error = "Category name is required.";

    }

    elseif (strlen($name) < 2) {

        $error = "Category name must contain at least 2 characters.";

    }

    else {


        /* =========================
           CHECK DUPLICATE
        ========================= */

        $stmt = mysqli_prepare(
            $conn,
            "SELECT category_id FROM categories WHERE name = ? LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $name
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $exists = mysqli_num_rows($result) > 0;

        mysqli_stmt_close($stmt);


        if ($exists) {

            $error = "This category already exists.";

        }

        else {


            /* =========================
               INSERT CATEGORY
            ========================= */

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO categories (name, description)
                 VALUES (?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $name,
                $description
            );


            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                header("Location: manage.php?success=added");

                exit();

            }

            else {

                $error = "Category could not be added.";

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

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Category - Super Store</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family: Arial, sans-serif;

    background: #f4f6f9;

    color: #333;

}


/* =========================
   HEADER
========================= */

.header {

    background: #1f2937;

    color: white;

    padding: 18px 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 10px;

}


.header h1 {

    font-size: 24px;

}


.user-info {

    font-size: 14px;

}


.user-info span {

    font-weight: bold;

}


/* =========================
   CONTAINER
========================= */

.container {

    width: 95%;

    max-width: 750px;

    margin: 35px auto;

}


/* =========================
   CARD
========================= */

.card {

    background: white;

    border-radius: 12px;

    padding: 30px;

    box-shadow: 0 3px 12px rgba(0,0,0,0.08);

}


.card-header {

    border-bottom: 1px solid #eee;

    padding-bottom: 18px;

    margin-bottom: 25px;

}


.card-header h2 {

    font-size: 22px;

    margin-bottom: 6px;

}


.card-header p {

    color: #777;

    font-size: 14px;

}


/* =========================
   ALERT
========================= */

.alert {

    padding: 13px 15px;

    border-radius: 7px;

    margin-bottom: 20px;

    font-size: 14px;

}


.alert-danger {

    background: #f8d7da;

    color: #842029;

    border: 1px solid #f5c2c7;

}


/* =========================
   FORM
========================= */

.form-group {

    margin-bottom: 20px;

}


label {

    display: block;

    margin-bottom: 8px;

    font-weight: bold;

    font-size: 14px;

}


.required {

    color: #dc3545;

}


input,
textarea {

    width: 100%;

    padding: 12px 13px;

    border: 1px solid #d5d9df;

    border-radius: 7px;

    font-size: 14px;

    outline: none;

    font-family: Arial, sans-serif;

}


input:focus,
textarea:focus {

    border-color: #0d6efd;

    box-shadow: 0 0 0 3px rgba(13,110,253,0.08);

}


textarea {

    min-height: 130px;

    resize: vertical;

}


/* =========================
   BUTTONS
========================= */

.buttons {

    display: flex;

    gap: 10px;

    margin-top: 25px;

    flex-wrap: wrap;

}


.btn {

    display: inline-block;

    padding: 11px 18px;

    border: none;

    border-radius: 7px;

    text-decoration: none;

    cursor: pointer;

    font-size: 14px;

}


.btn-save {

    background: #198754;

    color: white;

}


.btn-save:hover {

    background: #157347;

}


.btn-cancel {

    background: #6c757d;

    color: white;

}


.btn-cancel:hover {

    background: #5c636a;

}


/* =========================
   RESPONSIVE
========================= */

@media(max-width: 600px) {

    .container {

        width: 95%;

        margin: 20px auto;

    }


    .card {

        padding: 20px;

    }


    .header {

        padding: 15px;

    }


    .header h1 {

        font-size: 20px;

    }

}

</style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<div class="header">

    <h1>Super Store Management</h1>


    <div class="user-info">

        Welcome,

        <span>

            <?php

            echo htmlspecialchars(
                $_SESSION["username"] ?? "User"
            );

            ?>

        </span>

        |

        <span>

            <?php

            echo htmlspecialchars(
                $_SESSION["role"] ?? ""
            );

            ?>

        </span>

    </div>

</div>



<!-- =========================
     CONTAINER
========================= -->

<div class="container">


<div class="card">


    <div class="card-header">

        <h2>Add New Category</h2>

        <p>
            Create a new product category for your store.
        </p>

    </div>



    <!-- ERROR -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>



    <!-- FORM -->

    <form method="POST">


        <!-- CATEGORY NAME -->

        <div class="form-group">

            <label for="name">

                Category Name

                <span class="required">*</span>

            </label>


            <input

                type="text"

                id="name"

                name="name"

                placeholder="Enter category name"

                value="<?php echo htmlspecialchars($name); ?>"

                maxlength="100"

                required

            >

        </div>



        <!-- DESCRIPTION -->

        <div class="form-group">

            <label for="description">

                Description

            </label>


            <textarea

                id="description"

                name="description"

                placeholder="Enter category description (optional)"

            ><?php echo htmlspecialchars($description); ?></textarea>

        </div>



        <!-- BUTTONS -->

        <div class="buttons">

            <button
                type="submit"
                class="btn btn-save"
            >
                Save Category
            </button>


            <a
                href="manage.php"
                class="btn btn-cancel"
            >
                Cancel
            </a>

        </div>


    </form>


</div>


</div>


</body>

</html>