
<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("company_owner");

$companyId = (int) $_SESSION["user_id"];
$message = "";
$messageType = "success";

// --------------------------------------------------
// UPDATE REQUEST STATUS
// --------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestId = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT);
    $newStatus = trim($_POST["status"] ?? "");
    $companyMessage = trim($_POST["company_message"] ?? "");

    $allowedStatuses = ["In Progress", "Not Fixable", "Resolved"];

    if (!$requestId || !in_array($newStatus, $allowedStatuses, true)) {
        $message = "Invalid request or status.";
        $messageType = "danger";
    } elseif ($newStatus === "Not Fixable" && $companyMessage === "") {
        $message = "Please explain why the equipment cannot be fixed.";
        $messageType = "danger";
    } elseif (strlen($companyMessage) > 2000) {
        $message = "The explanation cannot exceed 2000 characters.";
        $messageType = "danger";
    } else {
        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE maintenance_requests
             SET status = ?,
                 company_message = CASE
                     WHEN ? = 'Not Fixable' THEN ?
                     ELSE company_message
                 END,
                 resolved_at = CASE
                     WHEN ? = 'Resolved' THEN CURRENT_TIMESTAMP
                     ELSE NULL
                 END
             WHERE request_id = ?
               AND assigned_company_id = ?
               AND status NOT IN ('Resolved', 'Not Fixable', 'Not Fixed', 'Not Resolved')"
        );

        if (!$updateStmt) {
            $message = "Unable to prepare the database update.";
            $messageType = "danger";
        } else {
            mysqli_stmt_bind_param(
                $updateStmt,
                "ssssii",
                $newStatus,
                $newStatus,
                $companyMessage,
                $newStatus,
                $requestId,
                $companyId
            );

            if (mysqli_stmt_execute($updateStmt)) {
                if (mysqli_stmt_affected_rows($updateStmt) > 0) {
                    $equipmentStatus = ($newStatus === "Resolved")
                        ? "Working"
                        : "Under Maintenance";

                    $equipmentStmt = mysqli_prepare(
                        $conn,
                        "UPDATE equipment e
                         INNER JOIN maintenance_requests mr
                            ON mr.equipment_id = e.equipment_id
                         SET e.equipment_status = ?
                         WHERE mr.request_id = ?
                           AND mr.assigned_company_id = ?"
                    );

                    if ($equipmentStmt) {
                        mysqli_stmt_bind_param(
                            $equipmentStmt,
                            "sii",
                            $equipmentStatus,
                            $requestId,
                            $companyId
                        );
                        mysqli_stmt_execute($equipmentStmt);
                        mysqli_stmt_close($equipmentStmt);
                    }

                    $message = "Maintenance request updated successfully.";
                    $messageType = "success";
                } else {
                    $checkStmt = mysqli_prepare(
                        $conn,
                        "SELECT status
                         FROM maintenance_requests
                         WHERE request_id = ?
                           AND assigned_company_id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $checkStmt,
                        "ii",
                        $requestId,
                        $companyId
                    );
                    mysqli_stmt_execute($checkStmt);
                    $checkResult = mysqli_stmt_get_result($checkStmt);
                    $existing = mysqli_fetch_assoc($checkResult);
                    mysqli_stmt_close($checkStmt);

                    if (!$existing) {
                        $message = "This request is not assigned to your company.";
                        $messageType = "danger";
                    } else {
                        $message = "Current database status: " . $existing["status"];
                        $messageType = "warning";
                    }
                }
            } else {
                $message = "Database update failed: " .
                    mysqli_stmt_error($updateStmt);
                $messageType = "danger";
            }

            mysqli_stmt_close($updateStmt);
        }
    }
}

// --------------------------------------------------
// LOAD REQUESTS ASSIGNED TO THIS COMPANY
// --------------------------------------------------
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        mr.request_id,
        mr.issue_type,
        mr.description,
        mr.status,
        mr.company_message,
        mr.reported_at,
        mr.resolved_at,
        e.equipment_name,
        e.equipment_code,
        e.equipment_status,
        l.lab_name
     FROM maintenance_requests mr
     INNER JOIN equipment e
        ON mr.equipment_id = e.equipment_id
     LEFT JOIN laboratories l
        ON e.lab_id = l.lab_id
     WHERE mr.assigned_company_id = ?
     ORDER BY mr.reported_at DESC"
);

if (!$stmt) {
    exit("Unable to load requests: " . htmlspecialchars(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt, "i", $companyId);
mysqli_stmt_execute($stmt);
$requests = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Maintenance Requests | ChemLab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        body {
            margin: 0;
            background: #f4f7fb;
            font-family: Arial, sans-serif;
            color: #172b4d;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: #102a43;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 25px 16px;
            z-index: 1000;
        }

        .brand {
            font-size: 23px;
            font-weight: bold;
            margin: 0 0 35px 8px;
        }

        .brand i {
            color: #45d6c5;
            margin-right: 8px;
        }

        .sidebar .nav-link {
            display: block;
            color: #d7e3f0;
            padding: 13px 14px;
            margin-bottom: 7px;
            border-radius: 9px;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #087f8c;
            color: white;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-width: 0;
        }

        .topbar,
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            box-shadow: 0 3px 14px rgba(20, 45, 75, .04);
            margin-bottom: 24px;
        }

        .topbar h4 {
            color: #102a43;
        }

        .table th {
            color: #718096;
            font-size: 13px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            padding: 15px 10px;
        }

        .status {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: auto !important;
            min-width: 90px;
            min-height: 30px;
            height: auto !important;
            padding: 6px 12px !important;
            line-height: 1.4;
            white-space: nowrap;
            overflow: visible;
            border: 0;
            border-radius: 20px;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            font-size: 13px !important;
            font-weight: 600;
            text-indent: 0;
            letter-spacing: normal;
            opacity: 1;
        }

        .status-reported {
            background: #ffe9e9 !important;
            color: #c0392b !important;
        }

        .status-in-progress {
            background: #fff0d9 !important;
            color: #9a5700 !important;
        }

        .status-not-fixable {
            background: #fde8e8 !important;
            color: #b42318 !important;
            min-width: 100px;
        }

        .status-resolved {
            background: #e0f7eb !important;
            color: #168456 !important;
        }

        .btn-primary {
            background: #087f8c;
            border-color: #087f8c;
        }

        .btn-primary:hover {
            background: #066873;
            border-color: #066873;
        }

        .company-message {
            margin-top: 8px;
            padding: 9px 10px;
            background: #fff8ed;
            border-left: 3px solid #e0a13a;
            border-radius: 5px;
            color: #76501b;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 210px;
        }

        .status-form .form-select {
            min-width: 125px;
        }

        .completed-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .completed-label.resolved {
            color: #168456;
        }

        .completed-label.not-fixable {
            color: #b42318;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: #718096;
        }

        .empty-state i {
            display: block;
            font-size: 40px;
            margin-bottom: 10px;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 75px;
                padding: 20px 10px;
            }

            .brand {
                font-size: 0;
                margin-left: 12px;
            }

            .brand i {
                font-size: 24px;
            }

            .sidebar .nav-link {
                text-align: center;
                font-size: 0;
            }

            .sidebar .nav-link i {
                font-size: 20px;
                margin: 0;
            }

            .main-content {
                margin-left: 75px;
                padding: 18px;
            }
        }

        @media (max-width: 500px) {
            .main-content {
                padding: 12px;
            }

            .topbar,
            .content-card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <i class="bi bi-tools"></i>ChemLab
    </div>

    <nav>
        <a class="nav-link" href="dashboard.php">
            <i class="bi bi-grid"></i> Dashboard
        </a>
        <a class="nav-link active" href="assigned-requests.php">
            <i class="bi bi-clipboard-check"></i> Maintenance Requests
        </a>
        
        <a class="nav-link" href="maintenance-history.php">
            <i class="bi bi-clock-history"></i> Maintenance History
        </a>
        <a class="nav-link" href="../logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">

    <header class="topbar">
        <h4 class="fw-bold mb-1">Maintenance Requests</h4>
        <p class="text-secondary mb-0">
            Review assigned work and update repair progress.
        </p>
    </header>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <section class="content-card">
        <h5 class="fw-bold mb-4">Requests Assigned to Your Company</h5>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Equipment</th>
                        <th>Laboratory</th>
                        <th>Issue Details</th>
                        <th>Reported Date</th>
                        <th>Status</th>
                        <th>Update Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($requests) > 0): ?>

                    <?php while ($request = mysqli_fetch_assoc($requests)): ?>
                        <?php
                        $savedStatus = trim((string) ($request["status"] ?? ""));

                        // Support the previous database status label.
                        if (in_array(strtolower($savedStatus), ["not fixed", "not resolved"], true)) {
                            $savedStatus = "Not Fixable";
                        }

                        if ($savedStatus === "") {
                            $savedStatus = "Reported";
                        }

                        $isNotFixable = ($savedStatus === "Not Fixable");
                        $isResolved = ($savedStatus === "Resolved");

                        $statusClass = "status-reported";

                        if ($savedStatus === "In Progress") {
                            $statusClass = "status-in-progress";
                        } elseif ($isNotFixable) {
                            $statusClass = "status-not-fixable";
                        } elseif ($isResolved) {
                            $statusClass = "status-resolved";
                        }
                        ?>

                        <tr>
                            <td>#<?= (int) $request["request_id"] ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($request["equipment_name"]) ?>
                                </div>
                                <small class="text-secondary">
                                    <?= htmlspecialchars($request["equipment_code"]) ?>
                                </small>
                            </td>

                            <td>
                                <?= htmlspecialchars($request["lab_name"] ?? "Not assigned") ?>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($request["issue_type"]) ?>
                                </div>
                                <small class="text-secondary">
                                    <?= htmlspecialchars($request["description"]) ?>
                                </small>

                                <?php if (!empty($request["company_message"])): ?>
                                    <div class="company-message">
                                        <strong>Company message:</strong><br>
                                        <?= nl2br(htmlspecialchars($request["company_message"])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= date("d M Y", strtotime($request["reported_at"])) ?>
                            </td>

                            <td>
                                <span class="status <?= $statusClass ?>">
                                    <?= htmlspecialchars($savedStatus) ?>
                                </span>
                            </td>

                            <!-- Updated Update Status cell -->
                            <td>
                                <?php
                                // Use the normalized status from the row above so
                                // legacy terminal labels also hide the editor.
                                ?>

                                <?php if (
                                    $savedStatus === "Not Fixable" ||
                                    $savedStatus === "Resolved"
                                ): ?>

                                    <?php if ($savedStatus === "Not Fixable"): ?>
                                        <span class="completed-label not-fixable">
                                            <i class="bi bi-exclamation-circle"></i>
                                            Not Resolved
                                        </span>
                                    <?php else: ?>
                                        <span class="completed-label resolved">
                                            <i class="bi bi-check-circle"></i>
                                            Completed
                                        </span>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="request_id"
                                               value="<?= (int) $request["request_id"] ?>">

                                        <input type="hidden" name="company_message" value="">

                                        <select name="status"
                                                class="form-select form-select-sm"
                                                required>
                                            <option value="In Progress"
                                                <?= $savedStatus === "In Progress" ? "selected" : "" ?>>
                                                In Progress
                                            </option>
                                            <option value="Not Fixable">Not Fixable</option>
                                            <option value="Resolved">Resolved</option>
                                        </select>

                                        <button type="submit"
                                                class="btn btn-primary btn-sm">
                                            Save
                                        </button>
                                    </form>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-clipboard-x"></i>
                                <h6 class="fw-bold text-dark">No Maintenance Requests</h6>
                                <p class="mb-0">
                                    Requests assigned to your company will appear here.
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<!-- Not Fixable explanation popup -->
<div class="modal fade" id="notFixedModal" tabindex="-1"
     aria-labelledby="notFixedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="notFixedModalLabel">
                    <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                    Equipment Not Fixable
                </h5>
                <button type="button" class="btn-close"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-secondary">
                    Explain why this equipment cannot be repaired.
                    The college admin can view this message.
                </p>

                <label for="companyReason" class="form-label fw-semibold">
                    Reason <span class="text-danger">*</span>
                </label>

                <textarea id="companyReason" class="form-control"
                          rows="4" maxlength="2000"
                          placeholder="Explain why the equipment cannot be repaired."
                          required></textarea>

                <div id="reasonError" class="text-danger small mt-2"
                     style="display:none">
                    Please enter a reason before submitting.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary"
                        id="confirmNotFixed">Submit Reason</button>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
let activeMaintenanceForm = null;
let previousStatus = "";

const modalElement = document.getElementById("notFixedModal");
const reasonField = document.getElementById("companyReason");
const reasonError = document.getElementById("reasonError");
const notFixableModal = bootstrap.Modal.getOrCreateInstance(modalElement);

document.querySelectorAll(
    "form.status-form select[name='status']"
).forEach(function (select) {
    select.dataset.previousStatus = select.value;

    select.addEventListener("change", function () {
        if (this.value === "Not Fixable") {
            activeMaintenanceForm = this.form;
            previousStatus = this.dataset.previousStatus || "In Progress";

            reasonField.value = "";
            reasonError.style.display = "none";

            notFixableModal.show();
        } else {
            this.dataset.previousStatus = this.value;
        }
    });
});

document.getElementById("confirmNotFixed").addEventListener(
    "click",
    function () {
        const reason = reasonField.value.trim();

        if (!reason) {
            reasonError.style.display = "block";
            reasonField.focus();
            return;
        }

        if (!activeMaintenanceForm || reason.length > 2000) {
            return;
        }

        const formToSubmit = activeMaintenanceForm;

        formToSubmit.querySelector(
            "input[name='company_message']"
        ).value = reason;

        formToSubmit.querySelector(
            "select[name='status']"
        ).value = "Not Fixable";

        activeMaintenanceForm = null;
        notFixableModal.hide();

        // Submit the status and message together.
        formToSubmit.submit();
    }
);

modalElement.addEventListener("hidden.bs.modal", function () {
    if (activeMaintenanceForm) {
        const select = activeMaintenanceForm.querySelector(
            "select[name='status']"
        );

        if (select) {
            select.value = previousStatus;
        }
    }

    activeMaintenanceForm = null;
});
</script>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
