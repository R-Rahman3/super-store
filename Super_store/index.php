<?php
session_start();


$store_name = "Super Store";
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?php echo $store_name; ?></title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


body {

    font-family: Arial, sans-serif;

    background: #f4f6f9;

    color: #1f2937;

}


/* =========================
   NAVBAR
========================= */

.navbar {

    width: 100%;

    background: #1f2937;

    color: white;

    padding: 18px 6%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    position: sticky;

    top: 0;

    z-index: 1000;

}


.logo {

    font-size: 25px;

    font-weight: bold;

    letter-spacing: 0.5px;

}


.nav-links {

    display: flex;

    align-items: center;

    gap: 25px;

}


.nav-links a {

    color: white;

    text-decoration: none;

    font-size: 15px;

    transition: 0.3s;

}


.nav-links a:hover {

    color: #20c997;

}


.login-btn {

    background: #198754;

    padding: 10px 18px;

    border-radius: 7px;

    color: white !important;

}


.login-btn:hover {

    background: #157347;

}


/* =========================
   HERO
========================= */

.hero {

    min-height: 560px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 50px;

    padding: 70px 8%;

    background: white;

}


.hero-content {

    max-width: 650px;

}


.hero-content .small-title {

    color: #198754;

    font-weight: bold;

    font-size: 15px;

    margin-bottom: 15px;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.hero-content h1 {

    font-size: 52px;

    line-height: 1.15;

    margin-bottom: 20px;

}


.hero-content h1 span {

    color: #198754;

}


.hero-content p {

    color: #6b7280;

    font-size: 18px;

    line-height: 1.7;

    margin-bottom: 30px;

}


.hero-buttons {

    display: flex;

    gap: 12px;

    flex-wrap: wrap;

}


.btn {

    display: inline-block;

    padding: 13px 23px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 15px;

    transition: 0.3s;

}


.btn-primary {

    background: #198754;

    color: white;

}


.btn-primary:hover {

    background: #157347;

}


.btn-secondary {

    background: #1f2937;

    color: white;

}


.btn-secondary:hover {

    background: #111827;

}


/* =========================
   HERO CARD
========================= */

.hero-card {

    width: 400px;

    min-height: 330px;

    background: #1f2937;

    border-radius: 20px;

    padding: 35px;

    color: white;

    box-shadow: 0 15px 40px rgba(0,0,0,0.15);

}


.store-icon {

    width: 75px;

    height: 75px;

    background: #198754;

    border-radius: 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 38px;

    margin-bottom: 25px;

}


.hero-card h2 {

    font-size: 28px;

    margin-bottom: 15px;

}


.hero-card p {

    color: #d1d5db;

    line-height: 1.6;

}


/* =========================
   FEATURES
========================= */

.features {

    padding: 70px 7%;

    background: #f4f6f9;

}


.section-title {

    text-align: center;

    margin-bottom: 45px;

}


.section-title h2 {

    font-size: 32px;

    margin-bottom: 10px;

}


.section-title p {

    color: #6b7280;

}


.feature-grid {

    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 22px;

    max-width: 1200px;

    margin: auto;

}


.feature-card {

    background: white;

    padding: 28px 22px;

    border-radius: 12px;

    text-align: center;

    box-shadow: 0 3px 12px rgba(0,0,0,0.06);

    transition: 0.3s;

}


.feature-card:hover {

    transform: translateY(-5px);

    box-shadow: 0 8px 25px rgba(0,0,0,0.10);

}

    
.feature-icon {

    font-size: 35px;

    margin-bottom: 15px;

}


.feature-card h3 {

    font-size: 18px;

    margin-bottom: 10px;

}


.feature-card p {

    color: #6b7280;

    font-size: 14px;

    line-height: 1.6;

}


/* =========================
   ABOUT
========================= */

.about {

    padding: 75px 8%;

    background: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 50px;

}


.about-content {

    max-width: 650px;

}


.about-content h2 {

    font-size: 32px;

    margin-bottom: 18px;

}


.about-content p {

    color: #6b7280;

    line-height: 1.8;

    margin-bottom: 15px;

}


.about-box {

    width: 350px;

    background: #f4f6f9;

    padding: 30px;

    border-radius: 15px;

}


.about-box h3 {

    margin-bottom: 20px;

}


.about-item {

    display: flex;

    justify-content: space-between;

    padding: 13px 0;

    border-bottom: 1px solid #ddd;

}


.about-item:last-child {

    border-bottom: none;

}


/* =========================
   CTA
========================= */

.cta {

    padding: 65px 20px;

    background: #198754;

    color: white;

    text-align: center;

}


.cta h2 {

    font-size: 32px;

    margin-bottom: 15px;

}


.cta p {

    margin-bottom: 25px;

    opacity: 0.9;

}


.cta-btn {

    background: white;

    color: #198754;

    font-weight: bold;

}


.cta-btn:hover {

    background: #f1f1f1;

}


/* =========================
   FOOTER
========================= */

.footer {

    background: #111827;

    color: #d1d5db;

    padding: 25px 7%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 15px;

}


.footer p {

    font-size: 14px;

}


.footer a {

    color: white;

    text-decoration: none;

}


/* =========================
   RESPONSIVE
========================= */

@media(max-width: 1000px) {

    .hero {

        flex-direction: column;

        text-align: center;

        padding: 60px 5%;

    }


    .hero-buttons {

        justify-content: center;

    }


    .hero-card {

        width: 100%;

        max-width: 450px;

    }


    .feature-grid {

        grid-template-columns: repeat(2, 1fr);

    }


    .about {

        flex-direction: column;

        text-align: center;

    }


    .about-box {

        width: 100%;

        max-width: 450px;

    }

}


@media(max-width: 650px) {

    .navbar {

        padding: 15px 5%;

    }


    .nav-links a:not(.login-btn) {

        display: none;

    }


    .logo {

        font-size: 21px;

    }


    .hero {

        min-height: auto;

        padding: 55px 5%;

    }


    .hero-content h1 {

        font-size: 38px;

    }


    .hero-content p {

        font-size: 16px;

    }


    .feature-grid {

        grid-template-columns: 1fr;

    }


    .section-title h2 {

        font-size: 27px;

    }


    .about-content h2 {

        font-size: 27px;

    }


    .cta h2 {

        font-size: 27px;

    }


    .footer {

        flex-direction: column;

        text-align: center;

    }

}

</style>

</head>


<body>


<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar">

    <div class="logo">

        🛒 <?php echo $store_name; ?>

    </div>


    <div class="nav-links">

        <a href="#home">Home</a>

        <a href="#features">Features</a>

        <a href="#about">About</a>

        <a href="#contact">Contact</a>


        <?php if (isset($_SESSION["user_id"])): ?>

            <a href="login.php" class="login-btn">
                Login
            </a>
            

        <?php else: ?>

            <a href="login.php" class="login-btn">
                Login
            </a>

        <?php endif; ?>

    </div>

</nav>



<!-- =========================
     HERO
========================= -->

<section class="hero" id="home">


    <div class="hero-content">

        <div class="small-title">
            Complete Store Management System
        </div>


        <h1>

            Manage Your Store

            <span>Smarter & Faster</span>

        </h1>


        <p>

            Super Store is a modern management system designed
            to help you manage products, sales, purchases,
            customers, suppliers and financial records easily.

        </p>


        <div class="hero-buttons">

            <a
                href="login.php"
                class="btn btn-primary"
            >
                Get Started
            </a>


            <a
                href="#features"
                class="btn btn-secondary"
            >
                Explore Features
            </a>

        </div>

    </div>



    <div class="hero-card">

        <div class="store-icon">
            🛒
        </div>


        <h2>
            Super Store
        </h2>


        <p>

            One powerful platform for managing
            your entire store operation.

        </p>

    </div>


</section>



<!-- =========================
     FEATURES
========================= -->

<section class="features" id="features">


    <div class="section-title">

        <h2>
            Powerful Features
        </h2>

        <p>
            Everything you need to manage your store
        </p>

    </div>



    <div class="feature-grid">


        <div class="feature-card">

            <div class="feature-icon">
                📦
            </div>

            <h3>
                Products
            </h3>

            <p>
                Manage products, stock, prices,
                categories and expiry dates.
            </p>

        </div>



        <div class="feature-card">

            <div class="feature-icon">
                🧾
            </div>

            <h3>
                Sales & POS
            </h3>

            <p>
                Process sales quickly and manage
                invoices and customer payments.
            </p>

        </div>



        <div class="feature-card">

            <div class="feature-icon">
                🚚
            </div>

            <h3>
                Purchases
            </h3>

            <p>
                Manage suppliers, purchases,
                purchase invoices and payments.
            </p>

        </div>



        <div class="feature-card">

            <div class="feature-icon">
                📊
            </div>

            <h3>
                Reports
            </h3>

            <p>
                View sales, expenses, profit,
                stock and financial reports.
            </p>

        </div>


    </div>

</section>



<!-- =========================
     ABOUT
========================= -->

<section class="about" id="about">


    <div class="about-content">

        <h2>
            About Super Store
        </h2>


        <p>

            Super Store Management System provides
            a simple and professional way to manage
            daily store operations.

        </p>


        <p>

            From inventory management to sales,
            purchases, customers and financial
            reporting, everything is available
            in one centralized system.

        </p>

    </div>



    <div class="about-box">

        <h3>
            System Modules
        </h3>


        <div class="about-item">

            <span>Inventory</span>

            <strong>✓</strong>

        </div>


        <div class="about-item">

            <span>Sales</span>

            <strong>✓</strong>

        </div>


        <div class="about-item">

            <span>Purchases</span>

            <strong>✓</strong>

        </div>


        <div class="about-item">

            <span>Customers</span>

            <strong>✓</strong>

        </div>


        <div class="about-item">

            <span>Reports</span>

            <strong>✓</strong>

        </div>

    </div>


</section>



<!-- =========================
     CTA
========================= -->

<section class="cta" id="contact">


    <h2>
        Ready to Manage Your Store?
    </h2>


    <p>
        Login to your Super Store Management System.
    </p>


    <a
        href="login.php"
        class="btn cta-btn"
    >
        Login to System
    </a>


</section>



<!-- =========================
     FOOTER
========================= -->

<footer class="footer">


    <p>
        © <?php echo date("Y"); ?>
        <?php echo $store_name; ?>.
        All Rights Reserved.
    </p>


    <p>
        Store Management System
    </p>


</footer>


</body>

</html>