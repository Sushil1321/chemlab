<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

requireRole("college_admin");

$message = "";
$messageType = "success";

/* Add a laboratory */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_lab"])) {
    $lab_name = trim($_POST["lab_name"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($lab_name === "" || $location === "") {
        $message = "Please enter the laboratory name and location.";
        $messageType = "danger";
    } else {
        /* Prevent duplicate laboratory names */
        $check = $conn->prepare(
            "SELECT lab_id FROM laboratories WHERE lab_name = ?"
        );
        $check->bind_param("s", $lab_name);
        $check->execute();
        $duplicate = $check->get_result()->num_rows > 0;
        $check->close();

        if ($duplicate) {
            $message = "A laboratory with this name already exists.";
            $messageType = "danger";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO laboratories (lab_name, location, description)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param("sss", $lab_name, $location, $description);

            if ($stmt->execute()) {
                $message = "Laboratory added successfully.";
            } else {
                $message = "Unable to add the laboratory. Please check your database structure.";
                $messageType = "danger";
            }

            $stmt->close();
        }
    }
}

/* Search */
$search = trim($_GET["search"] ?? "");

/* Laboratory list and equipment counts */
$sql = "
    SELECT
        l.lab_id,
        l.lab_name,
        l.location,
        l.description,
        l.created_at,
        COUNT(e.equipment_id) AS equipment_count,
        SUM(
            CASE WHEN e.equipment_status = 'Working'
            THEN 1 ELSE 0 END
        ) AS working_count,
        SUM(
            CASE WHEN e.equipment_status = 'Under Maintenance'
            THEN 1 ELSE 0 END
        ) AS maintenance_count
    FROM laboratories l
    LEFT JOIN equipment e ON l.lab_id = e.lab_id
";

if ($search !== "") {
    $sql .= "
        WHERE l.lab_name LIKE ?
           OR l.location LIKE ?
           OR l.description LIKE ?
    ";
}

$sql .= "
    GROUP BY l.lab_id, l.lab_name, l.location, l.description, l.created_at
    ORDER BY l.lab_id DESC
";

if ($search !== "") {
    $stmt = $conn->prepare($sql);
    $searchTerm = "%" . $search . "%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $laboratories = $stmt->get_result();
} else {
    $laboratories = $conn->query($sql);
}

/* Summary statistics */
$summaryResult = $conn->query("
    SELECT
        COUNT(*) AS total_labs,
        (
            SELECT COUNT(*) FROM equipment
        ) AS total_equipment
    FROM laboratories
");

$summary = $summaryResult ? $summaryResult->fetch_assoc() : [];

$totalLabs = (int) ($summary["total_labs"] ?? 0);
$totalEquipment = (int) ($summary["total_equipment"] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laboratories | ChemLab</title>

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

        .description-cell {
            min-width: 160px;
            max-width: 260px;
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

            <a class="nav-link active" href="laboratories.php">
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
                <i class="bi bi-building text-info"></i> Laboratories
            </h2>

            <p class="text-muted mb-0">
                Manage laboratories and view their equipment inventory.
            </p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Summary cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-label">Total Laboratories</div>
                    <div class="stat-number text-info">
                        <?php echo $totalLabs; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-label">Total Equipment</div>
                    <div class="stat-number text-primary">
                        <?php echo $totalEquipment; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add laboratory -->
        <div class="content-card">
            <h5 class="mb-4">
                <i class="bi bi-plus-circle text-info"></i> Add Laboratory
            </h5>

            <form method="POST" action="laboratories.php">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label for="lab_name" class="form-label">
                            Laboratory Name *
                        </label>

                        <input type="text" class="form-control" id="lab_name" name="lab_name"
                            placeholder="Enter laboratory name" required>
                    </div>

                    <div class="col-md-6">
                        <label for="location" class="form-label">
                            Location *
                        </label>

                        <input type="text" class="form-control" id="location" name="location"
                            placeholder="Enter laboratory location" required>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">
                            Description
                        </label>

                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Enter laboratory details"></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" name="add_lab" class="btn btn-info text-white">
                            <i class="bi bi-plus-lg"></i> Add Laboratory
                        </button>
                    </div>

                </div>
            </form>
        </div>

        <!-- Laboratory inventory -->
        <div class="content-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h5 class="mb-1">Laboratory Inventory</h5>
                    <p class="text-muted mb-0">
                        View laboratory locations and equipment counts.
                    </p>
                </div>

                <button type="button" onclick="window.print()" class="btn btn-outline-info no-print">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>

            <form method="GET" action="laboratories.php" class="row g-3 mb-4 no-print">

                <div class="col-md-9">
                    <label for="search" class="form-label">
                        Search Laboratories
                    </label>

                    <input type="text" class="form-control" id="search" name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by name, location or description">
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-info text-white">
                        <i class="bi bi-search"></i> Search
                    </button>

                    <a href="laboratories.php" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Laboratory Name</th>
                            <th>Location</th>
                            <th>Description</th>
                            <th>Total Equipment</th>
                            <th>Working</th>
                            <th>Under Maintenance</th>
                            <th>Created At</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($laboratories && $laboratories->num_rows > 0): ?>
                            <?php while ($lab = $laboratories->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo (int) $lab["lab_id"]; ?></td>

                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($lab["lab_name"]); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($lab["location"]); ?>
                                    </td>

                                    <td class="description-cell">
                                        <?php
                                        echo !empty($lab["description"])
                                            ? nl2br(htmlspecialchars($lab["description"]))
                                            : '<span class="text-muted">No description</span>';
                                        ?>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-info">
                                            <?php echo (int) $lab["equipment_count"]; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-success">
                                            <?php echo (int) ($lab["working_count"] ?? 0); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge text-bg-warning">
                                            <?php echo (int) ($lab["maintenance_count"] ?? 0); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php
                                        echo !empty($lab["created_at"])
                                            ? htmlspecialchars(date("d M Y", strtotime($lab["created_at"])))
                                            : "N/A";
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No laboratories found.
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