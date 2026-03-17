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

// If no action in POST/GET, check JSON input
if (empty($action)) {
    $json_input = json_decode(file_get_contents('php://input'), true);
    $action = $json_input['action'] ?? '';
}

switch($action) {
    case 'update_user':
        $email = $_POST['email'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $middle_name = $_POST['middle_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if(empty($email)){
            $response = ['status' => 'error', 'message' => 'Email is required'];
            break;
        }
        
        // Update user information with separate name fields
        $update_sql = "UPDATE users SET email = ?, first_name = ?, middle_name = ?, last_name = ?, phone = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "sssssi", $email, $first_name, $middle_name, $last_name, $phone, $user_id);
        
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
        $business_address = $_POST['business_address'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $country = $_POST['country'] ?? '';
        
        if(empty($business_name)){
            $response = ['status' => 'error', 'message' => 'Business name is required'];
            break;
        }
        
        // Handle logo upload
        $logo_path = null;
        if(isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK){
            $upload_dir = '../public/uploads/logos/';
            $file_extension = strtolower(pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if(!in_array($file_extension, $allowed_extensions)){
                $response = ['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
                break;
            }
            
            $max_size = 2 * 1024 * 1024; // 2MB
            if($_FILES['logo_upload']['size'] > $max_size){
                $response = ['status' => 'error', 'message' => 'File size must be less than 2MB.'];
                break;
            }
            
            // Generate unique filename
            $new_filename = 'logo_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Delete old logo if exists
            if(!empty($business['logo_path'])){
                $old_logo_path = $upload_dir . $business['logo_path'];
                if(file_exists($old_logo_path)){
                    unlink($old_logo_path);
                }
            }
            
            if(move_uploaded_file($_FILES['logo_upload']['tmp_name'], $upload_path)){
                $logo_path = $new_filename;
            }
        }
        
        // Update business information
        if($logo_path){
            $update_sql = "UPDATE businesses SET business_name = ?, industry = ?, description = ?, business_address = ?, city = ?, state = ?, postal_code = ?, country = ?, logo_path = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sssssssssi", $business_name, $industry, $description, $business_address, $city, $state, $postal_code, $country, $logo_path, $user_id);
        } else {
            $update_sql = "UPDATE businesses SET business_name = ?, industry = ?, description = ?, business_address = ?, city = ?, state = ?, postal_code = ?, country = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "ssssssssi", $business_name, $industry, $description, $business_address, $city, $state, $postal_code, $country, $user_id);
        }
        
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
        
    case 'get_user_data':
        $user_sql = "SELECT * FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $user_sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $user_result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($user_result);
        mysqli_stmt_close($stmt);
        
        // Build full_name from separate fields for backward compatibility
        $user['full_name'] = trim($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);
        
        $response = ['status' => 'success', 'user' => $user];
        break;
        
    case 'save_questions':
        // Get JSON input (already parsed above)
        $json_input = json_decode(file_get_contents('php://input'), true);
        $questions = $json_input['questions'] ?? [];
        
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
