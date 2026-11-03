
<?php
require_once "auth_check.php";
require_once "config/db.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";

$allowedTypes = [
    "Property",
    "Land",
    "Vehicle",
    "Equipment",
    "Business",
    "Investment",
    "Other"
];

// Add or delete an asset.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        $error = "Invalid request. Please refresh the page.";
    } elseif (($_POST["action"] ?? "") === "add") {

        $name = trim($_POST["asset_name"] ?? "");
        $type = $_POST["asset_type"] ?? "";
        $description = trim($_POST["description"] ?? "");
        $value = $_POST["estimated_value"] ?? "";
        $purchaseDate = $_POST["purchase_date"] ?? "";

        if ($name === "" || !in_array($type, $allowedTypes, true)) {
            $error = "Please enter a valid asset name and type.";
        } elseif (!is_numeric($value) || (float)$value < 0) {
            $error = "Enter a valid asset value.";
        } elseif (
            $purchaseDate !== "" &&
            !DateTime::createFromFormat("Y-m-d", $purchaseDate)
        ) {
            $error = "Please enter a valid purchase date.";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO assets
                (user_id, asset_name, asset_type, description,
                 estimated_value, purchase_date)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $_SESSION["user_id"],
                $name,
                $type,
                $description,
                $value,
                $purchaseDate ?: null
            ]);

            header("Location: assets.php?added=1");
            exit;
        }

    } elseif (($_POST["action"] ?? "") === "delete") {

        $assetId = filter_var(
            $_POST["asset_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        if ($assetId) {
            $stmt = $pdo->prepare(
                "DELETE FROM assets WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$assetId, $_SESSION["user_id"]]);

            header("Location: assets.php?deleted=1");
            exit;
        }

        $error = "Invalid asset selected.";
    }
}

// Retrieve only this user's assets.
$stmt = $pdo->prepare(
    "SELECT * FROM assets
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->execute([$_SESSION["user_id"]]);
$assets = $stmt->fetchAll();

$totalAssets = 0;

foreach ($assets as $asset) {
    $totalAssets += (float)$asset["estimated_value"];
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assets | AssetFlow</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/assets-page.css">
</head>
<body>

<div class="dashboard-layout">

    <aside class="sidebar">
        <a href="dashboard.php" class="logo">
            <span class="logo-icon">A</span>
            AssetFlow
        </a>

        <p class="menu-label">WORKSPACE</p>

        <nav class="sidebar-nav">
            <a href="dashboard.php">▦ Dashboard</a>
            <a href="assets.php" class="active">⌂ My Assets</a>
            <a href="#" class="coming-soon">▣ My Businesses</a>
            <a href="#" class="coming-soon">♙ Workers</a>
            <a href="#" class="coming-soon">↗ Transactions</a>
            <a href="#" class="coming-soon">▤ Debts</a>
            <a href="#" class="coming-soon">▥ Reports</a>
        </nav>

        <div class="sidebar-bottom">
            <a href="#" class="coming-soon">⚙ Settings</a>
            <a href="logout.php" class="logout-link">↪ Log out</a>
        </div>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h2>My Assets</h2>
                <p>Manage everything you own.</p>
            </div>

            <div class="user-profile">
                <div class="avatar">
                    <?= e(strtoupper(substr($_SESSION["user_name"], 0, 1))) ?>
                </div>
                <div>
                    <strong><?= e($_SESSION["user_name"]) ?></strong>
                    <small>Account owner</small>
                </div>
            </div>
        </header>

        <section class="asset-heading">
            <div>
                <h1>Your assets</h1>
                <p>Keep track of your properties, businesses and investments.</p>
            </div>
        </section>

        <?php if ($error): ?>
            <div class="asset-message error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET["added"])): ?>
            <div class="asset-message success">Asset added successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET["deleted"])): ?>
            <div class="asset-message success">Asset deleted successfully.</div>
        <?php endif; ?>

        <section class="asset-total">
            <div>
                <span>Total estimated asset value</span>
                <h2>RWF <?= number_format($totalAssets, 2) ?></h2>
                <small><?= count($assets) ?> registered asset(s)</small>
            </div>
            <div class="total-icon">⌂</div>
        </section>

        <section class="asset-content">

            <div class="asset-form-card">
                <h3>Add a new asset</h3>
                <p>Enter the details of something you own.</p>

                <form method="POST" action="assets.php">
                    <input type="hidden" name="csrf_token"
                           value="<?= e($_SESSION["csrf_token"]) ?>">
                    <input type="hidden" name="action" value="add">

                    <label for="asset_name">Asset name</label>
                    <input type="text" id="asset_name" name="asset_name"
                           placeholder="e.g. Family house or My shop"
                           maxlength="150" required>

                    <label for="asset_type">Asset type</label>
                    <select id="asset_type" name="asset_type" required>
                        <option value="">Select asset type</option>
                        <?php foreach ($allowedTypes as $type): ?>
                            <option value="<?= e($type) ?>">
                                <?= e($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="estimated_value">Estimated value (RWF)</label>
                    <input type="number" id="estimated_value"
                           name="estimated_value" min="0" step="0.01"
                           placeholder="e.g. 15000000" required>

                    <label for="purchase_date">Purchase date (optional)</label>
                    <input type="date" id="purchase_date" name="purchase_date">

                    <label for="description">Description (optional)</label>
                    <textarea id="description" name="description"
                              rows="3" placeholder="Add more details..."></textarea>

                    <button type="submit" class="asset-submit">
                        + Add Asset
                    </button>
                </form>
            </div>

            <div class="asset-list-card">
                <div class="asset-list-heading">
                    <div>
                        <h3>Registered assets</h3>
                        <p>Your personal asset collection.</p>
                    </div>
                </div>

                <?php if (count($assets) > 0): ?>

                    <div class="asset-table-wrapper">
                        <table class="asset-table">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Type</th>
                                    <th>Estimated value</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($assets as $asset): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($asset["asset_name"]) ?></strong>
                                            <small><?= e($asset["description"]) ?></small>
                                        </td>
                                        <td>
                                            <span class="asset-type">
                                                <?= e($asset["asset_type"]) ?>
                                            </span>
                                        </td>
                                        <td>
                                            RWF <?= number_format(
                                                (float)$asset["estimated_value"], 2
                                            ) ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="assets.php"
                                                  onsubmit="return confirm('Delete this asset?');">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= e($_SESSION["csrf_token"]) ?>">
                                                <input type="hidden" name="action"
                                                       value="delete">
                                                <input type="hidden" name="asset_id"
                                                       value="<?= (int)$asset["id"] ?>">
                                                <button type="submit" class="delete-button">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>

                    <div class="empty-assets">
                        <div>⌂</div>
                        <h3>No assets yet</h3>
                        <p>Add your first asset using the form.</p>
                    </div>

                <?php endif; ?>
            </div>

        </section>

    </main>
</div>

</body>
</html>
