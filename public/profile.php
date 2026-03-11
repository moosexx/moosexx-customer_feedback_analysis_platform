<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "../php/config.php";

// Check if business profile already exists
$user_id = $_SESSION["id"];
$sql = "SELECT id FROM businesses WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// If business already exists, redirect to dashboard
if(mysqli_fetch_assoc($result)){
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    header("location: dashboard.php");
    exit;
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
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
            <h2>Set Up Your Business</h2>
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
        <div class="link-container" style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
            <a href="dashboard.php" style="background: var(--primary-color); color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">Go to Dashboard</a>
            <a href="../php/logout.php" style="background: transparent; color: var(--text-main); padding: 0.75rem 1.5rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; border: 1px solid var(--border-color);">Logout</a>
        </div>
    </div>
    <script src="js/auth.js"></script>
</body>
</html>
