<?php
require_once 'config.php'; // Include the config file
// Prevent any output before JSON response
ob_start();

// Disable HTML error display for AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
}

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear any previous output
    ob_clean();
    
    header('Content-Type: application/json');
    
    try {
        // Check if config.php exists
        if (!file_exists('config.php')) {
            echo json_encode([
                'success' => false, 
                'message' => 'Configuration file missing. Please create config.php with database settings.'
            ]);
            exit;
        }
        
        require_once 'config.php';
        
        // Check if database variables are defined
        if (!isset($host) || !isset($dbname) || !isset($username) || !isset($password)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Database configuration incomplete in config.php'
            ]);
            exit;
        }
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $email = $_POST['email'] ?? '';
        $password_input = $_POST['password'] ?? '';
        $user_type = $_POST['user_type'] ?? '';
        
        if (empty($email) || empty($password_input) || empty($user_type)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit;
        }
        
        // Query based on user type
        $table_map = [
            'admin' => 'admins',
            'bride_groom' => 'customers',
            'vendor' => 'vendors'
        ];
        
        if (!isset($table_map[$user_type])) {
            echo json_encode(['success' => false, 'message' => 'Invalid user type']);
            exit;
        }
        
        $table = $table_map[$user_type];
        
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Database table '$table' does not exist. Please run database setup."
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password_input, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $user['name'] ?? ($user['first_name'] . ' ' . $user['last_name']);
            
            // Redirect based on user type
            $redirects = [
                'admin' => 'Admin_Dashboard.php',
                'bride_groom' => 'Customer_Dashboard.php',
                'vendor' => 'Vendor_Dashboard.php'
            ];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login successful!',
                'redirect' => $redirects[$user_type]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        }
        
    } catch (PDOException $e) {
        $error_message = 'Database connection failed';
        
        // Provide more specific error messages for common issues
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            $error_message = 'Database access denied. Check username/password in config.php';
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            $error_message = 'Database does not exist. Please create the database first.';
        } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
            $error_message = 'Cannot connect to database server. Is MySQL running?';
        }
        
        echo json_encode(['success' => false, 'message' => $error_message]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
    exit;
}

// Clear output buffer for HTML page
ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All in One Wedding - Your Perfect Day Starts Here</title>
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

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 650px;
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
            border-radius: 50%;
            opacity: 0.9;
        }

        .logo-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .logo-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
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
        }

        .login-form {
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

        .login-btn {
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

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
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

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .login-btn {
            background: linear-gradient(135deg, #ccc 0%, #999 100%);
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
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
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-content">
                <img class="logo-image" src="All in one Wedding logo.png" alt="All in One Wedding Logo">
                <h1 class="logo-title">All in One Wedding</h1>
                <p class="logo-subtitle">Where Dreams Become Reality</p>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-header">
                <h2 class="form-title">Love Planned Perfectly</h2>
                <p class="form-subtitle">Sign in to plan your perfect wedding day</p>
            </div>
            
            <div id="errorMessage" class="error-message"></div>
            <div id="successMessage" class="success-message"></div>
            
            <form class="login-form" id="loginForm" method="POST">
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
                
                <div class="form-group">
                    <input type="password" class="form-input" id="password" name="password" placeholder=" " required>
                    <label class="form-label" for="password">Password</label>
                </div>
                
                <button type="submit" class="login-btn">Sign In to Your Wedding Journey</button>
                
                <div class="form-links">
                    <a href="Forgot_Password.php">Forgot Password?</a>
                    <span>|</span>
                    <a href="register.php">Create New Account</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const userType = document.querySelector('input[name="user_type"]:checked')?.value;
            const errorDiv = document.getElementById('errorMessage');
            const successDiv = document.getElementById('successMessage');
            const formSection = document.querySelector('.form-section');
            
            // Hide previous messages
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            // Basic validation
            if (!email || !password || !userType) {
                showError('Please fill in all fields and select your user type');
                return;
            }
            
            if (!isValidEmail(email)) {
                showError('Please enter a valid email address');
                return;
            }
            
            // Show loading state
            formSection.classList.add('loading');
            
            // Submit form data
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            formData.append('user_type', userType);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response. Check server configuration.');
                }
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                formSection.classList.remove('loading');
                if (data.success) {
                    showSuccess('Login successful! Redirecting...');
                    setTimeout(() => {
                        window.location.href = data.redirect || 'Dashboard.php';
                    }, 1500);
                } else {
                    showError(data.message || 'Login failed. Please try again.');
                }
            })
            .catch(error => {
                formSection.classList.remove('loading');
                console.error('Fetch error:', error);
                showError(error.message || 'Connection error. Please try again.');
            });
        });
        
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            successDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
    </script>
</body>
</html>
