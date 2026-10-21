
<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("company_owner");

$companyId = (int) $_SESSION["user_id"];

// --------------------------------------------------
// FETCH COMPANY REQUEST STATISTICS
// --------------------------------------------------
$statsStmt = mysqli_prepare(
    $conn,
    "SELECT
        COUNT(*) AS total_requests,
        SUM(CASE
            WHEN status = 'Reported' THEN 1 ELSE 0
        END) AS reported_requests,
        SUM(CASE
            WHEN status = 'In Progress' THEN 1 ELSE 0
        END) AS in_progress_requests,
        SUM(CASE
            WHEN status IN ('Not Fixable', 'Not Fixed', 'Not Resolved')
            THEN 1 ELSE 0
        END) AS not_fixable_requests,
        SUM(CASE
            WHEN status = 'Resolved' THEN 1 ELSE 0
        END) AS resolved_requests
     FROM maintenance_requests
     WHERE assigned_company_id = ?"
);

if (!$statsStmt) {
    exit("Unable to load dashboard statistics.");
}

mysqli_stmt_bind_param($statsStmt, "i", $companyId);
mysqli_stmt_execute($statsStmt);

$statsResult = mysqli_stmt_get_result($statsStmt);
$stats = mysqli_fetch_assoc($statsResult);

$totalRequests = (int) ($stats["total_requests"] ?? 0);
$reportedRequests = (int) ($stats["reported_requests"] ?? 0);
$inProgressRequests = (int) ($stats["in_progress_requests"] ?? 0);
$notFixableRequests = (int) ($stats["not_fixable_requests"] ?? 0);
$resolvedRequests = (int) ($stats["resolved_requests"] ?? 0);

mysqli_stmt_close($statsStmt);

// --------------------------------------------------
// FETCH RECENT COMPANY REQUESTS
// --------------------------------------------------
$recentStmt = mysqli_prepare(
    $conn,
    "SELECT
        mr.request_id,
        mr.issue_type,
        mr.description,
        mr.status,
        mr.company_message,
        mr.reported_at,
        e.equipment_name,
        e.equipment_code,
        l.lab_name
     FROM maintenance_requests mr
     INNER JOIN equipment e
        ON mr.equipment_id = e.equipment_id
     LEFT JOIN laboratories l
        ON e.lab_id = l.lab_id
     WHERE mr.assigned_company_id = ?
     ORDER BY mr.reported_at DESC
     LIMIT 5"
);

if (!$recentStmt) {
    exit("Unable to load recent maintenance requests.");
}

mysqli_stmt_bind_param($recentStmt, "i", $companyId);
mysqli_stmt_execute($recentStmt);
$recentRequests = mysqli_stmt_get_result($recentStmt);

// --------------------------------------------------
// DISPLAY HELPERS
// --------------------------------------------------
function companyDashboardStatus($status)
{
    if (in_array($status, ["Not Fixed", "Not Resolved"], true)) {
        return "Not Fixable";
    }

    return $status !== "" ? $status : "Reported";
}

function companyDashboardStatusClass($status)
{
    switch (companyDashboardStatus($status)) {
        case "In Progress":
            return "status-in-progress";

        case "Not Fixable":
            return "status-not-fixable";

        case "Resolved":
            return "status-resolved";

        default:
            return "status-reported";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Company Dashboard | ChemLab</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

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
        .content-card,
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 24px;
            box-shadow: 0 3px 14px rgba(20, 45, 75, .04);
            margin-bottom: 24px;
        }

        .topbar h4 {
            color: #102a43;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 17px;
            height: calc(100% - 24px);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            flex-shrink: 0;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
        }

        .icon-blue {
            background: #e4efff;
            color: #2463a6;
        }

        .icon-orange {
            background: #fff0d9;
            color: #9a5700;
        }

        .icon-red {
            background: #fde8e8;
            color: #b42318;
        }

        .icon-green {
            background: #e0f7eb;
            color: #168456;
        }

        .icon-teal {
            background: #d9f5f2;
            color: #087f8c;
        }

        .stat-label {
            color: #718096;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .stat-number {
            color: #102a43;
            font-size: 28px;
            font-weight: bold;
            line-height: 1.2;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: auto;
            min-width: 90px;
            min-height: 30px;
            padding: 6px 12px;
            line-height: 1.4;
            white-space: nowrap;
            border-radius: 20px;
            box-sizing: border-box;
            font-size: 13px;
            font-weight: 600;
        }

        .status-reported {
            background: #ffe9e9;
            color: #c0392b;
        }

        .status-in-progress {
            background: #fff0d9;
            color: #9a5700;
        }

        .status-not-fixable {
            background: #fde8e8;
            color: #b42318;
        }

        .status-resolved {
            background: #e0f7eb;
            color: #168456;
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

        .btn-primary {
            background: #087f8c;
            border-color: #087f8c;
        }

        .btn-primary:hover {
            background: #066873;
            border-color: #066873;
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
            .content-card,
            .stat-card {
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
        <a class="nav-link active" href="dashboard.php">
            <i class="bi bi-grid"></i> Dashboard
        </a>

        <a class="nav-link" href="assigned-requests.php">
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
        <h4 class="fw-bold mb-1">Company Dashboard</h4>
        <p class="text-secondary mb-0">
            Overview of maintenance requests assigned to your company.
        </p>
    </header>

    <!-- Summary cards -->
    <div class="row g-3">

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="bi bi-clipboard-data"></i>
                </div>
                <div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-number"><?= $totalRequests ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="stat-label">Reported</div>
                    <div class="stat-number"><?= $reportedRequests ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon icon-teal">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="stat-label">In Progress</div>
                    <div class="stat-number"><?= $inProgressRequests ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="stat-label">Not Fixable</div>
                    <div class="stat-number"><?= $notFixableRequests ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="stat-label">Resolved</div>
                    <div class="stat-number"><?= $resolvedRequests ?></div>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent requests -->
    <section class="content-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <h5 class="fw-bold mb-0">Recent Maintenance Requests</h5>

            <a href="assigned-requests.php" class="btn btn-primary btn-sm">
                <i class="bi bi-clipboard-check me-1"></i>
                View All Requests
            </a>
        </div>

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
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($recentRequests) > 0): ?>

                    <?php while ($request = mysqli_fetch_assoc($recentRequests)): ?>

                        <?php
                        $displayStatus = companyDashboardStatus(
                            trim((string) ($request["status"] ?? ""))
                        );

                        $statusClass = companyDashboardStatusClass($displayStatus);
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
                                    <?= htmlspecialchars($displayStatus) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-clipboard-x"></i>
                                <h6 class="fw-bold text-dark">
                                    No Maintenance Requests
                                </h6>
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

</body>
</html>

<?php
mysqli_stmt_close($recentStmt);
?>
