<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Profile - Feedback Platform</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="centered-layout">
    <div class="container" style="max-width: 500px;">
        <div class="profile-header">
            <h2>Business Profile</h2>
            <p class="subtitle">Logged in as <strong><?php echo htmlspecialchars($_SESSION["email"]); ?></strong></p>
        </div>
        <form id="profileForm">
            <div class="form-group">
                <label for="business_name">Business Name</label>
                <input type="text" id="business_name" name="business_name" required>
            </div>
            <div class="form-group">
                <label for="industry">Industry</label>
                <select id="industry" name="industry">
                    <option value="retail">Retail</option>
                    <option value="food">Food & Beverage</option>
                    <option value="healthcare">Healthcare</option>
                    <option value="tech">Technology</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="description">Business Description</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            <button type="submit">Save Profile</button>
        </form>
        <div id="message"></div>
        <div class="link-container">
            <a href="../php/logout.php">Logout</a>
        </div>
    </div>
    <script src="js/auth.js"></script>
</body>
</html>
