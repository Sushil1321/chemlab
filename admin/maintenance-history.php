
<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("college_admin");

/* Status helpers */
function historyStatus($status) {
    if (in_array($status, ["Not Fixable", "Not Fixed", "Not Resolved"], true)) {
        return "Not Fixable";
    }

    return $status;
}

function historyBadgeClass($status) {
    $status = historyStatus($status);

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

/* Read filters */
$filterStatus = trim($_GET["status"] ?? "");
$search = trim($_GET["search"] ?? "");

$validStatuses = ["Resolved", "Not Fixable"];

if (!in_array($filterStatus, $validStatuses, true)) {
    $filterStatus = "";
}

/* Summary counts */
$summarySql = "
    SELECT
        COUNT(*) AS total_completed,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN status IN ('Not Fixable', 'Not Fixed', 'Not Resolved')
                 THEN 1 ELSE 0 END) AS not_fixable_count
    FROM maintenance_requests
    WHERE status IN ('Resolved', 'Not Fixable', 'Not Fixed', 'Not Resolved')
";

$summaryResult = $conn->query($summarySql);
$summary = $summaryResult ? $summaryResult->fetch_assoc() : [];

$totalCompleted = (int)($summary["total_completed"] ?? 0);
$resolvedCount = (int)($summary["resolved_count"] ?? 0);
$notFixableCount = (int)($summary["not_fixable_count"] ?? 0);

/* Load completed requests */
$sql = "
    SELECT
        mr.request_id,
        mr.issue_type,
        mr.description,
        mr.status,
        mr.reported_at,
        mr.resolved_at,
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
    WHERE mr.status IN ('Resolved', 'Not Fixable', 'Not Fixed', 'Not Resolved')
";

$conditions = [];
$parameters = [];
$types = "";

if ($filterStatus === "Resolved") {
    $conditions[] = "mr.status = ?";
    $parameters[] = "Resolved";
    $types .= "s";
} elseif ($filterStatus === "Not Fixable") {
    $conditions[] = "mr.status IN ('Not Fixable', 'Not Fixed', 'Not Resolved')";
}

if ($search !== "") {
    $conditions[] = "(
        e.equipment_name LIKE ?
        OR e.equipment_code LIKE ?
        OR mr.issue_type LIKE ?
        OR company.name LIKE ?
        OR mr.description LIKE ?
        OR mr.company_message LIKE ?
    )";

    $searchTerm = "%" . $search . "%";

    for ($i = 0; $i < 6; $i++) {
        $parameters[] = $searchTerm;
        $types .= "s";
    }
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY COALESCE(mr.resolved_at, mr.reported_at) DESC";

if (!empty($parameters)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$parameters);
    $stmt->execute();
    $history = $stmt->get_result();
} else {
    $history = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Maintenance History | ChemLab</title>

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

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 14px;
            box-shadow: 0 3px 14px rgba(16, 42, 67, 0.06);
            height: 100%;
        }

        .stat-label {
            color: #627d98;
            font-size: 14px;
        }

        .stat-number {
            font-size: 29px;
            font-weight: 700;
            margin-top: 8px;
        }

        .stat-icon {
            font-size: 25px;
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

        @media print {
            .sidebar,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin: 0;
                padding: 0;
            }

            body {
                background: white;
            }

            .topbar,
            .content-card,
            .stat-card {
                box-shadow: none;
                border: 1px solid #ddd;
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

        <a class="nav-link" href="issues.php">
            <i class="bi bi-clipboard-check"></i> Maintenance Requests
        </a>

        <a class="nav-link active" href="maintenance-history.php">
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
            <i class="bi bi-clock-history text-info"></i>
            Maintenance History
        </h2>

        <p class="text-muted mb-0">
            Review completed maintenance work and company responses.
        </p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Completed Requests</div>
                        <div class="stat-number">
                            <?php echo $totalCompleted; ?>
                        </div>
                    </div>
                    <i class="bi bi-clipboard-check stat-icon text-info"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Resolved</div>
                        <div class="stat-number text-success">
                            <?php echo $resolvedCount; ?>
                        </div>
                    </div>
                    <i class="bi bi-check-circle stat-icon text-success"></i>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Not Fixable</div>
                        <div class="stat-number text-danger">
                            <?php echo $notFixableCount; ?>
                        </div>
                    </div>
                    <i class="bi bi-exclamation-triangle stat-icon text-danger"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h5 class="mb-1">Completed Requests</h5>
                <p class="text-muted mb-0">
                    Resolved and not-fixable requests are shown here.
                </p>
            </div>

            <button type="button" onclick="window.print()"
                    class="btn btn-outline-info no-print">
                <i class="bi bi-printer"></i> Print History
            </button>
        </div>

        <form method="GET" action="maintenance-history.php"
              class="row g-3 mb-4 no-print">

            <div class="col-md-4">
                <label for="status" class="form-label">Filter by Status</label>

                <select name="status" id="status" class="form-select">
                    <option value="">All Completed Requests</option>

                    <option value="Resolved"
                        <?php echo $filterStatus === "Resolved" ? "selected" : ""; ?>>
                        Resolved
                    </option>

                    <option value="Not Fixable"
                        <?php echo $filterStatus === "Not Fixable" ? "selected" : ""; ?>>
                        Not Fixable
                    </option>
                </select>
            </div>

            <div class="col-md-5">
                <label for="search" class="form-label">Search</label>

                <input type="text" id="search" name="search"
                       class="form-control"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Equipment, company, issue...">
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-info text-white">
                    <i class="bi bi-search"></i> Search
                </button>

                <a href="maintenance-history.php"
                   class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Equipment</th>
                        <th>Laboratory</th>
                        <th>Issue Type</th>
                        <th>Description</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Company Message</th>
                        <th>Reported At</th>
                        <th>Completed At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($history && $history->num_rows > 0): ?>
                        <?php while ($row = $history->fetch_assoc()): ?>
                            <?php
                            $readableStatus = historyStatus($row["status"] ?? "");
                            ?>

                            <tr>
                                <td>
                                    #<?php echo (int)$row["request_id"]; ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($row["equipment_name"] ?? "N/A"); ?>
                                    </strong>

                                    <div class="small text-muted">
                                        <?php echo htmlspecialchars($row["equipment_code"] ?? ""); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["lab_name"] ?? "N/A"); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["issue_type"]); ?>
                                </td>

                                <td class="description-cell">
                                    <?php echo nl2br(htmlspecialchars($row["description"])); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($row["company_name"] ?? "Not assigned"); ?>
                                </td>

                                <td>
                                    <span class="badge text-bg-<?php echo historyBadgeClass($row["status"] ?? ""); ?>">
                                        <?php echo htmlspecialchars($readableStatus); ?>
                                    </span>
                                </td>

                                <td class="description-cell">
                                    <?php
                                    $companyMessage = trim($row["company_message"] ?? "");

                                    if ($companyMessage !== "") {
                                        echo nl2br(htmlspecialchars($companyMessage));
                                    } else {
                                        echo '<span class="text-muted">No message</span>';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($row["reported_at"])
                                        ? htmlspecialchars(date("d M Y, h:i A", strtotime($row["reported_at"])))
                                        : "N/A";
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($row["resolved_at"])
                                        ? htmlspecialchars(date("d M Y, h:i A", strtotime($row["resolved_at"])))
                                        : "Not recorded";
                                    ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                No completed maintenance requests found.
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
