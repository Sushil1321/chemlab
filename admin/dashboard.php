
<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("college_admin");

/* Equipment statistics */
$equipmentResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN equipment_status = 'Working' THEN 1 ELSE 0 END) AS working,
        SUM(CASE WHEN equipment_status = 'Under Maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM equipment
");

$equipmentStats = $equipmentResult
    ? $equipmentResult->fetch_assoc()
    : ["total" => 0, "working" => 0, "maintenance" => 0];

/* Laboratory statistics */
$labResult = $conn->query("
    SELECT COUNT(*) AS total FROM laboratories
");

$totalLabs = $labResult ? (int)$labResult->fetch_assoc()["total"] : 0;

/* Maintenance request statistics */
$requestResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Reported' THEN 1 ELSE 0 END) AS reported,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved,
        SUM(
            CASE WHEN status IN ('Not Fixable', 'Not Fixed', 'Not Resolved')
            THEN 1 ELSE 0 END
        ) AS not_fixable
    FROM maintenance_requests
");

$requestStats = $requestResult
    ? $requestResult->fetch_assoc()
    : [
        "total" => 0,
        "reported" => 0,
        "in_progress" => 0,
        "resolved" => 0,
        "not_fixable" => 0
    ];

/* Recent maintenance requests */
$recentRequests = $conn->query("
    SELECT
        mr.request_id,
        mr.issue_type,
        mr.description,
        mr.status,
        mr.reported_at,
        e.equipment_name,
        e.equipment_code,
        l.lab_name,
        company.name AS company_name
    FROM maintenance_requests mr
    LEFT JOIN equipment e ON mr.equipment_id = e.equipment_id
    LEFT JOIN laboratories l ON e.lab_id = l.lab_id
    LEFT JOIN users company ON mr.assigned_company_id = company.user_id
    ORDER BY mr.reported_at DESC
    LIMIT 8
");

function dashboardStatus($status) {
    if (in_array($status, ["Not Fixable", "Not Fixed", "Not Resolved"], true)) {
        return "Not Fixable";
    }

    if (in_array($status, ["Reported", "In Progress", "Resolved"], true)) {
        return $status;
    }

    return "Reported";
}

function dashboardBadgeClass($status) {
    $status = dashboardStatus($status);

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

    <title>Admin Dashboard | ChemLab</title>

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
        .stat-card,
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
            height: 100%;
            margin-bottom: 0;
        }

        .stat-label {
            color: #627d98;
            font-size: 14px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: 700;
            margin-top: 8px;
        }

        .stat-icon {
            font-size: 28px;
        }

        .quick-link {
            display: block;
            color: #243447;
            text-decoration: none;
            padding: 18px;
            border: 1px solid #e6edf3;
            border-radius: 12px;
            height: 100%;
            transition: 0.2s;
        }

        .quick-link:hover {
            border-color: #087f8c;
            background: #f4fbfb;
            transform: translateY(-2px);
        }

        .quick-link i {
            font-size: 25px;
            color: #087f8c;
            display: block;
            margin-bottom: 10px;
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
            min-width: 160px;
            max-width: 240px;
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
            .stat-card,
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
        <a class="nav-link active" href="dashboard.php">
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
            <i class="bi bi-grid text-info"></i> Admin Dashboard
        </h2>

        <p class="text-muted mb-0">
            Overview of laboratory equipment and maintenance activities.
        </p>
    </div>

    <!-- Main statistics -->
    <div class="row g-3 mb-4">

        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Total Equipment</div>
                        <div class="stat-number">
                            <?php echo (int)$equipmentStats["total"]; ?>
                        </div>
                    </div>
                    <i class="bi bi-tools stat-icon text-info"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Laboratories</div>
                        <div class="stat-number">
                            <?php echo $totalLabs; ?>
                        </div>
                    </div>
                    <i class="bi bi-building stat-icon text-primary"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Maintenance Requests</div>
                        <div class="stat-number">
                            <?php echo (int)$requestStats["total"]; ?>
                        </div>
                    </div>
                    <i class="bi bi-clipboard-check stat-icon text-warning"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Resolved Requests</div>
                        <div class="stat-number text-success">
                            <?php echo (int)$requestStats["resolved"]; ?>
                        </div>
                    </div>
                    <i class="bi bi-check-circle stat-icon text-success"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Equipment status -->
    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Working Equipment</div>
                        <div class="stat-number text-success">
                            <?php echo (int)$equipmentStats["working"]; ?>
                        </div>
                    </div>
                    <i class="bi bi-check-circle stat-icon text-success"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label">Under Maintenance</div>
                        <div class="stat-number text-warning">
                            <?php echo (int)$equipmentStats["maintenance"]; ?>
                        </div>
                    </div>
                    <i class="bi bi-wrench-adjustable stat-icon text-warning"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Maintenance status -->
    <div class="content-card">
        <h5 class="mb-4">Maintenance Status Overview</h5>

        <div class="row g-3">

            <div class="col-md-3">
                <div class="p-3 rounded bg-light">
                    <div class="text-muted">Reported</div>
                    <h3 class="text-warning mb-0">
                        <?php echo (int)$requestStats["reported"]; ?>
                    </h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="p-3 rounded bg-light">
                    <div class="text-muted">In Progress</div>
                    <h3 class="text-primary mb-0">
                        <?php echo (int)$requestStats["in_progress"]; ?>
                    </h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="p-3 rounded bg-light">
                    <div class="text-muted">Resolved</div>
                    <h3 class="text-success mb-0">
                        <?php echo (int)$requestStats["resolved"]; ?>
                    </h3>
                </div>
            </div>

            <div class="col-md-3">
                <div class="p-3 rounded bg-light">
                    <div class="text-muted">Not Fixable</div>
                    <h3 class="text-danger mb-0">
                        <?php echo (int)$requestStats["not_fixable"]; ?>
                    </h3>
                </div>
            </div>

        </div>
    </div>


    <!-- Recent requests -->
    <div class="content-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h5 class="mb-1">Recent Maintenance Requests</h5>
                <p class="text-muted mb-0">
                    Latest equipment maintenance activity.
                </p>
            </div>

            <a href="issues.php" class="btn btn-outline-info">
                View All Requests <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Equipment</th>
                        <th>Laboratory</th>
                        <th>Issue Type</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Reported At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($recentRequests && $recentRequests->num_rows > 0): ?>
                        <?php while ($request = $recentRequests->fetch_assoc()): ?>
                            <?php
                            $status = dashboardStatus($request["status"] ?? "");
                            ?>

                            <tr>
                                <td>#<?php echo (int)$request["request_id"]; ?></td>

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

                                <td>
                                    <?php echo htmlspecialchars($request["company_name"] ?? "Not assigned"); ?>
                                </td>

                                <td>
                                    <span class="badge text-bg-<?php echo dashboardBadgeClass($request["status"] ?? ""); ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($request["reported_at"])
                                        ? htmlspecialchars(date("d M Y, h:i A", strtotime($request["reported_at"])))
                                        : "N/A";
                                    ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
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
