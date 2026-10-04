
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
   VARIABLES
========================= */

$error = "";
$success = "";


/* =========================
   GET CATEGORIES
========================= */

$categories = mysqli_query(
    $conn,
    "SELECT category_id, name FROM categories ORDER BY name ASC"
);


/* =========================
   ADD PRODUCT
========================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $barcode = trim($_POST["barcode"]);
    $name = trim($_POST["name"]);
    $category_id = intval($_POST["category_id"]);
    $purchase_price = floatval($_POST["purchase_price"]);
    $selling_price = floatval($_POST["selling_price"]);
    $stock = intval($_POST["stock"]);
    $min_stock = intval($_POST["min_stock"]);
    $unit = trim($_POST["unit"]);
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

    } else {

        /* =========================
           CHECK BARCODE
        ========================= */

        if ($barcode != "") {

            $check = mysqli_prepare(
                $conn,
                "SELECT id FROM products WHERE barcode = ? LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $check,
                "s",
                $barcode
            );

            mysqli_stmt_execute($check);

            $check_result = mysqli_stmt_get_result($check);

            if (mysqli_num_rows($check_result) > 0) {

                $error = "This barcode already exists.";

            }

            mysqli_stmt_close($check);
        }


        /* =========================
           INSERT PRODUCT
        ========================= */

        if ($error == "") {

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO products
                (
                    barcode,
                    name,
                    category_id,
                    purchase_price,
                    selling_price,
                    stock,
                    min_stock,
                    unit,
                    expiry_date
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "ssiddiiss",
                $barcode,
                $name,
                $category_id,
                $purchase_price,
                $selling_price,
                $stock,
                $min_stock,
                $unit,
                $expiry_date
            );


            if (mysqli_stmt_execute($stmt)) {

                header("Location: manage.php?success=added");
                exit();

            } else {

                $error = "Failed to add product.";

            }

            mysqli_stmt_close($stmt);
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Product - Super Store</title>

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


/* =========================
   CONTAINER
========================= */

.container {

    width: 95%;

    max-width: 900px;

    margin: 35px auto;

}


/* =========================
   HEADER
========================= */

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


/* =========================
   FORM
========================= */

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


.form-group.full {

    grid-column: 1 / -1;

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


/* =========================
   BUTTONS
========================= */

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


/* =========================
   ERROR
========================= */

.error {

    background: #fee2e2;

    color: #b91c1c;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 20px;

}


/* =========================
   INFO
========================= */

.info {

    background: #eff6ff;

    color: #1e40af;

    padding: 12px;

    border-radius: 7px;

    margin-bottom: 20px;

    font-size: 13px;

}


/* =========================
   RESPONSIVE
========================= */

@media(max-width: 650px) {

    .form-grid {

        grid-template-columns: 1fr;

    }

    .form-group.full {

        grid-column: auto;

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

            <h1>Add Product</h1>

            <p>Add a new product to your inventory</p>

        </div>


        <div>

            <a
                href="manage.php"
                class="btn btn-secondary">

                ← Products

            </a>

        </div>

    </div>


    <!-- FORM -->

    <div class="form-box">


        <?php if ($error != ""): ?>

            <div class="error">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <div class="info">

            Fields marked with normal values can be entered according to
            your store's products. Barcode is optional.

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
                        placeholder="Enter barcode"
                        value="<?php echo htmlspecialchars($_POST["barcode"] ?? ""); ?>"
                    >

                </div>


                <!-- PRODUCT NAME -->

                <div class="form-group">

                    <label>
                        Product Name *
                    </label>

                    <input
                        type="text"
                        name="name"
                        placeholder="Enter product name"
                        value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
                        required
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
                                    if (
                                        isset($_POST["category_id"]) &&
                                        $_POST["category_id"] == $category["category_id"]
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

                    <select name="unit">

                        <option value="Piece">
                            Piece
                        </option>

                        <option value="Box">
                            Box
                        </option>

                        <option value="Kg">
                            Kg
                        </option>

                        <option value="Liter">
                            Liter
                        </option>

                        <option value="Dozen">
                            Dozen
                        </option>

                        <option value="Pack">
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
                        placeholder="0.00"
                        value="<?php echo htmlspecialchars($_POST["purchase_price"] ?? ""); ?>"
                        required
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
                        placeholder="0.00"
                        value="<?php echo htmlspecialchars($_POST["selling_price"] ?? ""); ?>"
                        required
                    >

                </div>


                <!-- STOCK -->

                <div class="form-group">

                    <label>
                        Initial Stock *
                    </label>

                    <input
                        type="number"
                        name="stock"
                        min="0"
                        placeholder="0"
                        value="<?php echo htmlspecialchars($_POST["stock"] ?? "0"); ?>"
                        required
                    >

                </div>


                <!-- MINIMUM STOCK -->

                <div class="form-group">

                    <label>
                        Minimum Stock Alert
                    </label>

                    <input
                        type="number"
                        name="min_stock"
                        min="0"
                        value="<?php echo htmlspecialchars($_POST["min_stock"] ?? "5"); ?>"
                    >

                </div>


                <!-- EXPIRY DATE -->

                <div class="form-group">

                    <label>
                        Expiry Date
                    </label>

                    <input
                        type="date"
                        name="expiry_date"
                        value="<?php echo htmlspecialchars($_POST["expiry_date"] ?? ""); ?>"
                    >

                </div>


            </div>


            <!-- BUTTONS -->

            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-primary">

                    Save Product

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
