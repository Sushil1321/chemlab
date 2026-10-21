
<?php
require_once "config/db.php";
require_once "includes/functions.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, name, email, password, role
         FROM users WHERE email = ? AND role = 'company_owner'"
    );

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user["password"])) {
        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["role"] = $user["role"];

        redirect("/chemistry_lab/company/dashboard.php");
    } else {
        $error = "Invalid email or password.";
    }

    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Owner Login | ChemLab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #102a43, #176b57);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 430px;
            background: #fff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 15px 45px rgba(0,0,0,.2);
        }
        .brand-icon {
            width: 65px;
            height: 65px;
            border-radius: 16px;
            background: #e6f6ed;
            color: #176b57;
            display: grid;
            place-items: center;
            margin: 0 auto 16px;
            font-size: 30px;
        }
        .btn-login {
            background: #176b57;
            color: #fff;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #10513f;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand-icon">🛠</div>
        <h3 class="fw-bold">Company Owner Login</h3>
        <p class="text-secondary">Manage assigned repair and maintenance work</p>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email address</label>
            <input type="email" name="email" class="form-control"
                   placeholder="Enter your email" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn btn-login w-100">
            Sign In
        </button>
    </form>

    <div class="text-center mt-4">
        <a href="admin-login.php">College Admin Login</a>
        <br>
        <a href="index.php" class="text-secondary small">Back to Home</a>
    </div>
</div>
</body>
</html>
