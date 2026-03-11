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

// Generate prescriptive recommendations
$recommendations = [];

if($avg_rating < 3.5 && $total_feedback > 0){
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'Improve Overall Satisfaction',
        'description' => 'Your average rating is below 3.5. Consider implementing customer service training and reviewing your service delivery processes.',
        'priority' => 'high'
    ];
}

if(isset($categories[0]) && strtolower($categories[0]['category']) == 'service' && $categories[0]['count'] > ($total_feedback * 0.3)){
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Focus on Service Quality',
        'description' => 'Service-related feedback is prominent. Review your service protocols and consider staff training programs.',
        'priority' => 'medium'
    ];
}

if($recent_feedback < ($total_feedback / 4) && $total_feedback > 10){
    $recommendations[] = [
        'type' => 'success',
        'title' => 'Maintain Current Strategy',
        'description' => 'Recent feedback trends are stable. Continue your current approach and monitor for sustained improvement.',
        'priority' => 'low'
    ];
}

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
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Thin Navigation */
        .navbar {
            background: white;
            padding: 0.75rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: 1.25rem;
        }

        .logo-img {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 10px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            transition: color 0.2s;
            padding: 0.5rem 0;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: 0.5rem;
            background: transparent;
            border: none;
        }

        .hamburger span {
            width: 24px;
            height: 2px;
            background: var(--text-main);
            border-radius: 2px;
            transition: all 0.3s;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
            }

            .hamburger {
                display: flex;
            }

            .nav-links {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                gap: 0;
                padding: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .nav-links.active {
                max-height: 200px;
            }

            .nav-links a {
                padding: 1rem 2rem;
                border-bottom: 1px solid var(--border-color);
                width: 100%;
                display: block;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="centered-layout" style="background: var(--bg-muted); display: block;">
    <nav class="navbar">
        <a href="index.php" class="nav-logo">
            <img src="images/logo.jpg" alt="FeedbackIQ" class="logo-img">
        </a>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="nav-links" id="navLinks">
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="../php/logout.php" class="btn-secondary">Logout</a>
        </div>
    </nav>

    <script>
        // Hamburger menu toggle
        const hamburger = document.getElementById('hamburger');
        const navLinks = document.getElementById('navLinks');
        
        if(hamburger) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navLinks.classList.toggle('active');
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                    hamburger.classList.remove('active');
                    navLinks.classList.remove('active');
                }
            });
        }
    </script>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION["email"]); ?>!</h1>
                <p class="dashboard-subtitle"><?php echo htmlspecialchars($business['business_name']); ?> • <?php echo ucfirst($business['industry']); ?></p>
            </div>
            <div class="header-actions">
                <button class="btn-secondary" onclick="window.print()">
                    <i data-lucide="printer"></i> Export Report
                </button>
                <a href="profile.php" class="btn-primary">
                    <i data-lucide="settings"></i> Manage Profile
                </a>
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
