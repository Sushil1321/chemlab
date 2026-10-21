
<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("college_admin");

/* Treat legacy status names consistently */
function reportStatusCondition($status) {
    if ($status === "Not Fixable") {
        return "('Not Fixable', 'Not Fixed', 'Not Resolved')";
    }

    return "('" . $status . "')";
}

/* Equipment statistics */
$equipmentStats = [
    "total" => 0,
    "working" => 0,
    "under_maintenance" => 0,
    "not_working" => 0
];

$equipmentResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN equipment_status = 'Working' THEN 1 ELSE 0 END) AS working,
        SUM(CASE WHEN equipment_status = 'Under Maintenance' THEN 1 ELSE 0 END) AS under_maintenance,
        SUM(CASE WHEN equipment_status NOT IN ('Working', 'Under Maintenance')
                 THEN 1 ELSE 0 END) AS not_working
    FROM equipment
");

if ($equipmentResult) {
    $equipmentStats = array_merge(
        $equipmentStats,
        $equipmentResult->fetch_assoc()
    );
}

/* Laboratory count */
$labResult = $conn->query("SELECT COUNT(*) AS total FROM laboratories");
$totalLabs = $labResult ? (int)$labResult->fetch_assoc()["total"] : 0;

/* Maintenance request statistics */
$requestStats = [
    "total" => 0,
    "reported" => 0,
    "in_progress" => 0,
    "resolved" => 0,
    "not_fixable" => 0
];

$requestResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Reported' THEN 1 ELSE 0 END) AS reported,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN status IN ('Not Fixable', 'Not Fixed', 'Not Resolved')
                 THEN 1 ELSE 0 END) AS not_fixable
    FROM maintenance_requests
");

if ($requestResult) {
    $requestStats = array_merge(
        $requestStats,
        $requestResult->fetch_assoc()
    );
}

/* Laboratory-wise equipment */
$labEquipment = $conn->query("
    SELECT
        l.lab_name,
        COUNT(e.equipment_id) AS equipment_count,
        SUM(CASE WHEN e.equipment_status = 'Working' THEN 1 ELSE 0 END) AS working_count,
        SUM(CASE WHEN e.equipment_status = 'Under Maintenance' THEN 1 ELSE 0 END) AS maintenance_count
    FROM laboratories l
    LEFT JOIN equipment e ON l.lab_id = e.lab_id
    GROUP BY l.lab_id, l.lab_name
    ORDER BY l.lab_name
");

/* Equipment status distribution */
$equipmentStatusResult = $conn->query("
    SELECT equipment_status, COUNT(*) AS total
    FROM equipment
    GROUP BY equipment_status
    ORDER BY equipment_status
");

$equipmentStatusLabels = [];
$equipmentStatusValues = [];

if ($equipmentStatusResult) {
    while ($row = $equipmentStatusResult->fetch_assoc()) {
        $equipmentStatusLabels[] = $row["equipment_status"];
        $equipmentStatusValues[] = (int)$row["total"];
    }
}

/* Maintenance request status distribution */
$statusLabels = ["Reported", "In Progress", "Resolved", "Not Fixable"];

$statusValues = [
    (int)$requestStats["reported"],
    (int)$requestStats["in_progress"],
    (int)$requestStats["resolved"],
    (int)$requestStats["not_fixable"]
];

/* Recent requests */
$recentRequests = $conn->query("
    SELECT
        mr.request_id,
        mr.issue_type,
        mr.status,
        mr.reported_at,
        e.equipment_name,
        e.equipment_code,
        company.name AS company_name
    FROM maintenance_requests mr
    LEFT JOIN equipment e ON mr.equipment_id = e.equipment_id
    LEFT JOIN users company ON mr.assigned_company_id = company.user_id
    ORDER BY mr.reported_at DESC
    LIMIT 10
");

/* CSV export */
if (isset($_GET["export"]) && $_GET["export"] === "csv") {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=chemistry_lab_report.csv");

    $output = fopen("php://output", "w");

    fputcsv($output, [
        "Request ID",
        "Equipment Code",
        "Equipment Name",
        "Issue Type",
        "Status",
        "Assigned Company",
        "Reported At"
    ]);

    $exportResult = $conn->query("
        SELECT
            mr.request_id,
            e.equipment_code,
            e.equipment_name,
            mr.issue_type,
            mr.status,
            company.name AS company_name,
            mr.reported_at
        FROM maintenance_requests mr
        LEFT JOIN equipment e ON mr.equipment_id = e.equipment_id
        LEFT JOIN users company ON mr.assigned_company_id = company.user_id
        ORDER BY mr.reported_at DESC
    ");

    if ($exportResult) {
        while ($row = $exportResult->fetch_assoc()) {
            if (in_array($row["status"], ["Not Fixable", "Not Fixed", "Not Resolved"], true)) {
                $row["status"] = "Not Fixable";
            }

            fputcsv($output, [
                $row["request_id"],
                $row["equipment_code"],
                $row["equipment_name"],
                $row["issue_type"],
                $row["status"],
                $row["company_name"],
                $row["reported_at"]
            ]);
        }
    }

    fclose($output);
    exit;
}

function reportReadableStatus($status) {
    if (in_array($status, ["Not Fixable", "Not Fixed", "Not Resolved"], true)) {
        return "Not Fixable";
    }

    return $status;
}

function reportBadgeClass($status) {
    $status = reportReadableStatus($status);

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

    <title>Reports | ChemLab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        .content-card,
        .stat-card {
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
            font-size: 29px;
            font-weight: 700;
            margin-top: 8px;
        }

        .stat-icon {
            font-size: 26px;
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

        .chart-container {
            position: relative;
            height: 300px;
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
            .content-card,
            .stat-card {
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

        <a class="nav-link" href="maintenance-history.php">
            <i class="bi bi-clock-history"></i> Maintenance History
        </a>

        <a class="nav-link active" href="reports.php">
            <i class="bi bi-bar-chart"></i> Reports
        </a>

        <a class="nav-link" href="../logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">

    <div class="topbar d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="page-title">
                <i class="bi bi-bar-chart text-info"></i> Reports
            </h2>

            <p class="text-muted mb-0">
                Equipment, laboratory and maintenance performance overview.
            </p>
        </div>

        <div class="d-flex gap-2 no-print">
            <a href="reports.php?export=csv" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
            </a>

            <button type="button" onclick="window.print()"
                    class="btn btn-info text-white">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Main statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
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
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-label">Total Laboratories</div>
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
                <div class="d-flex justify-content-between">
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
                <div class="d-flex justify-content-between">
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
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Working Equipment</div>
                <div class="stat-number text-success">
                    <?php echo (int)$equipmentStats["working"]; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Under Maintenance</div>
                <div class="stat-number text-warning">
                    <?php echo (int)$equipmentStats["under_maintenance"]; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Other Equipment Status</div>
                <div class="stat-number">
                    <?php echo (int)$equipmentStats["not_working"]; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Request status -->
    <div class="content-card">
        <h5 class="mb-4">Maintenance Request Status</h5>

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

    <!-- Charts -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="content-card">
                <h5 class="mb-4">Maintenance Request Distribution</h5>

                <div class="chart-container">
                    <canvas id="requestChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="content-card">
                <h5 class="mb-4">Equipment Status Distribution</h5>

                <div class="chart-container">
                    <canvas id="equipmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Laboratory equipment table -->
    <div class="content-card">
        <h5 class="mb-4">Laboratory-wise Equipment Summary</h5>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Laboratory</th>
                        <th>Total Equipment</th>
                        <th>Working</th>
                        <th>Under Maintenance</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($labEquipment && $labEquipment->num_rows > 0): ?>
                        <?php while ($lab = $labEquipment->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($lab["lab_name"]); ?>
                                </td>

                                <td><?php echo (int)$lab["equipment_count"]; ?></td>

                                <td>
                                    <span class="badge text-bg-success">
                                        <?php echo (int)$lab["working_count"]; ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge text-bg-warning">
                                        <?php echo (int)$lab["maintenance_count"]; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No laboratory data found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent maintenance requests -->
    <div class="content-card">
        <h5 class="mb-4">Recent Maintenance Requests</h5>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Equipment</th>
                        <th>Issue Type</th>
                        <th>Assigned Company</th>
                        <th>Status</th>
                        <th>Reported At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($recentRequests && $recentRequests->num_rows > 0): ?>
                        <?php while ($request = $recentRequests->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo (int)$request["request_id"]; ?></td>

                                <td>
                                    <?php echo htmlspecialchars($request["equipment_name"] ?? "N/A"); ?>
                                    <div class="small text-muted">
                                        <?php echo htmlspecialchars($request["equipment_code"] ?? ""); ?>
                                    </div>
                                </td>

                                <td><?php echo htmlspecialchars($request["issue_type"]); ?></td>

                                <td>
                                    <?php echo htmlspecialchars($request["company_name"] ?? "Not assigned"); ?>
                                </td>

                                <td>
                                    <?php
                                    $status = reportReadableStatus($request["status"] ?? "");
                                    ?>
                                    <span class="badge text-bg-<?php echo reportBadgeClass($request["status"] ?? ""); ?>">
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
                            <td colspan="6" class="text-center text-muted">
                                No maintenance requests found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
const requestLabels = <?php echo json_encode($statusLabels); ?>;
const requestValues = <?php echo json_encode($statusValues); ?>;

const equipmentLabels = <?php echo json_encode($equipmentStatusLabels); ?>;
const equipmentValues = <?php echo json_encode($equipmentStatusValues); ?>;

new Chart(document.getElementById("requestChart"), {
    type: "doughnut",
    data: {
        labels: requestLabels,
        datasets: [{
            data: requestValues
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom"
            }
        }
    }
});

new Chart(document.getElementById("equipmentChart"), {
    type: "bar",
    data: {
        labels: equipmentLabels,
        datasets: [{
            label: "Number of Equipment",
            data: equipmentValues
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

</body>
</html>
