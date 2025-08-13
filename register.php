<?php
session_start();
require_once 'config.php'; // Include the config file

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add detailed error logging
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context);
    }
    error_log($logMessage . PHP_EOL, 3, 'registration_errors.log');
}

try {
    // Create PDO connection with more detailed error handling
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Test the connection
    $testQuery = $pdo->query("SELECT 1");
    logError("Database connection successful");
    
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'debug' => true
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set content type for JSON response
    header('Content-Type: application/json');
    
    try {
        logError("Registration attempt started", $_POST);
        
        // Get and sanitize input data
        $userType = trim($_POST['userType'] ?? '');
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        $newsletter = isset($_POST['newsletter']) ? 1 : 0;
        
        logError("Basic fields extracted", [
            'userType' => $userType,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone ? 'provided' : 'empty',
            'newsletter' => $newsletter
        ]);
        
        // User type specific fields
        $weddingDate = null;
        $guestCount = null;
        $businessName = null;
        $businessType = null;
        $serviceArea = null;
        
        if ($userType === 'couple') {
            $weddingDate = $_POST['weddingDate'] ?? null;
            $guestCount = $_POST['guestCount'] ?? null;
            logError("Couple fields extracted", [
                'weddingDate' => $weddingDate,
                'guestCount' => $guestCount
            ]);
        } elseif ($userType === 'vendor') {
            $businessName = trim($_POST['businessName'] ?? '');
            $businessType = trim($_POST['businessType'] ?? '');
            $serviceArea = trim($_POST['serviceArea'] ?? '');
            logError("Vendor fields extracted", [
                'businessName' => $businessName,
                'businessType' => $businessType,
                'serviceArea' => $serviceArea
            ]);
        }
        
        // Validate required fields
        if (empty($userType) || empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $missingFields = [];
            if (empty($userType)) $missingFields[] = 'userType';
            if (empty($firstName)) $missingFields[] = 'firstName';
            if (empty($lastName)) $missingFields[] = 'lastName';
            if (empty($email)) $missingFields[] = 'email';
            if (empty($password)) $missingFields[] = 'password';
            
            logError("Missing required fields", $missingFields);
            echo json_encode([
                'success' => false,
                'message' => 'Please fill in all required fields: ' . implode(', ', $missingFields)
            ]);
            exit;
        }
        
        // Validate user type
        if (!in_array($userType, ['couple', 'vendor'])) {
            logError("Invalid user type", ['userType' => $userType]);
            echo json_encode([
                'success' => false,
                'message' => 'Please select a valid user type'
            ]);
            exit;
        }
        
        // Vendor-specific validation
        if ($userType === 'vendor') {
            if (empty($businessName) || empty($businessType)) {
                logError("Missing vendor fields", [
                    'businessName' => empty($businessName) ? 'missing' : 'provided',
                    'businessType' => empty($businessType) ? 'missing' : 'provided'
                ]);
                echo json_encode([
                    'success' => false,
                    'message' => 'Please fill in all required business information'
                ]);
                exit;
            }
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            logError("Invalid email format", ['email' => $email]);
            echo json_encode([
                'success' => false,
                'message' => 'Please enter a valid email address'
            ]);
            exit;
        }
        
        // Validate password requirements
        if (!isValidPassword($password)) {
            logError("Invalid password format");
            echo json_encode([
                'success' => false,
                'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, and numbers'
            ]);
            exit;
        }
        
        // Check if passwords match
        if ($password !== $confirmPassword) {
            logError("Passwords do not match");
            echo json_encode([
                'success' => false,
                'message' => 'Passwords do not match'
            ]);
            exit;
        }
        
        // Validate phone number if provided
        if (!empty($phone) && !isValidPhone($phone)) {
            logError("Invalid phone number", ['phone' => $phone]);
            echo json_encode([
                'success' => false,
                'message' => 'Please enter a valid phone number'
            ]);
            exit;
        }
        
        // Validate wedding date if provided (couples only)
        if ($userType === 'couple' && !empty($weddingDate)) {
            $today = new DateTime();
            $wedding = new DateTime($weddingDate);
            if ($wedding < $today) {
                logError("Wedding date in the past", ['weddingDate' => $weddingDate]);
                echo json_encode([
                    'success' => false,
                    'message' => 'Wedding date must be in the future'
                ]);
                exit;
            }
        }
        
        // Validate guest count if provided (couples only)
        if ($userType === 'couple' && !empty($guestCount) && ($guestCount < 1 || $guestCount > 1000)) {
            logError("Invalid guest count", ['guestCount' => $guestCount]);
            echo json_encode([
                'success' => false,
                'message' => 'Guest count must be between 1 and 1000'
            ]);
            exit;
        }
        
        logError("All validation passed");
        
        // Start transaction to prevent duplicate entries
        $pdo->beginTransaction();
        
        // Check if email already exists in both tables
        $emailCheckSql = "
            SELECT 'customer' as type, id FROM customers WHERE email = ? 
            UNION 
            SELECT 'vendor' as type, id FROM vendors WHERE email = ?
        ";
        $stmt = $pdo->prepare($emailCheckSql);
        $stmt->execute([$email, $email]);
        
        if ($stmt->fetch()) {
            $pdo->rollback();
            logError("Email already exists", ['email' => $email]);
            echo json_encode([
                'success' => false,
                'message' => 'An account with this email already exists'
            ]);
            exit;
        }
        
        logError("Email availability check passed");
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Convert empty strings to null for optional fields
        $phone = empty($phone) ? null : $phone;
        $weddingDate = empty($weddingDate) ? null : $weddingDate;
        $guestCount = empty($guestCount) ? null : (int)$guestCount;
        $businessName = empty($businessName) ? null : $businessName;
        $businessType = empty($businessType) ? null : $businessType;
        $serviceArea = empty($serviceArea) ? null : $serviceArea;
        
        $userId = null;
        
        // Insert into appropriate table based on user type
        if ($userType === 'couple') {
            logError("Preparing to insert customer", [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone ? 'provided' : null,
                'weddingDate' => $weddingDate,
                'guestCount' => $guestCount,
                'password' => $password
            ]);
            
            // Insert into customers table
            $sql = "INSERT INTO customers (
                first_name, 
                last_name, 
                email, 
                phone, 
                wedding_date, 
                guest_count,
                password,
                email_verification_token,
                newsletter_subscription,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
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
            logError("Customer inserted successfully", ['customerId' => $userId]);
            
        } else { // vendor
            logError("Preparing to insert vendor", [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone ? 'provided' : null,
                'businessName' => $businessName,
                'businessType' => $businessType,
                'serviceArea' => $serviceArea,
                'password' => $password
            ]);
            
            // Insert into vendors table
           $sql = "INSERT INTO vendors (
    first_name, 
    last_name, 
    email, 
    phone, 
    business_name,
    business_type,
    service_area,
    password,
    email_verification_token,
    newsletter_subscription,
    created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $firstName,
                $lastName,
                $email,
                $phone,
                $businessName,
                $businessType,
                $serviceArea,
                $passwordHash,
                $verificationToken,
                $newsletter
            ]);
            
            $userId = $pdo->lastInsertId();
            logError("Vendor inserted successfully", ['vendorId' => $userId]);
        }
        
        // Send verification email
        $verificationSent = @sendVerificationEmail($email, $firstName, $verificationToken, $userType);
if (!$verificationSent) {
    logError("Email verification failed to send, but account created", ['email' => $email]);
}

echo json_encode([
    'success' => true,
    'message' => 'Account created successfully!' . ($verificationSent ? ' Please check your email to verify your account.' : ''),
    'user_id' => $userId,
    'user_type' => $userType,
    'verification_sent' => $verificationSent
]);
        // Commit transaction
        $pdo->commit();
        logError("Transaction committed successfully");
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        logError("PDO Exception: " . $e->getMessage(), [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred. Please try again.',
            'debug' => false // Don't expose database errors to user
        ]);
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        logError("PDO Exception: " . $e->getMessage(), [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred. Please try again.',
            'debug' => false // Don't expose database errors to user
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        logError("General Exception: " . $e->getMessage(), [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again.',
            'debug' => false
        ]);
    }
    
    // Always exit after handling POST request
    exit;
} else {
    logError("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
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

function sendVerificationEmail($email, $firstName, $token, $userType) {
    try {
        logError("Attempting to send verification email", [
            'email' => $email,
            'firstName' => $firstName,
            'userType' => $userType
        ]);
        
        // Email configuration - you'll need to configure this with your SMTP settings
        $to = $email;
        $subject = "Verify Your All in One Wedding Account";
        $verificationLink = "http://yourwebsite.com/verify-email.php?token=" . $token;
        
        $userTypeText = ($userType === 'vendor') ? 'vendor' : 'couple';
        $welcomeMessage = ($userType === 'vendor') 
            ? "Welcome to our vendor community! Start showcasing your services to engaged couples."
            : "Welcome to our wedding planning community! Start planning your perfect day.";
        
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
                        Thank you for joining as a {$userTypeText}. {$welcomeMessage}
                        To complete your registration, please verify your email address.
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
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        logError("Email sending result", ['success' => $result]);
        return $result;
        
    } catch (Exception $e) {
        logError("Email sending error: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - All in One Wedding</title>
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

        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 700px;
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
        }

        .logo-image {
            width: 200px;
            height: 200px;
            margin-bottom: 20px;
            opacity: 0.9;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            margin: 0 auto 20px;
        }

        .logo-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .logo-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .form-section {
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .register-form {
            width: 100%;
        }

        .user-type-section {
            margin-bottom: 25px;
        }

        .user-type-label {
            display: block;
            margin-bottom: 15px;
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .user-type-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
        }

.user-type-option {
    position: relative;
    cursor: pointer;
}

.user-type-radio {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.user-type-card {
    border: 2px solid #e1e5e9;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    background: white;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

        .user-type-card:hover {
            border-color: #667eea;
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .user-type-radio:checked + .user-type-card {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .user-type-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: block;
        }

        .user-type-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: #333;
            transition: color 0.3s ease;
        }

        .user-type-desc {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 18px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-label {
            position: absolute;
            left: 18px;
            top: 12px;
            color: #666;
            transition: all 0.3s ease;
            pointer-events: none;
            background: #f8f9fa;
            padding: 0 5px;
            font-size: 15px;
        }

        .form-input:focus + .form-label,
        .form-input:not(:placeholder-shown) + .form-label,
        .form-select:focus + .form-label,
        .form-select:not([value=""]) + .form-label {
            top: -8px;
            font-size: 12px;
            color: #667eea;
            background: white;
        }

        .conditional-fields {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .conditional-fields.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            line-height: 1.4;
        }

        .requirement {
            display: block;
            transition: color 0.3s ease;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .checkbox-input {
            margin-right: 10px;
            margin-top: 2px;
        }

        .checkbox-label {
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }

        .checkbox-label a {
            color: #667eea;
            text-decoration: none;
        }

        .checkbox-label a:hover {
            text-decoration: underline;
        }

        .register-btn {
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

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .register-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .form-links {
            text-align: center;
            margin-top: 15px;
        }

        .form-links a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .form-links a:hover {
            color: #764ba2;
        }

        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            font-size: 14px;
        }

        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #363;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            font-size: 14px;
        }

        .loading {
            display: none;
            text-align: center;
            color: #667eea;
            margin-top: 10px;
        }

        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .register-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .logo-section {
                display: none;
            }
            
            .form-section {
                padding: 30px 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .user-type-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo-section">
            <div class="logo-content">
            <img class="logo-image" src="All in one Wedding logo.png" alt="All in One Wedding Logo">
                <h1 class="logo-title">All in One Wedding</h1>
                <p class="logo-subtitle">Where Dreams Become Reality</p>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-header">
                <h2 class="form-title">Create Your Account</h2>
                <p class="form-subtitle">Join our wedding planning community</p>
            </div>
            
            <div id="errorMessage" class="error-message"></div>
            <div id="successMessage" class="success-message"></div>
            
            <form class="register-form" id="registerForm">
                <!-- User Type Selection -->
                <div class="user-type-section">
                    <label class="user-type-label">I am a:</label>
                    <div class="user-type-options">
                        <div class="user-type-option">
                            <input type="radio" class="user-type-radio" id="couple" name="userType" value="couple" required>
                            <label class="user-type-card" for="couple">
                                <span class="user-type-icon">👰💒🤵</span>
                                <div class="user-type-title">Bride/Groom</div>
                                <div class="user-type-desc">Planning my wedding</div>
                            </label>
                        </div>
                        <div class="user-type-option">
                            <input type="radio" class="user-type-radio" id="vendor" name="userType" value="vendor" required>
                            <label class="user-type-card" for="vendor">
                                <span class="user-type-icon">🏢✨📋</span>
                                <div class="user-type-title">Vendor</div>
                                <div class="user-type-desc">Offering wedding services</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" class="form-input" id="firstName" name="firstName" placeholder=" " required>
                        <label class="form-label" for="firstName">First Name</label>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" class="form-input" id="lastName" name="lastName" placeholder=" " required>
                        <label class="form-label" for="lastName">Last Name</label>
                    </div>
                </div>

                <div class="form-group">
                    <input type="email" class="form-input" id="email" name="email" placeholder=" " required>
                    <label class="form-label" for="email">Email Address</label>
                </div>

                <div class="form-group">
                    <input type="tel" class="form-input" id="phone" name="phone" placeholder=" ">
                    <label class="form-label" for="phone">Phone Number</label>
                </div>

                <!-- Couple-specific fields -->
                <div id="coupleFields" class="conditional-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <input type="date" class="form-input" id="weddingDate" name="weddingDate" placeholder=" ">
                            <label class="form-label" for="weddingDate">Wedding Date (Optional)</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="number" class="form-input" id="guestCount" name="guestCount" placeholder=" " min="1" max="1000">
                            <label class="form-label" for="guestCount">Expected Guests (Optional)</label>
                        </div>
                    </div>
                </div>

                <!-- Vendor-specific fields -->
                <div id="vendorFields" class="conditional-fields">
                    <div class="form-group">
                        <input type="text" class="form-input" id="businessName" name="businessName" placeholder=" ">
                        <label class="form-label" for="businessName">Business Name</label>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <select class="form-select" id="businessType" name="businessType">
                                <option value="">Select Service Type</option>
                                <option value="venue">Venue</option>
                                <option value="photographer">Photography</option>
                                <option value="videographer">Videography</option>
                                <option value="catering">Catering</option>
                                <option value="florist">Florist</option>
                                <option value="music">Music/DJ</option>
                                <option value="decoration">Decoration</option>
                                <option value="makeup">Makeup Artist</option>
                                <option value="transportation">Transportation</option>
                                <option value="planning">Wedding Planning</option>
                                <option value="other">Other</option>
                            </select>
                            <label class="form-label" for="businessType">Service Type</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" class="form-input" id="serviceArea" name="serviceArea" placeholder=" ">
                            <label class="form-label" for="serviceArea">Service Area (Optional)</label>
                        </div>
                    </div>
                </div>
                
                <!-- Password fields -->
                <div class="form-group">
                    <input type="password" class="form-input" id="password" name="password" placeholder=" " required>
                    <label class="form-label" for="password">Password</label>
                    <div class="password-requirements">
                        <span class="requirement" id="length">• At least 8 characters</span>
                        <span class="requirement" id="uppercase">• One uppercase letter</span>
                        <span class="requirement" id="lowercase">• One lowercase letter</span>
                        <span class="requirement" id="number">• One number</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <input type="password" class="form-input" id="confirmPassword" name="confirmPassword" placeholder=" " required>
                    <label class="form-label" for="confirmPassword">Confirm Password</label>
                    </div>
                
                <!-- Newsletter and Terms -->
                <div class="checkbox-group">
                    <input type="checkbox" class="checkbox-input" id="newsletter" name="newsletter" value="1">
                    <label class="checkbox-label" for="newsletter">
                        I would like to receive wedding tips and updates via email
                    </label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" class="checkbox-input" id="terms" name="terms" required>
                    <label class="checkbox-label" for="terms">
                        I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and 
                        <a href="privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="register-btn" id="registerBtn">
                    Create Account
                </button>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    Creating your account...
                </div>
            </form>
            
            <div class="form-links">
                Already have an account? <a href="index.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const userTypeRadios = document.querySelectorAll('input[name="userType"]');
            const coupleFields = document.getElementById('coupleFields');
            const vendorFields = document.getElementById('vendorFields');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const registerBtn = document.getElementById('registerBtn');
            const loading = document.getElementById('loading');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');

            // User type selection handler
            userTypeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'couple') {
                        coupleFields.classList.add('show');
                        vendorFields.classList.remove('show');
                        // Make couple fields optional
                        document.getElementById('weddingDate').required = false;
                        document.getElementById('guestCount').required = false;
                        // Make vendor fields not required
                        document.getElementById('businessName').required = false;
                        document.getElementById('businessType').required = false;
                    } else if (this.value === 'vendor') {
                        vendorFields.classList.add('show');
                        coupleFields.classList.remove('show');
                        // Make vendor fields required
                        document.getElementById('businessName').required = true;
                        document.getElementById('businessType').required = true;
                        // Make couple fields not required
                        document.getElementById('weddingDate').required = false;
                        document.getElementById('guestCount').required = false;
                    }
                });
            });

            // Password validation
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password)
                };

                Object.keys(requirements).forEach(req => {
                    const element = document.getElementById(req);
                    if (requirements[req]) {
                        element.classList.add('valid');
                        element.classList.remove('invalid');
                    } else {
                        element.classList.add('invalid');
                        element.classList.remove('valid');
                    }
                });

                checkPasswordMatch();
            });

            // Confirm password validation
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);

            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            }

            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Hide previous messages
                errorMessage.style.display = 'none';
                successMessage.style.display = 'none';
                
                // Show loading
                registerBtn.style.display = 'none';
                loading.style.display = 'block';

                // Prepare form data
                const formData = new FormData(form);

                // Submit form
                fetch('register.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
    loading.style.display = 'none';
    registerBtn.style.display = 'block';

    if (data.success) {
        successMessage.textContent = data.message;
        successMessage.style.display = 'block';
        form.reset();
        
        // Check if verification was actually sent
        if (data.verification_sent === false) {
            successMessage.textContent += ' (but verification email failed to send)';
        }
        
        setTimeout(() => {
            window.location.href = 'Index.php';
        }, 3000);
    } else {
        // Show both the user message and debug message if available
        errorMessage.textContent = data.message + 
            (data.debug_message ? ` (Debug: ${data.debug_message})` : '');
        errorMessage.style.display = 'block';
        errorMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
})
                .catch(error => {
                    console.error('Error:', error);
                    loading.style.display = 'none';
                    registerBtn.style.display = 'block';
                    errorMessage.textContent = 'An error occurred. Please try again.';
                    errorMessage.style.display = 'block';
                });
            });

            // Set minimum date for wedding date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('weddingDate').setAttribute('min', today);

            // Phone number formatting (optional enhancement)
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length >= 6) {
                    value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                } else if (value.length >= 3) {
                    value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
                }
                this.value = value;
            });

            // Enhanced form validation feedback
            const inputs = document.querySelectorAll('.form-input, .form-select');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.checkValidity()) {
                        this.style.borderColor = '#28a745';
                    } else if (this.value) {
                        this.style.borderColor = '#dc3545';
                    }
                });

                input.addEventListener('input', function() {
                    if (this.style.borderColor === '#dc3545' && this.checkValidity()) {
                        this.style.borderColor = '#28a745';
                    }
                });
            });
        });
    </script>
</body>
</html>