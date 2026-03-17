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

// Get user information for display name
$user_sql = "SELECT first_name, middle_name, last_name, email FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

// Build full name from separate fields with email fallback
$first_name = $user_data['first_name'] ?? '';
$middle_name = $user_data['middle_name'] ?? '';
$last_name = $user_data['last_name'] ?? '';
$user_email = $user_data['email'] ?? '';

// Concatenate names (skip empty middle name)
$full_name_parts = array_filter([$first_name, $middle_name, $last_name], function($part) {
    return !empty(trim($part));
});
$has_valid_name = !empty($full_name_parts);

if ($has_valid_name) {
    // Use first and last name for greeting
    $display_first = $first_name ?: ($middle_name ?: '');
    $display_last = $last_name ?: (count($full_name_parts) > 2 ? end($full_name_parts) : '');
} else {
    $display_first = '';
    $display_last = '';
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

// Category breakdown with ratings
$category_sql = "SELECT category, COUNT(*) as count, AVG(rating) as avg_rating FROM feedback WHERE business_id = ? AND category IS NOT NULL GROUP BY category ORDER BY count DESC LIMIT 5";
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

// Check if there's any feedback at all
if ($total_feedback === 0) {
    // No feedback received yet - show onboarding/encouragement recommendations
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Start Collecting Feedback',
        'description' => 'You haven\'t received any feedback yet. Share your QR code with customers or display it prominently at your business location to start gathering valuable insights.',
        'priority' => 'high'
    ];
    
    $recommendations[] = [
        'type' => 'success',
        'title' => 'Best Practices for Getting Started',
        'description' => 'Place QR codes at checkout counters, tables, or exit points. Encourage feedback by mentioning it to customers. Aim to collect your first 10 responses within the first week!',
        'priority' => 'medium'
    ];
} else {
    // Use advanced analytics engine if available
    if (file_exists('../php/advanced_analytics.php')) {
        require_once '../php/advanced_analytics.php';
        
        // Try Python engine first for even more advanced analysis
        if (file_exists('../php/python_bridge.php')) {
            require_once '../php/python_bridge.php';
            $python_data = [
                'total_feedback' => $total_feedback,
                'avg_rating' => $avg_rating,
                'recent_feedback' => $recent_feedback,
                'distribution' => $distribution,
                'categories' => $categories,
                'trend_data' => $trend_data
            ];
            $python_recommendations = run_python_analytics($python_data);
            if ($python_recommendations && !empty($python_recommendations)) {
                $recommendations = $python_recommendations;
            } else {
                // Fallback to PHP advanced analytics
                $recommendations = generate_advanced_recommendations(
                    $conn, 
                    $business_id, 
                    $total_feedback, 
                    $avg_rating, 
                    $recent_feedback, 
                    $distribution, 
                    $categories, 
                    $trend_data
                );
            }
        } else {
            // Use PHP advanced analytics
            $recommendations = generate_advanced_recommendations(
                $conn, 
                $business_id, 
                $total_feedback, 
                $avg_rating, 
                $recent_feedback, 
                $distribution, 
                $categories, 
                $trend_data
            );
        }
    } else {
        // Fallback to basic recommendations

        // Check for low average rating
        if($avg_rating < 3.5 && $total_feedback > 0){
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Improve Overall Satisfaction',
                'description' => 'Your average rating is below 3.5. Consider implementing customer service training and reviewing your service delivery processes.',
                'priority' => 'high'
            ];
        }

        // Check for service-related feedback dominance and quality
        if(!empty($categories)){
            $top_category = strtolower($categories[0]['category']);
            $top_category_count = $categories[0]['count'];
            $top_category_avg_rating = $categories[0]['avg_rating'];
            
            if(in_array($top_category, ['service', 'staff', 'support']) && $top_category_count > ($total_feedback * 0.3)){
                // Only show warning if the average rating is low (below 3.5)
                if($top_category_avg_rating < 3.5){
                    $recommendations[] = [
                        'type' => 'warning',
                        'title' => 'Improve ' . ucfirst($categories[0]['category']) . ' Quality',
                        'description' => ucfirst($categories[0]['category']) . '-related feedback has low ratings (' . round($top_category_avg_rating, 2) . '/5.0 average). Review your ' . $top_category . ' protocols and consider staff training programs.',
                        'priority' => 'high'
                    ];
                } else {
                    // Show positive reinforcement if ratings are good
                    $recommendations[] = [
                        'type' => 'success',
                        'title' => 'Excellent ' . ucfirst($categories[0]['category']) . ' Performance',
                        'description' => ucfirst($categories[0]['category']) . ' is receiving high volume of feedback (' . $top_category_count . ' mentions) with strong ratings (' . round($top_category_avg_rating, 2) . '/5.0). Keep up the great work!',
                        'priority' => 'low'
                    ];
                }
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

    } // End of else block for basic recommendations
}

// Filter out dismissed recommendations
if (!empty($recommendations)) {
    // Get dismissed recommendations hashes for this user
    $dismissed_sql = "SELECT recommendation_hash FROM dismissed_recommendations WHERE user_id = ?";
    $dismissed_stmt = mysqli_prepare($conn, $dismissed_sql);
    mysqli_stmt_bind_param($dismissed_stmt, "i", $user_id);
    mysqli_stmt_execute($dismissed_stmt);
    $dismissed_result = mysqli_stmt_get_result($dismissed_stmt);
    
    $dismissed_hashes = [];
    while ($row = mysqli_fetch_assoc($dismissed_result)) {
        $dismissed_hashes[] = $row['recommendation_hash'];
    }
    mysqli_stmt_close($dismissed_stmt);
    
    // Filter recommendations by removing dismissed ones
    $filtered_recommendations = [];
    foreach ($recommendations as $recommendation) {
        $recommendation_hash = hash('sha256', $recommendation['title'] . $recommendation['type'] . $recommendation['priority']);
        if (!in_array($recommendation_hash, $dismissed_hashes)) {
            $filtered_recommendations[] = $recommendation;
        }
    }
    $recommendations = $filtered_recommendations;
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

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 0.75rem;
            }
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

            .nav-links {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                background: white;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                padding: 4rem 1.5rem 2rem;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1000;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .nav-toggle-checkbox:checked ~ .nav-links {
                left: 0;
            }

            /* Overlay */
            .nav-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .nav-toggle-checkbox:checked ~ .nav-overlay {
                display: block;
                opacity: 1;
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
            overflow: hidden; /* Prevent content overflow */
            min-width: 0; /* Allow card to shrink below content size */
        }

        @media (max-width: 768px) {
            .chart-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .chart-card {
                padding: 1rem;
            }
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap; /* Allow wrapping on small screens */
        }

        @media (max-width: 768px) {
            .chart-header {
                margin-bottom: 1rem;
            }
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            flex-shrink: 1; /* Allow title to shrink if needed */
            min-width: 0; /* Prevent overflow */
        }

        @media (max-width: 768px) {
            .chart-title {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            .chart-title {
                font-size: 1rem;
            }
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%; /* Ensure container doesn't exceed parent width */
            max-width: 100%; /* Prevent overflow */
            overflow: hidden; /* Clip any overflowing chart elements */
        }

        @media (max-width: 1024px) {
            .chart-container {
                height: 250px;
            }
        }

        @media (max-width: 768px) {
            .chart-container {
                height: 220px;
            }

            .chart-card {
                padding: 1.5rem;
            }

            .chart-title {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            .chart-container {
                height: 200px;
            }
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
            position: relative;
            overflow: hidden;
        }

        .recommendations-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color) 0%, #c2e662 100%);
        }

        .recommendations-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .recommendations-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .recommendations-title h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .recommendations-badge {
            background: var(--primary-color);
            color: var(--text-main);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .recommendations-filter-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--bg-muted);
            border-radius: var(--radius-lg);
            flex-wrap: wrap;
        }

        .filter-label {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-main);
            margin: 0;
            white-space: nowrap;
        }

        .filter-dropdown {
            padding: 0.625rem 2.5rem 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: white;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 200px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.25rem;
        }

        .filter-dropdown:hover {
            border-color: var(--text-main);
            box-shadow: 0 0 0 3px rgba(17, 17, 16, 0.1);
        }

        .filter-dropdown:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(209, 244, 112, 0.2);
        }

        .recommendations-list {
            min-height: 200px;
        }

        .recommendation-pagination {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .pagination-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: nowrap;
        }

        .pagination-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-main);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.1875rem;
            min-width: auto;
        }

        .pagination-btn:hover:not(:disabled) {
            background: var(--bg-muted);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: var(--text-main);
            color: white;
            border-color: var(--text-main);
        }

        .pagination-info {
            font-size: 0.875rem;
            color: var(--text-muted);
            padding: 0 1rem;
        }

        .categories-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-height: fit-content; /* Only expand as needed */
        }

        .recommendation-item {
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            border-left: 4px solid;
            position: relative;
            background: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            overflow: hidden;
            max-width: 100%;
            word-wrap: break-word;
        }

        .recommendation-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .recommendation-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .recommendation-item:hover::before {
            opacity: 0.03;
        }

        .recommendation-item.high {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.02) 0%, rgba(239, 68, 68, 0.05) 100%);
            border-left-color: var(--error-color);
        }

        .recommendation-item.high::before {
            background: var(--error-color);
        }

        .recommendation-item.medium {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.02) 0%, rgba(245, 158, 11, 0.05) 100%);
            border-left-color: #f59e0b;
        }

        .recommendation-item.medium::before {
            background: #f59e0b;
        }

        .recommendation-item.low {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.02) 0%, rgba(16, 185, 129, 0.05) 100%);
            border-left-color: var(--success-color);
        }

        .recommendation-item.low::before {
            background: var(--success-color);
        }

        .recommendation-priority-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: blink 2s infinite;
        }

        .recommendation-item.high .recommendation-priority-indicator {
            background: var(--error-color);
        }

        .recommendation-item.medium .recommendation-priority-indicator {
            background: #f59e0b;
        }

        .recommendation-item.low .recommendation-priority-indicator {
            background: var(--success-color);
        }

        /* Enhanced recommendation interactions */
        .recommendation-item {
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .recommendation-item:nth-child(2) {
            animation-delay: 0.1s;
        }

        .recommendation-item:nth-child(3) {
            animation-delay: 0.2s;
        }

        .recommendation-item.completed {
            opacity: 0.6;
            background: var(--bg-muted);
            border-left-color: var(--success-color);
        }

        .recommendation-item.completed .recommendation-priority-indicator {
            background: var(--success-color);
            animation: none;
        }

        .recommendation-item.completed .recommendation-title {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .recommendation-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary-color);
            border-radius: 0 0 0 var(--radius-lg);
            transition: width 0.3s ease;
            z-index: 2;
        }

        .recommendation-item:hover .recommendation-progress {
            width: 100%;
        }

        /* Enhanced empty state */
        .recommendations-list .empty-state {
            background: linear-gradient(135deg, var(--bg-muted) 0%, rgba(209, 244, 112, 0.1) 100%);
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-lg);
            margin: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .recommendations-list .empty-state::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(209, 244, 112, 0.1), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%); }
            100% { transform: translateX(100%) translateY(100%); }
        }

        .recommendations-list .empty-state .empty-state-icon {
            position: relative;
            z-index: 1;
            opacity: 0.3;
        }

        .recommendations-list .empty-state p {
            position: relative;
            z-index: 1;
            font-weight: 500;
            color: var(--text-muted);
        }

        .recommendation-title {
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 1rem;
            line-height: 1.4;
            position: relative;
            z-index: 1;
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
        }

        .recommendation-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.8125rem;
            color: var(--text-muted);
            position: relative;
            z-index: 1;
        }

        .recommendation-timestamp {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .recommendation-impact {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
        }

        .recommendation-impact.high {
            color: var(--error-color);
        }

        .recommendation-impact.medium {
            color: #f59e0b;
        }

        .recommendation-impact.low {
            color: var(--success-color);
        }

        .recommendation-desc {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            position: relative;
            z-index: 1;
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            overflow-wrap: break-word;
        }

        .recommendation-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            position: relative;
            z-index: 1;
            flex-wrap: nowrap;
            align-items: center;
            opacity: 1;
            transform: translateY(0);
            transition: all 0.3s ease;
        }


        .recommendation-action-btn {
            padding: 0.5rem 0.875rem;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-main);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            white-space: nowrap;
            flex-shrink: 1;
            min-width: fit-content;
        }

        .recommendation-action-btn:hover {
            background: var(--bg-muted);
            transform: translateY(-1px);
        }

        .recommendation-action-btn.primary {
            background: var(--primary-color);
            color: var(--text-main);
            border-color: var(--primary-color);
        }

        .recommendation-action-btn.primary:hover {
            background: var(--primary-hover);
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

            .dashboard-header {
                margin-bottom: 2rem;
            }
        }

        @media (max-width: 768px) {
            .recommendations-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .recommendations-title h3 {
                font-size: 1.125rem;
            }

            .recommendations-filter-container {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
                padding: 0.75rem;
            }

            .filter-label {
                font-size: 0.8125rem;
                margin-bottom: 0.25rem;
            }

            .filter-dropdown {
                min-width: 100%;
                padding: 0.75rem 2.5rem 0.75rem 1rem;
                font-size: 0.875rem;
            }

            .recommendation-item {
                padding: 1.25rem;
                margin-bottom: 0.875rem;
            }

            .recommendation-item:hover {
                transform: translateX(4px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            }

            .recommendation-title {
                font-size: 0.9375rem;
                gap: 0.5rem;
                line-height: 1.3;
            }

            .recommendation-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                margin-bottom: 0.75rem;
            }

            .recommendation-desc {
                font-size: 0.875rem;
                line-height: 1.5;
            }

            .recommendation-actions {
                flex-wrap: nowrap;
                gap: 0.375rem;
                overflow-x: auto;
                padding-bottom: 0.25rem;
            }

            .recommendation-action-btn {
                flex: 0 0 auto;
                min-width: fit-content;
                justify-content: center;
                padding: 0.4375rem 0.625rem;
                font-size: 0.75rem;
                gap: 0.25rem;
            }

            .recommendation-action-btn span {
                display: inline;
            }

            .recommendation-priority-indicator {
                top: 0.75rem;
                right: 0.75rem;
                width: 6px;
                height: 6px;
            }
        }

        @media (max-width: 480px) {
            .recommendations-card {
                padding: 1.25rem;
            }

            .recommendations-badge {
                font-size: 0.6875rem;
                padding: 0.1875rem 0.625rem;
            }

            .recommendations-filter-container {
                padding: 0.625rem;
                gap: 0.5rem;
            }

            .filter-label {
                font-size: 0.75rem;
            }

            .filter-dropdown {
                padding: 0.625rem 2.25rem 0.625rem 0.875rem;
                font-size: 0.8125rem;
            }

            .recommendation-item {
                padding: 1rem;
                margin-bottom: 0.75rem;
            }

            .recommendation-title {
                font-size: 0.875rem;
                line-height: 1.3;
            }

            .recommendation-desc {
                font-size: 0.8125rem;
                line-height: 1.5;
            }

            .recommendation-actions {
                flex-wrap: nowrap;
                gap: 0.375rem;
                overflow-x: auto;
                padding-bottom: 0.25rem;
                -webkit-overflow-scrolling: touch;
            }

            .recommendation-action-btn {
                flex: 0 0 auto;
                min-width: fit-content;
                justify-content: center;
                padding: 0.375rem 0.5rem;
                font-size: 0.6875rem;
                gap: 0.1875rem;
            }

            .recommendation-action-btn span {
                display: inline;
            }

            .recommendation-priority-indicator {
                top: 0.75rem;
                right: 0.75rem;
                width: 6px;
                height: 6px;
            }

            .pagination-btn {
                padding: 0.25rem 0.375rem;
                font-size: 0.6875rem;
                min-width: auto;
            }

            .pagination-info {
                font-size: 0.75rem;
                padding: 0 0.75rem;
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
        <div class="nav-overlay"></div>
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
                <h1>Welcome back, <?php 
                    if ($has_valid_name && !empty($display_first)) {
                        echo htmlspecialchars($display_first . ($display_last ? ' ' . $display_last : ''));
                    } else {
                        // Fallback to email username if no valid name
                        $email_username = explode('@', $user_email)[0];
                        echo htmlspecialchars(ucfirst($email_username));
                    }
                ?>!</h1>
                <p class="dashboard-subtitle"><?php echo htmlspecialchars($business['business_name']); ?> • <?php echo ucfirst($business['industry']); ?></p>
            </div>
            <div class="header-actions">
                <a href="feedback_form.php?business_id=<?php echo $business_id; ?>&view=preview" 
                   class="btn-primary" 
                   style="display: inline-flex; align-items: center; gap: 0.5rem;"
                   target="_blank">
                    <i data-lucide="external-link" style="width: 18px; height: 18px;"></i>
                    View Feedback Form
                </a>
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
                <div class="recommendations-header">
                    <div class="recommendations-title">
                        <h3>Prescriptive Recommendations</h3>
                        <span class="recommendations-badge" id="recommendationCount"><?php echo count($recommendations); ?> Active</span>
                    </div>
                    <i data-lucide="lightbulb" style="width: 24px; height: 24px; color: #fbbf24;"></i>
                </div>
                
                <!-- Filter Dropdown -->
                <div class="recommendations-filter-container">
                    <?php 
                    // Get current filter from query parameter or default to 'all'
                    $current_filter = isset($_GET['rec_filter']) ? $_GET['rec_filter'] : 'all';
                    
                    // Count recommendations by priority
                    $high_count = count(array_filter($recommendations, function($r) { return $r['priority'] === 'high'; }));
                    $medium_count = count(array_filter($recommendations, function($r) { return $r['priority'] === 'medium'; }));
                    $low_count = count(array_filter($recommendations, function($r) { return $r['priority'] === 'low'; }));
                    ?>
                    <label for="recommendationFilter" class="filter-label">
                        <i data-lucide="filter" style="width: 16px; height: 16px; margin-right: 0.5rem;"></i>
                        Filter by Priority:
                    </label>
                    <select id="recommendationFilter" class="filter-dropdown" onchange="changeFilter(this.value)">
                        <option value="all" <?php echo $current_filter === 'all' ? 'selected' : ''; ?>>
                            All Recommendations (<?php echo count($recommendations); ?>)
                        </option>
                        <option value="high" <?php echo $current_filter === 'high' ? 'selected' : ''; ?>>
                            High Priority (<?php echo $high_count; ?>)
                        </option>
                        <option value="medium" <?php echo $current_filter === 'medium' ? 'selected' : ''; ?>>
                            Medium Priority (<?php echo $medium_count; ?>)
                        </option>
                        <option value="low" <?php echo $current_filter === 'low' ? 'selected' : ''; ?>>
                            Low Priority (<?php echo $low_count; ?>)
                        </option>
                    </select>
                </div>

                <div class="recommendations-list" id="recommendationsList">
                    <?php 
                    // Pagination settings
                    $items_per_page = 3;
                    $total_recommendations = count($recommendations);
                    $total_pages = max(1, ceil($total_recommendations / $items_per_page));
                    
                    // Get current page from query parameter or default to 1
                    $current_page = isset($_GET['rec_page']) ? max(1, min(intval($_GET['rec_page']), $total_pages)) : 1;
                    
                    // Filter recommendations
                    $filtered_recommendations = $recommendations;
                    if ($current_filter !== 'all') {
                        $filtered_recommendations = array_filter($recommendations, function($rec) use ($current_filter) {
                            return $rec['priority'] === $current_filter;
                        });
                        $filtered_recommendations = array_values($filtered_recommendations); // Re-index array
                    }
                    
                    // Update total pages after filtering
                    $filtered_total = count($filtered_recommendations);
                    $filtered_pages = max(1, ceil($filtered_total / $items_per_page));
                    
                    // Adjust current page if needed
                    $current_page = max(1, min($current_page, $filtered_pages));
                    
                    // Calculate offset
                    $offset = ($current_page - 1) * $items_per_page;
                    
                    // Get recommendations for current page
                    $paged_recommendations = array_slice($filtered_recommendations, $offset, $items_per_page);
                    
                    if (count($paged_recommendations) > 0):
                        foreach($paged_recommendations as $index => $rec): ?>
                            <div class="recommendation-item <?php echo $rec['priority']; ?>" data-recommendation-id="<?php echo $index; ?>">
                                <div class="recommendation-priority-indicator"></div>
                                <div class="recommendation-progress"></div>
                                <div class="recommendation-title">
                                    <?php if($rec['priority'] == 'high'): ?>
                                        <i data-lucide="alert-triangle" style="width: 20px; height: 20px; color: var(--error-color);"></i>
                                    <?php elseif($rec['priority'] == 'medium'): ?>
                                        <i data-lucide="info" style="width: 20px; height: 20px; color: #f59e0b;"></i>
                                    <?php else: ?>
                                        <i data-lucide="flag" style="width: 20px; height: 20px; color: var(--success-color);"></i>
                                    <?php endif; ?>
                                    <?php echo $rec['title']; ?>
                                </div>
                                <div class="recommendation-meta">
                                    <div class="recommendation-timestamp">
                                        <i data-lucide="clock" style="width: 14px; height: 14px;"></i>
                                        <span>Just now</span>
                                    </div>
                                    <div class="recommendation-impact <?php echo $rec['priority']; ?>">
                                        <i data-lucide="trending-up" style="width: 14px; height: 14px;"></i>
                                        <span><?php echo ucfirst($rec['priority']); ?> Impact</span>
                                    </div>
                                </div>
                                <p class="recommendation-desc"><?php echo $rec['description']; ?></p>
                                <div class="recommendation-actions">
                                    <button class="recommendation-action-btn primary" onclick="handleRecommendationAction('<?php echo $rec['priority']; ?>', 'implement', <?php echo $index; ?>)">
                                        <i data-lucide="play" style="width: 14px; height: 14px;"></i>
                                        <span>Implement</span>
                                    </button>
                                    <button class="recommendation-action-btn" onclick="handleRecommendationAction('<?php echo $rec['priority']; ?>', 'learn', <?php echo $index; ?>)">
                                        <i data-lucide="book-open" style="width: 14px; height: 14px;"></i>
                                        <span>Learn More</span>
                                    </button>
                                    <button class="recommendation-action-btn" onclick="handleRecommendationAction('<?php echo $rec['priority']; ?>', 'dismiss', <?php echo $index; ?>)">
                                        <i data-lucide="x" style="width: 14px; height: 14px;"></i>
                                        <span>Dismiss</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 3rem 1rem;">
                            <i data-lucide="inbox" class="empty-state-icon" style="width: 48px; height: 48px;"></i>
                            <p>No recommendations found for this filter</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination Controls -->
                <?php if ($filtered_pages > 1): ?>
                    <div class="recommendation-pagination">
                        <div class="pagination-buttons">
                            <button class="pagination-btn" 
                                            onclick="changePage(<?php echo $current_page - 1; ?>, '<?php echo $current_filter; ?>')" 
                                            <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>>
                                <i data-lucide="chevron-left" style="width: 16px; height: 16px;"></i>
                                Previous
                            </button>
                            
                            <?php for ($i = 1; $i <= $filtered_pages; $i++): ?>
                                <button class="pagination-btn <?php echo ($i === $current_page) ? 'active' : ''; ?>" 
                                        onclick="changePage(<?php echo $i; ?>, '<?php echo $current_filter; ?>')">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            
                            <button class="pagination-btn" 
                                            onclick="changePage(<?php echo $current_page + 1; ?>, '<?php echo $current_filter; ?>')" 
                                            <?php echo ($current_page >= $filtered_pages) ? 'disabled' : ''; ?>>
                                Next
                                <i data-lucide="chevron-right" style="width: 16px; height: 16px;"></i>
                            </button>
                        </div>
                        
                        <div class="pagination-info">
                            Page <?php echo $current_page; ?> of <?php echo $filtered_pages; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="categories-card">
                <div class="chart-header">
                    <h3 class="chart-title">Top Categories</h3>
                </div>
                <?php if(count($categories) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach($categories as $index => $cat): ?>
                            <div style="padding: 0.5rem 0; <?php echo ($index < count($categories) - 1) ? 'border-bottom: 1px solid var(--border-color);' : ''; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                    <span style="font-weight: 500; font-size: 0.9375rem;"><?php echo ucfirst($cat['category']); ?></span>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <span style="color: var(--text-muted); font-size: 0.8125rem;"><?php echo $cat['count']; ?></span>
                                        <span style="font-weight: 600; color: <?php echo $cat['avg_rating'] >= 4.0 ? 'var(--success-color)' : ($cat['avg_rating'] >= 3.0 ? '#f59e0b' : 'var(--error-color)'); ?>; font-size: 0.875rem;">
                                            ★ <?php echo round($cat['avg_rating'], 1); ?>
                                        </span>
                                    </div>
                                </div>
                                <div style="background: var(--bg-muted); height: 6px; border-radius: 3px; overflow: hidden;">
                                    <div style="background: <?php echo $cat['avg_rating'] >= 4.0 ? 'var(--success-color)' : ($cat['avg_rating'] >= 3.0 ? '#f59e0b' : 'var(--error-color)'); ?>; height: 100%; width: <?php echo ($cat['count'] / $total_feedback) * 100; ?>%; transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 2rem 1rem;">
                        <p style="font-size: 0.875rem;">No categories available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Comments -->
        <div class="comments-list">
            <div class="chart-header">
                <h3 class="chart-title">Recent Customer Feedback</h3>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 0.875rem; color: var(--text-muted);">Last <span id="feedbackCount">10</span> submissions</span>
                    <button id="refreshFeedback" class="btn-secondary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;" onclick="refreshFeedback(true)">
                        <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i>
                        Refresh
                    </button>
                </div>
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

        // Pagination and filter functions
        function changePage(page, filter) {
            // Show loading state
            const recommendationsList = document.getElementById('recommendationsList');
            const originalContent = recommendationsList.innerHTML;
            
            // Store current scroll position
            const recommendationsCard = recommendationsList.closest('.recommendations-card');
            const cardRect = recommendationsCard.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const cardTop = cardRect.top + scrollTop;
            
            recommendationsList.innerHTML = `
                <div class="loading-state" style="text-align: center; padding: 3rem;">
                    <div style="display: inline-flex; align-items: center; gap: 0.75rem; color: var(--text-muted);">
                        <div class="loading-spinner" style="width: 20px; height: 20px; border: 2px solid var(--border-color); border-top: 2px solid var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
                        <span>Loading recommendations...</span>
                    </div>
                </div>
            `;
            
            // Make AJAX request to get paginated recommendations
            fetch(`dashboard.php?rec_filter=${filter}&rec_page=${page}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                // Create a temporary DOM element to parse the response
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                
                // Extract the recommendations list from the response
                const newRecommendationsList = tempDiv.querySelector('#recommendationsList');
                const newPagination = tempDiv.querySelector('.recommendation-pagination');
                
                if (newRecommendationsList) {
                    recommendationsList.innerHTML = newRecommendationsList.innerHTML;
                    
                    // Update pagination if exists
                    const existingPagination = document.querySelector('.recommendation-pagination');
                    if (newPagination && existingPagination) {
                        existingPagination.innerHTML = newPagination.innerHTML;
                    } else if (newPagination && !existingPagination) {
                        recommendationsList.parentNode.insertBefore(newPagination, recommendationsList.nextSibling);
                    } else if (!newPagination && existingPagination) {
                        existingPagination.remove();
                    }
                    
                    // Re-initialize Lucide icons for new content
                    lucide.createIcons();
                    
                    // Re-attach intersection observer for new items
                    const observerOptions = {
                        threshold: 0.1,
                        rootMargin: '0px 0px -50px 0px'
                    };
                    
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.style.opacity = '1';
                                entry.target.style.transform = 'translateY(0)';
                            }
                        });
                    }, observerOptions);
                    
                    document.querySelectorAll('.recommendation-item').forEach(item => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateY(20px)';
                        item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        observer.observe(item);
                    });
                    
                    // Scroll back to the recommendations card after content loads
                    setTimeout(() => {
                        const newCardRect = recommendationsCard.getBoundingClientRect();
                        const newScrollTop = newCardRect.top + (window.pageYOffset || document.documentElement.scrollTop);
                        window.scrollTo({
                            top: newScrollTop,
                            behavior: 'smooth'
                        });
                    }, 100);
                }
                
                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('rec_filter', filter);
                url.searchParams.set('rec_page', page);
                window.history.pushState({}, '', url.toString());
            })
            .catch(error => {
                console.error('Error loading recommendations:', error);
                recommendationsList.innerHTML = originalContent;
                showNotification('Failed to load recommendations. Please try again.', 'error');
            });
        }

        function changeFilter(filter) {
            // Show loading state
            const recommendationsList = document.getElementById('recommendationsList');
            const originalContent = recommendationsList.innerHTML;
            
            recommendationsList.innerHTML = `
                <div class="loading-state" style="text-align: center; padding: 3rem;">
                    <div style="display: inline-flex; align-items: center; gap: 0.75rem; color: var(--text-muted);">
                        <div class="loading-spinner" style="width: 20px; height: 20px; border: 2px solid var(--border-color); border-top: 2px solid var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite;"></div>
                        <span>Loading recommendations...</span>
                    </div>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `;
            
            // Make AJAX request to get filtered recommendations
            fetch(`dashboard.php?rec_filter=${filter}&rec_page=1`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                // Create a temporary DOM element to parse the response
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                
                // Extract the recommendations list from the response
                const newRecommendationsList = tempDiv.querySelector('#recommendationsList');
                const newPagination = tempDiv.querySelector('.recommendation-pagination');
                
                if (newRecommendationsList) {
                    recommendationsList.innerHTML = newRecommendationsList.innerHTML;
                    
                    // Update pagination if exists
                    const existingPagination = document.querySelector('.recommendation-pagination');
                    if (newPagination && existingPagination) {
                        existingPagination.innerHTML = newPagination.innerHTML;
                    } else if (newPagination && !existingPagination) {
                        recommendationsList.parentNode.insertBefore(newPagination, recommendationsList.nextSibling);
                    } else if (!newPagination && existingPagination) {
                        existingPagination.remove();
                    }
                    
                    // Re-initialize Lucide icons for new content
                    lucide.createIcons();
                    
                    // Re-attach intersection observer for new items
                    const observerOptions = {
                        threshold: 0.1,
                        rootMargin: '0px 0px -50px 0px'
                    };
                    
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.style.opacity = '1';
                                entry.target.style.transform = 'translateY(0)';
                            }
                        });
                    }, observerOptions);
                    
                    document.querySelectorAll('.recommendation-item').forEach(item => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateY(20px)';
                        item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        observer.observe(item);
                    });
                }
                
                // Update URL without page reload
                const url = new URL(window.location);
                url.searchParams.set('rec_filter', filter);
                url.searchParams.delete('rec_page');
                window.history.pushState({}, '', url.toString());
            })
            .catch(error => {
                console.error('Error loading recommendations:', error);
                recommendationsList.innerHTML = originalContent;
                showNotification('Failed to load recommendations. Please try again.', 'error');
            });
        }

        // Real-time feedback updates
        let feedbackUpdateInterval;
        let lastFeedbackCount = <?php echo count($recent_comments); ?>;
        
        function refreshFeedback(isManual = false) {
            fetch('../php/get_recent_feedback.php?business_id=<?php echo $business_id; ?>', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.feedback) {
                    updateFeedbackDisplay(data.feedback);
                    if (isManual) {
                        showNotification('Feedback updated successfully!', 'success');
                    }
                } else if (isManual) {
                    showNotification('No new feedback available', 'info');
                }
            })
            .catch(error => {
                console.error('Error refreshing feedback:', error);
                if (isManual) {
                    showNotification('Failed to refresh feedback', 'error');
                }
            });
        }
        
        function updateFeedbackDisplay(newFeedback) {
            const commentsContainer = document.querySelector('.comments-list');
            const feedbackCount = document.getElementById('feedbackCount');
            
            if (commentsContainer && newFeedback && newFeedback.length > 0) {
                // Clear existing comments
                const existingComments = commentsContainer.querySelectorAll('.comment-item');
                existingComments.forEach(comment => comment.remove());
                
                // Add new comments
                newFeedback.forEach((comment, index) => {
                    const commentDiv = document.createElement('div');
                    commentDiv.className = 'comment-item';
                    commentDiv.style.opacity = '0';
                    commentDiv.style.transform = 'translateY(20px)';
                    commentDiv.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    
                    commentDiv.innerHTML = `
                        <div class="comment-header">
                            <div class="comment-rating">
                                ${generateStars(comment.rating)}
                            </div>
                            <span class="comment-date">${formatDate(comment.submitted_at)}</span>
                        </div>
                        ${comment.category ? `<div style="margin-bottom: 0.75rem;"><span style="background: var(--bg-muted); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">${comment.category}</span></div>` : ''}
                        <p class="comment-text">${escapeHtml(comment.comment)}</p>
                    `;
                    
                    commentsContainer.appendChild(commentDiv);
                    
                    // Animate in
                    setTimeout(() => {
                        commentDiv.style.opacity = '1';
                        commentDiv.style.transform = 'translateY(0)';
                    }, index * 100);
                });
                
                // Update count
                if (feedbackCount) {
                    feedbackCount.textContent = newFeedback.length;
                }
                
                // Re-initialize Lucide icons
                lucide.createIcons();
            }
        }
        
        function generateStars(rating) {
            let stars = '';
            for (let i = 0; i < 5; i++) {
                stars += `<i data-lucide="star" style="width: 16px; height: 16px; ${i < rating ? 'fill: currentColor;' : ''}"></i>`;
            }
            return stars;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric'
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-refresh feedback every 30 seconds
        function startAutoRefresh() {
            if (feedbackUpdateInterval) {
                clearInterval(feedbackUpdateInterval);
            }
            
            feedbackUpdateInterval = setInterval(() => {
                refreshFeedback(false);
            }, 30000); // 30 seconds
        }
        
        // Start auto-refresh on page load
        startAutoRefresh();
        
        // Stop auto-refresh when page is not visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (feedbackUpdateInterval) {
                    clearInterval(feedbackUpdateInterval);
                }
            } else {
                startAutoRefresh();
            }
        });

        // Handle recommendation actions
        function handleRecommendationAction(priority, action, recommendationId) {
            const messages = {
                implement: {
                    high: 'Critical issue! Implementing this recommendation should be your top priority.',
                    medium: 'Good choice! This improvement will help optimize your operations.',
                    low: 'Nice! This enhancement will further improve your customer experience.'
                },
                learn: {
                    high: 'Understanding this critical issue is the first step toward resolution.',
                    medium: 'Learn more about this optimization opportunity.',
                    low: 'Discover how this enhancement can benefit your business.'
                },
                dismiss: {
                    high: '⚠️ Warning: Dismissing high-priority recommendations may impact customer satisfaction.',
                    medium: 'This recommendation has been dismissed. You can review it later.',
                    low: 'This recommendation has been dismissed.'
                }
            };

            const message = messages[action][priority];
            
            // Handle different actions
            if (action === 'implement') {
                // Mark as completed
                const recommendationElement = document.querySelector(`[data-recommendation-id="${recommendationId}"]`);
                if (recommendationElement) {
                    recommendationElement.classList.add('completed');
                    
                    // Update progress bar
                    const progressBar = recommendationElement.querySelector('.recommendation-progress');
                    if (progressBar) {
                        progressBar.style.width = '100%';
                    }
                    
                    // Update action buttons
                    const actionsContainer = recommendationElement.querySelector('.recommendation-actions');
                    actionsContainer.innerHTML = `
                        <button class="recommendation-action-btn" style="background: var(--success-color); color: white;">
                            <i data-lucide="check" style="width: 14px; height: 14px;"></i>
                            <span>Completed</span>
                        </button>
                    `;
                    lucide.createIcons();
                }
            } else if (action === 'dismiss') {
                // Call backend to persist dismissal
                persistRecommendationDismissal(priority, recommendationId)
                    .then(() => {
                        // Remove the recommendation with animation
                        const recommendationElement = document.querySelector(`[data-recommendation-id="${recommendationId}"]`);
                        if (recommendationElement) {
                            recommendationElement.style.transition = 'all 0.3s ease';
                            recommendationElement.style.opacity = '0';
                            recommendationElement.style.transform = 'translateX(-100%)';
                            
                            setTimeout(() => {
                                recommendationElement.remove();
                                updateRecommendationCount();
                            }, 300);
                        }
                    })
                    .catch(error => {
                        console.error('Failed to dismiss recommendation:', error);
                        showNotification('Failed to dismiss recommendation. Please try again.', 'error');
                    });
            }
            
            showNotification(message, action === 'dismiss' && priority === 'high' ? 'error' : 'success');

            // Log the action for analytics (in a real app)
            console.log(`Recommendation action: ${action} on ${priority} priority item`);
        }

        // Update recommendation count
        function updateRecommendationCount() {
            const activeRecommendations = document.querySelectorAll('.recommendation-item:not(.completed)').length;
            const countBadge = document.getElementById('recommendationCount');
            if (countBadge) {
                countBadge.textContent = `${activeRecommendations} Active`;
            }
        }

        // Persist recommendation dismissal to backend
        async function persistRecommendationDismissal(priority, recommendationId) {
            const recommendationElement = document.querySelector(`[data-recommendation-id="${recommendationId}"]`);
            if (!recommendationElement) {
                throw new Error('Recommendation element not found');
            }
            
            // Extract recommendation data from the element
            const title = recommendationElement.querySelector('.recommendation-title').textContent.trim();
            const type = recommendationElement.classList.contains('high') ? 'warning' : 
                       recommendationElement.classList.contains('medium') ? 'info' : 'success';
            
            const response = await fetch('../php/dismiss_recommendation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'dismiss_recommendation',
                    title: title,
                    type: type,
                    priority: priority
                })
            });
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Failed to dismiss recommendation');
            }
            
            return result;
        }

        // Enhanced notification system
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            const existingNotification = document.querySelector('.recommendation-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.className = `recommendation-notification ${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i data-lucide="${type === 'error' ? 'alert-triangle' : 'check-circle'}" style="width: 20px; height: 20px;"></i>
                    <span>${message}</span>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                </button>
            `;

            // Add styles
            notification.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                background: ${type === 'error' ? 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)' : 'linear-gradient(135deg, #10b981 0%, #059669 100%)'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: var(--radius-lg);
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                z-index: 10000;
                min-width: 320px;
                max-width: 90%;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            `;

            const notificationContent = notification.querySelector('.notification-content');
            notificationContent.style.cssText = `
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex: 1;
            `;

            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.style.cssText = `
                background: transparent;
                border: none;
                color: white;
                cursor: pointer;
                padding: 0.25rem;
                opacity: 0.8;
                transition: opacity 0.2s;
            `;

            document.body.appendChild(notification);
            lucide.createIcons();

            // Animate in
            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 100);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
        
        // Generate QR Code for business
        const businessId = <?php echo $business_id; ?>;
        // Use relative path to ensure it works regardless of localhost setup
        const surveyUrl = window.location.protocol + '//' + window.location.host + '/feedback_platform/public/feedback_form.php?business_id=' + businessId;
        
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            // Generate QR Code
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
            
            // Copy Link functionality
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
                    fill: true,
                    pointRadius: function(context) {
                        // Reduce point size on mobile
                        const width = context.chart.width;
                        return width < 400 ? 2 : 3;
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: function() {
                        // Disable animations on mobile for better performance
                        return window.innerWidth < 768 ? 0 : 1000;
                    }()
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: function() {
                                    // Smaller legend font on mobile
                                    return window.innerWidth < 480 ? 10 : 11;
                                }()
                            },
                            padding: 8
                        }
                    },
                    tooltip: {
                        enabled: true,
                        bodyFont: {
                            size: function() {
                                return window.innerWidth < 480 ? 11 : 12;
                            }()
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: function() {
                                    return window.innerWidth < 480 ? 9 : 10;
                                }()
                            },
                            maxRotation: function() {
                                // Rotate labels on very small screens
                                return window.innerWidth < 400 ? 45 : 0;
                            }()
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: function() {
                                    return window.innerWidth < 480 ? 9 : 10;
                                }()
                            },
                            maxRotation: function() {
                                // Rotate date labels on small screens
                                return window.innerWidth < 480 ? 45 : (window.innerWidth < 600 ? 30 : 0);
                            }(),
                            autoSkip: function() {
                                // Skip labels on small screens to prevent crowding
                                return window.innerWidth < 500 ? true : false;
                            }()
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if(count($distribution) > 0): ?>
        const distCtx = document.getElementById('distributionChart').getContext('2d');
        
        // Define color mapping for ratings (5 stars = lightest, 1 star = darkest)
        const ratingColors = {
            1: 'rgba(239, 68, 68, 0.9)',      // 1 star - darkest red
            2: 'rgba(245, 158, 11, 0.85)',    // 2 stars - dark orange
            3: 'rgba(251, 191, 36, 0.8)',     // 3 stars - yellow
            4: 'rgba(16, 185, 129, 0.75)',    // 4 stars - medium green
            5: 'rgba(5, 150, 105, 0.6)'       // 5 stars - lightest green
        };
        
        // Build the colors array based on actual ratings in the data
        const backgroundColors = [];
        <?php foreach($distribution as $rating => $count): ?>
        backgroundColors.push(ratingColors[<?php echo $rating; ?>]);
        <?php endforeach; ?>
        
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php foreach($distribution as $rating => $count): ?>'<?php echo $rating; ?> Stars',<?php endforeach; ?>],
                datasets: [{
                    data: [<?php foreach($distribution as $count): echo $count; ?>,<?php endforeach; ?>],
                    backgroundColor: backgroundColors,
                    borderWidth: function() {
                        // Thinner borders on mobile
                        return window.innerWidth < 480 ? 1 : 2;
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: function() {
                        return window.innerWidth < 768 ? 0 : 1000;
                    }()
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: function() {
                                return window.innerWidth < 480 ? 10 : 12;
                            }(),
                            font: {
                                size: function() {
                                    return window.innerWidth < 480 ? 10 : 11;
                                }()
                            },
                            padding: function() {
                                return window.innerWidth < 480 ? 6 : 8;
                            }()
                        }
                    },
                    tooltip: {
                        enabled: true,
                        bodyFont: {
                            size: function() {
                                return window.innerWidth < 480 ? 11 : 12;
                            }()
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
    <script>
        // Update filter button active states based on current filter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentFilter = urlParams.get('rec_filter') || 'all';
            
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(button => {
                if (button.getAttribute('data-filter') === currentFilter) {
                    button.classList.add('active');
                } else {
                    button.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
