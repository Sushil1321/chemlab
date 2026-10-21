
<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("college_admin");

$message = "";
$messageType = "success";

/* Create a new maintenance request */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_request"])) {
    $equipment_id = (int)($_POST["equipment_id"] ?? 0);
    $issue_type = trim($_POST["issue_type"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $assigned_company_id = (int)($_POST["assigned_company_id"] ?? 0);
    $reported_by = (int)$_SESSION["user_id"];

    if (
        $equipment_id <= 0 ||
        $issue_type === "" ||
        $description === "" ||
        $assigned_company_id <= 0
    ) {
        $message = "Please fill in all required fields.";
        $messageType = "danger";
    } else {
        /* Verify equipment */
        $checkEquipment = $conn->prepare(
            "SELECT equipment_id FROM equipment WHERE equipment_id = ?"
        );
        $checkEquipment->bind_param("i", $equipment_id);
        $checkEquipment->execute();
        $equipmentExists = $checkEquipment->get_result()->num_rows > 0;
        $checkEquipment->close();

        /* Verify company account using the role in your database */
        $checkCompany = $conn->prepare(
            "SELECT user_id FROM users
             WHERE user_id = ? AND role = 'company_owner'"
        );
        $checkCompany->bind_param("i", $assigned_company_id);
        $checkCompany->execute();
        $companyExists = $checkCompany->get_result()->num_rows > 0;
        $checkCompany->close();

        if (!$equipmentExists) {
            $message = "The selected equipment was not found.";
            $messageType = "danger";
        } elseif (!$companyExists) {
            $message = "Please select a valid maintenance company.";
            $messageType = "danger";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO maintenance_requests
                (equipment_id, reported_by, issue_type, description,
                 status, reported_at, assigned_company_id)
                VALUES (?, ?, ?, ?, 'Reported', NOW(), ?)"
            );

            $stmt->bind_param(
                "iissi",
                $equipment_id,
                $reported_by,
                $issue_type,
                $description,
                $assigned_company_id
            );

            if ($stmt->execute()) {
                $message = "Maintenance request created and assigned successfully.";
            } else {
                $message = "Unable to create the request. Please check your database structure.";
                $messageType = "danger";
            }

            $stmt->close();
        }
    }
}

/* Equipment dropdown */
$equipmentList = $conn->query(
    "SELECT e.equipment_id, e.equipment_code, e.equipment_name,
            l.lab_name
     FROM equipment e
     LEFT JOIN laboratories l ON e.lab_id = l.lab_id
     ORDER BY e.equipment_name"
);

/* Company dropdown */
$companyList = $conn->query(
    "SELECT user_id, name, email
     FROM users
     WHERE role = 'company_owner'
     ORDER BY name"
);

/* Status filter */
$allowedStatuses = [
    "Reported",
    "In Progress",
    "Not Fixable",
    "Resolved"
];

$filterStatus = trim($_GET["status"] ?? "");

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = "";
}

/* Requests list */
$sql = "
    SELECT
        mr.request_id,
        mr.issue_type,
        mr.description,
        mr.status,
        mr.reported_at,
        mr.company_message,
        e.equipment_code,
        e.equipment_name,
        l.lab_name,
        reporter.name AS reporter_name,
        company.name AS company_name
    FROM maintenance_requests mr
    LEFT JOIN equipment e ON mr.equipment_id = e.equipment_id
    LEFT JOIN laboratories l ON e.lab_id = l.lab_id
    LEFT JOIN users reporter ON mr.reported_by = reporter.user_id
    LEFT JOIN users company ON mr.assigned_company_id = company.user_id
";

if ($filterStatus === "Not Fixable") {
    $sql .= " WHERE mr.status IN ('Not Fixable', 'Not Fixed', 'Not Resolved')";
} elseif ($filterStatus !== "") {
    $sql .= " WHERE mr.status = ?";
}

$sql .= " ORDER BY mr.reported_at DESC";

if ($filterStatus !== "" && $filterStatus !== "Not Fixable") {
    $requestStmt = $conn->prepare($sql);
    $requestStmt->bind_param("s", $filterStatus);
    $requestStmt->execute();
    $requests = $requestStmt->get_result();
} else {
    $requests = $conn->query($sql);
}

function displayStatus($status) {
    if (in_array($status, ["Not Fixable", "Not Fixed", "Not Resolved"], true)) {
        return "Not Fixable";
    }

    if ($status === "Resolved") {
        return "Resolved";
    }

    if ($status === "In Progress") {
        return "In Progress";
    }

    return "Reported";
}

function statusClass($status) {
    $status = displayStatus($status);

    if ($status === "Resolved") {
        return "success";
    }

    if ($status === "Not Fixable") {
        return "danger";
    }

    if ($status === "In Progress") {
        return "primary";
    }

    return "warning";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Maintenance Requests | ChemLab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <style>
        body {
            margin: 0;
            background: #f3f7fb;
            font-family: Arial, sans-serif;
            color: #243447;
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

        .nav-link {
            color: #d7e3f0;
            padding: 13px 14px;
            margin-bottom: 7px;
            border-radius: 9px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #087f8c;
            color: white;
        }

        .nav-link i {
            margin-right: 10px;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar,
        .content-card {
            background: white;
            padding: 24px;
            border-radius: 14px;
            box-shadow: 0 3px 14px rgba(16, 42, 67, 0.06);
            margin-bottom: 24px;
        }

        .page-title {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .form-label {
            font-weight: 600;
        }

        .table th {
            background: #f0f5f9;
            color: #334e68;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .badge {
            padding: 8px 10px;
            font-weight: 500;
        }

        .description-cell {
            min-width: 180px;
            max-width: 280px;
            white-space: normal;
            overflow-wrap: anywhere;
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

            .nav-link {
                text-align: center;
                font-size: 0;
            }

            .nav-link i {
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
        <i class="bi bi-droplet-half"></i>ChemLab
    </div>

    <nav>
        <a class="nav-link" href="dashboard.php">
            <i class="bi bi-grid"></i> Dashboard
        </a>

        <a class="nav-link" href="laboratories.php">
            <i class="bi bi-building"></i> Laboratories
        </a>

        <a class="nav-link" href="equipment.php">
            <i class="bi bi-tools"></i> Equipment
        </a>

        <a class="nav-link active" href="issues.php">
            <i class="bi bi-clipboard-check"></i> Maintenance Requests
        </a>

        <a class="nav-link" href="maintenance-history.php">
            <i class="bi bi-clock-history"></i> Maintenance History
        </a>

        <a class="nav-link" href="reports.php">
            <i class="bi bi-bar-chart"></i> Reports
        </a>

        <a class="nav-link" href="../logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">

    <div class="topbar">
        <h2 class="page-title">
            <i class="bi bi-clipboard-check text-info"></i>
            Maintenance Requests
        </h2>

        <p class="text-muted mb-0">
            Create requests, assign maintenance companies, and track progress.
        </p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="content-card">
        <h5 class="mb-4">
            <i class="bi bi-plus-circle text-info"></i>
            Create Maintenance Request
        </h5>

        <form method="POST" action="issues.php">
            <div class="row g-3">

                <div class="col-md-6">
                    <label for="equipment_id" class="form-label">Equipment *</label>

                    <select class="form-select" id="equipment_id"
                            name="equipment_id" required>
                        <option value="">Select Equipment</option>

                        <?php if ($equipmentList): ?>
                            <?php while ($equipment = $equipmentList->fetch_assoc()): ?>
                                <option value="<?php echo (int)$equipment["equipment_id"]; ?>">
                                    <?php
                                    echo htmlspecialchars(
                                        $equipment["equipment_code"] . " - " .
                                        $equipment["equipment_name"] .
                                        ($equipment["lab_name"]
                                            ? " (" . $equipment["lab_name"] . ")"
                                            : "")
                                    );
                                    ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="issue_type" class="form-label">Issue Type *</label>

                    <select class="form-select" id="issue_type"
                            name="issue_type" required>
                        <option value="">Select Issue Type</option>
                        <option value="Equipment Malfunction">Equipment Malfunction</option>
                        <option value="Calibration">Calibration</option>
                        <option value="Electrical Issue">Electrical Issue</option>
                        <option value="Physical Damage">Physical Damage</option>
                        <option value="Preventive Maintenance">Preventive Maintenance</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="assigned_company_id" class="form-label">
                        Assign Maintenance Company *
                    </label>

                    <select class="form-select" id="assigned_company_id"
                            name="assigned_company_id" required>
                        <option value="">Select Company</option>

                        <?php if ($companyList): ?>
                            <?php while ($company = $companyList->fetch_assoc()): ?>
                                <option value="<?php echo (int)$company["user_id"]; ?>">
                                    <?php
                                    echo htmlspecialchars(
                                        $company["name"] . " (" .
                                        $company["email"] . ")"
                                    );
                                    ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="description" class="form-label">
                        Issue Description *
                    </label>

                    <textarea class="form-control" id="description"
                              name="description" rows="3" required
                              placeholder="Describe the equipment problem..."></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" name="create_request"
                            class="btn btn-info text-white">
                        <i class="bi bi-send"></i>
                        Create and Assign Request
                    </button>
                </div>

            </div>
        </form>
    </div>

    <div class="content-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h5 class="mb-1">All Maintenance Requests</h5>
                <p class="text-muted mb-0">
                    View request status and company responses.
                </p>
            </div>

            <form method="GET" action="issues.php" class="d-flex gap-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>

                    <?php foreach ($allowedStatuses as $statusOption): ?>
                        <option value="<?php echo htmlspecialchars($statusOption); ?>"
                            <?php echo $filterStatus === $statusOption ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($statusOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-outline-info">
                    Filter
                </button>

                <a href="issues.php" class="btn btn-outline-secondary">
                    Reset
                </a>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Equipment</th>
                        <th>Laboratory</th>
                        <th>Issue</th>
                        <th>Description</th>
                        <th>Assigned Company</th>
                        <th>Status</th>
                        <th>Company Message</th>
                        <th>Reported At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($requests && $requests->num_rows > 0): ?>
                        <?php while ($request = $requests->fetch_assoc()): ?>
                            <?php
                            $readableStatus = displayStatus($request["status"] ?? "");
                            ?>

                            <tr>
                                <td>
                                    #<?php echo (int)$request["request_id"]; ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($request["equipment_name"] ?? "N/A"); ?>
                                    </strong>

                                    <div class="small text-muted">
                                        <?php echo htmlspecialchars($request["equipment_code"] ?? ""); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($request["lab_name"] ?? "N/A"); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($request["issue_type"]); ?>
                                </td>

                                <td class="description-cell">
                                    <?php echo nl2br(htmlspecialchars($request["description"])); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($request["company_name"] ?? "Not assigned"); ?>
                                </td>

                                <td>
                                    <span class="badge text-bg-<?php echo statusClass($request["status"] ?? ""); ?>">
                                        <?php echo htmlspecialchars($readableStatus); ?>
                                    </span>
                                </td>

                                <td class="description-cell">
                                    <?php
                                    $companyMessage = trim($request["company_message"] ?? "");

                                    if ($companyMessage !== "") {
                                        echo nl2br(htmlspecialchars($companyMessage));
                                    } else {
                                        echo '<span class="text-muted">No message</span>';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($request["reported_at"])
                                        ? htmlspecialchars(
                                            date(
                                                "d M Y, h:i A",
                                                strtotime($request["reported_at"])
                                            )
                                        )
                                        : "N/A";
                                    ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No maintenance requests found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
