<?php
session_start();
require_once 'config.php'; // Include the config file

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $weddingDate = $_POST['weddingDate'] ?? null;
    $guestCount = $_POST['guestCount'] ?? null;
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    // Validate required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all required fields'
        ]);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address'
        ]);
        exit;
    }
    
    // Validate password requirements
    if (!isValidPassword($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, and numbers'
        ]);
        exit;
    }
    
    // Check if passwords match
    if ($password !== $confirmPassword) {
        echo json_encode([
            'success' => false,
            'message' => 'Passwords do not match'
        ]);
        exit;
    }
    
    // Validate phone number if provided
    if (!empty($phone) && !isValidPhone($phone)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid phone number'
        ]);
        exit;
    }
    
    // Validate wedding date if provided
    if (!empty($weddingDate)) {
        $today = new DateTime();
        $wedding = new DateTime($weddingDate);
        if ($wedding < $today) {
            echo json_encode([
                'success' => false,
                'message' => 'Wedding date must be in the future'
            ]);
            exit;
        }
    }
    
    // Validate guest count if provided
    if (!empty($guestCount) && ($guestCount < 1 || $guestCount > 1000)) {
        echo json_encode([
            'success' => false,
            'message' => 'Guest count must be between 1 and 1000'
        ]);
        exit;
    }
    
    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'An account with this email already exists'
            ]);
            exit;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Convert empty strings to null for optional fields
        $phone = empty($phone) ? null : $phone;
        $weddingDate = empty($weddingDate) ? null : $weddingDate;
        $guestCount = empty($guestCount) ? null : (int)$guestCount;
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (
                first_name, 
                last_name, 
                email, 
                phone, 
                wedding_date, 
                guest_count, 
                password_hash, 
                email_verification_token,
                newsletter_subscription,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([
            $firstName,
            $lastName,
            $email,
            $phone,
            $weddingDate,
            $guestCount,
            $passwordHash,
            $verificationToken,
            $newsletter
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Send verification email
        $verificationSent = sendVerificationEmail($email, $firstName, $verificationToken);
        
        // Log registration
        $logStmt = $pdo->prepare("
            INSERT INTO registration_logs (
                user_id, 
                ip_address, 
                user_agent, 
                registration_time
            ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $logStmt->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully! Please check your email to verify your account.',
            'user_id' => $userId,
            'verification_sent' => $verificationSent
        ]);
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while creating your account. Please try again.'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

// Validation functions
function isValidPassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

function isValidPhone($phone) {
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    // Check if it's between 10-15 digits (accommodates international numbers)
    return strlen($cleaned) >= 10 && strlen($cleaned) <= 15;
}

function sendVerificationEmail($email, $firstName, $token) {
    try {
        // Email configuration - you'll need to configure this with your SMTP settings
        $to = $email;
        $subject = "Verify Your All in One Wedding Account";
        $verificationLink = "http://yourwebsite.com/verify-email.php?token=" . $token;
        
        $message = "
        <html>
        <head>
            <title>Verify Your Account</title>
        </head>
        <body>
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>All in One Wedding</h1>
                </div>
                <div style='padding: 30px; background: #f9f9f9;'>
                    <h2 style='color: #333;'>Welcome to All in One Wedding, {$firstName}!</h2>
                    <p style='color: #666; line-height: 1.6;'>
                        Thank you for joining our wedding planning community. To complete your registration 
                        and start planning your perfect day, please verify your email address.
                    </p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$verificationLink}' 
                           style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                  color: white; padding: 15px 30px; text-decoration: none; 
                                  border-radius: 5px; display: inline-block;'>
                            Verify My Email
                        </a>
                    </div>
                    <p style='color: #666; font-size: 14px;'>
                        If the button doesn't work, copy and paste this link into your browser:<br>
                        <a href='{$verificationLink}'>{$verificationLink}</a>
                    </p>
                    <p style='color: #666; font-size: 14px;'>
                        This verification link will expire in 24 hours.
                    </p>
                </div>
                <div style='background: #333; padding: 20px; text-align: center; color: #999; font-size: 12px;'>
                    © " . date('Y') . " All in One Wedding. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=iso-8859-1',
            'From: noreply@yourwebsite.com',
            'Reply-To: support@yourwebsite.com',
            'X-Mailer: PHP/' . phpversion()
        );
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}
?>