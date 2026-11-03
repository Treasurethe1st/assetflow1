
<?php
require_once "auth_check.php";
require_once "config/db.php";

$userId = (int) $_SESSION["user_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$error = "";

// Fetch this user's businesses.
$businessStmt = $pdo->prepare(
    "SELECT id, business_name
     FROM businesses
     WHERE user_id = ?
     ORDER BY business_name"
);
$businessStmt->execute([$userId]);
$businesses = $businessStmt->fetchAll();

// Fetch this user's workers, including contact information.
$workerStmt = $pdo->prepare(
    "SELECT id, full_name, phone, email, address
     FROM workers
     WHERE user_id = ?
     ORDER BY full_name"
);
$workerStmt->execute([$userId]);
$workers = $workerStmt->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $action = $_POST["action"] ?? "";

        // Register a worker.
        if ($action === "add_worker") {

            $name = trim($_POST["full_name"] ?? "");
            $phone = trim($_POST["phone"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $address = trim($_POST["address"] ?? "");

            if ($name === "") {
                $error = "Worker's full name is required.";
            } elseif (strlen($name) > 150) {
                $error = "Worker's name is too long.";
            } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Enter a valid email address.";
            } else {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO workers
                    (user_id, full_name, phone, email, address)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $insertStmt->execute([
                    $userId,
                    $name,
                    $phone ?: null,
                    $email ?: null,
                    $address ?: null
                ]);

                header("Location: workers.php?added=1");
                exit;
            }
        }

        // Assign a worker to a business.
        elseif ($action === "assign_worker") {

            $workerId = filter_input(
                INPUT_POST,
                "worker_id",
                FILTER_VALIDATE_INT
            );

            $businessId = filter_input(
                INPUT_POST,
                "business_id",
                FILTER_VALIDATE_INT
            );

            $jobTitle = trim($_POST["job_title"] ?? "");
            $salary = $_POST["monthly_salary"] ?? "";
            $startDate = $_POST["start_date"] ?? "";

            $dateObject = is_string($startDate)
                ? DateTime::createFromFormat("!Y-m-d", $startDate)
                : false;

            $validDate = $dateObject &&
                $dateObject->format("Y-m-d") === $startDate;

            if (!$workerId || !$businessId) {
                $error = "Select a valid worker and business.";
            } elseif ($jobTitle === "" || strlen($jobTitle) > 100) {
                $error = "Enter a valid job title.";
            } elseif (
                !is_numeric($salary) ||
                (float) $salary < 0 ||
                (float) $salary > 9999999999999.99
            ) {
                $error = "Enter a valid monthly salary.";
            } elseif (!$validDate) {
                $error = "Enter a valid start date.";
            } else {

                // Verify that both records belong to this user.
                $ownershipStmt = $pdo->prepare(
                    "SELECT w.id, b.id
                     FROM workers w
                     INNER JOIN businesses b ON b.user_id = w.user_id
                     WHERE w.id = ?
                       AND b.id = ?
                       AND w.user_id = ?"
                );

                $ownershipStmt->execute([
                    $workerId,
                    $businessId,
                    $userId
                ]);

                if (!$ownershipStmt->fetch()) {
                    $error = "Worker or business not found.";
                } else {
                    try {
                        $assignStmt = $pdo->prepare(
                            "INSERT INTO worker_assignments
                            (user_id, worker_id, business_id,
                             job_title, monthly_salary, start_date)
                            VALUES (?, ?, ?, ?, ?, ?)"
                        );

                        $assignStmt->execute([
                            $userId,
                            $workerId,
                            $businessId,
                            $jobTitle,
                            $salary,
                            $startDate
                        ]);

                        header("Location: workers.php?assigned=1");
                        exit;

                    } catch (PDOException $e) {
                        if ($e->getCode() === "23000") {
                            $error = "This worker is already assigned to that business.";
                        } else {
                            error_log($e->getMessage());
                            $error = "Could not assign the worker.";
                        }
                    }
                }
            }
        }

        // Delete a worker and their assignments.
        elseif ($action === "delete_worker") {

            $workerId = filter_input(
                INPUT_POST,
                "worker_id",
                FILTER_VALIDATE_INT
            );

            if (!$workerId || $workerId <= 0) {
                $error = "Invalid worker.";
            } else {
                $deleteStmt = $pdo->prepare(
                    "DELETE FROM workers
                     WHERE id = ? AND user_id = ?"
                );

                $deleteStmt->execute([$workerId, $userId]);

                if ($deleteStmt->rowCount() > 0) {
                    header("Location: workers.php?deleted=1");
                    exit;
                }

                $error = "Worker not found.";
            }
        }

        // Remove one business assignment.
        elseif ($action === "delete_assignment") {

            $assignmentId = filter_input(
                INPUT_POST,
                "assignment_id",
                FILTER_VALIDATE_INT
            );

            if (!$assignmentId || $assignmentId <= 0) {
                $error = "Invalid assignment.";
            } else {
                $deleteStmt = $pdo->prepare(
                    "DELETE FROM worker_assignments
                     WHERE id = ? AND user_id = ?"
                );

                $deleteStmt->execute([$assignmentId, $userId]);

                if ($deleteStmt->rowCount() > 0) {
                    header("Location: workers.php?assignment_deleted=1");
                    exit;
                }

                $error = "Assignment not found.";
            }
        }

        else {
            $error = "Unknown request.";
        }
    }
}

// Refresh workers after any operation.
$workerStmt->execute([$userId]);
$workers = $workerStmt->fetchAll();

// Get all assignments for this user's workers and businesses.
$assignmentStmt = $pdo->prepare(
    "SELECT
        wa.id,
        wa.worker_id,
        wa.job_title,
        wa.monthly_salary,
        wa.start_date,
        wa.status,
        w.full_name,
        b.business_name
     FROM worker_assignments wa
     INNER JOIN workers w ON wa.worker_id = w.id
     INNER JOIN businesses b ON wa.business_id = b.id
     WHERE wa.user_id = ?
       AND w.user_id = ?
       AND b.user_id = ?
     ORDER BY w.full_name, b.business_name"
);

$assignmentStmt->execute([$userId, $userId, $userId]);
$assignments = $assignmentStmt->fetchAll();

// Calculate the total monthly salary commitment.
$totalMonthlySalary = 0;

foreach ($assignments as $assignment) {
    if ($assignment["status"] === "Active") {
        $totalMonthlySalary += (float) $assignment["monthly_salary"];
    }
}

function workerEscape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

$successMessage = "";

if (isset($_GET["added"])) {
    $successMessage = "Worker registered successfully.";
} elseif (isset($_GET["assigned"])) {
    $successMessage = "Worker assigned successfully.";
} elseif (isset($_GET["deleted"])) {
    $successMessage = "Worker deleted successfully.";
} elseif (isset($_GET["assignment_deleted"])) {
    $successMessage = "Worker assignment removed successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Workers | AssetFlow</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/workers.css">
</head>

<body>

<div class="workers-page">

    <header class="workers-header">
        <div>
            <h1><i class="fas fa-users"></i> Workers Management</h1>
            <p>Register employees and manage their business assignments.</p>
        </div>

        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </header>

    <?php if ($successMessage): ?>
        <div class="success-message">
            <?= workerEscape($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            <?= workerEscape($error) ?>
        </div>
    <?php endif; ?>

    <section class="workers-summary">

        <article class="worker-summary-card">
            <i class="fas fa-users"></i>
            <div>
                <span>Registered Workers</span>
                <h2><?= count($workers) ?></h2>
            </div>
        </article>

        <article class="worker-summary-card">
            <i class="fas fa-briefcase"></i>
            <div>
                <span>Business Assignments</span>
                <h2><?= count($assignments) ?></h2>
            </div>
        </article>

        <article class="worker-summary-card">
            <i class="fas fa-money-bill-wave"></i>
            <div>
                <span>Active Monthly Salaries</span>
                <h2><?= number_format($totalMonthlySalary, 2) ?> RWF</h2>
            </div>
        </article>

    </section>

    <div class="workers-grid">

        <!-- REGISTER WORKER -->
        <section class="worker-section">

            <h2><i class="fas fa-user-plus"></i> Register Worker</h2>

            <form method="POST" action="workers.php">

                <input type="hidden" name="csrf_token"
                       value="<?= workerEscape($csrfToken) ?>">

                <input type="hidden" name="action"
                       value="add_worker">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name"
                           maxlength="150" required
                           placeholder="Enter worker's full name">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone"
                           maxlength="30"
                           placeholder="+250...">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email"
                           maxlength="150"
                           placeholder="worker@example.com">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address"
                           maxlength="255"
                           placeholder="Worker's address">
                </div>

                <button type="submit" class="primary-btn">
                    <i class="fas fa-plus"></i> Register Worker
                </button>

            </form>

        </section>

        <!-- ASSIGN WORKER -->
        <section class="worker-section">

            <h2><i class="fas fa-user-tie"></i> Assign Worker</h2>

            <?php if (empty($workers) || empty($businesses)): ?>

                <p class="empty-message">
                    You need at least one registered worker and one business
                    before creating an assignment.
                </p>

            <?php else: ?>

                <form method="POST" action="workers.php">

                    <input type="hidden" name="csrf_token"
                           value="<?= workerEscape($csrfToken) ?>">

                    <input type="hidden" name="action"
                           value="assign_worker">

                    <div class="form-group">
                        <label>Select Worker</label>

                        <select name="worker_id" required>
                            <option value="">Choose worker</option>

                            <?php foreach ($workers as $worker): ?>
                                <option value="<?= (int) $worker["id"] ?>">
                                    <?= workerEscape($worker["full_name"]) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label>Select Business</label>

                        <select name="business_id" required>
                            <option value="">Choose business</option>

                            <?php foreach ($businesses as $business): ?>
                                <option value="<?= (int) $business["id"] ?>">
                                    <?= workerEscape($business["business_name"]) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label>Job Title</label>
                        <input type="text" name="job_title"
                               maxlength="100" required
                               placeholder="e.g. Sales Manager">
                    </div>

                    <div class="form-group">
                        <label>Monthly Salary (RWF)</label>
                        <input type="number" name="monthly_salary"
                               min="0" max="9999999999999.99"
                               step="0.01" required
                               placeholder="e.g. 150000">
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date"
                               value="<?= date("Y-m-d") ?>" required>
                    </div>

                    <button type="submit" class="primary-btn">
                        <i class="fas fa-link"></i> Assign Worker
                    </button>

                </form>

            <?php endif; ?>

        </section>

    </div>

    <!-- WORKERS LIST -->
    <section class="worker-section">

        <h2>Registered Workers</h2>

        <?php if (empty($workers)): ?>

            <p class="empty-message">
                No workers registered yet.
            </p>

        <?php else: ?>

            <div class="table-wrapper">

                <table class="workers-table">

                    <thead>
                        <tr>
                            <th>Worker</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($workers as $worker): ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= workerEscape($worker["full_name"]) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= workerEscape($worker["phone"] ?: "Not provided") ?>
                                </td>

                                <td>
                                    <?= workerEscape($worker["email"] ?: "Not provided") ?>
                                </td>

                                <td>
                                    <form method="POST" action="workers.php"
                                          onsubmit="return confirm('Delete this worker and all their business assignments?');">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= workerEscape($csrfToken) ?>">

                                        <input type="hidden" name="action"
                                               value="delete_worker">

                                        <input type="hidden" name="worker_id"
                                               value="<?= (int) $worker["id"] ?>">

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

    <!-- ASSIGNMENTS LIST -->
    <section class="worker-section">

        <h2>Business Assignments</h2>

        <?php if (empty($assignments)): ?>

            <p class="empty-message">
                No business assignments yet.
            </p>

        <?php else: ?>

            <div class="table-wrapper">

                <table class="workers-table">

                    <thead>
                        <tr>
                            <th>Worker</th>
                            <th>Business</th>
                            <th>Job Title</th>
                            <th>Monthly Salary</th>
                            <th>Start Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($assignments as $assignment): ?>

                            <tr>
                                <td>
                                    <?= workerEscape($assignment["full_name"]) ?>
                                </td>

                                <td>
                                    <?= workerEscape($assignment["business_name"]) ?>
                                </td>

                                <td>
                                    <?= workerEscape($assignment["job_title"]) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float) $assignment["monthly_salary"],
                                        2
                                    ) ?> RWF
                                </td>

                                <td>
                                    <?= workerEscape($assignment["start_date"]) ?>
                                </td>

                                <td>
                                    <form method="POST" action="workers.php"
                                          onsubmit="return confirm('Remove this business assignment?');">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= workerEscape($csrfToken) ?>">

                                        <input type="hidden" name="action"
                                               value="delete_assignment">

                                        <input type="hidden" name="assignment_id"
                                               value="<?= (int) $assignment["id"] ?>">

                                        <button type="submit" class="delete-btn">
                                            Remove
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
