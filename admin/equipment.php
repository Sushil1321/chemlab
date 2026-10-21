
<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("college_admin");

$message = "";
$messageType = "success";

/* ADD EQUIPMENT */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_equipment"])) {
    $equipmentCode = trim($_POST["equipment_code"] ?? "");
    $equipmentName = trim($_POST["equipment_name"] ?? "");
    $equipmentType = trim($_POST["equipment_type"] ?? "");
    $labId = (int)($_POST["lab_id"] ?? 0);

    if ($equipmentCode === "" || $equipmentName === "" ||
        $equipmentType === "" || $labId <= 0) {
        $message = "Please fill in all fields correctly.";
        $messageType = "danger";
    } else {
        $check = $conn->prepare(
            "SELECT equipment_id FROM equipment WHERE equipment_code = ?"
        );
        $check->bind_param("s", $equipmentCode);
        $check->execute();
        $duplicate = $check->get_result()->num_rows > 0;
        $check->close();

        $labCheck = $conn->prepare(
            "SELECT lab_id FROM laboratories WHERE lab_id = ?"
        );
        $labCheck->bind_param("i", $labId);
        $labCheck->execute();
        $labExists = $labCheck->get_result()->num_rows > 0;
        $labCheck->close();

        if ($duplicate) {
            $message = "This equipment code already exists.";
            $messageType = "danger";
        } elseif (!$labExists) {
            $message = "Please select a valid laboratory.";
            $messageType = "danger";
        } else {
            // Equipment status is automatically set to Working.
            $stmt = $conn->prepare(
                "INSERT INTO equipment
                    (equipment_code, equipment_name, equipment_type, lab_id, equipment_status)
                 VALUES (?, ?, ?, ?, 'Working')"
            );

            $stmt->bind_param(
                "sssi",
                $equipmentCode,
                $equipmentName,
                $equipmentType,
                $labId
            );

            if ($stmt->execute()) {
                $message = "Equipment added successfully.";
            } else {
                $message = "Unable to add equipment. Please check your database structure.";
                $messageType = "danger";
            }

            $stmt->close();
        }
    }
}

/* LOAD LABORATORIES */
$laboratories = $conn->query(
    "SELECT lab_id, lab_name FROM laboratories ORDER BY lab_name"
);

/* SEARCH EQUIPMENT */
$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT
        e.equipment_id,
        e.equipment_code,
        e.equipment_name,
        e.equipment_type,
        e.created_at,
        l.lab_name
    FROM equipment e
    LEFT JOIN laboratories l ON e.lab_id = l.lab_id
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= "
        AND (
            e.equipment_code LIKE ?
            OR e.equipment_name LIKE ?
            OR e.equipment_type LIKE ?
            OR l.lab_name LIKE ?
        )
    ";

    $searchTerm = "%" . $search . "%";

    for ($i = 0; $i < 4; $i++) {
        $params[] = $searchTerm;
        $types .= "s";
    }
}

$sql .= " ORDER BY e.equipment_id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $equipmentList = $stmt->get_result();
} else {
    $equipmentList = $conn->query($sql);
}

/* SUMMARY STATISTICS */
$statsResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN equipment_status = 'Working' THEN 1 ELSE 0 END) AS working,
        SUM(CASE WHEN equipment_status = 'Under Maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM equipment
");

$stats = $statsResult ? $statsResult->fetch_assoc() : [];

$totalEquipment = (int)($stats["total"] ?? 0);
$workingEquipment = (int)($stats["working"] ?? 0);
$maintenanceEquipment = (int)($stats["maintenance"] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment | ChemLab</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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

        .table th {
            background: #f0f5f9;
            color: #334e68;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
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

        <a class="nav-link active" href="equipment.php">
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
            <i class="bi bi-tools text-info"></i> Equipment
        </h2>
        <p class="text-muted mb-0">Manage laboratory equipment inventory.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Total Equipment</div>
                <div class="stat-number"><?php echo $totalEquipment; ?></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Working</div>
                <div class="stat-number text-success"><?php echo $workingEquipment; ?></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-label">Under Maintenance</div>
                <div class="stat-number text-warning"><?php echo $maintenanceEquipment; ?></div>
            </div>
        </div>
    </div>

    <!-- ADD EQUIPMENT -->
    <div class="content-card">
        <h5 class="mb-4">
            <i class="bi bi-plus-circle text-info"></i>
            Add Equipment
        </h5>

        <form method="POST" action="equipment.php">
            <div class="row g-3">

                <div class="col-md-6">
                    <label for="equipment_code" class="form-label">Equipment Code *</label>
                    <input type="text" class="form-control" id="equipment_code"
                           name="equipment_code" placeholder="Example: CHEM-007" required>
                </div>

                <div class="col-md-6">
                    <label for="equipment_name" class="form-label">Equipment Name *</label>
                    <input type="text" class="form-control" id="equipment_name"
                           name="equipment_name" placeholder="Enter equipment name" required>
                </div>

                <div class="col-md-6">
                    <label for="equipment_type" class="form-label">Equipment Type *</label>
                    <input type="text" class="form-control" id="equipment_type"
                           name="equipment_type"
                           placeholder="Example: Measuring Instrument" required>
                </div>

                <div class="col-md-6">
                    <label for="lab_id" class="form-label">Laboratory *</label>
                    <select class="form-select" id="lab_id" name="lab_id" required>
                        <option value="">Select Laboratory</option>

                        <?php if ($laboratories): ?>
                            <?php while ($lab = $laboratories->fetch_assoc()): ?>
                                <option value="<?php echo (int)$lab["lab_id"]; ?>">
                                    <?php echo htmlspecialchars($lab["lab_name"]); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" name="add_equipment" class="btn btn-info text-white">
                        <i class="bi bi-plus-lg"></i> Add Equipment
                    </button>
                </div>

            </div>
        </form>
    </div>

    <!-- EQUIPMENT INVENTORY -->
    <div class="content-card">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h5 class="mb-1">Equipment Inventory</h5>
                <p class="text-muted mb-0">Search your laboratory equipment.</p>
            </div>

            <button type="button" onclick="window.print()"
                    class="btn btn-outline-info no-print">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>

        <!-- SEARCH -->
        <form method="GET" action="equipment.php" class="row g-3 mb-4 no-print">
            <div class="col-md-10">
                <label for="search" class="form-label">Search Equipment</label>
                <input type="text" class="form-control" id="search" name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search code, name, type or laboratory">
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-info text-white">Search</button>
                <a href="equipment.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <!-- EQUIPMENT TABLE: NO STATUS OR ACTION COLUMNS -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Equipment Code</th>
                        <th>Equipment Name</th>
                        <th>Type</th>
                        <th>Laboratory</th>
                        <th>Added On</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($equipmentList && $equipmentList->num_rows > 0): ?>
                        <?php while ($equipment = $equipmentList->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$equipment["equipment_id"]; ?></td>

                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($equipment["equipment_code"]); ?>
                                    </strong>
                                </td>

                                <td><?php echo htmlspecialchars($equipment["equipment_name"]); ?></td>

                                <td><?php echo htmlspecialchars($equipment["equipment_type"]); ?></td>

                                <td><?php echo htmlspecialchars($equipment["lab_name"] ?? "N/A"); ?></td>

                                <td>
                                    <?php
                                    echo !empty($equipment["created_at"])
                                        ? htmlspecialchars(date("d M Y", strtotime($equipment["created_at"])))
                                        : "N/A";
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No equipment found.
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
