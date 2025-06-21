<?php
session_start();
require_once 'config.php'; // Include the config file
header('Content-Type: application/json');

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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all fields'
        ]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid email address'
        ]);
        exit;
    }
    
    try {
        // Check if user exists and get user data
        $stmt = $pdo->prepare("
            SELECT id, email, password_hash, first_name, last_name, 
                   is_active, email_verified, created_at, last_login
            FROM users 
            WHERE email = ? AND is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if email is verified
            if (!$user['email_verified']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in'
                ]);
                exit;
            }
            
            // Update last login time
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET last_login = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $updateStmt->execute([$user['id']]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['login_time'] = time();
            
            // Log successful login
            $logStmt = $pdo->prepare("
                INSERT INTO login_logs (user_id, ip_address, user_agent, login_time) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $logStmt->execute([
                $user['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'redirect' => 'dashboard.php',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['first_name'] . ' ' . $user['last_name']
                ]
            ]);
            
        } else {
            // Log failed login attempt
            $failedStmt = $pdo->prepare("
                INSERT INTO failed_logins (email, ip_address, user_agent, attempt_time) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $failedStmt->execute([
                $email,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again.'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>