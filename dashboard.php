
<?php

require_once "auth_check.php";
require_once "config/db.php";

// Get logged-in user's information.
$stmt = $pdo->prepare(
    "SELECT full_name, email FROM users WHERE id = ?"
);
$stmt->execute([$_SESSION["user_id"]]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION = [];
    session_destroy();

    header("Location: login.php");
    exit;
}

// Prepare user information.
$name = $user["full_name"];
$email = $user["email"];
$initial = strtoupper(substr($name, 0, 1));

// Escape output safely.
$safeName = htmlspecialchars($name, ENT_QUOTES, "UTF-8");
$safeEmail = htmlspecialchars($email, ENT_QUOTES, "UTF-8");

// Get total asset value.
$assetStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(estimated_value), 0) AS total,
            COUNT(*) AS total_count
     FROM assets
     WHERE user_id = ?"
);

$assetStmt->execute([$_SESSION["user_id"]]);
$assetData = $assetStmt->fetch();

$totalAssets = (float)$assetData["total"];
$assetCount = (int)$assetData["total_count"];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | AssetFlow</title>
    <link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <a href="dashboard.php" class="logo">
            <span class="logo-icon">A</span>
            AssetFlow
        </a>

        <p class="menu-label">WORKSPACE</p>

       
<nav class="sidebar-nav">

    <a href="dashboard.php" class="active">
        <i class="fas fa-gauge-high"></i>
        <span>Dashboard</span>
    </a>

    <a href="assets.php">
        <i class="fas fa-house"></i>
        <span>My Assets</span>
    </a>

    <a href="businesses.php">
        <i class="fas fa-building"></i>
        <span>My Businesses</span>
    </a>

    <a href="business_finances.php">
        <i class="fas fa-wallet"></i>
        <span>Business Finances</span>
    </a>

    <a href="workers.php">
    <i class="fas fa-users"></i>
    <span>Workers</span>
</a>


    <a href="payroll.php">
    <i class="fas fa-money-check-dollar"></i>
    <span>Salary & Payroll</span>
</a>

    <a href="#" class="coming-soon">
        <i class="fas fa-credit-card"></i>
        <span>Debts</span>
    </a>

    <a href="#" class="coming-soon">
        <i class="fas fa-chart-line"></i>
        <span>Reports</span>
    </a>

</nav>

<div class="sidebar-bottom">

    <a href="#" class="coming-soon">
        <i class="fas fa-gear"></i>
        <span>Settings</span>
    </a>

    <a href="logout.php" class="logout-link">
        <i class="fas fa-right-from-bracket"></i>
        <span>Log out</span>
    </a>

</div>


    </aside>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOP BAR -->
        <header class="topbar">

            <div>
                <h2>Dashboard</h2>
                <p>Your financial overview</p>
            </div>

            <div class="user-profile">

                <div class="avatar">
                    <?= htmlspecialchars($initial) ?>
                </div>

                <div>
                    <strong><?= $safeName ?></strong>
                    <small>Account owner</small>
                </div>

            </div>

        </header>


        <!-- WELCOME SECTION -->
        <section class="welcome-section">

            <div>
                <h1>Welcome back, <?= $safeName ?>!</h1>

                <p>
                    Here's an overview of everything you're managing.
                </p>
            </div>

            <span class="date">
                <?= date("d M Y") ?>
            </span>

        </section>


        <!-- FINANCIAL SUMMARY -->
        <section class="summary-grid">

            <article class="summary-card">

                <span>Total assets</span>

                <h2>
                    RWF <?= number_format($totalAssets, 2) ?>
                </h2>

                <small>
                    <?= $assetCount ?> registered asset(s)
                </small>

            </article>


            <article class="summary-card">

                <span>Total liabilities</span>

                <h2>RWF 0</h2>

                <small>No debts recorded yet</small>

            </article>


            <article class="summary-card">

                <span>Monthly income</span>

                <h2>RWF 0</h2>

                <small>No income recorded yet</small>

            </article>


            <article class="summary-card">

                <span>Monthly expenses</span>

                <h2>RWF 0</h2>

                <small>No expenses recorded yet</small>

            </article>

        </section>


        <!-- LOWER DASHBOARD -->
        <section class="dashboard-bottom">


            <!-- GET STARTED -->
            <article class="content-card">

                <div class="card-heading">

                    <h3>Get started</h3>

                    <span>Your next steps</span>

                </div>


                <div class="task-item">

                    <div class="task-icon">⌂</div>

                    <div>

                        <strong>Add your first asset</strong>

                        <p>
                            Record your properties, vehicles, businesses
                            and other valuable possessions.
                        </p>

                        <a href="assets.php" class="task-link">
                            Manage assets →
                        </a>

                    </div>

                </div>


                <div class="task-item">

                    <div class="task-icon">▣</div>

                    <div>

                        <strong>Manage your businesses</strong>

                        <p>
                            Organize your businesses and monitor
                            their performance.
                        </p>

                        <span class="coming-label">
                            Coming soon
                        </span>

                    </div>

                </div>


                <div class="task-item">

                    <div class="task-icon">↗</div>

                    <div>

                        <strong>Track your money</strong>

                        <p>
                            Record income, expenses and financial activities.
                        </p>

                        <span class="coming-label">
                            Coming soon
                        </span>

                    </div>

                </div>

            </article>


            <!-- ACCOUNT INFORMATION -->
            <article class="content-card account-card">

                <div class="card-heading">

                    <h3>Your account</h3>

                </div>


                <div class="account-avatar">
                    <?= htmlspecialchars($initial) ?>
                </div>

                <h3><?= $safeName ?></h3>

                <p><?= $safeEmail ?></p>


                <div class="account-status">

                    <span></span>

                    Account active

                </div>

            </article>


        </section>


        <!-- FOOTER -->
        <footer class="dashboard-footer">

            © <?= date("Y") ?> AssetFlow.
            All rights reserved.

        </footer>

    </main>

</div>

</body>
</html>
