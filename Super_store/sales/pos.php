<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

include "../db.php";

$username = $_SESSION["username"] ?? "User";
$role = $_SESSION["role"] ?? "Cashier";

/* =========================
   CUSTOMERS
========================= */

$customers = [];

$customer_sql = "
    SELECT
        id,
        name,
        phone,
        balance
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

$invoice_no = "INV-" . date("YmdHis") . "-" . rand(10, 99);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>New Sale - POS</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        /* =========================
           HEADER
        ========================= */

        .topbar {
            height: 70px;
            background: #0f172a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
        }

        .brand {
            font-size: 22px;
            font-weight: bold;
        }

        .user-info {
            font-size: 14px;
        }

        .user-info span {
            margin-left: 10px;
            padding: 6px 10px;
            background: #1e293b;
            border-radius: 5px;
        }


        /* =========================
           MAIN
        ========================= */

        .container {
            padding: 25px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 26px;
        }

        .page-header p {
            margin: 5px 0 0;
            color: #64748b;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            border: none;
            padding: 10px 15px;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
        }

        .btn-dark {
            background: #0f172a;
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


        /* =========================
           GRID
        ========================= */

        .pos-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 20px;
            align-items: start;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.08);
            padding: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }


        /* =========================
           SEARCH
        ========================= */

        .search-area {
            display: grid;
            grid-template-columns: 1fr 180px;
            gap: 10px;
            margin-bottom: 15px;
        }

        .input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        .input:focus {
            border-color: #2563eb;
        }

        .product-results {
            display: none;
            max-height: 280px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .product-result {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .product-result:last-child {
            border-bottom: none;
        }

        .product-result:hover {
            background: #f8fafc;
        }

        .product-result-name {
            font-weight: bold;
        }

        .product-result-info {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .product-price {
            font-weight: bold;
            color: #16a34a;
            white-space: nowrap;
        }


        /* =========================
           CART TABLE
        ========================= */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 11px 8px;
            text-align: left;
            font-size: 13px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .qty-input {
            width: 70px;
            padding: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            text-align: center;
        }

        .price-input {
            width: 100px;
            padding: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            text-align: right;
        }

        .remove-btn {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 6px 9px;
            border-radius: 5px;
            cursor: pointer;
        }

        .empty-cart {
            text-align: center;
            padding: 50px 20px;
            color: #94a3b8;
        }


        /* =========================
           RIGHT SIDE
        ========================= */

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        select.input {
            background: white;
        }

        .customer-balance {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 7px;
            font-size: 13px;
            margin-top: 7px;
            display: none;
        }


        /* =========================
           TOTALS
        ========================= */

        .totals {
            border-top: 1px solid #e2e8f0;
            margin-top: 20px;
            padding-top: 15px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .grand-total {
            font-size: 23px;
            font-weight: bold;
            padding: 12px 0;
            border-top: 1px dashed #cbd5e1;
            border-bottom: 1px dashed #cbd5e1;
        }

        .total-amount {
            color: #16a34a;
        }

        .due-amount {
            color: #dc2626;
        }

        .change-amount {
            color: #2563eb;
        }


        /* =========================
           COMPLETE BUTTON
        ========================= */

        .complete-sale {
            width: 100%;
            border: none;
            background: #16a34a;
            color: white;
            padding: 15px;
            border-radius: 8px;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }

        .complete-sale:hover {
            background: #15803d;
        }

        .complete-sale:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }


        /* =========================
           QUICK INFO
        ========================= */

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            padding: 10px;
            border-radius: 7px;
            font-size: 12px;
            margin-bottom: 15px;
            line-height: 1.5;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .pos-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 700px) {

            .container {
                padding: 15px;
            }

            .topbar {
                padding: 0 15px;
            }

            .brand {
                font-size: 18px;
            }

            .user-info {
                display: none;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .search-area {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- =========================
     TOP BAR
========================= -->

<div class="topbar">

    <div class="brand">
        🛒 Super Store POS
    </div>

    <div class="user-info">

        <?php echo htmlspecialchars($username); ?>

        <span>
            <?php echo htmlspecialchars($role); ?>
        </span>

    </div>

</div>


<div class="container">


    <!-- =========================
         PAGE HEADER
    ========================= -->

    <div class="page-header">

        <div>

            <h1>
                New Sale
            </h1>

            <p>
                Create a new sales invoice
            </p>

        </div>


        <div class="header-buttons">

            <a
                href="manage.php"
                class="btn btn-dark"
            >
                Sales History
            </a>

            <a
                href="../dashboard.php"
                class="btn btn-blue"
            >
                Dashboard
            </a>

        </div>

    </div>


    <!-- =========================
         POS GRID
    ========================= -->

    <div class="pos-grid">


        <!-- =========================
             LEFT SIDE
        ========================= -->

        <div class="card">

            <div class="card-title">
                Products
            </div>


            <div class="info-box">

                Search by product name or barcode.
                You can also scan a barcode using a barcode scanner.

            </div>


            <!-- SEARCH -->

            <div class="search-area">

                <input
                    type="text"
                    id="productSearch"
                    class="input"
                    placeholder="Search product or barcode..."
                    autocomplete="off"
                    autofocus
                >

                <input
                    type="text"
                    id="barcodeInput"
                    class="input"
                    placeholder="Scan barcode..."
                    autocomplete="off"
                >

            </div>


            <!-- PRODUCT RESULTS -->

            <div
                id="productResults"
                class="product-results"
            ></div>


            <!-- CART -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Qty
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                                #
                            </th>

                        </tr>

                    </thead>


                    <tbody id="cartBody">

                        <tr id="emptyCart">

                            <td
                                colspan="5"
                                class="empty-cart"
                            >

                                🛒

                                <br><br>

                                No products added yet.

                                <br>

                                Search for a product above.

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =========================
             RIGHT SIDE
        ========================= -->

        <div class="card">

            <div class="card-title">
                Sale Information
            </div>


            <form
                id="saleForm"
                method="POST"
                action="save_sale.php"
            >


                <!-- INVOICE -->

                <div class="form-group">

                    <label>
                        Invoice Number
                    </label>

                    <input
                        type="text"
                        name="invoice_no"
                        class="input"
                        value="<?php echo htmlspecialchars($invoice_no); ?>"
                        readonly
                    >

                </div>


                <!-- CUSTOMER -->

                <div class="form-group">

                    <label>
                        Customer
                    </label>

                    <select
                        name="customer_id"
                        id="customer_id"
                        class="input"
                    >

                        <option value="">
                            Walk-in Customer
                        </option>

                        <?php foreach ($customers as $customer): ?>

                            <option
                                value="<?php echo $customer["id"]; ?>"
                                data-balance="<?php echo $customer["balance"]; ?>"
                            >

                                <?php
                                echo htmlspecialchars($customer["name"]);
                                ?>

                                <?php if (!empty($customer["phone"])): ?>

                                    -
                                    <?php
                                    echo htmlspecialchars($customer["phone"]);
                                    ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <div
                        id="customerBalance"
                        class="customer-balance"
                    ></div>

                </div>


                <!-- DISCOUNT -->

                <div class="form-group">

                    <label>
                        Discount
                    </label>

                    <input
                        type="number"
                        name="discount"
                        id="discount"
                        class="input"
                        value="0"
                        min="0"
                        step="0.01"
                    >

                </div>


                <!-- PAYMENT METHOD -->

                <div class="form-group">

                    <label>
                        Payment Method
                    </label>

                    <select
                        name="payment_method"
                        id="payment_method"
                        class="input"
                    >

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


                <!-- PAID -->

                <div class="form-group">

                    <label>
                        Paid Amount
                    </label>

                    <input
                        type="number"
                        name="paid_amount"
                        id="paid_amount"
                        class="input"
                        value="0"
                        min="0"
                        step="0.01"
                    >

                </div>


                <!-- HIDDEN CART FIELDS -->

                <div id="hiddenItems"></div>


                <!-- TOTALS -->

                <div class="totals">


                    <div class="total-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>
                            <span id="subtotal">
                                0.00
                            </span>
                            AFN
                        </strong>

                    </div>


                    <div class="total-row">

                        <span>
                            Discount
                        </span>

                        <strong>
                            <span id="discountDisplay">
                                0.00
                            </span>
                            AFN
                        </strong>

                    </div>


                    <div class="total-row grand-total">

                        <span>
                            TOTAL
                        </span>

                        <span class="total-amount">

                            <span id="total">
                                0.00
                            </span>

                            AFN

                        </span>

                    </div>


                    <div class="total-row">

                        <span>
                            Due
                        </span>

                        <span class="due-amount">

                            <span id="due">
                                0.00
                            </span>

                            AFN

                        </span>

                    </div>


                    <div class="total-row">

                        <span>
                            Change
                        </span>

                        <span class="change-amount">

                            <span id="change">
                                0.00
                            </span>

                            AFN

                        </span>

                    </div>

                </div>


                <!-- COMPLETE -->

                <button
                    type="submit"
                    id="completeSale"
                    class="complete-sale"
                    disabled
                >
                    ✓ Complete Sale
                </button>


            </form>

        </div>

    </div>

</div>


<script>

/* =========================
   PRODUCTS FROM PHP
========================= */

const products = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;


/* =========================
   CART
========================= */

let cart = [];


/* =========================
   ELEMENTS
========================= */

const productSearch =
    document.getElementById("productSearch");

const barcodeInput =
    document.getElementById("barcodeInput");

const productResults =
    document.getElementById("productResults");

const cartBody =
    document.getElementById("cartBody");

const discountInput =
    document.getElementById("discount");

const paidInput =
    document.getElementById("paid_amount");

const paymentMethod =
    document.getElementById("payment_method");

const customerSelect =
    document.getElementById("customer_id");

const customerBalance =
    document.getElementById("customerBalance");

const completeSale =
    document.getElementById("completeSale");


/* =========================
   SEARCH PRODUCT
========================= */

productSearch.addEventListener("input", function () {

    const keyword =
        this.value.trim().toLowerCase();

    if (keyword === "") {

        productResults.style.display = "none";

        productResults.innerHTML = "";

        return;
    }


    const matches = products.filter(product => {

        const name =
            String(product.name).toLowerCase();

        const barcode =
            String(product.barcode ?? "").toLowerCase();

        return (
            name.includes(keyword) ||
            barcode.includes(keyword)
        );

    });


    showProductResults(matches);

});


/* =========================
   BARCODE SCANNER
========================= */

barcodeInput.addEventListener("keydown", function (event) {

    if (event.key !== "Enter") {
        return;
    }

    event.preventDefault();

    const barcode =
        this.value.trim();

    if (barcode === "") {
        return;
    }


    const product = products.find(item =>
        String(item.barcode) === barcode
    );


    if (!product) {

        alert("Product with this barcode was not found.");

        this.value = "";

        this.focus();

        return;
    }


    addToCart(product);

    this.value = "";

    this.focus();

});


/* =========================
   SHOW SEARCH RESULTS
========================= */

function showProductResults(matches) {

    productResults.innerHTML = "";

    if (matches.length === 0) {

        productResults.innerHTML = `
            <div style="padding:15px;text-align:center;color:#64748b;">
                No product found.
            </div>
        `;

        productResults.style.display = "block";

        return;
    }


    matches.slice(0, 30).forEach(product => {

        const item =
            document.createElement("div");

        item.className =
            "product-result";


        item.innerHTML = `

            <div>

                <div class="product-result-name">
                    ${escapeHtml(product.name)}
                </div>

                <div class="product-result-info">

                    Barcode:
                    ${escapeHtml(product.barcode ?? "-")}

                    |
                    Stock:
                    ${Number(product.stock).toFixed(2)}

                    ${escapeHtml(product.unit ?? "")}

                </div>

            </div>

            <div class="product-price">

                ${Number(product.selling_price).toFixed(2)}
                AFN

            </div>

        `;


        item.addEventListener("click", function () {

            addToCart(product);

            productSearch.value = "";

            productResults.style.display = "none";

            productResults.innerHTML = "";

            productSearch.focus();

        });


        productResults.appendChild(item);

    });


    productResults.style.display = "block";

}


/* =========================
   ADD TO CART
========================= */

function addToCart(product) {

    const existing =
        cart.find(item =>
            Number(item.id) === Number(product.id)
        );


    if (existing) {

        if (
            Number(existing.quantity) + 1 >
            Number(product.stock)
        ) {

            alert("Not enough stock available.");

            return;
        }

        existing.quantity++;

    } else {

        cart.push({

            id: Number(product.id),

            name: product.name,

            barcode: product.barcode,

            selling_price:
                Number(product.selling_price),

            purchase_price:
                Number(product.purchase_price),

            stock:
                Number(product.stock),

            unit:
                product.unit

        });

    }


    renderCart();

}


/* =========================
   RENDER CART
========================= */

function renderCart() {

    cartBody.innerHTML = "";


    if (cart.length === 0) {

        cartBody.innerHTML = `

            <tr>

                <td
                    colspan="5"
                    class="empty-cart"
                >

                    🛒

                    <br><br>

                    No products added yet.

                    <br>

                    Search for a product above.

                </td>

            </tr>

        `;

        calculateTotals();

        return;
    }


    cart.forEach((item, index) => {

        const row =
            document.createElement("tr");


        const itemTotal =
            item.quantity *
            item.selling_price;


        row.innerHTML = `

            <td>

                <strong>
                    ${escapeHtml(item.name)}
                </strong>

                <br>

                <small style="color:#64748b;">
                    ${escapeHtml(item.unit ?? "")}
                </small>

            </td>


            <td>

                <input
                    type="number"
                    class="qty-input"
                    value="${item.quantity}"
                    min="0.01"
                    max="${item.stock}"
                    step="0.01"
                    onchange="changeQuantity(${index}, this.value)"
                >

            </td>


            <td>

                <input
                    type="number"
                    class="price-input"
                    value="${item.selling_price.toFixed(2)}"
                    min="0"
                    step="0.01"
                    onchange="changePrice(${index}, this.value)"
                >

            </td>


            <td>

                <strong>
                    ${itemTotal.toFixed(2)}
                </strong>

            </td>


            <td>

                <button
                    type="button"
                    class="remove-btn"
                    onclick="removeItem(${index})"
                >
                    ✕
                </button>

            </td>

        `;


        cartBody.appendChild(row);

    });


    calculateTotals();

}


/* =========================
   CHANGE QUANTITY
========================= */

function changeQuantity(index, value) {

    let quantity =
        parseFloat(value);


    if (!Number.isFinite(quantity) || quantity <= 0) {

        removeItem(index);

        return;
    }


    if (quantity > cart[index].stock) {

        alert(
            "Available stock: " +
            cart[index].stock
        );

        quantity =
            cart[index].stock;

    }


    cart[index].quantity =
        quantity;


    renderCart();

}


/* =========================
   CHANGE PRICE
========================= */

function changePrice(index, value) {

    let price =
        parseFloat(value);


    if (!Number.isFinite(price) || price < 0) {

        price =
            cart[index].selling_price;

    }


    cart[index].selling_price =
        price;


    renderCart();

}


/* =========================
   REMOVE ITEM
========================= */

function removeItem(index) {

    cart.splice(index, 1);

    renderCart();

}


/* =========================
   CALCULATE TOTALS
========================= */

function calculateTotals() {

    let subtotal = 0;


    cart.forEach(item => {

        subtotal +=
            item.quantity *
            item.selling_price;

    });


    let discount =
        parseFloat(discountInput.value) || 0;


    if (discount < 0) {
        discount = 0;
    }


    if (discount > subtotal) {

        discount = subtotal;

        discountInput.value =
            subtotal.toFixed(2);

    }


    const total =
        subtotal - discount;


    let paid =
        parseFloat(paidInput.value) || 0;


    if (paid < 0) {
        paid = 0;
    }


    let due = 0;

    let change = 0;


    if (paid >= total) {

        change =
            paid - total;

        due = 0;

    } else {

        due =
            total - paid;

        change = 0;

    }


    document.getElementById("subtotal").textContent =
        subtotal.toFixed(2);

    document.getElementById("discountDisplay").textContent =
        discount.toFixed(2);

    document.getElementById("total").textContent =
        total.toFixed(2);

    document.getElementById("due").textContent =
        due.toFixed(2);

    document.getElementById("change").textContent =
        change.toFixed(2);


    completeSale.disabled =
        cart.length === 0;

}


/* =========================
   DISCOUNT
========================= */

discountInput.addEventListener(
    "input",
    calculateTotals
);


/* =========================
   PAID
========================= */

paidInput.addEventListener(
    "input",
    calculateTotals
);


/* =========================
   CUSTOMER
========================= */

customerSelect.addEventListener(
    "change",
    updateCustomer
);


function updateCustomer() {

    const option =
        customerSelect.options[
            customerSelect.selectedIndex
        ];


    if (!option || !option.value) {

        customerBalance.style.display =
            "none";

        customerBalance.innerHTML = "";

        return;
    }


    const balance =
        parseFloat(
            option.dataset.balance || 0
        );


    customerBalance.style.display =
        "block";


    customerBalance.innerHTML = `

        Existing Balance:

        <strong>
            ${balance.toFixed(2)} AFN
        </strong>

    `;

}


/* =========================
   PAYMENT METHOD
========================= */

paymentMethod.addEventListener(
    "change",
    updatePaymentMethod
);


function updatePaymentMethod() {

    const method =
        paymentMethod.value;


    const total =
        parseFloat(
            document.getElementById("total").textContent
        ) || 0;


    if (method === "Cash") {

        paidInput.value =
            total.toFixed(2);

    }


    if (method === "Card") {

        paidInput.value =
            total.toFixed(2);

    }


    if (method === "Credit") {

        paidInput.value =
            "0";

    }


    calculateTotals();

}


/* =========================
   FORM SUBMIT
========================= */

document
    .getElementById("saleForm")
    .addEventListener("submit", function(event) {

        if (cart.length === 0) {

            event.preventDefault();

            alert("Please add at least one product.");

            return;
        }


        const method =
            paymentMethod.value;


        const customer =
            customerSelect.value;


        const subtotal =
            cart.reduce(
                (sum, item) =>
                    sum +
                    (
                        item.quantity *
                        item.selling_price
                    ),
                0
            );


        const discount =
            parseFloat(
                discountInput.value
            ) || 0;


        const total =
            subtotal - discount;


        const paid =
            parseFloat(
                paidInput.value
            ) || 0;


        /* CREDIT */

        if (method === "Credit" && !customer) {

            event.preventDefault();

            alert(
                "Please select a customer for credit sale."
            );

            customerSelect.focus();

            return;
        }


        /* CASH/CARD */

        if (
            (method === "Card") &&
            paid < total
        ) {

            event.preventDefault();

            alert(
                "Card payment must cover the total amount."
            );

            paidInput.focus();

            return;
        }


        /* CREATE HIDDEN ITEMS */

        const hiddenItems =
            document.getElementById("hiddenItems");

        hiddenItems.innerHTML = "";


        cart.forEach(item => {

            hiddenItems.insertAdjacentHTML(
                "beforeend",
                `
                <input
                    type="hidden"
                    name="product_id[]"
                    value="${item.id}"
                >

                <input
                    type="hidden"
                    name="quantity[]"
                    value="${item.quantity}"
                >

                <input
                    type="hidden"
                    name="selling_price[]"
                    value="${item.selling_price}"
                >
                `
            );

        });


        completeSale.disabled =
            true;


        completeSale.textContent =
            "Processing Sale...";

    });


/* =========================
   ESCAPE HTML
========================= */

function escapeHtml(value) {

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

}


/* =========================
   INITIAL
========================= */

calculateTotals();

</script>


</body>
</html>