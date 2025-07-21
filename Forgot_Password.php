<?php
// forgot-password.php

// Prevent any output before JSON response
ob_start();

// Disable HTML error display for AJAX requests on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

session_start();

// Include the configuration file
if (!file_exists('config.php')) {
    // This error will be caught by the client-side fetch if it's a POST request
    // For GET requests (initial page load), it will just continue to the HTML
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Configuration file missing. Please create config.php with database and email settings.']);
        exit;
    }
}
require_once 'config.php';

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure PHPMailer is loaded
require_once __DIR__ . '/vendor/autoload.php'; // Use Composer's autoloader

// Ensure PHPMailer is loaded
require_once __DIR__ . '/vendor/autoload.php'; // Use Composer's autoloader

// If not using Composer, manually include the PHPMailer files:
// require_once 'path/to/PHPMailer/src/Exception.php';
// require_once 'path/to/PHPMailer/src/PHPMailer.php';
// require_once 'path/to/PHPMailer/src/SMTP.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any previous output
    ob_clean();
    
    header('Content-Type: application/json');
    
    try {
        // Check if database variables are defined from config.php
        if (!isset($host) || !isset($dbname) || !isset($username) || !isset($password)) {
            echo json_encode(['success' => false, 'message' => 'Database configuration incomplete in config.php']);
            exit;
        }

        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $email = $_POST['email'] ?? '';
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        // Check all potential user tables for the email
        $user_found = false;
        $user_table = '';
        $user_id = null;
        $user_name = '';

        $tables_to_check = ['admins', 'customers', 'vendors']; // Add or remove tables as needed

        foreach ($tables_to_check as $table) {
            // Check if table exists before querying
            $stmt_check_table = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt_check_table->execute([$table]);
            if ($stmt_check_table->rowCount() === 0) {
                // Table doesn't exist, skip to next
                continue;
            }

            $stmt = $pdo->prepare("SELECT id, email, name, first_name, last_name FROM $table WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $user_found = true;
                $user_table = $table;
                $user_id = $user['id'];
                $user_name = $user['name'] ?? ($user['first_name'] . ' ' . $user['last_name']);
                break; // User found, no need to check other tables
            }
        }

        if (!$user_found) {
            echo json_encode(['success' => false, 'message' => 'No account found with that email address.']);
            exit;
        }

        // Generate a unique token
        $token = bin2hex(random_bytes(32)); // 64 character hex string
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour')); // Token valid for 1 hour

        // Store token in a password_resets table
        // Create this table if it doesn't exist:
        // CREATE TABLE password_resets (
        //     id INT AUTO_INCREMENT PRIMARY KEY,
        //     email VARCHAR(255) NOT NULL,
        //     token VARCHAR(64) NOT NULL,
        //     expires DATETIME NOT NULL,
        //     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        //     INDEX (token),
        //     INDEX (email)
        // );
        // You might also want to add a user_id and user_type column for better tracking
        // ALTER TABLE password_resets ADD COLUMN user_id INT, ADD COLUMN user_type VARCHAR(50);

        // Delete any existing tokens for this email to prevent multiple valid tokens
        $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt_delete->execute([$email]);

        $stmt_insert = $pdo->prepare("INSERT INTO password_resets (email, token, expires, user_id, user_type) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->execute([$email, $token, $expires, $user_id, $user_table]);
        
        // Send email using PHPMailer
        // Ensure PHPMailer is properly initialized
        require 'path/to/PHPMailer/src/Exception.php';
        require 'path/to/PHPMailer/src/PHPMailer.php';
        require 'path/to/PHPMailer/src/SMTP.php';

        $mail = new PHPMailer(true); // true enables exceptions
        try {
            // Server settings (from config.php)
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_username;
            $mail->Password   = $smtp_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use ENCRYPTION_SMTPS for port 465
            $mail->Port       = $smtp_port;

            // Recipients
            $mail->setFrom($smtp_from_email, $smtp_from_name);
            $mail->addAddress($email, $user_name); // Add a recipient

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request for All in One Wedding';
            $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/reset-password.php?email=" . urlencode($email) . "&token=" . urlencode($token);
            $mail->Body    = "
                <p>Dear $user_name,</p>
                <p>We received a request to reset your password for your All in One Wedding account.</p>
                <p>To reset your password, please click on the link below:</p>
                <p><a href=\"$reset_link\">$reset_link</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you did not request a password reset, please ignore this email.</p>
                <p>Thank you,<br>The All in One Wedding Team</p>
            ";
            $mail->AltBody = "Dear $user_name,\n\nWe received a request to reset your password for your All in One Wedding account. To reset your password, please visit the following link: $reset_link\n\nThis link will expire in 1 hour.\n\nIf you did not request a password reset, please ignore this email.\n\nThank you,\nThe All in One Wedding Team";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'A password reset link has been sent to your email address. Please check your inbox (and spam folder).']);
        } catch (PDOException $e) {
            // Log the error for debugging, but provide a generic message to the user
            error_log("Mailer Error: {$mail->ErrorInfo}");
            echo json_encode(['success' => false, 'message' => 'Could not send reset email. Please try again later.']);
        }
        
    } catch (PDOException $e) {
        $error_message = 'Database connection failed';
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            $error_message = 'Database access denied. Check username/password in config.php';
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            $error_message = 'Database does not exist. Please create the database first.';
        } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
            $error_message = 'Cannot connect to database server. Is MySQL running?';
        }
        echo json_encode(['success' => false, 'message' => $error_message]);
    } catch (PDOException $e) {
        // Catch any other general exceptions
        echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// Clear output buffer for HTML page if it's a GET request
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - All in One Wedding</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .forgot-password-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><defs><pattern id="hearts" patternUnits="userSpaceOnUse" width="40" height="40"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="400" height="400" fill="url(%23hearts)"/></svg>');
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .header-content {
            position: relative;
            z-index: 2;
            color: white;
        }

        .header-icon {
            font-size: 4rem;
            margin-bottom: 15px;
            display: block;
            opacity: 0.9;
        }

        .header-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .form-section {
            padding: 40px 30px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-size: 14px;
            font-weight: bold;
            color: #666;
            transition: all 0.3s ease;
        }

        .step.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .step.completed {
            background: #28a745;
            color: white;
        }

        .step::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 2px;
            background: #e1e5e9;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            z-index: -1;
        }

        .step:last-child::after {
            display: none;
        }

        .step.completed::after {
            background: #28a745;
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .step-title {
            text-align: center;
            color: #333;
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .step-description {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-label {
            position: absolute;
            left: 20px;
            top: 15px;
            color: #666;
            transition: all 0.3s ease;
            pointer-events: none;
            background: #f8f9fa;
            padding: 0 5px;
        }

        .form-input:focus + .form-label,
        .form-input:not(:placeholder-shown) + .form-label {
            top: -10px;
            font-size: 12px;
            color: #667eea;
            background: white;
        }

        .user-type-group {
            margin-bottom: 25px;
        }

        .user-type-label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }

        .user-type-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .user-type-option {
            position: relative;
        }

        .user-type-input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .user-type-card {
            display: block;
            padding: 15px 10px;
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }

        .user-type-card:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .user-type-input:checked + .user-type-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .user-type-icon {
            font-size: 20px;
            margin-bottom: 5px;
            display: block;
        }

        .action-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .action-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .action-btn:active {
            transform: translateY(0);
        }

        .action-btn:disabled {
            background: linear-gradient(135deg, #ccc 0%, #999 100%);
            cursor: not-allowed;
        }

        .back-btn {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .back-btn:hover {
            background: #667eea;
            color: white;
        }

        .form-links {
            text-align: center;
            margin-top: 20px;
        }

        .form-links a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .form-links a:hover {
            color: #764ba2;
        }

        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }

        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #363;
        }

        .info-message {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #0066cc;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .verification-code-input {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 5px;
            font-family: 'Courier New', monospace;
        }

        .password-strength {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .strength-medium {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .strength-strong {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }

        .resend-code {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }

        .resend-link {
            color: #667eea;
            text-decoration: none;
            cursor: pointer;
        }

        .resend-link:hover {
            color: #764ba2;
        }

        .resend-link:disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .forgot-password-container {
                margin: 10px;
            }
            
            .form-section {
                padding: 30px 20px;
            }

            .user-type-options {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .step-indicator {
                margin-bottom: 20px;
            }

            .step {
                width: 25px;
                height: 25px;
                font-size: 12px;
                margin: 0 5px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="header-section">
            <div class="header-content">
                <span class="header-icon">🔐</span>
                <h1 class="header-title">Forgot Password</h1>
                <p class="header-subtitle">Don't worry, we'll help you reset it</p>
            </div>
        </div>
        
        <div class="form-section">
            <div class="step-indicator">
                <div class="step active" data-step="1">1</div>
                <div class="step" data-step="2">2</div>
                <div class="step" data-step="3">3</div>
            </div>

            <div id="errorMessage" class="message error-message"></div>
            <div id="successMessage" class="message success-message"></div>
            <div id="infoMessage" class="message info-message"></div>
            
            <!-- Step 1: Email and User Type -->
            <div class="form-step active" id="step1">
                <h2 class="step-title">Identify Your Account</h2>
                <p class="step-description">Enter your email address and select your account type to begin the password reset process.</p>
                
                <form id="emailForm">
                    <div class="user-type-group">
                        <label class="user-type-label">I am a:</label>
                        <div class="user-type-options">
                            <div class="user-type-option">
                                <input type="radio" id="admin_reset" name="user_type" value="admin" class="user-type-input">
                                <label for="admin_reset" class="user-type-card">
                                    <span class="user-type-icon">⚙️</span>
                                    Admin
                                </label>
                            </div>
                            <div class="user-type-option">
                                <input type="radio" id="bride_groom_reset" name="user_type" value="bride_groom" class="user-type-input" checked>
                                <label for="bride_groom_reset" class="user-type-card">
                                    <span class="user-type-icon">💑</span>
                                    Bride & Groom
                                </label>
                            </div>
                            <div class="user-type-option">
                                <input type="radio" id="vendor_reset" name="user_type" value="vendor" class="user-type-input">
                                <label for="vendor_reset" class="user-type-card">
                                    <span class="user-type-icon">🏢</span>
                                    Vendor
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="email" class="form-input" id="email" name="email" placeholder=" " required>
                        <label class="form-label" for="email">Email Address</label>
                    </div>
                    
                    <button type="submit" class="action-btn" id="sendCodeBtn">Send Verification Code</button>
                </form>

                <div class="form-links">
                    <a href="index.php">← Back to Login</a>
                </div>
            </div>

            <!-- Step 2: Verification Code -->
            <div class="form-step" id="step2">
                <h2 class="step-title">Enter Verification Code</h2>
                <p class="step-description">We've sent a 6-digit verification code to your email address. Please check your inbox and spam folder.</p>
                
                <form id="verificationForm">
                    <div class="form-group">
                        <input type="text" class="form-input verification-code-input" id="verificationCode" name="verification_code" placeholder=" " maxlength="6" required>
                        <label class="form-label" for="verificationCode">Verification Code</label>
                    </div>
                    
                    <button type="submit" class="action-btn" id="verifyCodeBtn">Verify Code</button>
                    <button type="button" class="action-btn back-btn" onclick="goToStep(1)">Back</button>
                </form>

                <div class="resend-code">
                    <span>Didn't receive the code?</span>
                    <a href="#" class="resend-link" id="resendCode">Resend Code</a>
                    <span id="resendTimer" style="display: none;"></span>
                </div>
            </div>

            <!-- Step 3: New Password -->
            <div class="form-step" id="step3">
                <h2 class="step-title">Create New Password</h2>
                <p class="step-description">Choose a strong password for your account. Make sure it's at least 8 characters long.</p>
                
                <form id="passwordForm">
                    <div class="form-group">
                        <input type="password" class="form-input" id="newPassword" name="new_password" placeholder=" " required>
                        <label class="form-label" for="newPassword">New Password</label>
                    </div>
                    
                    <div id="passwordStrength" class="password-strength" style="display: none;"></div>
                    
                    <div class="form-group">
                        <input type="password" class="form-input" id="confirmPassword" name="confirm_password" placeholder=" " required>
                        <label class="form-label" for="confirmPassword">Confirm New Password</label>
                    </div>
                    
                    <button type="submit" class="action-btn" id="resetPasswordBtn">Reset Password</button>
                    <button type="button" class="action-btn back-btn" onclick="goToStep(2)">Back</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let userEmail = '';
        let userType = '';
        let verificationToken = '';
        let resendTimer = null;
        let resendCountdown = 0;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initializeFloatingLabels();
            
            // Email form submission
            document.getElementById('emailForm').addEventListener('submit', function(e) {
                e.preventDefault();
                handleEmailSubmission();
            });

            // Verification form submission
            document.getElementById('verificationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                handleVerificationSubmission();
            });

            // Password form submission
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                e.preventDefault();
                handlePasswordReset();
            });

            // Password strength checker
            document.getElementById('newPassword').addEventListener('input', checkPasswordStrength);

            // Resend code functionality
            document.getElementById('resendCode').addEventListener('click', function(e) {
                e.preventDefault();
                handleResendCode();
            });

            // Auto-format verification code
            document.getElementById('verificationCode').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                e.target.value = value;
            });
        });

        function handleEmailSubmission() {
            const email = document.getElementById('email').value.trim();
            const userTypeElement = document.querySelector('input[name="user_type"]:checked');
            
            if (!email || !userTypeElement) {
                showMessage('error', 'Please fill in all fields and select your account type');
                return;
            }

            if (!isValidEmail(email)) {
                showMessage('error', 'Please enter a valid email address');
                return;
            }

            userEmail = email;
            userType = userTypeElement.value;

            setLoading('sendCodeBtn', true);
            hideMessages();

            const formData = new FormData();
            formData.append('action', 'send_code');
            formData.append('email', email);
            formData.append('user_type', userType);

            fetch('forgot-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setLoading('sendCodeBtn', false);
                if (data.success) {
                    showMessage('success', 'Verification code sent successfully! Check your email.');
                    goToStep(2);
                    startResendTimer();
                } else {
                    showMessage('error', data.message || 'Failed to send verification code. Please try again.');
                }
            })
            .catch(error => {
                setLoading('sendCodeBtn', false);
                console.error('Error:', error);
                showMessage('error', 'Connection error. Please try again.');
            });
        }

        function handleVerificationSubmission() {
            const code = document.getElementById('verificationCode').value.trim();
            
            if (!code || code.length !== 6) {
                showMessage('error', 'Please enter the complete 6-digit verification code');
                return;
            }

            setLoading('verifyCodeBtn', true);
            hideMessages();

            const formData = new FormData();
            formData.append('action', 'verify_code');
            formData.append('email', userEmail);
            formData.append('user_type', userType);
            formData.append('verification_code', code);

            fetch('forgot-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setLoading('verifyCodeBtn', false);
                if (data.success) {
                    verificationToken = data.token;
                    showMessage('success', 'Code verified successfully!');
                    goToStep(3);
                } else {
                    showMessage('error', data.message || 'Invalid verification code. Please try again.');
                }
            })
            .catch(error => {
                setLoading('verifyCodeBtn', false);
                console.error('Error:', error);
                showMessage('error', 'Connection error. Please try again.');
            });
        }

        function handlePasswordReset() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword.length < 8) {
                showMessage('error', 'Password must be at least 8 characters long');
                return;
            }

            if (newPassword !== confirmPassword) {
                showMessage('error', 'Passwords do not match');
                return;
            }

            if (getPasswordStrength(newPassword) === 'weak') {
                showMessage('error', 'Please choose a stronger password');
                return;
            }

            setLoading('resetPasswordBtn', true);
            hideMessages();

            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('email', userEmail);
            formData.append('user_type', userType);
            formData.append('token', verificationToken);
            formData.append('new_password', newPassword);

            fetch('forgot-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                setLoading('resetPasswordBtn', false);
                if (data.success) {
                    showMessage('success', 'Password reset successful! You can now login with your new password.');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 3000);
                } else {
                    showMessage('error', data.message || 'Failed to reset password. Please try again.');
                }
            })
            .catch(error => {
                setLoading('resetPasswordBtn', false);
                console.error('Error:', error);
                showMessage('error', 'Connection error. Please try again.');
            });
        }

        function handleResendCode() {
            if (resendCountdown > 0) {
                return;
            }

            const resendLink = document.getElementById('resendCode');
            resendLink.style.pointerEvents = 'none';
            resendLink.style.color = '#ccc';

            const formData = new FormData();
            formData.append('action', 'send_code');
            formData.append('email', userEmail);
            formData.append('user_type', userType);

            fetch('forgot-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('info', 'New verification code sent!');
                    startResendTimer();
                } else {
                    showMessage('error', data.message || 'Failed to resend code');
                    resendLink.style.pointerEvents = 'auto';
                    resendLink.style.color = '#667eea';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Connection error. Please try again.');
                resendLink.style.pointerEvents = 'auto';
                resendLink.style.color = '#667eea';
            });
        }

        function startResendTimer() {
            resendCountdown = 60;
            const resendLink = document.getElementById('resendCode');
            const resendTimerElement = document.getElementById('resendTimer');
            
            resendLink.style.display = 'none';
            resendTimerElement.style.display = 'inline';
            
            resendTimer = setInterval(() => {
                resendCountdown--;
                resendTimerElement.textContent = `(${resendCountdown}s)`;
                
                if (resendCountdown <= 0) {
                    clearInterval(resendTimer);
                    resendLink.style.display = 'inline';
                    resendLink.style.pointerEvents = 'auto';
                    resendLink.style.color = '#667eea';
                    resendTimerElement.style.display = 'none';
                }
            }, 1000);
        }

        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }

            const strength = getPasswordStrength(password);
            strengthDiv.style.display = 'block';
            
            // Remove all strength classes
            strengthDiv.className = 'password-strength';
            
            if (strength === 'weak') {
                strengthDiv.classList.add('strength-weak');
                strengthDiv.textContent = 'Weak password. Add numbers, special characters, and uppercase letters.';
            } else if (strength === 'medium') {
                strengthDiv.classList.add('strength-medium');
                strengthDiv.textContent = 'Good password. Consider adding more variety for better security.';
            } else {
                strengthDiv.classList.add('strength-strong');
                strengthDiv.textContent = 'Strong password! ✓';
            }
        }

        function getPasswordStrength(password) {
            let score = 0;
            
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            if (score < 3) return 'weak';
            if (score < 5) return 'medium';
            return 'strong';
        }

        function goToStep(step) {
            // Hide current step
            document.querySelector('.form-step.active').classList.remove('active');
            document.querySelector('.step.active').classList.remove('active');
            
            // Show new step
            document.getElementById(`step${step}`).classList.add('active');
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            
            // Update completed steps
            for (let i = 1; i < step; i++) {
                document.querySelector(`.step[data-step="${i}"]`).classList.add('completed');
            }
            
            currentStep = step;
            hideMessages();
        }

        function showMessage(type, message) {
            hideMessages();
            const messageDiv = document.getElementById(`${type}Message`);
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideMessages() {
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.display = 'none';
            });
        }

        function setLoading(buttonId, isLoading) {
            const button = document.getElementById(buttonId);
            button.disabled = isLoading;
            if (isLoading) {
                button.textContent = 'Processing...';
                button.style.opacity = '0.7';
            } else {
                // Reset button text based on button
                const originalTexts = {
                    'sendCodeBtn': 'Send Verification Code',
                    'verifyCodeBtn': 'Verify Code',
                    'resetPasswordBtn': 'Reset Password'
                };
                button.textContent = originalTexts[buttonId] || 'Submit';
                button.style.opacity = '1';
            }
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function initializeFloatingLabels() {
            document.querySelectorAll('.form-input').forEach(input => {
                const checkLabel = () => {
                    const label = input.nextElementSibling;
                    if (label && label.classList.contains('form-label')) {
                        if (input.value !== '' || input === document.activeElement) {
                            label.style.top = '-10px';
                            label.style.fontSize = '12px';
                            label.style.color = '#667eea';
                            label.style.background = 'white';
                        } else {
                            label.style.top = '15px';
                            label.style.fontSize = '16px';
                            label.style.color = '#666';
                            label.style.background = '#f8f9fa';
                        }
                    }
                };
                input.addEventListener('input', checkLabel);
                checkLabel(); // Initial check for existing values
            });
            document.querySelectorAll('.user-type-input').forEach(input => {
                const checkLabel = () => {
                    const label = input.nextElementSibling;
                    if (label && label.classList.contains('user-type-card')) {
                        if (input.checked) {
                            label.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                            label.style.color = 'white';
                        } else {
                            label.style.background = '#f8f9fa';
                            label.style.color = '#666';
                        }
                    }
                };
                input.addEventListener('change', checkLabel);
                checkLabel(); // Initial check for existing values
            });
        }
    </script>
</body>
</html>