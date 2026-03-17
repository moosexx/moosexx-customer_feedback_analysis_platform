<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'config.php';

$business_id = $_GET['business_id'] ?? 0;

if ($business_id > 0) {
    try {
        // Get recent feedback with business filter using prepared statement
        $recent_query = "SELECT f.id, f.rating, f.comment, f.category, f.submitted_at 
                        FROM feedback f 
                        WHERE f.business_id = ? 
                        ORDER BY f.submitted_at DESC 
                        LIMIT 10";
        
        $stmt = mysqli_prepare($conn, $recent_query);
        mysqli_stmt_bind_param($stmt, "i", $business_id);
        mysqli_stmt_execute($stmt);
        $recent_result = mysqli_stmt_get_result($stmt);
        
        $recent_comments = [];
        
        while ($row = mysqli_fetch_assoc($recent_result)) {
            $recent_comments[] = [
                'id' => $row['id'],
                'rating' => (int)$row['rating'],
                'comment' => $row['comment'],
                'category' => $row['category'],
                'submitted_at' => $row['submitted_at']
            ];
        }
        
        mysqli_stmt_close($stmt);
        
        echo json_encode([
            'success' => true,
            'feedback' => $recent_comments
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid business ID'
    ]);
}
?>
