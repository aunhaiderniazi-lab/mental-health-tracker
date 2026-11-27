<?php
session_start();
include 'connection.php';

if (isset($_SESSION['user_id'])) {
    header("Location: final.php");
    exit();
}

$error = "";

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['login_lock_until'])) {
    $_SESSION['login_lock_until'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        http_response_code(403);
        $error = "Invalid form token. Please reload the page and try again.";
    } elseif (time() < $_SESSION['login_lock_until']) {
        $error = "Too many login attempts. Try again in "
            . ($_SESSION['login_lock_until'] - time()) . " seconds.";
    } elseif (isset($_POST['login'])) {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            $stmt = $conn->prepare("SELECT id, user_name, password FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password'])) {
                    session_regenerate_id(true);   // prevent session fixation
                    $_SESSION['user_id']   = $row['id'];
                    $_SESSION['user_name'] = $row['user_name'];
                    unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
                    header("Location: final.php");
                    exit();
                }
            }
            $error = "Invalid email or password.";   // generic: no account enumeration
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_lock_until'] = time() + 300;
                $_SESSION['login_attempts'] = 0;
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Mental Health Tracker</title>
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
        .login-box {
            max-width: 430px;
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
<div class="login-box shadow">
    <div class="text-center mb-4">
        <i class="fas fa-brain fa-2x gold-text mb-2"></i>
        <h4 class="fw-bold mb-1">Mental Health Tracker</h4>
        <p class="text-secondary small">Sign in to view your journal & mood charts</p>
    </div>

    <?php if ($error != ""): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" name="login" class="btn btn-maroon w-100 py-2 fw-semibold">Login</button>
    </form>

    <p class="text-center text-secondary small mt-3 mb-0">
        New here? <a href="user_register.php" class="gold-text text-decoration-none">Create an account</a>
    </p>
</div>
</body>
</html>
