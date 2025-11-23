<?php
// login.php
// Simple login script using POST method and session
session_start();

require_once 'db/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Escape user input to prevent SQL injection
        $username_escaped = mysqli_real_escape_string($conn, $username);
        $sql = "SELECT id, username, password FROM users WHERE username = '$username_escaped'";
        $result = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            // Verify password (assuming password is hashed)
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="css/login-register.css">
</head>
<body>
    <h2>Login</h2>
    <?php if (!empty($error)): ?>
        <div style="color: red;"> <?= htmlspecialchars($error) ?> </div>
    <?php endif; ?>
    <div class="form-container">
        <form method="post" action="login.php">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="register.php">Register here</a>.</p>
    </div>
</body>
</html>
