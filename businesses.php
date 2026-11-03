
<?php
require_once "auth_check.php";
require_once "config/db.php";

$userId = (int) $_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$message = "";
$error = "";

// Register or delete a business
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Invalid request. Please refresh the page.";
    } else {
        $action = $_POST["action"] ?? "";

        if ($action === "add") {
            $name = trim($_POST["business_name"] ?? "");
            $industry = trim($_POST["industry"] ?? "");
            $description = trim($_POST["description"] ?? "");
            $ownership = $_POST["ownership_percentage"] ?? "";
            $value = $_POST["estimated_value"] ?? "";

            if ($name === "") {
                $error = "Business name is required.";
            } elseif (
                !is_numeric($ownership) ||
                $ownership <= 0 ||
                $ownership > 100
            ) {
                $error = "Ownership must be between 0 and 100.";
            } elseif (
                !is_numeric($value) ||
                $value < 0 ||
                $value > 9999999999999.99
            ) {
                $error = "Enter a valid estimated value.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Create the business asset first.
                    $assetStmt = $pdo->prepare(
                        "INSERT INTO assets
                        (user_id, asset_name, asset_type, description, estimated_value)
                        VALUES (?, ?, 'Business', ?, ?)"
                    );

                    $assetStmt->execute([
                        $userId,
                        $name,
                        $description,
                        $value
                    ]);

                    $assetId = $pdo->lastInsertId();

                    // Link the business to its asset.
                    $businessStmt = $pdo->prepare(
                        "INSERT INTO businesses
                        (user_id, asset_id, business_name, industry,
                         description, ownership_percentage)
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $businessStmt->execute([
                        $userId,
                        $assetId,
                        $name,
                        $industry,
                        $description,
                        $ownership
                    ]);

                    $pdo->commit();

                    header("Location: businesses.php?added=1");
                    exit;

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log($e->getMessage());
                    $error = "Could not register the business. Please try again.";
                }
            }
        }

        if ($action === "delete") {
            $businessId = filter_input(
                INPUT_POST,
                "business_id",
                FILTER_VALIDATE_INT
            );

            if (!$businessId || $businessId <= 0) {
                $error = "Invalid business.";
            } else {
                try {
                    // Deleting the asset also deletes its linked business.
                    $deleteStmt = $pdo->prepare(
                        "DELETE FROM assets
                         WHERE id = (
                             SELECT asset_id FROM (
                                 SELECT asset_id
                                 FROM businesses
                                 WHERE id = ? AND user_id = ?
                             ) AS owned_business
                         )
                         AND user_id = ?"
                    );

                    $deleteStmt->execute([
                        $businessId,
                        $userId,
                        $userId
                    ]);

                    if ($deleteStmt->rowCount() > 0) {
                        header("Location: businesses.php?deleted=1");
                        exit;
                    }

                    $error = "Business not found.";

                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = "Could not delete the business.";
                }
            }
        }
    }
}

if (isset($_GET["added"])) {
    $message = "Business registered successfully.";
}

if (isset($_GET["deleted"])) {
    $message = "Business deleted successfully.";
}

// Fetch this user's businesses and linked asset values.
$stmt = $pdo->prepare(
    "SELECT
        b.id,
        b.business_name,
        b.industry,
        b.description,
        b.ownership_percentage,
        b.status,
        a.estimated_value
     FROM businesses b
     INNER JOIN assets a ON b.asset_id = a.id
     WHERE b.user_id = ? AND a.user_id = ?
     ORDER BY b.created_at DESC"
);

$stmt->execute([$userId, $userId]);
$businesses = $stmt->fetchAll();

// Calculate the combined value of the user's business assets.
$totalBusinessValue = 0;

foreach ($businesses as $business) {
    $totalBusinessValue += (float) $business["estimated_value"];
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Businesses | AssetFlow</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/businesses.css">
</head>
<body>

<div class="business-page">

    <header class="business-header">
        <div>
            <h1>My Businesses</h1>
            <p>Manage your businesses and track their estimated value.</p>
        </div>

        <a href="dashboard.php" class="back-link">Back to Dashboard</a>
    </header>

    <?php if ($message): ?>
        <div class="success-message">
            <?= escape($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <?= escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="business-summary">
        <div class="business-card">
            <h3>Total Businesses</h3>
            <p><?= count($businesses) ?></p>
        </div>

        <div class="business-card">
            <h3>Total Business Asset Value</h3>
            <p><?= number_format($totalBusinessValue, 2) ?> RWF</p>
        </div>
    </section>

    <section class="business-form-section">
        <h2>Register a Business</h2>

        <form method="POST" action="businesses.php">
            <input type="hidden" name="csrf_token"
                   value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="action" value="add">

            <div class="business-form-grid">

                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" name="business_name"
                           maxlength="150" required
                           placeholder="e.g. Tresor Electronics">
                </div>

                <div class="form-group">
                    <label>Industry</label>
                    <input type="text" name="industry"
                           maxlength="100"
                           placeholder="e.g. Technology">
                </div>

                <div class="form-group">
                    <label>Ownership Percentage (%)</label>
                    <input type="number" name="ownership_percentage"
                           min="0.01" max="100" step="0.01"
                           value="100" required>
                </div>

                <div class="form-group">
                    <label>Your Share's Estimated Value (RWF)</label>
                    <input type="number" name="estimated_value"
                           min="0" step="0.01" required
                           placeholder="e.g. 5000000">
                </div>

            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"
                          placeholder="Describe your business..."></textarea>
            </div>

            <button type="submit" class="add-business-btn">
                Register Business
            </button>
        </form>
    </section>

    <section class="business-list-section">
        <h2>Registered Businesses</h2>

        <?php if (empty($businesses)): ?>

            <p class="empty-message">
                You haven't registered any businesses yet.
            </p>

        <?php else: ?>

            <div class="business-table-wrapper">
                <table class="business-table">
                    <thead>
                        <tr>
                            <th>Business</th>
                            <th>Industry</th>
                            <th>Ownership</th>
                            <th>Your Share Value</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($businesses as $business): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= escape($business["business_name"]) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= escape($business["industry"] ?: "Not specified") ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float) $business["ownership_percentage"],
                                        2
                                    ) ?>%
                                </td>

                                <td>
                                    <?= number_format(
                                        (float) $business["estimated_value"],
                                        2
                                    ) ?> RWF
                                </td>

                                <td>
                                    <?= escape($business["status"]) ?>
                                </td>

                                <td>
                                    <form method="POST" action="businesses.php"
                                          onsubmit="return confirm('Delete this business and its linked asset?');">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= escape($csrfToken) ?>">

                                        <input type="hidden" name="action"
                                               value="delete">

                                        <input type="hidden" name="business_id"
                                               value="<?= (int) $business["id"] ?>">

                                        <button type="submit" class="delete-btn">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

</div>

</body>
</html>
