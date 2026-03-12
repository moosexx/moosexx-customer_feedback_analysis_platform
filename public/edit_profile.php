<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "../php/config.php";

$user_id = $_SESSION["id"];

// Get user information
$user_sql = "SELECT email, created_at FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

// Get business information
$business_sql = "SELECT * FROM businesses WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $business_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$business_result = mysqli_stmt_get_result($stmt);
$business = mysqli_fetch_assoc($business_result);
mysqli_stmt_close($stmt);

// If no business exists, redirect to initial profile setup
if(!$business){
    header("location: profile.php");
    exit;
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - FeedbackIQ</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-page-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .profile-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
            overflow-x: auto;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.2s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-button:hover {
            color: var(--text-main);
            background: rgba(209, 244, 112, 0.1);
        }

        .tab-button.active {
            color: var(--text-main);
            border-bottom-color: var(--primary-color);
        }

        .profile-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .profile-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .profile-section h2 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(209, 244, 112, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            font-weight: 500;
        }

        .message.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        @media (max-width: 768px) {
            .profile-page-container {
                padding: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
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

    <div class="profile-page-container">
        <div class="dashboard-header" style="margin-bottom: 2rem;">
            <div class="dashboard-title">
                <h1>Edit Your Profile</h1>
                <p class="dashboard-subtitle">Manage your account and business information</p>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="profile-tabs">
            <button class="tab-button active" onclick="switchTab('account')">
                <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                Account
            </button>
            <button class="tab-button" onclick="switchTab('business')">
                <i data-lucide="building" style="width: 18px; height: 18px;"></i>
                Business
            </button>
            <button class="tab-button" onclick="switchTab('questions')">
                <i data-lucide="circle-help" style="width: 18px; height: 18px;"></i>
                Questions
            </button>
        </div>

        <!-- User Account Section -->
        <div class="profile-section active" id="account-section">
            <h2>
                <i data-lucide="user" style="width: 24px; height: 24px;"></i>
                Account Information
            </h2>
            <form id="userForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password (leave empty to keep current)</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Update Account</button>
                </div>
            </form>
            <div id="userMessage"></div>
        </div>

        <!-- Business Information Section -->
        <div class="profile-section" id="business-section">
            <h2>
                <i data-lucide="building" style="width: 24px; height: 24px;"></i>
                Business Information
            </h2>
            <form id="businessForm">
                <div class="form-group">
                    <label for="business_name">Business Name</label>
                    <input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($business['business_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="industry">Industry</label>
                    <select id="industry" name="industry">
                        <option value="retail" <?php echo $business['industry'] === 'retail' ? 'selected' : ''; ?>>Retail</option>
                        <option value="food" <?php echo $business['industry'] === 'food' ? 'selected' : ''; ?>>Food & Beverage</option>
                        <option value="healthcare" <?php echo $business['industry'] === 'healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                        <option value="tech" <?php echo $business['industry'] === 'tech' ? 'selected' : ''; ?>>Technology</option>
                        <option value="hospitality" <?php echo $business['industry'] === 'hospitality' ? 'selected' : ''; ?>>Hospitality</option>
                        <option value="education" <?php echo $business['industry'] === 'education' ? 'selected' : ''; ?>>Education</option>
                        <option value="other" <?php echo $business['industry'] === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Business Description</label>
                    <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($business['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Update Business Info</button>
                </div>
            </form>
            <div id="businessMessage"></div>
        </div>

        <!-- Custom Questions Section -->
        <div class="profile-section" id="questions-section">
            <h2>
                <i data-lucide="circle-help" style="width: 24px; height: 24px;"></i>
                Custom Feedback Questions
            </h2>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Add custom questions to your feedback form to gather specific insights from your customers.</p>
            
            <div id="questionsList">
                <!-- Questions will be loaded here -->
            </div>

            <button id="addQuestionBtn" class="btn-secondary" style="margin-top: 1rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="plus" style="width: 18px; height: 18px;"></i>
                Add New Question
            </button>

            <div class="form-actions">
                <button type="button" id="saveQuestionsBtn" class="btn-primary">Save All Questions</button>
            </div>
            <div id="questionsMessage"></div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // Tab switching function
        function switchTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.profile-section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show selected section
            const selectedSection = document.getElementById(tabName + '-section');
            if (selectedSection) {
                selectedSection.classList.add('active');
            }

            // Add active class to clicked tab
            event.currentTarget.classList.add('active');

            // Re-render icons
            setTimeout(() => lucide.createIcons(), 0);
        }

        // Logout confirmation
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../php/logout.php';
            }
        });

        // Update User Account
        document.getElementById('userForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const messageDiv = document.getElementById('userMessage');
            
            try {
                const response = await fetch('../php/edit_profile_action.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        ...Object.fromEntries(formData),
                        action: 'update_user'
                    })
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    messageDiv.textContent = result.message;
                    messageDiv.className = 'message success';
                    setTimeout(() => messageDiv.textContent = '', 3000);
                } else {
                    messageDiv.textContent = result.message || 'Update failed';
                    messageDiv.className = 'message error';
                }
            } catch (error) {
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.className = 'message error';
            }
        });

        // Update Business Information
        document.getElementById('businessForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const messageDiv = document.getElementById('businessMessage');
            
            try {
                const response = await fetch('../php/edit_profile_action.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        ...Object.fromEntries(formData),
                        action: 'update_business'
                    })
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    messageDiv.textContent = result.message;
                    messageDiv.className = 'message success';
                    setTimeout(() => messageDiv.textContent = '', 3000);
                } else {
                    messageDiv.textContent = result.message || 'Update failed';
                    messageDiv.className = 'message error';
                }
            } catch (error) {
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.className = 'message error';
            }
        });

        // Load existing questions
        async function loadQuestions() {
            try {
                const response = await fetch('../php/edit_profile_action.php?action=get_questions');
                const result = await response.json();
                
                if (result.status === 'success') {
                    displayQuestions(result.questions || []);
                }
            } catch (error) {
                console.error('Error loading questions:', error);
            }
        }

        function displayQuestions(questions) {
            const container = document.getElementById('questionsList');
            container.innerHTML = '';

            questions.forEach((question, index) => {
                const questionDiv = document.createElement('div');
                questionDiv.style.cssText = `
                    background: var(--bg-muted);
                    padding: 0.75rem;
                    border-radius: var(--radius-md);
                    margin-bottom: 1rem;
                    display: grid;
                    grid-template-columns: 1fr auto;
                    gap: 0.75rem;
                    align-items: center;
                `;
                
                questionDiv.innerHTML = `
                    <input type="text" 
                           value="${question.question_text}" 
                           data-question-id="${question.id}"
                           placeholder="Enter your question"
                           style="width: 100%; padding: 0.625rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 1rem; box-sizing: border-box;">
                    <button onclick="removeQuestion(${index})" 
                            style="background: transparent; border: 1px solid var(--error-color); color: var(--error-color); padding: 0.625rem 0.75rem; border-radius: var(--radius-md); cursor: pointer; white-space: nowrap; height: fit-content; align-self: center;">
                        <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                    </button>
                `;
                
                container.appendChild(questionDiv);
            });

            lucide.createIcons();
        }

        let questionIndex = 0;

        document.getElementById('addQuestionBtn').addEventListener('click', () => {
            const container = document.getElementById('questionsList');
            const questionDiv = document.createElement('div');
            questionDiv.style.cssText = `
                background: var(--bg-muted);
                padding: 0.75rem;
                border-radius: var(--radius-md);
                margin-bottom: 1rem;
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 0.75rem;
                align-items: center;
            `;
            
            questionDiv.innerHTML = `
                <input type="text" 
                       data-new-index="${questionIndex++}"
                       placeholder="Enter your custom question"
                       style="width: 100%; padding: 0.625rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 1rem; box-sizing: border-box;">
                <button onclick="this.parentElement.remove()" 
                        style="background: transparent; border: 1px solid var(--error-color); color: var(--error-color); padding: 0.625rem 0.75rem; border-radius: var(--radius-md); cursor: pointer; white-space: nowrap; height: fit-content; align-self: center;">
                    <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                </button>
            `;
            
            container.appendChild(questionDiv);
            lucide.createIcons();
        });

        document.getElementById('saveQuestionsBtn').addEventListener('click', async () => {
            const messageDiv = document.getElementById('questionsMessage');
            const questions = [];
            
            // Get existing questions with IDs
            document.querySelectorAll('[data-question-id]').forEach(input => {
                questions.push({
                    id: input.dataset.questionId,
                    text: input.value
                });
            });
            
            // Get new questions without IDs
            document.querySelectorAll('[data-new-index]').forEach(input => {
                if (input.value.trim()) {
                    questions.push({
                        text: input.value
                    });
                }
            });

            try {
                const response = await fetch('../php/edit_profile_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'save_questions',
                        questions: questions
                    })
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    messageDiv.textContent = result.message;
                    messageDiv.className = 'message success';
                    setTimeout(() => {
                        messageDiv.textContent = '';
                        loadQuestions(); // Reload to refresh
                    }, 3000);
                } else {
                    messageDiv.textContent = result.message || 'Save failed';
                    messageDiv.className = 'message error';
                }
            } catch (error) {
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.className = 'message error';
            }
        });

        // Load questions on page load
        loadQuestions();
    </script>
</body>
</html>
