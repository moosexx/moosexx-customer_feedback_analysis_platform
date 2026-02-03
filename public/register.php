<?php
session_start();
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: profile.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registration - Feedback Platform</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="centered-layout">
    <div class="container">
        <h2>Create an account</h2>
        <p class="subtitle">Join our platform to start collecting feedback</p>
        <form id="registerForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <div id="message"></div>
        <div class="link-container">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    <script src="js/auth.js"></script>
</body>
</html>
