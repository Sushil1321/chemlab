
<?php
require_once "config/db.php";

$name = "College Admin";
$email = "admin@chemistrylab.com";
$password = "Admin@123";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = "college_admin";

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO users (name, email, password, role)
     VALUES (?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt, "ssss", $name, $email, $hashedPassword, $role
);

if (mysqli_stmt_execute($stmt)) {
    echo "Admin account created successfully!<br>";
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password: " . htmlspecialchars($password);
} else {
    echo "Could not create account. It may already exist.";
}

mysqli_stmt_close($stmt);
?>
