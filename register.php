
<?php
session_start();
require_once "config/db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Your password must have at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Your passwords do not match.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = "An account with this email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare(
                "INSERT INTO users (full_name, email, password)
                 VALUES (?, ?, ?)"
            );

            $insert->execute([
                $name,
                $email,
                $hashed_password
            ]);

            header("Location: login.php?registered=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | AssetFlow</title>
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
        <h1>Create your account</h1>
        <p class="auth-description">
            Start managing your assets and businesses in one place.
        </p>

        <?php if ($error): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">

            <label for="full_name">Full name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                placeholder="Enter your full name"
                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                maxlength="120"
                required
            >

            <label for="email">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="example@email.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                maxlength="150"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="At least 8 characters"
                minlength="8"
                required
            >

            <label for="confirm_password">Confirm password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Enter your password again"
                required
            >

            <button type="submit" class="btn btn-primary auth-button">
                Create Account
            </button>

        </form>

        <p class="auth-switch">
            Already have an account?
            <a href="login.php">Log in</a>
        </p>
    </div>

    <p class="auth-footer">
        © <?= date("Y") ?> AssetFlow
    </p>

</div>

</body>
</html>
