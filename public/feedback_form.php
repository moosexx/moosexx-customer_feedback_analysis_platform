<?php
session_start();
require_once "../php/config.php";

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['view'])){
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get form data
        $business_id = isset($_POST['business_id']) ? intval($_POST['business_id']) : 0;
        $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : null;
        $email = isset($_POST['email']) ? trim($_POST['email']) : null;
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        $custom_answers = isset($_POST['custom_answers']) ? $_POST['custom_answers'] : [];
        
        // Validate required fields
        if($business_id <= 0){
            throw new Exception('Invalid business ID');
        }
        
        if($rating < 1 || $rating > 5){
            throw new Exception('Rating must be between 1 and 5');
        }
        
        if(empty($category)){
            throw new Exception('Category is required');
        }
        
        if(empty($comment)){
            throw new Exception('Feedback comment is required');
        }
        
        // Verify business exists
        $check_sql = "SELECT id FROM businesses WHERE id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $business_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if(!mysqli_fetch_assoc($check_result)){
            throw new Exception('Business not found');
        }
        mysqli_stmt_close($check_stmt);
        
        // Insert feedback into database
        $insert_sql = "INSERT INTO feedback (business_id, customer_name, email, phone, rating, category, comment) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, "isssiss", 
            $business_id, 
            $customer_name, 
            $email, 
            $phone, 
            $rating, 
            $category, 
            $comment
        );
        
        if(!mysqli_stmt_execute($stmt)){
            throw new Exception('Failed to submit feedback. Please try again.');
        }
        
        $feedback_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Save custom question answers if any
        if(!empty($custom_answers) && is_array($custom_answers)){
            $answer_sql = "INSERT INTO feedback_answers (feedback_id, question_id, answer_text) 
                          VALUES (?, ?, ?)";
            $answer_stmt = mysqli_prepare($conn, $answer_sql);
            
            foreach($custom_answers as $question_id => $answer){
                $question_id = intval($question_id);
                $answer_text = trim($answer);
                
                if($question_id > 0 && !empty($answer_text)){
                    mysqli_stmt_bind_param($answer_stmt, "iis", $feedback_id, $question_id, $answer_text);
                    mysqli_stmt_execute($answer_stmt);
                }
            }
            mysqli_stmt_close($answer_stmt);
        }
        
        $response['success'] = true;
        $response['message'] = 'Feedback submitted successfully';
        
    } catch(Exception $e){
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit; // Stop further execution
}

// Get business_id from URL parameter
$business_id = isset($_GET['business_id']) ? intval($_GET['business_id']) : 0;

// Check if this is a preview mode (from dashboard button)
$is_preview = isset($_GET['view']) && $_GET['view'] === 'preview';

// Validate business_id
if($business_id <= 0){
    die("Invalid business ID");
}

// Get business information
$business_sql = "SELECT * FROM businesses WHERE id = ?";
$stmt = mysqli_prepare($conn, $business_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$business_result = mysqli_stmt_get_result($stmt);
$business = mysqli_fetch_assoc($business_result);
mysqli_stmt_close($stmt);

// If business doesn't exist, show error
if(!$business){
    die("Business not found");
}

// Get custom questions for this business
$user_id = $business['user_id'];
$questions_sql = "SELECT * FROM custom_questions WHERE user_id = ? AND is_active = TRUE ORDER BY created_at";
$stmt = mysqli_prepare($conn, $questions_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$questions_result = mysqli_stmt_get_result($stmt);
$custom_questions = [];
while($row = mysqli_fetch_assoc($questions_result)){
    $custom_questions[] = $row;
}
mysqli_stmt_close($stmt);

// Get available categories
$categories = ['Service', 'Product', 'Staff', 'Cleanliness', 'Value', 'Other'];

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Form - <?php echo htmlspecialchars($business['business_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .feedback-container {
            max-width: 700px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .feedback-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .feedback-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
        }

        .feedback-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .feedback-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .feedback-subtitle {
            color: var(--text-muted);
            font-size: 1.125rem;
        }

        .feedback-form {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rating-section {
            text-align: center;
        }

        .rating-input {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
            font-size: 2.5rem;
            line-height: 1;
            flex-direction: row-reverse;
        }

        .rating-input input[type="radio"] {
            display: none;
        }

        .rating-input label {
            cursor: pointer;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-muted);
            border-radius: 50%;
            transition: all 0.2s;
            color: #d1d5db;
        }

        .rating-input label:hover {
            background: rgba(194, 230, 98, 0.15);
            transform: scale(1.1);
            color: #c2e662;
        }

        /* Hover state class for JS */
        .rating-input label.hovered,
        .rating-input label.hover-prev {
            background: rgba(194, 230, 98, 0.15);
            transform: scale(1.1);
            color: #c2e662;
        }

        /* Selected star and all previous stars turn green */
        .rating-input input[value="5"]:checked ~ label,
        .rating-input input[value="4"]:checked ~ label,
        .rating-input input[value="3"]:checked ~ label,
        .rating-input input[value="2"]:checked ~ label,
        .rating-input input[value="1"]:checked ~ label {
            color: #c2e662;
        }

        .rating-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-family: inherit;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(209, 244, 112, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .custom-question {
            background: var(--bg-muted);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }

        .custom-question label {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.75rem;
            display: block;
        }

        .success-message {
            display: none;
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .success-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-main);
        }

        .success-text {
            color: var(--text-muted);
            font-size: 1.125rem;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: var(--primary-color);
            color: var(--text-main);
            border: none;
            border-radius: var(--radius-md);
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background: #b8d932;
            transform: translateY(-2px);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Preview mode banner */
        .preview-banner {
            background: var(--bg-muted);
            color: var(--text-main);
            padding: 1rem;
            text-align: center;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
        }

        /* Disabled form styles for preview mode */
        .preview-mode input,
        .preview-mode select,
        .preview-mode textarea {
            background: #f9fafb;
            cursor: not-allowed;
        }

        .preview-mode .rating-input label {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .preview-mode .btn-submit {
            display: none;
        }

        .return-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: white;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 2rem;
        }

        .return-btn:hover {
            background: var(--bg-muted);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .feedback-container {
                padding: 1rem;
            }

            .feedback-form {
                padding: 1.5rem;
            }

            .rating-input {
                gap: 0.5rem;
            }

            .rating-input label {
                width: 40px;
                height: 40px;
            }

            .rating-input label i {
                width: 24px;
                height: 24px;
            }
        }
    </style>
</head>
<body style="background: var(--bg-muted);">
    <div class="feedback-container">
        <?php if($is_preview): ?>
        <a href="dashboard.php" class="return-btn">
            <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
            Return to Dashboard
        </a>
        
        <div class="preview-banner">
            <i data-lucide="eye" style="width: 24px; height: 24px;"></i>
            <span>Preview Mode - This is how your customers will see the feedback form</span>
        </div>
        <?php endif; ?>

        <!-- Feedback Form -->
        <div id="feedbackFormContainer">
            <div class="feedback-header">
                <div class="feedback-logo">
                    <?php if(!empty($business['logo_path']) && file_exists('../uploads/logos/' . $business['logo_path'])): ?>
                        <img src="../uploads/logos/<?php echo htmlspecialchars($business['logo_path']); ?>" alt="<?php echo htmlspecialchars($business['business_name']); ?> Logo">
                    <?php else: ?>
                        <i data-lucide="message-square" style="width: 40px; height: 40px;"></i>
                    <?php endif; ?>
                </div>
                <h1 class="feedback-title"><?php echo htmlspecialchars($business['business_name']); ?></h1>
                <p class="feedback-subtitle">We value your feedback! Please share your experience with us.</p>
            </div>

            <form class="feedback-form <?php echo $is_preview ? 'preview-mode' : ''; ?>" id="feedbackForm">
                <input type="hidden" name="business_id" value="<?php echo $business_id; ?>">
                
                <!-- Rating Section -->
                <div class="form-section rating-section">
                    <h3 class="section-title">
                        <i data-lucide="star" style="width: 24px; height: 24px;"></i>
                        Overall Rating
                    </h3>
                    <div class="rating-input">
                        <input type="radio" name="rating" id="rating5" value="5" required <?php echo $is_preview ? 'disabled' : ''; ?>>
                        <label for="rating5">&#9733;</label>
                        
                        <input type="radio" name="rating" id="rating4" value="4" required <?php echo $is_preview ? 'disabled' : ''; ?>>
                        <label for="rating4">&#9733;</label>
                        
                        <input type="radio" name="rating" id="rating3" value="3" required <?php echo $is_preview ? 'disabled' : ''; ?>>
                        <label for="rating3">&#9733;</label>
                        
                        <input type="radio" name="rating" id="rating2" value="2" required <?php echo $is_preview ? 'disabled' : ''; ?>>
                        <label for="rating2">&#9733;</label>
                        
                        <input type="radio" name="rating" id="rating1" value="1" required <?php echo $is_preview ? 'disabled' : ''; ?>>
                        <label for="rating1">&#9733;</label>
                    </div>
                    <div class="rating-labels">
                        <span>Poor</span>
                        <span>Excellent</span>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i data-lucide="user" style="width: 24px; height: 24px;"></i>
                        Your Information (Optional)
                    </h3>
                    
                    <div class="form-group">
                        <label for="customer_name">Name</label>
                        <input type="text" id="customer_name" name="customer_name" placeholder="Your name" <?php echo $is_preview ? 'disabled' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="your@email.com" <?php echo $is_preview ? 'disabled' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" placeholder="+1 (555) 123-4567" <?php echo $is_preview ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <!-- Feedback Details -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i data-lucide="clipboard-list" style="width: 24px; height: 24px;"></i>
                        Feedback Details
                    </h3>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required <?php echo $is_preview ? 'disabled' : ''; ?>>
                            <option value="">Select a category</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo strtolower($cat); ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="comment">Your Feedback</label>
                        <textarea id="comment" name="comment" rows="5" placeholder="Tell us about your experience..." required <?php echo $is_preview ? 'disabled' : ''; ?>></textarea>
                    </div>
                </div>

                <!-- Custom Questions Section -->
                <?php if(count($custom_questions) > 0): ?>
                <div class="form-section">
                    <h3 class="section-title">
                        <i data-lucide="circle-help" style="width: 24px; height: 24px;"></i>
                        Additional Questions
                    </h3>
                    
                    <?php foreach($custom_questions as $index => $question): ?>
                        <div class="custom-question">
                            <label for="custom_<?php echo $question['id']; ?>">
                                <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?>
                            </label>
                            <textarea 
                                id="custom_<?php echo $question['id']; ?>" 
                                name="custom_answers[<?php echo $question['id']; ?>]" 
                                rows="3" 
                                placeholder="Your answer..."
                                required <?php echo $is_preview ? 'disabled' : ''; ?>></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn-submit">
                    <i data-lucide="send" style="width: 20px; height: 20px;"></i>
                    Submit Feedback
                </button>
            </form>
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="success-message">
            <div class="success-icon">
                <i data-lucide="check" style="width: 40px; height: 40px;"></i>
            </div>
            <h2 class="success-title">Thank You!</h2>
            <p class="success-text">Your feedback has been submitted successfully. We appreciate your input!</p>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Preview mode - disable form submission
        const isPreview = <?php echo $is_preview ? 'true' : 'false'; ?>;
        
        if(isPreview){
            document.getElementById('feedbackForm').addEventListener('submit', function(e){
                e.preventDefault();
                alert('This is preview mode. Form submission is disabled.');
            });
        } else {
            // Handle form submission
            document.getElementById('feedbackForm').addEventListener('submit', async function(e){
                e.preventDefault();
                
                const form = e.target;
                const submitBtn = form.querySelector('.btn-submit');
                const originalBtnText = submitBtn.innerHTML;
                
                // Disable form during submission
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i data-lucide="loader" style="width: 20px; height: 20px;"></i> Submitting...';
                lucide.createIcons();
                
                try {
                    const formData = new FormData(form);
                    
                    const response = await fetch('feedback_form.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if(result.success){
                        // Hide entire feedback container (header + form) and show success message
                        document.getElementById('feedbackFormContainer').style.display = 'none';
                        document.getElementById('successMessage').style.display = 'block';
                        window.scrollTo(0, 0);
                    } else {
                        alert('Error: ' + result.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                        lucide.createIcons();
                    }
                } catch(error){
                    alert('An error occurred. Please check your connection and try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    lucide.createIcons();
                }
            });
        }
        
        // Star rating hover effect
        const ratingLabels = document.querySelectorAll('.rating-input label');
        
        ratingLabels.forEach(label => {
            label.addEventListener('mouseenter', function() {
                // Get the value of the hovered star (1-5)
                const hoveredValue = parseInt(this.getAttribute('for').replace('rating', ''));
                
                // Add hover effect to stars with value <= hovered value
                ratingLabels.forEach(lbl => {
                    const lblValue = parseInt(lbl.getAttribute('for').replace('rating', ''));
                    if (lblValue <= hoveredValue) {
                        lbl.classList.add('hover-prev');
                    } else {
                        lbl.classList.remove('hover-prev');
                    }
                });
            });
            
            label.addEventListener('mouseleave', function() {
                // Remove hover effect from all stars
                ratingLabels.forEach(lbl => {
                    lbl.classList.remove('hover-prev');
                });
            });
        });
    </script>
</body>
</html>
