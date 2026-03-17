<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once "../php/config.php";

$user_id = $_SESSION["id"];
$response = ['success' => false, 'message' => 'Invalid request'];

// Handle POST request for dismissing recommendations
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $input = json_decode(file_get_contents('php://input'), true);
    
    if(isset($input['action']) && $input['action'] === 'dismiss_recommendation'){
        $recommendation_title = $input['title'] ?? '';
        $recommendation_type = $input['type'] ?? '';
        $recommendation_priority = $input['priority'] ?? '';
        
        if(empty($recommendation_title) || empty($recommendation_type) || empty($recommendation_priority)){
            echo json_encode(['success' => false, 'message' => 'Missing recommendation data']);
            exit;
        }
        
        // Create a unique hash for this recommendation to prevent duplicates
        $recommendation_hash = hash('sha256', $recommendation_title . $recommendation_type . $recommendation_priority);
        
        // Check if already dismissed
        $check_sql = "SELECT id FROM dismissed_recommendations WHERE user_id = ? AND recommendation_hash = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $user_id, $recommendation_hash);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if(mysqli_fetch_assoc($check_result)){
            echo json_encode(['success' => true, 'message' => 'Recommendation already dismissed']);
            exit;
        }
        mysqli_stmt_close($check_stmt);
        
        // Insert dismissed recommendation
        $insert_sql = "INSERT INTO dismissed_recommendations (user_id, recommendation_hash, recommendation_title, recommendation_type, recommendation_priority) 
                       VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "issss", $user_id, $recommendation_hash, $recommendation_title, $recommendation_type, $recommendation_priority);
        
        if(mysqli_stmt_execute($insert_stmt)){
            $response = ['success' => true, 'message' => 'Recommendation dismissed successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Failed to dismiss recommendation'];
        }
        
        mysqli_stmt_close($insert_stmt);
    }
}

mysqli_close($conn);
echo json_encode($response);
?>
