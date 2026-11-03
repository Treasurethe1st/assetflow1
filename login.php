
<?php
session_start();
require_once "config/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, full_name, email, password FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["full_name"];
            $_SESSION["user_email"] = $user["email"];

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Incorrect email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AssetFlow</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

<div class="auth-container">

    <a href="index.php" class="logo">
        <span class="logo-icon">A</span>
        AssetFlow
    </a>

    <div class="auth-card">
        <h1>Welcome back</h1>
        <p class="auth-description">
            Log in to continue managing your assets and businesses.
        </p>

        <?php if (isset($_GET["registered"])): ?>
            <div class="message success">
                Account created successfully. You can now log in.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <label for="email">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
            >

            <button type="submit" class="btn btn-primary auth-button">
                Log In
            </button>

        </form>

        <p class="auth-switch">
            Don't have an account?
            <a href="register.php">Create one</a>
        </p>
    </div>

    <p class="auth-footer">
        © <?= date("Y") ?> AssetFlow
    </p>

</div>

</body>
</html>
