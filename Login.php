<?php
session_start();
require_once 'config.php'; // Include the config file
try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Check if form was submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_POST['email'] ?? '';
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
        
        // Prepare and execute query
        // UPDATE TABLE NAME: Replace 'users' with your actual table name
        $stmt = $conn->prepare("SELECT id, email, password, first_name, last_name FROM users WHERE email = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            // If you're using password_hash(), use password_verify()
            if (password_verify($password, $user['password'])) {
                // Password is correct
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['logged_in'] = true;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'redirect' => 'dashboard.php'
                ]);
            } 
            // If you're storing plain text passwords (NOT RECOMMENDED), use this instead:
            elseif ($password === $user['password']) {
                // Plain text password match (INSECURE - should be changed)
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['logged_in'] = true;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'redirect' => 'dashboard.php'
                ]);
            }
            else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password'
            ]);
        }
        
        $stmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>