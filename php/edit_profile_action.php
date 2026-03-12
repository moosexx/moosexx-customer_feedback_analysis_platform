<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once "../php/config.php";

$user_id = $_SESSION["id"];
$response = ['status' => 'error', 'message' => 'Invalid request'];

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'update_user':
        $email = $_POST['email'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if(empty($email)){
            $response = ['status' => 'error', 'message' => 'Email is required'];
            break;
        }
        
        // Update email
        $update_sql = "UPDATE users SET email = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        
        if(mysqli_stmt_execute($stmt)){
            // Update password if provided
            if(!empty($new_password)){
                $password_sql = "UPDATE users SET password = ? WHERE id = ?";
                $pwd_stmt = mysqli_prepare($conn, $password_sql);
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($pwd_stmt, "si", $hashed_password, $user_id);
                mysqli_stmt_execute($pwd_stmt);
                mysqli_stmt_close($pwd_stmt);
            }
            
            $response = ['status' => 'success', 'message' => 'Account updated successfully'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to update account'];
        }
        
        mysqli_stmt_close($stmt);
        break;
        
    case 'update_business':
        $business_name = $_POST['business_name'] ?? '';
        $industry = $_POST['industry'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if(empty($business_name)){
            $response = ['status' => 'error', 'message' => 'Business name is required'];
            break;
        }
        
        $update_sql = "UPDATE businesses SET business_name = ?, industry = ?, description = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "sssi", $business_name, $industry, $description, $user_id);
        
        if(mysqli_stmt_execute($stmt)){
            $response = ['status' => 'success', 'message' => 'Business information updated successfully'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to update business information'];
        }
        
        mysqli_stmt_close($stmt);
        break;
        
    case 'get_questions':
        $questions_sql = "SELECT * FROM custom_questions WHERE user_id = ? ORDER BY created_at";
        $stmt = mysqli_prepare($conn, $questions_sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $questions = [];
        while($row = mysqli_fetch_assoc($result)){
            $questions[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        $response = ['status' => 'success', 'questions' => $questions];
        break;
        
    case 'save_questions':
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $questions = $input['questions'] ?? [];
        
        if(!is_array($questions)){
            $response = ['status' => 'error', 'message' => 'Invalid questions data'];
            break;
        }
        
        $success = true;
        
        foreach($questions as $question){
            if(isset($question['id']) && !empty($question['id'])){
                // Update existing question
                $update_sql = "UPDATE custom_questions SET question_text = ? WHERE id = ? AND user_id = ?";
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "sii", $question['text'], $question['id'], $user_id);
                
                if(!mysqli_stmt_execute($stmt)){
                    $success = false;
                }
                mysqli_stmt_close($stmt);
            } elseif(!empty($question['text'])){
                // Insert new question
                $insert_sql = "INSERT INTO custom_questions (user_id, question_text) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($stmt, "is", $user_id, $question['text']);
                
                if(!mysqli_stmt_execute($stmt)){
                    $success = false;
                }
                mysqli_stmt_close($stmt);
            }
        }
        
        if($success){
            $response = ['status' => 'success', 'message' => 'Questions saved successfully'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to save some questions'];
        }
        break;
        
    default:
        $response = ['status' => 'error', 'message' => 'Invalid action'];
}

mysqli_close($conn);
echo json_encode($response);
?>
