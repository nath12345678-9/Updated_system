<?php
// register.php
// Simple registration script using POST method
require_once 'db/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($username === '' || $password === '' || $confirm_password === '') {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Escape user input to prevent SQL injection
        $username_escaped = mysqli_real_escape_string($conn, $username);
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = '$username_escaped'";
        $result = mysqli_query($conn, $sql);
        if (mysqli_fetch_assoc($result)) {
            $error = 'Username already taken.';
        } else {
            // Insert new user (hash password)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $hashed_password_escaped = mysqli_real_escape_string($conn, $hashed_password);
            $sql_insert = "INSERT INTO users (username, password) VALUES ('$username_escaped', '$hashed_password_escaped')";
            if (mysqli_query($conn, $sql_insert)) {
                $success = 'Registration successful! You can now <a href="login.php">login</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="css/login-register.css">
</head>
<body>
    <h2>Register</h2>
    <?php if (!empty($error)): ?>
        <div style="color: red;"> <?= htmlspecialchars($error) ?> </div>
    <?php elseif (!empty($success)): ?>
        <div style="color: green;"> <?= $success ?> </div>
    <?php endif; ?>
    <div class="form-container">
        <form method="post" action="register.php">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required><br><br>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a>.</p>
    </div>
</body>
</html>
