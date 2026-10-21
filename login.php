<?php
require_once "config/db.php";
require_once "includes/functions.php";

$error = "";

// Redirect users who are already logged in.
if (isset($_SESSION["user_id"], $_SESSION["role"])) {
    if ($_SESSION["role"] === "college_admin") {
        header("Location: admin/dashboard.php");
        exit;
    }

    if ($_SESSION["role"] === "company_owner") {
        header("Location: company/dashboard.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            $stmt->close();

            if ($user && password_verify($password, $user["password"])) {
                if (
                    $user["role"] !== "college_admin" &&
                    $user["role"] !== "company_owner"
                ) {
                    $error = "Your account does not have permission to log in.";
                } else {
                    // Prevent session fixation.
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = (int) $user["user_id"];
                    $_SESSION["name"] = $user["name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    if ($user["role"] === "college_admin") {
                        header("Location: admin/dashboard.php");
                        exit;
                    }

                    header("Location: company/dashboard.php");
                    exit;
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Unable to process login. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | ChemLab</title>

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
            min-height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f7fb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            background: white;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 8px 35px rgba(16, 42, 67, 0.10);
        }

        .brand-icon {
            width: 65px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #d8f7f2;
            color: #087f8c;
            border-radius: 16px;
            font-size: 32px;
            margin: 0 auto 15px;
        }

        .brand-title {
            color: #102a43;
            font-weight: 700;
        }

        .subtitle {
            color: #627d98;
        }

        .form-control {
            padding: 12px;
            border-radius: 9px;
        }

        .btn-login {
            background: #087f8c;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 9px;
            font-weight: 600;
        }

        .btn-login:hover {
            background: #066974;
            color: white;
        }

        .input-group-text {
            background: #f0f5f9;
        }

        .footer-text {
            color: #829ab1;
            font-size: 13px;
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="text-center mb-4">
        <div class="brand-icon">
            <i class="bi bi-droplet-half"></i>
        </div>

        <h2 class="brand-title">ChemLab</h2>

        <p class="subtitle mb-0">
            Chemistry Lab Equipment &amp; Maintenance Management
        </p>

        <hr class="my-4">

        <h4 class="fw-bold">Welcome Back</h4>

        <p class="text-muted">
            Sign in to access your account
        </p>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="mb-3">
            <label for="email" class="form-label">
                Email Address
            </label>

            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-envelope"></i>
                </span>

                <input
                    type="email"
                    class="form-control"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                    autocomplete="username"
                    required
                >
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">
                Password
            </label>

            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-lock"></i>
                </span>

                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button
                    class="btn btn-outline-secondary"
                    type="button"
                    id="togglePassword"
                    aria-label="Show password"
                >
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        <button
            type="submit"
            class="btn btn-login w-100"
        >
            <i class="bi bi-box-arrow-in-right"></i>
            Sign In
        </button>

    </form>

    <div class="text-center mt-4">
        <p class="footer-text mb-0">
            &copy; <?php echo date("Y"); ?> ChemLab
        </p>
    </div>

</div>

<script>
document.getElementById("togglePassword").addEventListener("click", function () {
    const password = document.getElementById("password");
    const icon = this.querySelector("i");

    if (password.type === "password") {
        password.type = "text";
        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
        this.setAttribute("aria-label", "Hide password");
    } else {
        password.type = "password";
        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
        this.setAttribute("aria-label", "Show password");
    }
});
</script>

</body>
</html>