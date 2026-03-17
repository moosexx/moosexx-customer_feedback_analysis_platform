<?php
session_start();
header('Content-Type: application/json');
require_once "config.php";

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and sanitize form data
$business_id = isset($_POST['business_id']) ? intval($_POST['business_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : null;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
$category = isset($_POST['category']) ? trim($_POST['category']) : null;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validate required fields
if ($business_id <= 0 || $rating <= 0 || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate rating range
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit;
}

// Verify business exists
$check_business_sql = "SELECT id FROM businesses WHERE id = ?";
$stmt = mysqli_prepare($conn, $check_business_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (!mysqli_fetch_assoc($result)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Business not found']);
    exit;
}
mysqli_stmt_close($stmt);

// Insert feedback into database
$insert_sql = "INSERT INTO feedback (business_id, customer_name, email, phone, category, rating, comment) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $insert_sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

// Bind parameters (s = string, i = integer, d = double, b = blob)
// Format: "sssssis" = string, string, string, string, integer, string
mysqli_stmt_bind_param($stmt, "issssiss", 
    $business_id,      // i - integer
    $customer_name,    // s - string (nullable)
    $email,            // s - string (nullable)
    $phone,            // s - string (nullable)
    $category,         // s - string
    $rating,           // i - integer
    $comment           // s - string
);

// Execute the statement
if (mysqli_stmt_execute($stmt)) {
    $feedback_id = mysqli_insert_id($conn);
    
    // If there are custom question answers, save them
    if (isset($_POST['custom_answers']) && is_array($_POST['custom_answers'])) {
        $custom_answer_sql = "INSERT INTO feedback_answers (feedback_id, question_id, answer_text) VALUES (?, ?, ?)";
        $custom_stmt = mysqli_prepare($conn, $custom_answer_sql);
        
        foreach ($_POST['custom_answers'] as $question_id => $answer) {
            $question_id = intval($question_id);
            $answer_text = trim($answer);
            
            if (!empty($answer_text)) {
                mysqli_stmt_bind_param($custom_stmt, "iis", $feedback_id, $question_id, $answer_text);
                mysqli_stmt_execute($custom_stmt);
            }
        }
        
        mysqli_stmt_close($custom_stmt);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Feedback submitted successfully',
        'feedback_id' => $feedback_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit feedback: ' . mysqli_stmt_error($stmt)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
