<?php
session_start();
header('Content-Type: application/json');

require_once "../php/config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit;
}

$user_id = $_SESSION["id"];

// Check if request method is POST
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the questions data
$input = json_decode(file_get_contents('php://input'), true);
$questions = isset($input['questions']) ? $input['questions'] : [];

if(!is_array($questions)){
    echo json_encode(['success' => false, 'message' => 'Invalid questions format']);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Deactivate all existing questions first
    $deactivate_sql = "UPDATE custom_questions SET is_active = FALSE WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $deactivate_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Insert or reactivate questions
    $insert_sql = "INSERT INTO custom_questions (user_id, question_text, is_active) VALUES (?, ?, TRUE) 
                   ON DUPLICATE KEY UPDATE question_text = ?, is_active = TRUE";
    $stmt = mysqli_prepare($conn, $insert_sql);
    
    foreach($questions as $question_text){
        $question_text = trim($question_text);
        
        if(!empty($question_text)){
            mysqli_stmt_bind_param($stmt, "isss", $user_id, $question_text, $question_text);
            mysqli_stmt_execute($stmt);
        }
    }
    
    mysqli_stmt_close($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode(['success' => true, 'message' => 'Questions saved successfully']);
    
} catch(Exception $e){
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error saving questions: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>
