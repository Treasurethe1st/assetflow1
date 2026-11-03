
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AssetFlow | Manage What Matters</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="navbar">
    <a href="index.php" class="logo">
        <span class="logo-icon">A</span>
        AssetFlow
    </a>

    <nav>
        <a href="#features">Features</a>
        <a href="#about">About</a>
    </nav>

    <div class="nav-actions">
        <a href="login.php" class="login-link">Log in</a>
        <a href="register.php" class="btn btn-dark">Get Started</a>
    </div>
</header>

<main>
    <section class="hero">
        <div class="hero-content">
            <span class="eyebrow">YOUR MONEY. YOUR BUSINESS. ONE PLACE.</span>

            <h1>Manage what you own.<br>
                <span>Grow what you build.</span>
            </h1>

            <p>
                Keep track of your assets, businesses, income, expenses
                and workers — all from one simple place.
            </p>

            <div class="hero-actions">
                <a href="register.php" class="btn btn-primary">
                    Create Your Account
                </a>
                <a href="#features" class="learn-link">
                    Explore Features →
                </a>
            </div>

            <div class="trust-note">
                <span class="check">✓</span>
                Simple to use. Easy to manage.
            </div>
        </div>

        <div class="preview">
            <div class="preview-header">
                <div>
                    <small>YOUR OVERVIEW</small>
                    <h3>Financial Summary</h3>
                </div>
                <span class="period">This month</span>
            </div>

            <div class="balance-card">
                <small>Total assets</small>
                <h2>RWF 25,500,000</h2>
                <span>Example dashboard preview</span>
            </div>

            <div class="preview-stats">
                <div class="stat">
                    <span>Monthly income</span>
                    <strong>RWF 2,400,000</strong>
                    <small class="positive">Money received</small>
                </div>

                <div class="stat">
                    <span>Monthly expenses</span>
                    <strong>RWF 850,000</strong>
                    <small>Money spent</small>
                </div>
            </div>

            <div class="preview-footer">
                <span>Businesses</span>
                <strong>03</strong>
                <span>Assets</span>
                <strong>08</strong>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="section-heading">
            <span class="eyebrow">EVERYTHING IN ONE PLACE</span>
            <h2>Stay in control of what you own.</h2>
            <p>Manage your finances and operations without unnecessary complexity.</p>
        </div>

        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-icon">⌂</div>
                <h3>Manage Assets</h3>
                <p>Record your properties, vehicles, equipment and investments.</p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">▣</div>
                <h3>Manage Businesses</h3>
                <p>Organize multiple businesses and monitor their performance.</p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">↗</div>
                <h3>Track Money</h3>
                <p>Record income, expenses, debts and repayments.</p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">♙</div>
                <h3>Manage Workers</h3>
                <p>Keep worker records and assign them to different businesses.</p>
            </article>
        </div>
    </section>

    <section class="about" id="about">
        <h2>A clearer picture of everything you manage.</h2>
        <p>
            AssetFlow brings your personal assets and business activities
            together, helping you understand where your money goes and
            what your resources are worth.
        </p>
        <a href="register.php" class="btn btn-primary">Get Started</a>
    </section>
</main>

<footer>
    <a href="index.php" class="logo">
        <span class="logo-icon">A</span>
        AssetFlow
    </a>
    <p>Manage your money, properties and businesses in one place.</p>
    <small>© <?php echo date("Y"); ?> AssetFlow. All rights reserved.</small>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
