<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "../php/config.php";

// Get business information - use $_SESSION["id"] instead of $_SESSION["user_id"]
$user_id = $_SESSION["id"];
$sql = "SELECT * FROM businesses WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$business = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// If no business exists, redirect to profile
if(!$business){
    header("location: profile.php");
    exit;
}

// Get feedback statistics
$business_id = $business['id'];

// Total feedback count
$total_feedback_sql = "SELECT COUNT(*) as total FROM feedback WHERE business_id = ?";
$stmt = mysqli_prepare($conn, $total_feedback_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$total_result = mysqli_stmt_get_result($stmt);
$total_feedback = mysqli_fetch_assoc($total_result)['total'];
mysqli_stmt_close($stmt);

// Average rating
$avg_rating_sql = "SELECT AVG(rating) as avg_rating FROM feedback WHERE business_id = ?";
$stmt = mysqli_prepare($conn, $avg_rating_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$avg_result = mysqli_stmt_get_result($stmt);
$avg_rating = mysqli_fetch_assoc($avg_result)['avg_rating'];
mysqli_stmt_close($stmt);
$avg_rating = $avg_rating ? round($avg_rating, 1) : 0;

// Recent feedback (last 7 days)
$recent_sql = "SELECT COUNT(*) as recent FROM feedback WHERE business_id = ? AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$recent_result = mysqli_stmt_get_result($stmt);
$recent_feedback = mysqli_fetch_assoc($recent_result)['recent'];
mysqli_stmt_close($stmt);

// Rating distribution
$distribution_sql = "SELECT rating, COUNT(*) as count FROM feedback WHERE business_id = ? GROUP BY rating ORDER BY rating";
$stmt = mysqli_prepare($conn, $distribution_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$distribution_result = mysqli_stmt_get_result($stmt);
$distribution = [];
while($row = mysqli_fetch_assoc($distribution_result)){
    $distribution[$row['rating']] = $row['count'];
}
mysqli_stmt_close($stmt);

// Category breakdown
$category_sql = "SELECT category, COUNT(*) as count FROM feedback WHERE business_id = ? AND category IS NOT NULL GROUP BY category ORDER BY count DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $category_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$category_result = mysqli_stmt_get_result($stmt);
$categories = [];
while($row = mysqli_fetch_assoc($category_result)){
    $categories[] = $row;
}
mysqli_stmt_close($stmt);

// Recent comments
$comments_sql = "SELECT * FROM feedback WHERE business_id = ? ORDER BY submitted_at DESC LIMIT 10";
$stmt = mysqli_prepare($conn, $comments_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$comments_result = mysqli_stmt_get_result($stmt);
$recent_comments = [];
while($row = mysqli_fetch_assoc($comments_result)){
    $recent_comments[] = $row;
}
mysqli_stmt_close($stmt);

// Trend data (feedback over time - last 30 days)
$trend_sql = "SELECT DATE(submitted_at) as date, COUNT(*) as count, AVG(rating) as avg_rating 
              FROM feedback 
              WHERE business_id = ? AND submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
              GROUP BY DATE(submitted_at) 
              ORDER BY date";
$stmt = mysqli_prepare($conn, $trend_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$trend_result = mysqli_stmt_get_result($stmt);
$trend_data = [];
while($row = mysqli_fetch_assoc($trend_result)){
    $trend_data[] = $row;
}
mysqli_stmt_close($stmt);

// Generate prescriptive recommendations based on actual data
$recommendations = [];

// Check for low average rating
if($avg_rating < 3.5 && $total_feedback > 0){
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'Improve Overall Satisfaction',
        'description' => 'Your average rating is below 3.5. Consider implementing customer service training and reviewing your service delivery processes.',
        'priority' => 'high'
    ];
}

// Check for service-related feedback dominance
if(!empty($categories)){
    $top_category = strtolower($categories[0]['category']);
    $top_category_count = $categories[0]['count'];
    if(in_array($top_category, ['service', 'staff', 'support']) && $top_category_count > ($total_feedback * 0.3)){
        $recommendations[] = [
            'type' => 'info',
            'title' => 'Focus on ' . ucfirst($categories[0]['category']) . ' Quality',
            'description' => ucfirst($categories[0]['category']) . '-related feedback is prominent (' . $top_category_count . ' mentions). Review your ' . $top_category . ' protocols and consider staff training programs.',
            'priority' => 'medium'
        ];
    }
}

// Check for recent feedback trends
if($total_feedback > 10){
    $expected_recent = $total_feedback / 4;
    if($recent_feedback < $expected_recent){
        $recommendations[] = [
            'type' => 'info',
            'title' => 'Increase Customer Engagement',
            'description' => 'Recent feedback (' . $recent_feedback . ') is lower than expected. Consider promoting QR code usage to gather more customer insights.',
            'priority' => 'medium'
        ];
    } else {
        $recommendations[] = [
            'type' => 'success',
            'title' => 'Maintain Current Strategy',
            'description' => 'Recent feedback trends are stable with ' . $recent_feedback . ' submissions this week. Continue your current approach and monitor for sustained improvement.',
            'priority' => 'low'
        ];
    }
}

// If no specific recommendations, provide general guidance
if(empty($recommendations)){
    $recommendations[] = [
        'type' => 'success',
        'title' => 'Excellent Performance',
        'description' => 'Your feedback metrics are strong. Continue engaging with customers and maintaining quality standards.',
        'priority' => 'low'
    ];
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FeedbackIQ</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- QR Code Library - Using qrcodejs -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Dashboard-specific navbar adjustments */
        .dashboard-container + .navbar {
            position: sticky;
            top: 0;
        }

        @media (max-width: 768px) {
            .nav-toggle {
                display: block;
                cursor: pointer;
                padding: 0.5rem;
            }

            .nav-toggle-checkbox:checked ~ .nav-links {
                max-height: 300px;
            }

            .nav-links {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            gap: 2rem;
        }

        .dashboard-title h1 {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(209, 244, 112, 0.2);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--text-main);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .stat-change {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--error-color);
        }

        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .insights-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .recommendations-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .recommendation-item {
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            border-left: 4px solid;
        }

        .recommendation-item.high {
            background: rgba(239, 68, 68, 0.05);
            border-left-color: var(--error-color);
        }

        .recommendation-item.medium {
            background: rgba(245, 158, 11, 0.05);
            border-left-color: #f59e0b;
        }

        .recommendation-item.low {
            background: rgba(16, 185, 129, 0.05);
            border-left-color: var(--success-color);
        }

        .recommendation-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .recommendation-desc {
            color: var(--text-muted);
            font-size: 0.9375rem;
            line-height: 1.5;
        }

        .comments-list {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .comment-item {
            padding: 1.25rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .comment-rating {
            display: flex;
            gap: 0.25rem;
            color: #fbbf24;
        }

        .comment-date {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .comment-text {
            color: var(--text-main);
            line-height: 1.6;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .charts-section,
            .insights-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
            }
        }

        /* QR Code Section */
        .qr-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
        }

        .qr-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .qr-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .qr-content {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2rem;
            align-items: center;
        }

        .qr-canvas-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            background: #f9fafb;
            border-radius: var(--radius-md);
        }

        .qr-canvas-container canvas {
            max-width: 100%;
            height: auto;
        }

        .qr-info h4 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-main);
        }

        .qr-info p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .qr-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .qr-actions button {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .qr-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .qr-canvas-container {
                order: -1;
            }

            .qr-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body class="centered-layout" style="background: var(--bg-muted); display: block;">
    <nav class="navbar">
        <label for="nav-toggle" class="nav-toggle" aria-label="Toggle menu">
            <i data-lucide="menu" style="width: 24px; height: 24px;"></i>
        </label>
        <input type="checkbox" id="nav-toggle" class="nav-toggle-checkbox" style="display: none;">
        <a href="dashboard.php" class="nav-logo" style="visibility: hidden; pointer-events: none;">
            <span style="font-size: 1.5rem; font-weight: 700;">FeedbackIQ</span>
        </a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="edit_profile.php">Profile</a>
            <a href="#" id="logoutBtn" class="btn-secondary">Logout</a>
        </div>
    </nav>


    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION["email"]); ?>!</h1>
                <p class="dashboard-subtitle"><?php echo htmlspecialchars($business['business_name']); ?> • <?php echo ucfirst($business['industry']); ?></p>
            </div>
        </div>

        <!-- QR Code Section -->
        <div class="qr-section">
            <div class="qr-header">
                <h2 class="qr-title">
                    <i data-lucide="qr-code" style="width: 28px; height: 28px;"></i>
                    Your Business QR Code
                </h2>
            </div>
            <div class="qr-content">
                <div class="qr-canvas-container">
                    <canvas id="qrCanvas"></canvas>
                </div>
                <div class="qr-info">
                    <h4>Share Your Feedback Link</h4>
                    <p>Scan this QR code with your phone camera to access the feedback form. Download it and display at your business location to start collecting customer insights.</p>
                    <div class="qr-actions">
                        <button id="downloadBtn" class="btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="download" style="width: 18px; height: 18px;"></i>
                            Download QR Code
                        </button>
                        <button id="copyLinkBtn" class="btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="copy" style="width: 18px; height: 18px;"></i>
                            Copy Link
                        </button>
                    </div>
                    <div id="copyMessage" style="margin-top: 0.75rem; color: var(--success-color); font-size: 0.875rem; display: none;">Link copied to clipboard!</div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="message-square" style="width: 24px; height: 24px;"></i>
                </div>
                <div class="stat-label">Total Feedback</div>
                <div class="stat-value"><?php echo number_format($total_feedback); ?></div>
                <div class="stat-change positive">
                    <i data-lucide="trending-up" style="width: 16px; height: 16px;"></i>
                    <span><?php echo number_format($recent_feedback); ?> this week</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="star" style="width: 24px; height: 24px;"></i>
                </div>
                <div class="stat-label">Average Rating</div>
                <div class="stat-value"><?php echo $avg_rating; ?>/5.0</div>
                <div class="stat-change <?php echo $avg_rating >= 4.0 ? 'positive' : 'negative'; ?>">
                    <?php if($avg_rating >= 4.0): ?>
                        <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i>
                        <span>Excellent performance</span>
                    <?php else: ?>
                        <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i>
                        <span>Needs improvement</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="activity" style="width: 24px; height: 24px;"></i>
                </div>
                <div class="stat-label">Satisfaction Score</div>
                <div class="stat-value"><?php echo $avg_rating > 0 ? round(($avg_rating / 5) * 100) : 0; ?>%</div>
                <div class="stat-change positive">
                    <span>Based on all ratings</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i data-lucide="calendar" style="width: 24px; height: 24px;"></i>
                </div>
                <div class="stat-label">Active Since</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo date('M Y', strtotime($business['created_at'])); ?></div>
                <div class="stat-change">
                    <span><?php echo floor((time() - strtotime($business['created_at'])) / 86400); ?> days ago</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Feedback Trends</h3>
                </div>
                <div class="chart-container">
                    <?php if(count($trend_data) > 0): ?>
                        <canvas id="trendChart"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i data-lucide="circle-help" class="empty-state-icon"></i>
                            <p>No feedback data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Rating Distribution</h3>
                </div>
                <div class="chart-container">
                    <?php if(count($distribution) > 0): ?>
                        <canvas id="distributionChart"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i data-lucide="pie-chart" class="empty-state-icon"></i>
                            <p>No ratings yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Insights & Recommendations -->
        <div class="insights-section">
            <div class="recommendations-card">
                <div class="chart-header">
                    <h3 class="chart-title">Prescriptive Recommendations</h3>
                    <i data-lucide="lightbulb" style="width: 20px; height: 20px; color: #fbbf24;"></i>
                </div>
                
                <?php foreach($recommendations as $rec): ?>
                    <div class="recommendation-item <?php echo $rec['priority']; ?>">
                        <div class="recommendation-title">
                            <?php if($rec['priority'] == 'high'): ?>
                                <i data-lucide="alert-triangle" style="width: 20px; height: 20px; color: var(--error-color);"></i>
                            <?php elseif($rec['priority'] == 'medium'): ?>
                                <i data-lucide="info" style="width: 20px; height: 20px; color: #f59e0b;"></i>
                            <?php else: ?>
                                <i data-lucide="check-circle" style="width: 20px; height: 20px; color: var(--success-color);"></i>
                            <?php endif; ?>
                            <?php echo $rec['title']; ?>
                        </div>
                        <p class="recommendation-desc"><?php echo $rec['description']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Top Categories</h3>
                </div>
                <?php if(count($categories) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach($categories as $cat): ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="font-weight: 500;"><?php echo ucfirst($cat['category']); ?></span>
                                    <span style="color: var(--text-muted);"><?php echo $cat['count']; ?></span>
                                </div>
                                <div style="background: var(--bg-muted); height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: var(--primary-color); height: 100%; width: <?php echo ($cat['count'] / $total_feedback) * 100; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No categories available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Comments -->
        <div class="comments-list">
            <div class="chart-header">
                <h3 class="chart-title">Recent Customer Feedback</h3>
                <span style="font-size: 0.875rem; color: var(--text-muted);">Last 10 submissions</span>
            </div>

            <?php if(count($recent_comments) > 0): ?>
                <?php foreach($recent_comments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <div class="comment-rating">
                                <?php for($i = 0; $i < 5; $i++): ?>
                                    <i data-lucide="star" style="width: 16px; height: 16px; <?php echo $i < $comment['rating'] ? 'fill: currentColor;' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="comment-date"><?php echo date('M d, Y', strtotime($comment['submitted_at'])); ?></span>
                        </div>
                        <?php if($comment['category']): ?>
                            <div style="margin-bottom: 0.75rem;">
                                <span style="background: var(--bg-muted); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                    <?php echo ucfirst($comment['category']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <p class="comment-text"><?php echo htmlspecialchars($comment['comment']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="inbox" class="empty-state-icon"></i>
                    <p>No feedback received yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        // Logout confirmation
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../php/logout.php';
            }
        });
        
        // Generate QR Code for business
        const businessId = <?php echo $business_id; ?>;
        const surveyUrl = window.location.origin + '/public/feedback_form.php?business_id=' + businessId;
        
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            const canvasContainer = document.getElementById('qrCanvas');
            if (canvasContainer && typeof QRCode !== 'undefined') {
                // Clear any existing QR code
                canvasContainer.innerHTML = '';
                
                // Create a new div for the QR code to ensure proper rendering
                const qrDiv = document.createElement('div');
                qrDiv.style.display = 'inline-block';
                
                // Generate new QR code using qrcodejs library
                const qrCodeObj = new QRCode(qrDiv, {
                    text: surveyUrl,
                    width: 200,
                    height: 200,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
                
                // Append to container
                canvasContainer.parentNode.replaceChild(qrDiv, canvasContainer);
            } else if (canvasContainer) {
                console.error('QRCode library not loaded');
            }
        });
        
        // Download QR Code
        document.getElementById('downloadBtn').addEventListener('click', () => {
            const qrContainer = document.getElementById('qrCanvas');
            if (qrContainer) {
                const canvas = qrContainer.querySelector('canvas');
                if (canvas) {
                    const link = document.createElement('a');
                    link.download = 'feedback-qr-code.png';
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                }
            }
        });
        
        // Copy Link
        document.getElementById('copyLinkBtn').addEventListener('click', () => {
            navigator.clipboard.writeText(surveyUrl).then(() => {
                const message = document.getElementById('copyMessage');
                message.style.display = 'block';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 3000);
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        });
        
        // Initialize charts
        <?php if(count($trend_data) > 0): ?>
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach($trend_data as $data): ?>'<?php echo date('M d', strtotime($data['date'])); ?>',<?php endforeach; ?>],
                datasets: [{
                    label: 'Feedback Count',
                    data: [<?php foreach($trend_data as $data): echo $data['count']; ?>,<?php endforeach; ?>],
                    borderColor: '#d1f470',
                    backgroundColor: 'rgba(209, 244, 112, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if(count($distribution) > 0): ?>
        const distCtx = document.getElementById('distributionChart').getContext('2d');
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php foreach($distribution as $rating => $count): ?>'<?php echo $rating; ?> Stars',<?php endforeach; ?>],
                datasets: [{
                    data: [<?php foreach($distribution as $count): echo $count; ?>,<?php endforeach; ?>],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(5, 150, 105, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
