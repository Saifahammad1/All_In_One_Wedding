<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "all_in_one_wedding";

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
   $action = $_POST['action'];
   
   switch ($action) {
       case 'saveProfile':
           saveProfile();
           exit;
       case 'getWeddingData':
           getWeddingData();
           exit;
       case 'addBudgetItem':
           addBudgetItem();
           exit;
       case 'addGuest':
           addGuest();
           exit;
       case 'updateGuestStatus':
           updateGuestStatus();
           exit;
       case 'addCustomTask':
           addCustomTask();
           exit;
       case 'toggleTaskCompletion':
           toggleTaskCompletion();
           exit;
       default:
           echo json_encode(['success' => false, 'message' => 'Invalid action']);
           exit;
   }
}

// If it's a GET request to fetch data on page load
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['getData'])) {
   getWeddingData();
   exit;
}
// SMTP Configuration
define('SMTP_HOST', 'smtp.yourdomain.com'); // Your SMTP server
define('SMTP_PORT', 587); // Typically 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'ahammadsaif@gmail.com'); // SMTP username
define('SMTP_PASSWORD', 'your_smtp_password'); // SMTP password
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
define('SMTP_FROM_EMAIL', 'ahammadsaif@gmail.com');
define('SMTP_FROM_NAME', 'All in One Wedding');
?>
