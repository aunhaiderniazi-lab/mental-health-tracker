<?php
session_start();
include 'connection.php';

// Already logged in? Send straight to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: final.php");
    exit();
}

$msg = "";
$msgType = "danger";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        http_response_code(403);
        $msg = "Invalid form token. Please reload the page and try again.";
    } elseif (isset($_POST['register'])) {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $address  = trim($_POST['address'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $msg = "Please fill in your name, email, and password.";
        } elseif (strlen($password) < 12) {
            $msg = "Password should be at least 12 characters long.";
        } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $msg = "That email is already registered. Try logging in instead.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (user_name, email, password, address) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashedPassword, $address);

            if ($stmt->execute()) {
                $msg = "Account created! You can log in now.";
                $msgType = "success";
            } else {
                $msg = "Something went wrong. Please try again.";
            }
            $stmt->close();
            }
            $check->close();
        }
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Mental Health Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #140b0e, #2b0d16);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-box {
            max-width: 450px;
            width: 100%;
            background: #1f1216;
            border: 1px solid #4a232e;
            border-radius: 14px;
            padding: 32px;
        }
        .btn-maroon {
            background-color: #7a1c32;
            border-color: #7a1c32;
            color: #fff;
        }
        .btn-maroon:hover {
            background-color: #5e1426;
            border-color: #5e1426;
            color: #fff;
        }
        .gold-text { color: #d4a359; }
    </style>
</head>
<body>
<div class="register-box shadow">
    <div class="text-center mb-4">
        <i class="fas fa-brain fa-2x gold-text mb-2"></i>
        <h4 class="fw-bold mb-1">Create Account</h4>
        <p class="text-secondary small">Join Mental Health Tracker to log your daily mood</p>
    </div>

    <?php if ($msg != ""): ?>
        <div class="alert alert-<?= $msgType ?> py-2">
            <?= htmlspecialchars($msg) ?>
            <?php if ($msgType === "success"): ?>
                <a href="index.php" class="alert-link">Login here</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="user_register.php">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" minlength="12" required>
        </div>
        <div class="mb-4">
            <label class="form-label">City / Address</label>
            <input type="text" name="address" class="form-control" placeholder="Optional">
        </div>
        <button type="submit" name="register" class="btn btn-maroon w-100 py-2 fw-semibold">Register</button>
    </form>

    <p class="text-center text-secondary small mt-3 mb-0">
        Already registered? <a href="index.php" class="gold-text text-decoration-none">Sign in</a>
    </p>
</div>
</body>
</html>
