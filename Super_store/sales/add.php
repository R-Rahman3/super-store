<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";
$role     = $_SESSION["role"] ?? "";


/* =========================
   CUSTOMERS
========================= */

$customers = [];

$customer_sql = "
    SELECT id, name, phone, balance
    FROM customers
    ORDER BY name ASC
";

$customer_result = mysqli_query($conn, $customer_sql);

if ($customer_result) {
    while ($customer = mysqli_fetch_assoc($customer_result)) {
        $customers[] = $customer;
    }
}


/* =========================
   PRODUCTS
========================= */

$products = [];

$product_sql = "
    SELECT
        id,
        barcode,
        name,
        selling_price,
        purchase_price,
        stock,
        unit
    FROM products
    WHERE stock > 0
    ORDER BY name ASC
";

$product_result = mysqli_query($conn, $product_sql);

if ($product_result) {
    while ($product = mysqli_fetch_assoc($product_result)) {
        $products[] = $product;
    }
}


/* =========================
   INVOICE NUMBER
========================= */

$invoice_no =
    "INV-" .
    date("YmdHis") .
    "-" .
    rand(10, 99);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>New Sale - Super Store</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .topbar {
            background: #111827;
            color: white;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .topbar h2 {
            margin: 0;
            font-size: 22px;
        }

        .user-info {
            font-size: 14px;
            color: #d1d5db;
        }

        .container {
            max-width: 1400px;
            margin: 25px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 7px;
            padding: 11px 16px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
        }

        .btn-dark {
            background: #111827;
            color: white;
        }

        .btn-blue {
            background: #2563eb;
            color: white;
        }

        .btn-green {
            background: #16a34a;
            color: white;
        }

        .btn-red {
            background: #dc2626;
            color: white;
        }

        .btn-gray {
            background: #6b7280;
            color: white;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
        }

        .card-title {
            margin: 0 0 18px;
            font-size: 19px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
            background: white;
        }

        input:focus,
        select:focus {
            border-color: #2563eb;
        }

        .product-search {
            display: grid;
            grid-template-columns: 1fr 160px;
            gap: 10px;
            margin-bottom: 18px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th,
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            font-weight: bold;
        }

        .qty-input {
            width: 90px;
        }

        .price-input {
            width: 120px;
        }

        .remove-btn {
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 7px 10px;
            cursor: pointer;
        }

        .summary {
            max-width: 450px;
            margin-left: auto;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            border-bottom: none;
        }

        .summary input {
            width: 180px;
            text-align: right;
        }

        .customer-balance {
            margin-top: 8px;
            font-size: 13px;
            color: #6b7280;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #6b7280;
        }

        @media (max-width: 1000px) {

            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 650px) {

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .product-search {
                grid-template-columns: 1fr;
            }

            .summary {
                max-width: 100%;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>


<!-- TOP BAR -->

<div class="topbar">

    <h2>Super Store Management System</h2>

    <div class="user-info">
        <?php echo htmlspecialchars($username); ?>
        |
        <?php echo htmlspecialchars($role); ?>
    </div>

</div>


<div class="container">


    <!-- HEADER -->

    <div class="page-header">

        <div>
            <h1>New Sale</h1>
        </div>

        <div class="buttons">

            <a
                href="manage.php"
                class="btn btn-dark"
            >
                Sales History
            </a>

            <a
                href="../dashboard.php"
                class="btn btn-gray"
            >
                Dashboard
            </a>

        </div>

    </div>


    <!-- SALE INFORMATION -->

    <div class="card">

        <h3 class="card-title">
            Sale Information
        </h3>

        <div class="form-grid">

            <div class="form-group">

                <label>
                    Invoice Number
                </label>

                <input
                    type="text"
                    id="invoice_no"
                    value="<?php echo htmlspecialchars($invoice_no); ?>"
                    readonly
                >

            </div>


            <div class="form-group">

                <label>
                    Customer
                </label>

                <select id="customer_id">

                    <option value="">
                        Walk-in Customer
                    </option>

                    <?php foreach ($customers as $customer): ?>

                        <option
                            value="<?php echo $customer["id"]; ?>"
                            data-balance="<?php echo $customer["balance"]; ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $customer["name"]
                            );
                            ?>

                            <?php if (!empty($customer["phone"])): ?>

                                -
                                <?php
                                echo htmlspecialchars(
                                    $customer["phone"]
                                );
                                ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <div
                    class="customer-balance"
                    id="customerBalance"
                >
                    Existing Balance: AFN 0.00
                </div>

            </div>


            <div class="form-group">

                <label>
                    Payment Method
                </label>

                <select id="payment_method">

                    <option value="Cash">
                        Cash
                    </option>

                    <option value="Card">
                        Card
                    </option>

                    <option value="Credit">
                        Credit
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Sale Date
                </label>

                <input
                    type="text"
                    value="<?php echo date("Y-m-d H:i:s"); ?>"
                    readonly
                >

            </div>

        </div>

    </div>


    <!-- PRODUCTS -->

    <div class="card">

        <h3 class="card-title">
            Products
        </h3>


        <div class="product-search">

            <input
                type="text"
                id="productSearch"
                placeholder="Search product by name or barcode..."
                autocomplete="off"
            >

            <button
                type="button"
                class="btn btn-blue"
                onclick="addFirstProduct()"
            >
                Add Product
            </button>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Product</th>

                        <th>Barcode</th>

                        <th>Stock</th>

                        <th>Quantity</th>

                        <th>Price</th>

                        <th>Total</th>

                        <th>Action</th>

                    </tr>

                </thead>

                <tbody id="saleItems">

                    <tr id="emptyRow">

                        <td
                            colspan="8"
                            class="empty"
                        >
                            No products added yet.
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>


        <!-- SUMMARY -->

        <div class="summary">

            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <strong>
                    AFN
                    <span id="subtotal">
                        0.00
                    </span>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Discount
                </span>

                <input
                    type="number"
                    id="discount"
                    value="0"
                    min="0"
                    step="0.01"
                    oninput="calculateTotal()"
                >

            </div>


            <div class="summary-row total">

                <span>
                    Grand Total
                </span>

                <strong>
                    AFN
                    <span id="grandTotal">
                        0.00
                    </span>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Paid Amount
                </span>

                <input
                    type="number"
                    id="paid_amount"
                    value="0"
                    min="0"
                    step="0.01"
                    oninput="calculateTotal()"
                >

            </div>


            <div class="summary-row">

                <span>
                    Due
                </span>

                <strong>
                    AFN
                    <span id="dueAmount">
                        0.00
                    </span>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Change
                </span>

                <strong>
                    AFN
                    <span id="changeAmount">
                        0.00
                    </span>
                </strong>

            </div>

        </div>


        <!-- ACTIONS -->

        <div class="actions">

            <a
                href="manage.php"
                class="btn btn-gray"
            >
                Cancel
            </a>

            <button
                type="button"
                class="btn btn-green"
                onclick="completeSale()"
            >
                Complete Sale
            </button>

        </div>

    </div>

</div>


<script>

/* =========================
   PRODUCTS FROM DATABASE
========================= */

const products =
<?php echo json_encode(
    $products,
    JSON_UNESCAPED_UNICODE
); ?>;


/* =========================
   CART
========================= */

let cart = [];


/* =========================
   SEARCH PRODUCT
========================= */

const searchInput =
    document.getElementById(
        "productSearch"
    );

searchInput.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Enter") {

            event.preventDefault();

            addFirstProduct();
        }

    }
);


/* =========================
   ADD FIRST PRODUCT
========================= */

function addFirstProduct() {

    const search =
        searchInput.value
        .trim()
        .toLowerCase();

    if (search === "") {

        alert(
            "Please enter product name or barcode."
        );

        searchInput.focus();

        return;
    }


    const product =
        products.find(function(item) {

            return (
                item.name
                    .toLowerCase()
                    .includes(search)
                ||
                item.barcode
                    .toLowerCase()
                    === search
            );

        });


    if (!product) {

        alert("Product not found.");

        return;
    }


    addProduct(product);

    searchInput.value = "";

    searchInput.focus();

}


/* =========================
   ADD PRODUCT
========================= */

function addProduct(product) {

    const existing =
        cart.find(function(item) {

            return item.id == product.id;

        });


    if (existing) {

        if (
            existing.quantity + 1
            >
            Number(product.stock)
        ) {

            alert(
                "Not enough stock available."
            );

            return;
        }

        existing.quantity++;

    } else {

        cart.push({

            id: product.id,

            name: product.name,

            barcode: product.barcode,

            stock: Number(product.stock),

            quantity: 1,

            price:
                Number(product.selling_price)

        });

    }


    renderCart();

}


/* =========================
   RENDER CART
========================= */

function renderCart() {

    const tbody =
        document.getElementById(
            "saleItems"
        );


    tbody.innerHTML = "";


    if (cart.length === 0) {

        tbody.innerHTML = `
            <tr>
                <td
                    colspan="8"
                    class="empty"
                >
                    No products added yet.
                </td>
            </tr>
        `;

        calculateTotal();

        return;
    }


    cart.forEach(function(item, index) {

        const total =
            item.quantity *
            item.price;


        const row =
            document.createElement("tr");


        row.innerHTML = `

            <td>
                ${index + 1}
            </td>

            <td>
                ${escapeHtml(item.name)}
            </td>

            <td>
                ${escapeHtml(item.barcode)}
            </td>

            <td>
                ${item.stock}
            </td>

            <td>

                <input
                    type="number"
                    class="qty-input"
                    min="1"
                    max="${item.stock}"
                    value="${item.quantity}"
                    onchange="changeQuantity(${item.id}, this.value)"
                >

            </td>

            <td>

                <input
                    type="number"
                    class="price-input"
                    value="${item.price.toFixed(2)}"
                    readonly
                >

            </td>

            <td>
                AFN ${total.toFixed(2)}
            </td>

            <td>

                <button
                    type="button"
                    class="remove-btn"
                    onclick="removeProduct(${item.id})"
                >
                    Remove
                </button>

            </td>

        `;


        tbody.appendChild(row);

    });


    calculateTotal();

}


/* =========================
   CHANGE QUANTITY
========================= */

function changeQuantity(
    productId,
    value
) {

    const quantity =
        Number(value);


    const item =
        cart.find(function(item) {

            return item.id == productId;

        });


    if (!item) {
        return;
    }


    if (
        quantity < 1
        ||
        quantity > item.stock
    ) {

        alert(
            "Invalid quantity."
        );

        renderCart();

        return;
    }


    item.quantity = quantity;

    renderCart();

}


/* =========================
   REMOVE PRODUCT
========================= */

function removeProduct(productId) {

    cart =
        cart.filter(function(item) {

            return item.id != productId;

        });


    renderCart();

}


/* =========================
   CALCULATE TOTAL
========================= */

function calculateTotal() {

    let subtotal = 0;


    cart.forEach(function(item) {

        subtotal +=
            item.quantity *
            item.price;

    });


    let discount =
        Number(
            document.getElementById(
                "discount"
            ).value
        ) || 0;


    if (discount < 0) {
        discount = 0;
    }


    if (discount > subtotal) {

        discount = subtotal;

        document.getElementById(
            "discount"
        ).value = discount;

    }


    const total =
        subtotal - discount;


    let paid =
        Number(
            document.getElementById(
                "paid_amount"
            ).value
        ) || 0;


    if (paid < 0) {
        paid = 0;
    }


    const due =
        Math.max(
            total - paid,
            0
        );


    const change =
        Math.max(
            paid - total,
            0
        );


    document.getElementById(
        "subtotal"
    ).textContent =
        subtotal.toFixed(2);


    document.getElementById(
        "grandTotal"
    ).textContent =
        total.toFixed(2);


    document.getElementById(
        "dueAmount"
    ).textContent =
        due.toFixed(2);


    document.getElementById(
        "changeAmount"
    ).textContent =
        change.toFixed(2);

}


/* =========================
   CUSTOMER BALANCE
========================= */

document.getElementById(
    "customer_id"
).addEventListener(
    "change",
    function() {

        const option =
            this.options[
                this.selectedIndex
            ];


        const balance =
            Number(
                option.dataset.balance || 0
            );


        document.getElementById(
            "customerBalance"
        ).textContent =
            "Existing Balance: AFN "
            +
            balance.toFixed(2);

    }
);


/* =========================
   PAYMENT METHOD
========================= */

document.getElementById(
    "payment_method"
).addEventListener(
    "change",
    function() {

        const method = this.value;

        const total =
            Number(
                document.getElementById(
                    "grandTotal"
                ).textContent
            );


        const paidInput =
            document.getElementById(
                "paid_amount"
            );


        if (method === "Cash") {

            paidInput.value =
                total.toFixed(2);

        }


        if (method === "Card") {

            paidInput.value =
                total.toFixed(2);

        }


        if (method === "Credit") {

            paidInput.value = "0";

        }


        calculateTotal();

    }
);


/* =========================
   COMPLETE SALE
========================= */

function completeSale() {

    if (cart.length === 0) {

        alert(
            "Please add at least one product."
        );

        return;
    }


    const customerId =
        document.getElementById(
            "customer_id"
        ).value;


    const paymentMethod =
        document.getElementById(
            "payment_method"
        ).value;


    const discount =
        Number(
            document.getElementById(
                "discount"
            ).value
        ) || 0;


    const paid =
        Number(
            document.getElementById(
                "paid_amount"
            ).value
        ) || 0;


    let subtotal = 0;


    cart.forEach(function(item) {

        subtotal +=
            item.quantity *
            item.price;

    });


    const total =
        subtotal - discount;


    const due =
        Math.max(
            total - paid,
            0
        );


    /* Credit requires customer */

    if (
        paymentMethod === "Credit"
        &&
        customerId === ""
    ) {

        alert(
            "Please select a customer for credit sale."
        );

        return;
    }


    /* Cash must cover total */

    if (
        paymentMethod === "Cash"
        &&
        paid < total
    ) {

        alert(
            "Cash payment cannot be less than total amount."
        );

        return;
    }


    /* Card must equal total */

    if (
        paymentMethod === "Card"
        &&
        paid !== total
    ) {

        alert(
            "Card payment must equal the total amount."
        );

        return;
    }


    if (discount > subtotal) {

        alert(
            "Discount cannot be greater than subtotal."
        );

        return;
    }


    if (
        !confirm(
            "Are you sure you want to complete this sale?"
        )
    ) {

        return;
    }


    /* =========================
       CREATE POST FORM
    ========================= */

    const form =
        document.createElement("form");

    form.method = "POST";

    form.action = "save_sale.php";


    /* Invoice */

    addHidden(
        form,
        "invoice_no",
        document.getElementById(
            "invoice_no"
        ).value
    );


    /* Customer */

    addHidden(
        form,
        "customer_id",
        customerId
    );


    /* Discount */

    addHidden(
        form,
        "discount",
        discount
    );


    /* Paid */

    addHidden(
        form,
        "paid_amount",
        paid
    );


    /* Payment method */

    addHidden(
        form,
        "payment_method",
        paymentMethod
    );


    /* Products */

    cart.forEach(function(item) {

        addHidden(
            form,
            "product_id[]",
            item.id
        );


        addHidden(
            form,
            "quantity[]",
            item.quantity
        );


        /*
         * This price is sent for display/data,
         * but save_sale.php MUST NOT trust it.
         * It should get the real price from DB.
         */

        addHidden(
            form,
            "selling_price[]",
            item.price
        );

    });


    document.body.appendChild(form);

    form.submit();

}


/* =========================
   HIDDEN INPUT
========================= */

function addHidden(
    form,
    name,
    value
) {

    const input =
        document.createElement("input");

    input.type = "hidden";

    input.name = name;

    input.value = value;

    form.appendChild(input);

}


/* =========================
   HTML ESCAPE
========================= */

function escapeHtml(text) {

    const div =
        document.createElement("div");

    div.textContent = text;

    return div.innerHTML;

}


/* =========================
   INITIAL CALCULATION
========================= */

calculateTotal();

</script>

</body>

</html>