```php
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


/* =========================
   CHECK PRODUCT ID
========================= */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: manage.php");
    exit();
}

$product_id = intval($_GET["id"]);


/* =========================
   GET PRODUCT
========================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM products
     WHERE id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$product = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$product) {
    header("Location: manage.php");
    exit();
}


/* =========================
   GET CATEGORIES
========================= */

$categories = mysqli_query(
    $conn,
    "SELECT category_id, name
     FROM categories
     ORDER BY name ASC"
);


/* =========================
   VARIABLES
========================= */

$error = "";


/* =========================
   UPDATE PRODUCT
========================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $barcode = trim($_POST["barcode"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $category_id = intval($_POST["category_id"] ?? 0);
    $purchase_price = floatval($_POST["purchase_price"] ?? 0);
    $selling_price = floatval($_POST["selling_price"] ?? 0);
    $stock = intval($_POST["stock"] ?? 0);
    $min_stock = intval($_POST["min_stock"] ?? 5);
    $unit = trim($_POST["unit"] ?? "Piece");
    $expiry_date = !empty($_POST["expiry_date"])
        ? $_POST["expiry_date"]
        : NULL;


    /* =========================
       VALIDATION
    ========================= */

    if ($name == "") {

        $error = "Product name is required.";

    } elseif ($purchase_price < 0) {

        $error = "Purchase price cannot be negative.";

    } elseif ($selling_price < 0) {

        $error = "Selling price cannot be negative.";

    } elseif ($stock < 0) {

        $error = "Stock cannot be negative.";

    } elseif ($min_stock < 0) {

        $error = "Minimum stock cannot be negative.";

    }


    /* =========================
       CHECK BARCODE
    ========================= */

    if ($error == "" && $barcode != "") {

        $check = mysqli_prepare(
            $conn,
            "SELECT id
             FROM products
             WHERE barcode = ?
             AND id != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check,
            "si",
            $barcode,
            $product_id
        );

        mysqli_stmt_execute($check);

        $check_result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_result) > 0) {

            $error = "This barcode already belongs to another product.";

        }

        mysqli_stmt_close($check);
    }


    /* =========================
       UPDATE
    ========================= */

    if ($error == "") {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE products SET
                barcode = ?,
                name = ?,
                category_id = ?,
                purchase_price = ?,
                selling_price = ?,
                stock = ?,
                min_stock = ?,
                unit = ?,
                expiry_date = ?
             WHERE id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "ssiddiissi",
            $barcode,
            $name,
            $category_id,
            $purchase_price,
            $selling_price,
            $stock,
            $min_stock,
            $unit,
            $expiry_date,
            $product_id
        );


        if (mysqli_stmt_execute($stmt)) {

            header("Location: manage.php?success=updated");
            exit();

        } else {

            $error = "Failed to update product.";

        }

        mysqli_stmt_close($stmt);
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Product - Super Store</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: Arial, sans-serif;

    background: #f1f5f9;

    color: #0f172a;
}


.container {

    width: 95%;

    max-width: 900px;

    margin: 35px auto;

}


.header {

    background: white;

    padding: 20px;

    border-radius: 10px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.05);

}


.header h1 {

    font-size: 24px;

}


.header p {

    color: #64748b;

    margin-top: 5px;

}


.form-box {

    background: white;

    padding: 30px;

    border-radius: 10px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.05);

}


.form-grid {

    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 20px;

}


.form-group {

    display: flex;

    flex-direction: column;

}


.form-group label {

    margin-bottom: 7px;

    font-weight: bold;

    color: #334155;

    font-size: 14px;

}


.form-group input,
.form-group select {

    width: 100%;

    padding: 12px;

    border: 1px solid #cbd5e1;

    border-radius: 7px;

    font-size: 14px;

}


.form-group input:focus,
.form-group select:focus {

    outline: none;

    border-color: #2563eb;

}


.buttons {

    margin-top: 25px;

    display: flex;

    gap: 10px;

}


.btn {

    display: inline-block;

    padding: 11px 18px;

    border-radius: 7px;

    text-decoration: none;

    border: none;

    cursor: pointer;

    font-size: 14px;

}


.btn-primary {

    background: #2563eb;

    color: white;

}


.btn-primary:hover {

    background: #1d4ed8;

}


.btn-secondary {

    background: #64748b;

    color: white;

}


.btn-secondary:hover {

    background: #475569;

}


.error {

    background: #fee2e2;

    color: #b91c1c;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 20px;

}


.info {

    background: #eff6ff;

    color: #1e40af;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 20px;

    font-size: 13px;

}


@media(max-width: 650px) {

    .form-grid {

        grid-template-columns: 1fr;

    }

    .header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

}

</style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>Edit Product</h1>

            <p>
                Update product information
            </p>

        </div>


        <a
            href="manage.php"
            class="btn btn-secondary">

            ← Products

        </a>

    </div>


    <div class="form-box">


        <?php if ($error != ""): ?>

            <div class="error">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <div class="info">

            You are editing:

            <strong>
                <?php echo htmlspecialchars($product["name"]); ?>
            </strong>

        </div>


        <form method="POST">


            <div class="form-grid">


                <!-- BARCODE -->

                <div class="form-group">

                    <label>
                        Barcode
                    </label>

                    <input
                        type="text"
                        name="barcode"
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["barcode"]
                                ?? $product["barcode"]
                                ?? ""
                            );
                        ?>"
                    >

                </div>


                <!-- NAME -->

                <div class="form-group">

                    <label>
                        Product Name *
                    </label>

                    <input
                        type="text"
                        name="name"
                        required
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["name"]
                                ?? $product["name"]
                            );
                        ?>"
                    >

                </div>


                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select name="category_id">

                        <option value="0">
                            -- Select Category --
                        </option>


                        <?php if ($categories): ?>

                            <?php while ($category = mysqli_fetch_assoc($categories)): ?>

                                <option
                                    value="<?php echo $category["category_id"]; ?>"
                                    <?php

                                    $selected_category =
                                        $_POST["category_id"]
                                        ?? $product["category_id"];

                                    if (
                                        $selected_category ==
                                        $category["category_id"]
                                    ) {
                                        echo "selected";
                                    }

                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $category["name"]
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>


                <!-- UNIT -->

                <div class="form-group">

                    <label>
                        Unit
                    </label>

                    <?php

                    $current_unit =
                        $_POST["unit"]
                        ?? $product["unit"];

                    ?>

                    <select name="unit">

                        <option
                            value="Piece"
                            <?php
                            echo $current_unit == "Piece"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Piece
                        </option>

                        <option
                            value="Box"
                            <?php
                            echo $current_unit == "Box"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Box
                        </option>

                        <option
                            value="Kg"
                            <?php
                            echo $current_unit == "Kg"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Kg
                        </option>

                        <option
                            value="Liter"
                            <?php
                            echo $current_unit == "Liter"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Liter
                        </option>

                        <option
                            value="Dozen"
                            <?php
                            echo $current_unit == "Dozen"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Dozen
                        </option>

                        <option
                            value="Pack"
                            <?php
                            echo $current_unit == "Pack"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Pack
                        </option>

                    </select>

                </div>


                <!-- PURCHASE PRICE -->

                <div class="form-group">

                    <label>
                        Purchase Price (AFN) *
                    </label>

                    <input
                        type="number"
                        name="purchase_price"
                        step="0.01"
                        min="0"
                        required
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["purchase_price"]
                                ?? $product["purchase_price"]
                            );
                        ?>"
                    >

                </div>


                <!-- SELLING PRICE -->

                <div class="form-group">

                    <label>
                        Selling Price (AFN) *
                    </label>

                    <input
                        type="number"
                        name="selling_price"
                        step="0.01"
                        min="0"
                        required
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["selling_price"]
                                ?? $product["selling_price"]
                            );
                        ?>"
                    >

                </div>


                <!-- STOCK -->

                <div class="form-group">

                    <label>
                        Stock *
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        required
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["stock"]
                                ?? $product["stock"]
                            );
                        ?>"
                    >

                </div>


                <!-- MIN STOCK -->

                <div class="form-group">

                    <label>
                        Minimum Stock Alert
                    </label>

                    <input
                        type="number"
                        name="min_stock"
                        min="0"
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["min_stock"]
                                ?? $product["min_stock"]
                            );
                        ?>"
                    >

                </div>


                <!-- EXPIRY -->

                <div class="form-group">

                    <label>
                        Expiry Date
                    </label>

                    <input
                        type="date"
                        name="expiry_date"
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["expiry_date"]
                                ?? $product["expiry_date"]
                                ?? ""
                            );
                        ?>"
                    >

                </div>


            </div>


            <!-- BUTTONS -->

            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary">

                    Update Product

                </button>


                <a
                    href="manage.php"
                    class="btn btn-secondary">

                    Cancel

                </a>

            </div>


        </form>


    </div>


</div>


</body>

</html>
```
