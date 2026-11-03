<?php
require_once "auth_check.php";
require_once "config/db.php";

$userId = (int) $_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$error = "";

// Fetch active worker assignments.
$assignmentStmt = $pdo->prepare(
    "SELECT
        wa.id,
        wa.monthly_salary,
        wa.job_title,
        w.full_name,
        b.business_name
     FROM worker_assignments wa
     INNER JOIN workers w ON w.id = wa.worker_id
     INNER JOIN businesses b ON b.id = wa.business_id
     WHERE wa.user_id = ?
       AND w.user_id = ?
       AND b.user_id = ?
       AND wa.status = 'Active'
     ORDER BY b.business_name, w.full_name"
);

$assignmentStmt->execute([$userId, $userId, $userId]);
$assignments = $assignmentStmt->fetchAll();

// Handle submitted forms.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $action = $_POST["action"] ?? "";

        // Generate a monthly salary record.
        if ($action === "generate_payroll") {

            $assignmentId = filter_input(
                INPUT_POST,
                "assignment_id",
                FILTER_VALIDATE_INT
            );

            $month = trim($_POST["payroll_month"] ?? "");

            $monthDate = DateTime::createFromFormat(
                "!Y-m-d",
                $month . "-01"
            );

            $validMonth = $monthDate &&
                $monthDate->format("Y-m") === $month;

            if (!$assignmentId || !$validMonth) {
                $error = "Select a valid worker assignment and month.";
            } else {

                // Get the assignment's salary and business.
                $checkStmt = $pdo->prepare(
                    "SELECT wa.id, wa.monthly_salary, wa.business_id
                     FROM worker_assignments wa
                     INNER JOIN workers w ON w.id = wa.worker_id
                     INNER JOIN businesses b ON b.id = wa.business_id
                     WHERE wa.id = ?
                       AND wa.user_id = ?
                       AND w.user_id = ?
                       AND b.user_id = ?
                       AND wa.status = 'Active'"
                );

                $checkStmt->execute([
                    $assignmentId,
                    $userId,
                    $userId,
                    $userId
                ]);

                $assignment = $checkStmt->fetch();

                if (!$assignment) {
                    $error = "Worker assignment not found or inactive.";
                } elseif ((float) $assignment["monthly_salary"] <= 0) {
                    $error = "This worker has no valid monthly salary.";
                } else {
                    try {
                        $insertStmt = $pdo->prepare(
                            "INSERT INTO payroll
                            (user_id, assignment_id, business_id,
                             payroll_month, amount_due)
                            VALUES (?, ?, ?, ?, ?)"
                        );

                        $insertStmt->execute([
                            $userId,
                            $assignmentId,
                            $assignment["business_id"],
                            $month . "-01",
                            $assignment["monthly_salary"]
                        ]);

                        header("Location: payroll.php?generated=1");
                        exit;

                    } catch (PDOException $e) {
                        if ($e->getCode() === "23000") {
                            $error = "Payroll for this worker and month already exists.";
                        } else {
                            error_log($e->getMessage());
                            $error = "Could not generate payroll.";
                        }
                    }
                }
            }
        }

        // Record a salary payment.
        elseif ($action === "record_payment") {

            $payrollId = filter_input(
                INPUT_POST,
                "payroll_id",
                FILTER_VALIDATE_INT
            );

            $amount = $_POST["amount"] ?? "";
            $paymentDate = trim($_POST["payment_date"] ?? "");
            $paymentMethod = trim($_POST["payment_method"] ?? "");
            $notes = trim($_POST["notes"] ?? "");

            $dateObject = DateTime::createFromFormat(
                "!Y-m-d",
                $paymentDate
            );

            $validDate = $dateObject &&
                $dateObject->format("Y-m-d") === $paymentDate;

            if (!$payrollId) {
                $error = "Invalid payroll record.";
            } elseif (
                !is_numeric($amount) ||
                (float) $amount <= 0 ||
                (float) $amount > 9999999999999.99
            ) {
                $error = "Enter a valid payment amount.";
            } elseif (!$validDate) {
                $error = "Enter a valid payment date.";
            } elseif (strlen($paymentMethod) > 50 || strlen($notes) > 255) {
                $error = "Payment method or notes are too long.";
            } else {

                try {
                    $pdo->beginTransaction();

                    // Lock the payroll record while calculating the balance.
                    $payrollStmt = $pdo->prepare(
                        "SELECT id, amount_due
                         FROM payroll
                         WHERE id = ? AND user_id = ?
                         FOR UPDATE"
                    );

                    $payrollStmt->execute([$payrollId, $userId]);
                    $payrollRecord = $payrollStmt->fetch();

                    if (!$payrollRecord) {
                        throw new RuntimeException("Payroll record not found.");
                    }

                    $paidStmt = $pdo->prepare(
                        "SELECT COALESCE(SUM(amount), 0)
                         FROM payroll_payments
                         WHERE payroll_id = ? AND user_id = ?"
                    );

                    $paidStmt->execute([$payrollId, $userId]);
                    $alreadyPaid = (float) $paidStmt->fetchColumn();

                    $amountDue = (float) $payrollRecord["amount_due"];
                    $remaining = round($amountDue - $alreadyPaid, 2);
                    $paymentAmount = round((float) $amount, 2);

                    if ($remaining <= 0) {
                        throw new RuntimeException(
                            "This salary has already been fully paid."
                        );
                    }

                    if ($paymentAmount > $remaining) {
                        throw new RuntimeException(
                            "Payment exceeds the outstanding balance of " .
                            number_format($remaining, 2) . " RWF."
                        );
                    }

                    $paymentStmt = $pdo->prepare(
                        "INSERT INTO payroll_payments
                        (user_id, payroll_id, amount, payment_date,
                         payment_method, notes)
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $paymentStmt->execute([
                        $userId,
                        $payrollId,
                        $paymentAmount,
                        $paymentDate,
                        $paymentMethod ?: null,
                        $notes ?: null
                    ]);

                    $pdo->commit();

                    header("Location: payroll.php?paid=1");
                    exit;

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    if ($e instanceof RuntimeException) {
                        $error = $e->getMessage();
                    } else {
                        error_log($e->getMessage());
                        $error = "Could not record the salary payment.";
                    }
                }
            }
        }
    }
}

// Fetch payroll records and calculate payment balances.
$payrollStmt = $pdo->prepare(
    "SELECT
        p.id,
        p.payroll_month,
        p.amount_due,
        w.full_name,
        wa.job_title,
        b.business_name,
        COALESCE(SUM(pp.amount), 0) AS amount_paid
     FROM payroll p
     INNER JOIN worker_assignments wa ON wa.id = p.assignment_id
     INNER JOIN workers w ON w.id = wa.worker_id
     INNER JOIN businesses b ON b.id = p.business_id
     LEFT JOIN payroll_payments pp
        ON pp.payroll_id = p.id
       AND pp.user_id = p.user_id
     WHERE p.user_id = ?
       AND wa.user_id = ?
       AND w.user_id = ?
       AND b.user_id = ?
     GROUP BY
        p.id, p.payroll_month, p.amount_due,
        w.full_name, wa.job_title, b.business_name
     ORDER BY p.payroll_month DESC, w.full_name"
);

$payrollStmt->execute([
    $userId,
    $userId,
    $userId,
    $userId
]);

$payrollRecords = $payrollStmt->fetchAll();

// Calculate summary totals.
$totalDue = 0;
$totalPaid = 0;
$totalOutstanding = 0;

foreach ($payrollRecords as $record) {
    $due = (float) $record["amount_due"];
    $paid = (float) $record["amount_paid"];
    $outstanding = max(0, $due - $paid);

    $totalDue += $due;
    $totalPaid += $paid;
    $totalOutstanding += $outstanding;
}

function payrollEscape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

$successMessage = "";

if (isset($_GET["generated"])) {
    $successMessage = "Monthly payroll generated successfully.";
} elseif (isset($_GET["paid"])) {
    $successMessage = "Salary payment recorded successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Salary & Payroll | AssetFlow</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/workers.css">
</head>

<body>

<div class="workers-page">

    <header class="workers-header">
        <div>
            <h1><i class="fas fa-money-check-dollar"></i> Salary & Payroll</h1>
            <p>Generate monthly salaries and record worker payments.</p>
        </div>

        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </header>

    <?php if ($successMessage): ?>
        <div class="success-message">
            <?= payrollEscape($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <?= payrollEscape($error) ?>
        </div>
    <?php endif; ?>

    <section class="workers-summary">

        <article class="worker-summary-card">
            <i class="fas fa-file-invoice-dollar"></i>
            <div>
                <span>Total Salary Due</span>
                <h2><?= number_format($totalDue, 2) ?> RWF</h2>
            </div>
        </article>

        <article class="worker-summary-card">
            <i class="fas fa-circle-check"></i>
            <div>
                <span>Total Paid</span>
                <h2><?= number_format($totalPaid, 2) ?> RWF</h2>
            </div>
        </article>

        <article class="worker-summary-card">
            <i class="fas fa-clock"></i>
            <div>
                <span>Outstanding</span>
                <h2><?= number_format($totalOutstanding, 2) ?> RWF</h2>
            </div>
        </article>

    </section>

    <section class="worker-section">

        <h2><i class="fas fa-calendar-plus"></i> Generate Monthly Payroll</h2>

        <?php if (empty($assignments)): ?>

            <p class="empty-message">
                You need an active worker assignment before generating payroll.
                <a href="workers.php">Manage workers</a>.
            </p>

        <?php else: ?>

            <form method="POST" action="payroll.php">

                <input type="hidden" name="csrf_token"
                       value="<?= payrollEscape($csrfToken) ?>">

                <input type="hidden" name="action"
                       value="generate_payroll">

                <div class="form-group">
                    <label>Worker and Business</label>

                    <select name="assignment_id" required>
                        <option value="">Choose an assignment</option>

                        <?php foreach ($assignments as $assignment): ?>
                            <option value="<?= (int) $assignment["id"] ?>">
                                <?= payrollEscape(
                                    $assignment["full_name"] .
                                    " - " . $assignment["business_name"] .
                                    " - " . number_format(
                                        (float) $assignment["monthly_salary"],
                                        2
                                    ) . " RWF"
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Payroll Month</label>
                    <input type="month" name="payroll_month"
                           value="<?= date("Y-m") ?>" required>
                </div>

                <button type="submit" class="primary-btn">
                    <i class="fas fa-plus"></i> Generate Payroll
                </button>

            </form>

        <?php endif; ?>

    </section>

    <section class="worker-section">

        <h2><i class="fas fa-list"></i> Payroll Records</h2>

        <?php if (empty($payrollRecords)): ?>

            <p class="empty-message">
                No payroll records yet. Generate your first monthly payroll above.
            </p>

        <?php else: ?>

            <div class="table-wrapper">

                <table class="workers-table">

                    <thead>
                        <tr>
                            <th>Worker</th>
                            <th>Business</th>
                            <th>Month</th>
                            <th>Salary Due</th>
                            <th>Paid</th>
                            <th>Outstanding</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($payrollRecords as $record): ?>

                        <?php
                            $due = (float) $record["amount_due"];
                            $paid = (float) $record["amount_paid"];
                            $remaining = max(0, round($due - $paid, 2));

                            if ($remaining <= 0) {
                                $status = "Paid";
                            } elseif ($paid > 0) {
                                $status = "Partially Paid";
                            } else {
                                $status = "Pending";
                            }
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= payrollEscape($record["full_name"]) ?>
                                </strong>
                                <br>
                                <small><?= payrollEscape($record["job_title"]) ?></small>
                            </td>

                            <td>
                                <?= payrollEscape($record["business_name"]) ?>
                            </td>

                            <td>
                                <?= date(
                                    "F Y",
                                    strtotime($record["payroll_month"])
                                ) ?>
                            </td>

                            <td>
                                <?= number_format($due, 2) ?> RWF
                            </td>

                            <td>
                                <?= number_format($paid, 2) ?> RWF
                            </td>

                            <td>
                                <?= number_format($remaining, 2) ?> RWF
                            </td>

                            <td>
                                <?php if ($status === "Paid"): ?>
                                    <span class="status-paid">Paid</span>
                                <?php elseif ($status === "Partially Paid"): ?>
                                    <span class="status-partial">Partially Paid</span>
                                <?php else: ?>
                                    <span class="status-pending">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($remaining > 0): ?>

                                    <form method="POST" action="payroll.php"
                                          class="payment-form">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= payrollEscape($csrfToken) ?>">

                                        <input type="hidden" name="action"
                                               value="record_payment">

                                        <input type="hidden" name="payroll_id"
                                               value="<?= (int) $record["id"] ?>">

                                        <input type="number" name="amount"
                                               min="0.01"
                                               max="<?= payrollEscape($remaining) ?>"
                                               step="0.01"
                                               placeholder="Amount"
                                               required>

                                        <input type="date" name="payment_date"
                                               value="<?= date("Y-m-d") ?>"
                                               required>

                                        <select name="payment_method">
                                            <option value="">Payment method</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Mobile Money">Mobile Money</option>
                                            <option value="Other">Other</option>
                                        </select>

                                        <input type="text" name="notes"
                                               maxlength="255"
                                               placeholder="Notes (optional)">

                                        <button type="submit" class="primary-btn">
                                            Record Payment
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span>Completed</span>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <p class="empty-message">
        <i class="fas fa-info-circle"></i>
        Salary payments are recorded here. We'll connect them to business
        expense reports in the next step.
    </p>

</div>

</body>
</html>