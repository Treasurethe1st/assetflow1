
<?php
require_once "auth_check.php";
require_once "config/db.php";

$userId = (int) $_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$error = "";

// Get businesses belonging to the logged-in user.
$businessStmt = $pdo->prepare(
    "SELECT id, business_name
     FROM businesses
     WHERE user_id = ?
     ORDER BY business_name"
);
$businessStmt->execute([$userId]);
$businesses = $businessStmt->fetchAll();

$businessIds = array_map(
    fn($business) => (int) $business["id"],
    $businesses
);

// Select a business from the URL.
$selectedBusinessId = filter_input(
    INPUT_GET,
    "business_id",
    FILTER_VALIDATE_INT
);

if (!$selectedBusinessId || !in_array($selectedBusinessId, $businessIds, true)) {
    $selectedBusinessId = $businessIds[0] ?? 0;
}

// Handle form submissions.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Invalid request. Please refresh the page.";
    } else {
        $action = $_POST["action"] ?? "";

        if ($action === "add") {
            $businessId = filter_input(
                INPUT_POST,
                "business_id",
                FILTER_VALIDATE_INT
            );

            $type = $_POST["transaction_type"] ?? "";
            $amount = $_POST["amount"] ?? "";
            $description = trim($_POST["description"] ?? "");
            $date = $_POST["transaction_date"] ?? "";

            $validDate = false;

            if (is_string($date)) {
                $dateObject = DateTime::createFromFormat("!Y-m-d", $date);
                $validDate = $dateObject &&
                    $dateObject->format("Y-m-d") === $date;
            }

            if (!$businessId || !in_array($businessId, $businessIds, true)) {
                $error = "Please select a valid business.";
            } elseif (!in_array($type, ["Income", "Expense"], true)) {
                $error = "Please select a valid transaction type.";
            } elseif (
                !is_numeric($amount) ||
                (float) $amount <= 0 ||
                (float) $amount > 9999999999999.99
            ) {
                $error = "Enter a valid transaction amount.";
            } elseif (!$validDate) {
                $error = "Please enter a valid transaction date.";
            } else {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO business_transactions
                    (user_id, business_id, transaction_type,
                     amount, description, transaction_date)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $insertStmt->execute([
                    $userId,
                    $businessId,
                    $type,
                    $amount,
                    $description,
                    $date
                ]);

                header("Location: business_finances.php?business_id=" . $businessId . "&saved=1");
                exit;
            }

            $selectedBusinessId = $businessId ?: $selectedBusinessId;
        }

        if ($action === "delete") {
            $transactionId = filter_input(
                INPUT_POST,
                "transaction_id",
                FILTER_VALIDATE_INT
            );

            if (!$transactionId || $transactionId <= 0) {
                $error = "Invalid transaction.";
            } else {
                $deleteStmt = $pdo->prepare(
                    "DELETE FROM business_transactions
                     WHERE id = ? AND user_id = ?"
                );

                $deleteStmt->execute([$transactionId, $userId]);

                if ($deleteStmt->rowCount() > 0) {
                    header("Location: business_finances.php?business_id=" . $selectedBusinessId . "&deleted=1");
                    exit;
                }

                $error = "Transaction not found.";
            }
        }
    }
}

// Calculate financial summary.
$income = 0;
$expenses = 0;
$transactions = [];

if ($selectedBusinessId) {
    $summaryStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE
                WHEN transaction_type = 'Income' THEN amount
                ELSE 0
            END), 0) AS total_income,

            COALESCE(SUM(CASE
                WHEN transaction_type = 'Expense' THEN amount
                ELSE 0
            END), 0) AS total_expenses

         FROM business_transactions
         WHERE user_id = ? AND business_id = ?"
    );

    $summaryStmt->execute([$userId, $selectedBusinessId]);
    $summary = $summaryStmt->fetch();

    $income = (float) $summary["total_income"];
    $expenses = (float) $summary["total_expenses"];

    // Get transaction history.
    $transactionStmt = $pdo->prepare(
        "SELECT *
         FROM business_transactions
         WHERE user_id = ? AND business_id = ?
         ORDER BY transaction_date DESC, id DESC"
    );

    $transactionStmt->execute([$userId, $selectedBusinessId]);
    $transactions = $transactionStmt->fetchAll();
}

$profit = $income - $expenses;

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$successMessage = "";

if (isset($_GET["saved"])) {
    $successMessage = "Transaction saved successfully.";
} elseif (isset($_GET["deleted"])) {
    $successMessage = "Transaction deleted successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Finances | AssetFlow</title>

    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/business-finances.css">
</head>

<body>

<div class="finance-page">

    <header class="finance-header">
        <div>
            <h1>Business Finances</h1>
            <p>Track income, expenses and business profitability.</p>
        </div>

        <a href="businesses.php" class="back-link">My Businesses</a>
    </header>

    <?php if ($successMessage): ?>
        <div class="success-message">
            <?= h($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($businesses)): ?>

        <section class="finance-section">
            <h2>No businesses found</h2>
            <p>Register a business before recording financial transactions.</p>
            <a href="businesses.php" class="primary-btn">Register a Business</a>
        </section>

    <?php else: ?>

        <section class="finance-section">
            <form method="GET" action="business_finances.php">
                <label for="business_id">Select Business</label>

                <select name="business_id" id="business_id"
                        onchange="this.form.submit()">

                    <?php foreach ($businesses as $business): ?>
                        <option
                            value="<?= (int) $business["id"] ?>"
                            <?= $selectedBusinessId === (int) $business["id"] ? "selected" : "" ?>>
                            <?= h($business["business_name"]) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </form>
        </section>

        <section class="finance-summary">

            <div class="summary-card">
                <h3>Total Income</h3>
                <p class="income-value">
                    <?= number_format($income, 2) ?> RWF
                </p>
            </div>

            <div class="summary-card">
                <h3>Total Expenses</h3>
                <p class="expense-value">
                    <?= number_format($expenses, 2) ?> RWF
                </p>
            </div>

            <div class="summary-card">
                <h3>Net Profit / Loss</h3>
                <p class="<?= $profit >= 0 ? "income-value" : "expense-value" ?>">
                    <?= number_format($profit, 2) ?> RWF
                </p>
            </div>

        </section>

        <section class="finance-section">
            <h2>Record a Transaction</h2>

            <form method="POST" action="business_finances.php?business_id=<?= $selectedBusinessId ?>">

                <input type="hidden" name="csrf_token"
                       value="<?= h($csrfToken) ?>">

                <input type="hidden" name="action" value="add">

                <input type="hidden" name="business_id"
                       value="<?= $selectedBusinessId ?>">

                <div class="finance-form-grid">

                    <div class="form-group">
                        <label>Transaction Type</label>

                        <select name="transaction_type" required>
                            <option value="">Choose type</option>
                            <option value="Income">Income</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Amount (RWF)</label>

                        <input type="number" name="amount"
                               min="0.01" max="9999999999999.99"
                               step="0.01" required
                               placeholder="Enter amount">
                    </div>

                    <div class="form-group">
                        <label>Transaction Date</label>

                        <input type="date" name="transaction_date"
                               value="<?= date("Y-m-d") ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>

                        <input type="text" name="description"
                               maxlength="255"
                               placeholder="e.g. Product sales">
                    </div>

                </div>

                <button type="submit" class="primary-btn">
                    Save Transaction
                </button>

            </form>
        </section>

        <section class="finance-section">
            <h2>Transaction History</h2>

            <?php if (empty($transactions)): ?>

                <p class="empty-message">
                    No transactions recorded for this business yet.
                </p>

            <?php else: ?>

                <div class="table-wrapper">
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td>
                                        <?= h($transaction["transaction_date"]) ?>
                                    </td>

                                    <td>
                                        <?= h($transaction["description"] ?: "No description") ?>
                                    </td>

                                    <td>
                                        <?= h($transaction["transaction_type"]) ?>
                                    </td>

                                    <td class="<?= $transaction["transaction_type"] === "Income" ? "income-value" : "expense-value" ?>">
                                        <?= number_format((float) $transaction["amount"], 2) ?> RWF
                                    </td>

                                    <td>
                                        <form method="POST"
                                              action="business_finances.php?business_id=<?= $selectedBusinessId ?>"
                                              onsubmit="return confirm('Delete this transaction?');">

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= h($csrfToken) ?>">

                                            <input type="hidden" name="action"
                                                   value="delete">

                                            <input type="hidden" name="transaction_id"
                                                   value="<?= (int) $transaction["id"] ?>">

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

    <?php endif; ?>

</div>

</body>
</html>
