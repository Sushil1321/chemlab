
<?php
require_once "config/db.php";

$name = "Maintenance Company Owner";
$email = "company@chemistrylab.com";
$password = "Company@123";
$role = "company_owner";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO users (name, email, password, role)
     VALUES (?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt, "ssss", $name, $email, $hashedPassword, $role
);

if (mysqli_stmt_execute($stmt)) {
    echo "Company account created successfully!<br>";
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password: " . htmlspecialchars($password);
} else {
    echo "Account could not be created. It may already exist.";
}

mysqli_stmt_close($stmt);
?>
