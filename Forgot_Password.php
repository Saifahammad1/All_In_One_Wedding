<?php
require_once 'config.php'; 
// forgot_password_handler.php
session_start();


// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'send_verification':
            handleSendVerification($input);
            break;
        case 'verify_code':
            handleVerifyCode($input);
            break;
        case 'reset_password':
            handleResetPassword($input);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleSendVerification($input) {
    global $pdo; // Assuming PDO connection from your config
    
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $userType = $input['user_type'] ?? '';
    
    if (!$email) {
        throw new Exception('Invalid email address');
    }
    
    if (!in_array($userType, ['admin', 'bride_groom', 'vendor'])) {
        throw new Exception('Invalid user type');
    }
    
    // Check if user exists in the appropriate table
    $user = findUserByEmailAndType($email, $userType);
    
    if (!$user) {
        throw new Exception('User not found for the selected account type');
    }
    
    // Generate verification code
    $verificationCode = sprintf('%06d', mt_rand(0, 999999));
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
    
    // Store verification code in database
    storeVerificationCode($email, $userType, $verificationCode, $expiresAt);
    
    // Send email
    if (sendVerificationEmail($email, $verificationCode, $user['name'] ?? 'User')) {
        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent successfully'
        ]);
    } else {
        throw new Exception('Failed to send verification email');
    }
}

function handleVerifyCode($input) {
    global $pdo;
    
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $userType = $input['user_type'] ?? '';
    $code = $input['verification_code'] ?? '';
    
    if (!$email || !$userType || !$code) {
        throw new Exception('Missing required fields');
    }
    
    // Verify the code
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE email = ? AND user_type = ? AND verification_code = ? 
        AND expires_at > NOW() AND used = 0
    ");
    $stmt->execute([$email, $userType, $code]);
    $token = $stmt->fetch();
    
    if (!$token) {
        throw new Exception('Invalid or expired verification code');
    }
    
    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $resetExpiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
    
    // Update the record with reset token
    $stmt = $pdo->prepare("
        UPDATE password_reset_tokens 
        SET reset_token = ?, reset_expires_at = ?, verified = 1 
        WHERE id = ?
    ");
    $stmt->execute([$resetToken, $resetExpiresAt, $token['id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Code verified successfully',
        'reset_token' => $resetToken
    ]);
}

function handleResetPassword($input) {
    global $pdo;
    
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $userType = $input['user_type'] ?? '';
    $resetToken = $input['reset_token'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    
    if (!$email || !$userType || !$resetToken || !$newPassword) {
        throw new Exception('Missing required fields');
    }
    
    // Validate password strength
    if (!isStrongPassword($newPassword)) {
        throw new Exception('Password does not meet security requirements');
    }
    
    // Verify reset token
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens 
        WHERE email = ? AND user_type = ? AND reset_token = ? 
        AND reset_expires_at > NOW() AND verified = 1 AND used = 0
    ");
    $stmt->execute([$email, $userType, $resetToken]);
    $token = $stmt->fetch();
    
    if (!$token) {
        throw new Exception('Invalid or expired reset token');
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update user password in appropriate table
    $updated = updateUserPassword($email, $userType, $hashedPassword);
    
    if (!$updated) {
        throw new Exception('Failed to update password');
    }
    
    // Mark token as used
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
    $stmt->execute([$token['id']]);
    
    // Send confirmation email
    sendPasswordResetConfirmationEmail($email, $token['user_name'] ?? 'User');
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully'
    ]);
}

function findUserByEmailAndType($email, $userType) {
    global $pdo;
    
    $table = '';
    $nameField = 'name';
    
    switch ($userType) {
        case 'admin':
            $table = 'admins';
            break;
        case 'bride_groom':
            $table = 'couples'; // or whatever your couples table is named
            $nameField = 'bride_name'; // adjust based on your schema
            break;
        case 'vendor':
            $table = 'vendors';
            $nameField = 'business_name'; // adjust based on your schema
            break;
        default:
            return null;
    }
    
    $stmt = $pdo->prepare("SELECT id, email, {$nameField} as name FROM {$table} WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function storeVerificationCode($email, $userType, $code, $expiresAt) {
    global $pdo;
    
    // Get user name for the email
    $user = findUserByEmailAndType($email, $userType);
    $userName = $user['name'] ?? '';
    
    // Delete any existing unused tokens for this email and user type
    $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ? AND user_type = ? AND used = 0");
    $stmt->execute([$email, $userType]);
    
    // Insert new verification code
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens 
        (email, user_type, user_name, verification_code, expires_at, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$email, $userType, $userName, $code, $expiresAt]);
}

function updateUserPassword($email, $userType, $hashedPassword) {
    global $pdo;
    
    $table = '';
    switch ($userType) {
        case 'admin':
            $table = 'admins';
            break;
        case 'bride_groom':
            $table = 'couples';
            break;
        case 'vendor':
            $table = 'vendors';
            break;
        default:
            return false;
    }
    
    $stmt = $pdo->prepare("UPDATE {$table} SET password = ? WHERE email = ?");
    return $stmt->execute([$hashedPassword, $email]);
}

function sendVerificationEmail($email, $code, $userName) {
    $subject = "Password Reset Verification Code - All in One Wedding";
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #667eea;'>All in One Wedding</h1>
            </div>
            
            <h2>Password Reset Request</h2>
            <p>Hello {$userName},</p>
            <p>You requested to reset your password. Please use the verification code below:</p>
            
            <div style='background: #f8f9fa; padding: 20px; text-align: center; margin: 20px 0;'>
                <h1 style='color: #667eea; letter-spacing: 5px; margin: 0;'>{$code}</h1>
            </div>
            
            <p><strong>Important:</strong></p>
            <ul>
                <li>This code will expire in 10 minutes</li>
                <li>Do not share this code with anyone</li>
                <li>If you didn't request this, please ignore this email</li>
            </ul>
            
            <p>Best regards,<br>All in One Wedding Team</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@allinone-wedding.com" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

function sendPasswordResetConfirmationEmail($email, $userName) {
    $subject = "Password Reset Successful - All in One Wedding";
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #667eea;'>All in One Wedding</h1>
            </div>
            
            <h2>Password Reset Successful</h2>
            <p>Hello {$userName},</p>
            <p>Your password has been successfully reset. You can now log in with your new password.</p>
            
            <p>If you didn't make this change, please contact our support team immediately.</p>
            
            <p>Best regards,<br>All in One Wedding Team</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@allinone-wedding.com" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

function isStrongPassword($password) {
    // Check minimum length
    if (strlen($password) < 8) {
        return false;
    }
    
    // Check for uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // Check for lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // Check for number
    if (!preg_match('/\d/', $password)) {
        return false;
    }
    
    return true;
}
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
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 650px;
            transition: all 0.3s ease;
            position: relative;
            color: #333;
            font-size: 16px;
            font-weight: 400;
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            overflow: hidden;
        }

        .logo-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            color: white;
            
        }

        .logo-section::before {
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

        .logo-content {
            text-align: center;
            color: white;
            z-index: 2;
            position: relative;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .logo-image {
            width: 200px;
            height: 200px;
            margin-bottom: 20px;
            border-radius: 50%;
            opacity: 0.9;
            background: rgba(255,255,255,0.1);
            font-size: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: url('All in one Wedding logo.png');
            background-size: cover;
            background-position: center;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            cursor: pointer;
            transition: transform 0.3s ease;
            transform: scale(1.05);
            background-repeat: no-repeat;
            background-size: contain;
            background-position: center;

        }

        .logo-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .logo-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
            color: rgba(255,255,255,0.8);
            margin-bottom: 20px;
            text-align: center;
        }

        .form-section {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-title {
            font-size: 2.2rem;
            color: #333;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-subtitle {
            color: #666;
            font-size: 1rem;
            line-height: 1.5;
        }

        .forgot-password-form {
            width: 100%;
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

        .reset-btn {
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
            margin-bottom: 20px;
        }

        .reset-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .reset-btn:active {
            transform: translateY(0);
        }

        .reset-btn:disabled {
            background: linear-gradient(135deg, #ccc 0%, #999 100%);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .form-links {
            text-align: center;
            margin-top: 20px;
        }

        .form-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
            transition: color 0.3s ease;
            cursor: pointer;
        }

        .form-links a:hover {
            color: #764ba2;
        }

        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #363;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .info-message {
            background: #e8f4fd;
            border: 1px solid #b8daff;
            color: #004085;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .reset-btn {
            background: linear-gradient(135deg, #ccc 0%, #999 100%);
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e1e5e9;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .step.active {
            background: #667eea;
            transform: scale(1.2);
        }

        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            color: #666;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement-icon {
            margin-right: 8px;
            font-size: 12px;
        }

        .back-to-login {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }

        .back-to-login a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            transition: color 0.3s ease;
        }

        .back-to-login a:hover {
            color: #764ba2;
        }

        .back-to-login a::before {
            content: '← ';
            margin-right: 5px;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .forgot-password-container {
                grid-template-columns: 1fr;
                max-width: 400px;
                min-height: auto;
                padding: 20px;
            }
            
            .logo-section {
                display: none;
            }
            
            .form-section {
                padding: 40px 30px;
            }

            .user-type-options {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <div class="logo-section">
            <div class="logo-content">
                <img class="logo-image" src="All in one Wedding logo.png" alt="All in One Wedding Logo">
                <h1 class="logo-title">All in One Wedding</h1>
                <p class="logo-subtitle">Secure Account Recovery</p>
            </div>
        </div>
        
        <div class="form-section">
            <div class="step-indicator">
                <div class="step active" id="step1"></div>
                <div class="step" id="step2"></div>
                <div class="step" id="step3"></div>
            </div>

            <div class="form-header">
                <h2 class="form-title" id="formTitle">Reset Your Password</h2>
                <p class="form-subtitle" id="formSubtitle">Enter your email address and select your account type to receive a password reset link</p>
            </div>
            
            <div id="errorMessage" class="error-message"></div>
            <div id="successMessage" class="success-message"></div>
            
            <!-- Step 1: Email and User Type -->
            <div id="step1Form" class="form-step">
                <form class="forgot-password-form" id="emailForm">
                    <div class="user-type-group">
                        <label class="user-type-label">I am a:</label>
                        <div class="user-type-options">
                            <div class="user-type-option">
                                <input type="radio" id="admin" name="user_type" value="admin" class="user-type-input">
                                <label for="admin" class="user-type-card">
                                    <span class="user-type-icon">⚙️</span>
                                    Admin
                                </label>
                            </div>
                            <div class="user-type-option">
                                <input type="radio" id="bride_groom" name="user_type" value="bride_groom" class="user-type-input" checked>
                                <label for="bride_groom" class="user-type-card">
                                    <span class="user-type-icon">💑</span>
                                    Bride & Groom
                                </label>
                            </div>
                            <div class="user-type-option">
                                <input type="radio" id="vendor" name="user_type" value="vendor" class="user-type-input">
                                <label for="vendor" class="user-type-card">
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

                    <div class="info-message">
                        📧 We'll send a secure verification code to your email address to verify your identity before allowing you to reset your password.
                    </div>
                    
                    <button type="submit" class="reset-btn" id="sendCodeBtn">Send Verification Code</button>
                </form>
            </div>

            <!-- Step 2: Verification Code -->
            <div id="step2Form" class="form-step" style="display: none;">
                <form class="forgot-password-form" id="verificationForm">
                    <div class="form-group">
                        <input type="text" class="form-input" id="verificationCode" name="verification_code" placeholder=" " required maxlength="6">
                        <label class="form-label" for="verificationCode">Verification Code</label>
                    </div>

                    <div class="info-message">
                        📱 Enter the 6-digit verification code sent to your email address. The code will expire in 10 minutes.
                    </div>
                    
                    <button type="submit" class="reset-btn" id="verifyCodeBtn">Verify Code</button>
                    
                    <div class="form-links">
                        <a href="#" id="resendCode">Didn't receive the code? Resend</a>
                    </div>
                </form>
            </div>

            <!-- Step 3: New Password -->
            <div id="step3Form" class="form-step" style="display: none;">
                <form class="forgot-password-form" id="passwordForm">
                    <div class="form-group">
                        <input type="password" class="form-input" id="newPassword" name="new_password" placeholder=" " required>
                        <label class="form-label" for="newPassword">New Password</label>
                    </div>

                    <div class="form-group">
                        <input type="password" class="form-input" id="confirmPassword" name="confirm_password" placeholder=" " required>
                        <label class="form-label" for="confirmPassword">Confirm New Password</label>
                    </div>

                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <div class="requirement" id="lengthReq">
                            <span class="requirement-icon">○</span>
                            At least 8 characters long
                        </div>
                        <div class="requirement" id="uppercaseReq">
                            <span class="requirement-icon">○</span>
                            Contains uppercase letter
                        </div>
                        <div class="requirement" id="lowercaseReq">
                            <span class="requirement-icon">○</span>
                            Contains lowercase letter
                        </div>
                        <div class="requirement" id="numberReq">
                            <span class="requirement-icon">○</span>
                            Contains number
                        </div>
                        <div class="requirement" id="matchReq">
                            <span class="requirement-icon">○</span>
                            Passwords match
                        </div>
                    </div>
                    
                    <button type="submit" class="reset-btn" id="resetPasswordBtn" disabled>Reset Password</button>
                </form>
            </div>

            <div class="back-to-login">
                <a href="index.php" id="backToLogin">Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let userEmail = '';
        let userType = '';
        let resetToken = '';

        // API endpoint - adjust this to match your server setup
        const API_ENDPOINT = 'forgot_password_handler.php';

        // Form elements
        const emailForm = document.getElementById('emailForm');
        const verificationForm = document.getElementById('verificationForm');
        const passwordForm = document.getElementById('passwordForm');
        const errorDiv = document.getElementById('errorMessage');
        const successDiv = document.getElementById('successMessage');
        const formSection = document.querySelector('.form-section');

        // Step 1: Email submission
        emailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const selectedUserType = document.querySelector('input[name="user_type"]:checked')?.value;
            
            if (!email || !selectedUserType) {
                showError('Please fill in all fields and select your user type');
                return;
            }
            
            if (!isValidEmail(email)) {
                showError('Please enter a valid email address');
                return;
            }
            
            userEmail = email;
            userType = selectedUserType;
            
            sendVerificationCode(email, selectedUserType);
        });

        // Step 2: Verification code submission
        verificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const code = document.getElementById('verificationCode').value;
            
            if (!code || code.length !== 6) {
                showError('Please enter the 6-digit verification code');
                return;
            }
            
            verifyCode(code);
        });

        // Step 3: Password reset submission
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!validatePassword(newPassword, confirmPassword)) {
                return;
            }
            
            resetPassword(newPassword);
        });

        // Resend code functionality
        document.getElementById('resendCode').addEventListener('click', function(e) {
            e.preventDefault();
            sendVerificationCode(userEmail, userType, true);
        });

        async function sendVerificationCode(email, userType, isResend = false) {
            const button = document.getElementById('sendCodeBtn');
            setButtonLoading(button, true);
            hideMessages();
            
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'send_verification',
                        email: email,
                        user_type: userType
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showSuccess(isResend ? 'Verification code resent successfully!' : 'Verification code sent to your email!');
                    if (!isResend) {
                        nextStep();
                    }
                } else {
                    throw new Error(data.error || 'Failed to send verification code');
                }
            } catch (error) {
                showError(error.message);
            } finally {
                setButtonLoading(button, false);
            }
        }

        async function verifyCode(code) {
            const button = document.getElementById('verifyCodeBtn');
            setButtonLoading(button, true);
            hideMessages();
            
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'verify_code',
                        email: userEmail,
                        user_type: userType,
                        verification_code: code
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showSuccess('Code verified successfully!');
                    resetToken = data.reset_token;
                    nextStep();
                } else {
                    throw new Error(data.error || 'Invalid verification code');
                }
            } catch (error) {
                showError(error.message);
            } finally {
                setButtonLoading(button, false);
            }
        }

        async function resetPassword(newPassword) {
            const button = document.getElementById('resetPasswordBtn');
            setButtonLoading(button, true);
            hideMessages();
            
            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reset_password',
                        email: userEmail,
                        user_type: userType,
                        reset_token: resetToken,
                        new_password: newPassword
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showSuccess('Password reset successfully! You can now login with your new password.');
                    
                    // Change the back to login text
                    const backToLogin = document.getElementById('backToLogin');
                    backToLogin.textContent = 'Continue to Login';
                    backToLogin.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                    backToLogin.style.color = 'white';
                    backToLogin.style.padding = '10px 20px';
                    backToLogin.style.borderRadius = '10px';
                    backToLogin.style.textDecoration = 'none';
                } else {
                    throw new Error(data.error || 'Failed to reset password');
                }
            } catch (error) {
                showError(error.message);
            } finally {
                setButtonLoading(button, false);
            }
        }

        function setButtonLoading(button, isLoading) {
            if (isLoading) {
                button.disabled = true;
                const originalText = button.textContent;
                button.innerHTML = '<span class="spinner"></span>' + originalText;
                button.dataset.originalText = originalText;
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || button.textContent.replace('Loading...', '');
            }
        }

        function nextStep() {
            currentStep++;
            updateStepIndicator();
            showCurrentStep();
            updateFormHeader();
        }

        function updateStepIndicator() {
            document.querySelectorAll('.step').forEach((step, index) => {
                if (index < currentStep) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });
        }

        function showCurrentStep() {
            document.querySelectorAll('.form-step').forEach(step => {
                step.style.display = 'none';
            });
            
            document.getElementById(`step${currentStep}Form`).style.display = 'block';
        }

        function updateFormHeader() {
            const titles = {
                1: 'Reset Your Password',
                2: 'Verify Your Identity',
                3: 'Create New Password'
            };
            
            const subtitles = {
                1: 'Enter your email address and select your account type to receive a password reset link',
                2: 'Enter the verification code sent to your email address',
                3: 'Choose a strong password for your account'
            };
            
            document.getElementById('formTitle').textContent = titles[currentStep];
            document.getElementById('formSubtitle').textContent = subtitles[currentStep];
        }

        // Password validation
        function validatePassword(password, confirmPassword) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                match: password === confirmPassword && password.length > 0
            };
            
            return Object.values(requirements).every(req => req);
        }

        // Real-time password validation
        document.getElementById('newPassword').addEventListener('input', function() {
            checkPasswordRequirements();
        });

        document.getElementById('confirmPassword').addEventListener('input', function() {
            checkPasswordRequirements();
        });

        function checkPasswordRequirements() {
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const resetBtn = document.getElementById('resetPasswordBtn');
            
            const requirements = {
                lengthReq: password.length >= 8,
                uppercaseReq: /[A-Z]/.test(password),
                lowercaseReq: /[a-z]/.test(password),
                numberReq: /\d/.test(password),
                matchReq: password === confirmPassword && password.length > 0
            };
            
            Object.entries(requirements).forEach(([id, isValid]) => {
                const element = document.getElementById(id);
                const icon = element.querySelector('.requirement-icon');
                
                if (isValid) {
                    element.classList.add('valid');
                    icon.textContent = '✓';
                } else {
                    element.classList.remove('valid');
                    icon.textContent = '○';
                }
            });
            
            const allValid = Object.values(requirements).every(req => req);
            resetBtn.disabled = !allValid;
        }

        function showError(message) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            successDiv.style.display = 'none';
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        function showSuccess(message) {
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            successDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        function hideMessages() {
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
        }
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Add floating animation to form inputs
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Update form label behavior for floating labels
        document.querySelectorAll('.form-input').forEach(input => {
            const checkLabel = () => {
                const label = input.nextElementSibling;
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
            };
            
            input.addEventListener('focus', checkLabel);
            input.addEventListener('blur', checkLabel);
            input.addEventListener('input', checkLabel);
            
            // Initial check
            checkLabel();
        });

        // Initialize
        updateStepIndicator();
        updateFormHeader();
    </script>
</body>
</html>