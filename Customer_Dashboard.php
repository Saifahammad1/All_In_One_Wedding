<?php
// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to sanitize input data
function sanitize_input($data) {
    global $link;
    return mysqli_real_escape_string($link, htmlspecialchars(strip_tags(trim($data))));
}

// Function to check if a user is logged in (basic example)
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Placeholder for user ID (in a real app, this would come from a login system)
function getCurrentUserId() {
    // For this example, let's assume a static user ID or retrieve from session
    // In a real application, this would be set upon successful login.
    if (!isset($_SESSION['user_id'])) {
        // This is a placeholder for development. In production, users should log in.
        $_SESSION['user_id'] = 1; // Assign a default user ID for testing
    }
    return $_SESSION['user_id'];
}

// --- Profile Functions ---
function saveProfile() {
    global $link;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    $brideName = sanitize_input($_POST['brideName']);
    $groomName = sanitize_input($_POST['groomName']);
    $weddingDate = sanitize_input($_POST['weddingDate']);
    $totalBudget = (float) sanitize_input($_POST['totalBudget']);
    $expectedGuests = (int) sanitize_input($_POST['expectedGuests']);

    // Check if profile already exists for the user
    $sql_check = "SELECT id FROM profiles WHERE user_id = ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "i", $userId);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) == 1) {
            // Update existing profile
            $sql = "UPDATE profiles SET bride_name = ?, groom_name = ?, wedding_date = ?, total_budget = ?, expected_guests = ? WHERE user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssdii", $brideName, $groomName, $weddingDate, $totalBudget, $expectedGuests, $userId);
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . mysqli_error($link)]);
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // Insert new profile
            $sql = "INSERT INTO profiles (user_id, bride_name, groom_name, wedding_date, total_budget, expected_guests) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "issdsi", $userId, $brideName, $groomName, $weddingDate, $totalBudget, $expectedGuests);
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Profile saved successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error saving profile: ' . mysqli_error($link)]);
                }
                mysqli_stmt_close($stmt);
            }
        }
        mysqli_stmt_close($stmt_check);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($link)]);
    }
}

function getWeddingData() {
    global $link;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    $data = [
        'profile' => [],
        'budget' => ['items' => [], 'totalSpent' => 0],
        'guests' => [],
        'checklist' => [],
        'vendors' => ['booked' => 0, 'total' => 6] // Static for now, can be dynamic
    ];

    // Get Profile Data
    $sql = "SELECT bride_name, groom_name, wedding_date, total_budget, expected_guests FROM profiles WHERE user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $brideName, $groomName, $weddingDate, $totalBudget, $expectedGuests);
        if (mysqli_stmt_fetch($stmt)) {
            $data['profile'] = [
                'brideName' => $brideName,
                'groomName' => $groomName,
                'weddingDate' => $weddingDate,
                'totalBudget' => $totalBudget,
                'expectedGuests' => $expectedGuests
            ];
        }
        mysqli_stmt_close($stmt);
    }

    // Get Budget Items
    $sql = "SELECT id, category, amount, description, created_at FROM budget_items WHERE user_id = ? ORDER BY created_at DESC";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id, $category, $amount, $description, $createdAt);
        while (mysqli_stmt_fetch($stmt)) {
            $data['budget']['items'][] = [
                'id' => $id,
                'category' => $category,
                'amount' => $amount,
                'description' => $description,
                'date' => date('m/d/Y', strtotime($createdAt))
            ];
            $data['budget']['totalSpent'] += $amount;
        }
        mysqli_stmt_close($stmt);
    }

    // Get Guests
    $sql = "SELECT id, name, email, phone, plus_one, status FROM guests WHERE user_id = ? ORDER BY name ASC";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id, $name, $email, $phone, $plusOne, $status);
        while (mysqli_stmt_fetch($stmt)) {
            $data['guests'][] = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'plusOne' => (bool)$plusOne,
                'status' => $status
            ];
        }
        mysqli_stmt_close($stmt);
    }

    // Get Checklist Items
    $sql = "SELECT id, task, completed, priority, due_date FROM checklist_items WHERE user_id = ? ORDER BY due_date ASC, priority DESC";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id, $task, $completed, $priority, $dueDate);
        while (mysqli_stmt_fetch($stmt)) {
            $data['checklist'][] = [
                'id' => $id,
                'task' => $task,
                'completed' => (bool)$completed,
                'priority' => $priority,
                'dueDate' => $dueDate
            ];
        }
        mysqli_stmt_close($stmt);
    }
    
    // You'd also fetch booked vendors from a 'booked_vendors' table if you had one
    // For now, bookedVendors is static.
    $sql = "SELECT COUNT(DISTINCT vendor_category) FROM booked_vendors WHERE user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $bookedVendorsCount);
        if (mysqli_stmt_fetch($stmt)) {
            $data['vendors']['booked'] = $bookedVendorsCount;
        }
        mysqli_stmt_close($stmt);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
}

// --- Budget Functions ---
function addBudgetItem() {
    global $link;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    $category = sanitize_input($_POST['category']);
    $amount = (float) sanitize_input($_POST['amount']);
    $description = sanitize_input($_POST['description']);

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount.']);
        return;
    }

    $sql = "INSERT INTO budget_items (user_id, category, amount, description) VALUES (?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "isds", $userId, $category, $amount, $description);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Expense added.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding expense: ' . mysqli_error($link)]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($link)]);
    }
}

// --- Guest Functions ---
function addGuest() {
    global $link;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $plusOne = (isset($_POST['plusOne']) && $_POST['plusOne'] === 'true') ? 1 : 0; // Convert boolean to int

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Guest name cannot be empty.']);
        return;
    }

    $sql = "INSERT INTO guests (user_id, name, email, phone, plus_one, status) VALUES (?, ?, ?, ?, ?, 'pending')";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "isssi", $userId, $name, $email, $phone, $plusOne);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Guest added.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding guest: ' . mysqli_error($link)]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($link)]);
    }
}

function updateGuestStatus() {
    global $link;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    $guestId = (int) sanitize_input($_POST['guestId']);
    $newStatus = sanitize_input($_POST['newStatus']);

    if (!in_array($newStatus, ['pending', 'confirmed', 'declined'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        return;
    }

    $sql = "UPDATE guests SET status = ? WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sii", $newStatus, $guestId, $userId);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Guest status updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating guest status: ' . mysqli_error($link)]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($link)]);
    }
}

// --- Checklist Functions ---
function addCustomTask() {
    global $link;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    $taskName = sanitize_input($_POST['taskName']);
    $dueDate = sanitize_input($_POST['taskDate']);
    $priority = sanitize_input($_POST['taskPriority']);

    if (empty($taskName)) {
        echo json_encode(['success' => false, 'message' => 'Task name cannot be empty.']);
        return;
    }

    $sql = "INSERT INTO checklist_items (user_id, task, due_date, priority, completed) VALUES (?, ?, ?, ?, 0)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "isss", $userId, $taskName, $dueDate, $priority);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Custom task added.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding custom task: ' . mysqli_error($link)]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($link)]);
    }
}

function toggleTaskCompletion() {
    global $link;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    $taskId = (int) sanitize_input($_POST['taskId']);
    $completed = (int) sanitize_input($_POST['completed']); // 0 or 1

    $sql = "UPDATE checklist_items SET completed = ? WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $completed, $taskId, $userId);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Task completion updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating task completion: ' . mysqli_error($link)]);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($link)]);
    }
}

// --- Logout Function ---
function logout() {
    session_unset();
    session_destroy();
    header('Location: /login.php'); // Redirect to your login page
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wedding Dashboard - All in One Wedding</title>
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
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 700;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.3s ease;
            position: absolute;
            right: 20px;
        }

        .toggle-btn:hover {
            transform: translateY(-50%) scale(1.1);
        }

        .sidebar.collapsed .toggle-btn {
            right: 15px;
            transform: translateY(-50%);
        }

        .sidebar.collapsed .toggle-btn:hover {
            transform: translateY(-50%) scale(1.1);
        }

        .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .user-avatar {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
            transition: opacity 0.3s ease;
        }

        .user-role {
            font-size: 0.9rem;
            opacity: 0.8;
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .user-name,
        .sidebar.collapsed .user-role {
            opacity: 0;
        }

        .nav-menu {
            list-style: none;
            padding: 20px 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: rgba(255,255,255,0.5);
        }

        .nav-link.active {
            background: rgba(255,255,255,0.2);
            border-left-color: white;
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }

        .nav-text {
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
        }

        .submenu {
            list-style: none;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0,0,0,0.1);
        }

        .submenu.active {
            max-height: 300px;
        }

        .submenu-item {
            padding: 10px 20px 10px 60px;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submenu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 60px;
        }

        .dashboard-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .dashboard-title {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .dashboard-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .content-area {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            min-height: 500px;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .section-title {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: #333;
        }

        .section-content {
            color: #666;
            line-height: 1.6;
        }

        /* Interactive Elements */
        .interactive-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .interactive-card:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .budget-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }

        .budget-category {
            font-weight: 600;
            color: #333;
        }

        .budget-amount {
            font-weight: bold;
            color: #667eea;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin: 8px 0;
            transition: all 0.3s ease;
        }

        .checklist-item.completed {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .checklist-checkbox {
            margin-right: 15px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .vendor-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .vendor-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .vendor-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .vendor-rating {
            color: #ffc107;
            margin: 5px 0;
        }

        .vendor-price {
            font-weight: bold;
            color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .guest-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin: 10px 0;
        }

        .guest-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-declined {
            background: #f8d7da;
            color: #721c24;
        }

        /* Color variants for stat cards */
        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card.pink .stat-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }

        .logout-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        .sidebar.collapsed .logout-btn {
            left: 10px;
            right: 10px;
            padding: 10px 5px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-data {
            color: #999;
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            
            .main-content {
                margin-left: 60px;
            }
            
            .dashboard-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">💒</div>
                <span class="logo-text">All in One Wedding</span>
            </div>
            <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
        </div>
        
        <div class="user-info">
            <div class="user-avatar" id="userAvatar">👤</div>
            <div class="user-name" id="userName">Welcome!</div>
            <div class="user-role" id="userRole">Please set up your profile</div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a class="nav-link active" onclick="showSection('dashboard')">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" onclick="showSection('profile')">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">Profile Setup</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" onclick="toggleSubmenu('vendors')">
                    <span class="nav-icon">🏪</span>
                    <span class="nav-text">Vendor Marketplace</span>
                </a>
                <ul class="submenu" id="vendors-submenu">
                    <li class="submenu-item" onclick="showSection('photographers')">📸 Photographers</li>
                    <li class="submenu-item" onclick="showSection('venues')">🏛️ Venues</li>
                    <li class="submenu-item" onclick="showSection('catering')">🍽️ Catering</li>
                    <li class="submenu-item" onclick="showSection('decor')">🎨 Decor</li>
                    <li class="submenu-item" onclick="showSection('dress')">👗 Dress Designers</li>
                    <li class="submenu-item" onclick="showSection('makeup')">💄 Makeup Artists</li>
                </ul>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" onclick="showSection('budget')">
                    <span class="nav-icon">💰</span>
                    <span class="nav-text">Smart Budget</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" onclick="showSection('guests')">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Guest Manager</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" onclick="showSection('checklist')">
                    <span class="nav-icon">✅</span>
                    <span class="nav-text">Smart Checklist</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" onclick="showSection('schedule')">
                    <span class="nav-icon">📅</span>
                    <span class="nav-text">Timeline Planner</span>
                </a>
            </li>
        </ul>
        
        <button class="logout-btn" onclick="logout()">👋 Sign Out</button>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title" id="dashboardTitle">Welcome to Your Wedding Dashboard! 💕</h1>
            <p class="dashboard-subtitle" id="dashboardSubtitle">Complete your profile to get started with planning your dream wedding!</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card purple" onclick="showSection('budget')">
                <div class="stat-icon">💰</div>
                <div class="stat-number" id="totalBudget">$0</div>
                <div class="stat-label">Budget Allocated</div>
                <div class="progress-bar">
                    <div class="progress-fill" id="budgetProgress" style="width: 0%;"></div>
                </div>
            </div>
            
            <div class="stat-card pink" onclick="showSection('guests')">
                <div class="stat-icon">💌</div>
                <div class="stat-number" id="totalGuests">0/0</div>
                <div class="stat-label">RSVP Responses</div>
                <div class="progress-bar">
                    <div class="progress-fill" id="guestProgress" style="width: 0%;"></div>
                </div>
            </div>
            
            <div class="stat-card blue" onclick="showSection('checklist')">
                <div class="stat-icon">🎯</div>
                <div class="stat-number" id="completedTasks">0/0</div>
                <div class="stat-label">Tasks Complete</div>
                <div class="progress-bar">
                    <div class="progress-fill" id="taskProgress" style="width: 0%;"></div>
                </div>
            </div>
            
            <div class="stat-card green" onclick="toggleSubmenu('vendors')">
                <div class="stat-icon">🤝</div>
                <div class="stat-number" id="bookedVendors">0/0</div>
                <div class="stat-label">Vendors Secured</div>
                <div class="progress-bar">
                    <div class="progress-fill" id="vendorProgress" style="width: 0%;"></div>
                </div>
            </div>
            
            <div class="stat-card orange" onclick="showSection('schedule')">
                <div class="stat-icon">⏰</div>
                <div class="stat-number" id="daysRemaining">--</div>
                <div class="stat-label">Days to Go!</div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Dashboard Section -->
            <div class="content-section active" id="dashboard-section">
                <h2 class="section-title">🌟 Your Wedding Command Center</h2>
                <div class="section-content">
                    <div class="empty-state" id="dashboardEmptyState">
                        <div class="empty-state-icon">💒</div>
                        <h3>Welcome to Your Wedding Journey!</h3>
                        <p>Get started by setting up your profile and adding your wedding details.</p>
                        <button class="btn" onclick="showSection('profile')" style="margin-top: 20px;">Complete Profile Setup</button>
                    </div>
                    
                    <div id="dashboardContent" style="display: none;">
                        <div class="interactive-card" onclick="showSection('checklist')">
                            <h3>🚨 Recent Activity</h3>
                            <p id="recentActivity">No recent activity</p>
                        </div>
                        
                        <div class="interactive-card" onclick="showSection('budget')">
                            <h3>💸 Budget Overview</h3>
                            <p id="budgetOverview">No budget set up yet</p>
                        </div>
                        
                        <div class="interactive-card" onclick="showSection('guests')">
                            <h3>📧 Guest Status</h3>
                            <p id="guestOverview">No guests added yet</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Setup Section -->
            <div class="content-section" id="profile-section">
                <h2 class="section-title">👤 Profile Setup</h2>
                <div class="section-content">
                    <div class="interactive-card">
                        <h3>Wedding Details</h3>
                        <form id="profileForm">
                            <div class="form-group">
                                <label>Bride's Name:</label>
                                <input type="text" id="brideName" placeholder="Enter bride's name">
                            </div>
                            <div class="form-group">
                                <label>Groom's Name:</label>
                                <input type="text" id="groomName" placeholder="Enter groom's name">
                            </div>
                            <div class="form-group">
                                <label>Wedding Date:</label>
                                <input type="date" id="weddingDate">
                            </div>
                            <div class="form-group">
                                <label>Total Budget:</label>
                                <input type="number" id="totalBudget" placeholder="0.00" step="0.01">
                            </div>
                            <div class="form-group">
                                <label>Expected Number of Guests:</label>
                                <input type="number" id="expectedGuests" placeholder="0">
                            </div>
                            <button type="button" class="btn" onclick="saveProfile()">Save Profile</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Budget Section -->
            <div class="content-section" id="budget-section">
                <h2 class="section-title">💰 Smart Budget Tracker</h2>
                <div class="section-content">
                    <div id="budgetEmptyState" class="empty-state">
                        <div class="empty-state-icon">💰</div>
                        <h3>No Budget Set Up Yet</h3>
                        <p>Complete your profile setup to start tracking your wedding budget.</p>
                        <button class="btn" onclick="showSection('profile')" style="margin-top: 20px;">Set Up Budget</button>
                    </div>
                    
                    <div id="budgetContent" style="display: none;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                            <div>
                                <h3>Total Budget: <span id="displayTotalBudget">$0</span></h3>
                                <p>Spent: <span id="totalSpent">$0</span> | Remaining: <span id="remainingBudget">$0</span></p>
                            </div>
                            <button class="btn" onclick="openBudgetModal()">Add Expense</button>
                        </div>
                        
                        <div id="budgetCategories">
                            <!-- Budget categories will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guest Management Section -->
            <div class="content-section" id="guests-section">
                <h2 class="section-title">👥 Guest List & RSVP Manager</h2>
                <div class="section-content">
                    <div id="guestsEmptyState" class="empty-state">
                        <div class="empty-state-icon">👥</div>
                        <h3>No Guests Added Yet</h3>
                        <p>Start building your guest list to track RSVPs and manage invitations.</p>
                        <button class="btn" onclick="openGuestModal()" style="margin-top: 20px;">Add First Guest</button>
                    </div>
                    
                    <div id="guestsContent" style="display: none;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                            <div>
                                <h3><span id="totalInvited">0</span> Guests Invited | <span id="totalResponded">0</span> Responded</h3>
                                <p>Confirmed: <span id="confirmedGuests">0</span> | Declined: <span id="declinedGuests">0</span> | Pending: <span id="pendingGuests">0</span></p>
                            </div>
                            <button class="btn" onclick="openGuestModal()">Add Guest</button>
                        </div>
                        
                        <div id="guestsList">
                            <!-- Guest list will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Checklist Section -->
            <div class="content-section" id="checklist-section">
                <h2 class="section-title">✅ Smart Wedding Checklist</h2>
                <div class="section-content">
                    <div id="checklistEmptyState" class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3>Checklist Not Generated Yet</h3>
                        <p>Complete your profile to generate a personalized wedding checklist.</p>
                        <button class="btn" onclick="generateChecklist()" style="margin-top: 20px;">Generate Checklist</button>
                    </div>
                    
                    <div id="checklistContent" style="display: none;">
                        <div id="checklistItems">
                            <!-- Checklist items will be populated here -->
                        </div>
                        
                        <div class="interactive-card" onclick="addCustomTask()">
                            <h3>➕ Add Custom Task</h3>
                            <p>Create personalized tasks for your unique wedding needs</p>
                        </div>
                    </div> 
                </div>
            </div>
            <div class="content-section" id="schedule-section">
                <h2 class="section-title">📅 Wedding Timeline Planner</h2>
                <div class="section-content">
                    <div id="scheduleEmptyState" class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <h3>Timeline Not Created Yet</h3>
                        <p>Set your wedding date to generate a personalized timeline.</p>
                        <button class="btn" onclick="showSection('profile')" style="margin-top: 20px;">Set Wedding Date</button>
                    </div>
                    
                    <div id="scheduleContent" style="display: none;">
                        <div id="timelineItems">
                            <!-- Timeline items will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vendor Sections -->
            <div class="content-section" id="photographers-section">
                <h2 class="section-title">📸 Photographers</h2>
                <div class="section-content">
                    <div id="photographersContent">
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Sarah's Wedding Photography</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.9/5)</div>
                                <p>Professional wedding photography with 10+ years experience</p>
                            </div>
                            <div>
                                <div class="vendor-price">$2,500 - $4,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Moments Photography Studio</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.8/5)</div>
                                <p>Artistic and creative wedding photography</p>
                            </div>
                            <div>
                                <div class="vendor-price">$1,800 - $3,500</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Classic Wedding Shots</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐ (4.7/5)</div>
                                <p>Traditional and timeless wedding photography</p>
                            </div>
                            <div>
                                <div class="vendor-price">$1,500 - $2,800</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section" id="venues-section">
                <h2 class="section-title">🏛️ Wedding Venues</h2>
                <div class="section-content">
                    <div id="venuesContent">
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Grand Ballroom Hotel</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.9/5)</div>
                                <p>Elegant ballroom venue with capacity for 200+ guests</p>
                            </div>
                            <div>
                                <div class="vendor-price">$8,000 - $15,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Garden Paradise Events</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.8/5)</div>
                                <p>Beautiful outdoor garden venue perfect for spring weddings</p>
                            </div>
                            <div>
                                <div class="vendor-price">$5,000 - $10,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Historic Manor House</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐ (4.6/5)</div>
                                <p>Charming historic venue with vintage character</p>
                            </div>
                            <div>
                                <div class="vendor-price">$4,000 - $8,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section" id="catering-section">
                <h2 class="section-title">🍽️ Catering Services</h2>
                <div class="section-content">
                    <div id="cateringContent">
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Gourmet Wedding Catering</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.9/5)</div>
                                <p>Fine dining catering with customizable menus</p>
                            </div>
                            <div>
                                <div class="vendor-price">$75 - $120/person</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Farm-to-Table Catering</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.8/5)</div>
                                <p>Fresh, local ingredients with seasonal menus</p>
                            </div>
                            <div>
                                <div class="vendor-price">$60 - $95/person</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Classic Banquet Services</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐ (4.7/5)</div>
                                <p>Traditional wedding catering with reliable service</p>
                            </div>
                            <div>
                                <div class="vendor-price">$45 - $75/person</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section" id="decor-section">
                <h2 class="section-title">🎨 Wedding Decor</h2>
                <div class="section-content">
                    <div id="decorContent">
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Elegant Events Decor</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.9/5)</div>
                                <p>Luxury wedding decor and floral arrangements</p>
                            </div>
                            <div>
                                <div class="vendor-price">$3,000 - $8,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Rustic Charm Decorations</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐ (4.7/5)</div>
                                <p>Country and rustic wedding decorations</p>
                            </div>
                            <div>
                                <div class="vendor-price">$1,500 - $4,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Modern Wedding Designs</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.8/5)</div>
                                <p>Contemporary and minimalist wedding decor</p>
                            </div>
                            <div>
                                <div class="vendor-price">$2,000 - $6,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section" id="dress-section">
                <h2 class="section-title">👗 Wedding Dress Designers</h2>
                <div class="section-content">
                    <div id="dressContent">
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Bridal Elegance Boutique</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.9/5)</div>
                                <p>Designer wedding dresses and custom alterations</p>
                            </div>
                            <div>
                                <div class="vendor-price">$1,200 - $4,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Couture Bridal Studio</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.8/5)</div>
                                <p>High-end custom wedding dress design</p>
                            </div>
                            <div>
                                <div class="vendor-price">$2,500 - $8,000</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Affordable Bridal Solutions</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐ (4.6/5)</div>
                                <p>Beautiful wedding dresses at budget-friendly prices</p>
                            </div>
                            <div>
                                <div class="vendor-price">$400 - $1,500</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section" id="makeup-section">
                <h2 class="section-title">💄 Makeup Artists</h2>
                <div class="section-content">
                    <div id="makeupContent">
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Glamour Beauty Studio</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.9/5)</div>
                                <p>Professional bridal makeup and hair styling</p>
                            </div>
                            <div>
                                <div class="vendor-price">$300 - $600</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Natural Beauty Artists</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐ (4.7/5)</div>
                                <p>Natural and organic bridal makeup services</p>
                            </div>
                            <div>
                                <div class="vendor-price">$200 - $400</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                        
                        <div class="vendor-card">
                            <div class="vendor-info">
                                <h4>Vintage Glam Makeup</h4>
                                <div class="vendor-rating">⭐⭐⭐⭐⭐ (4.8/5)</div>
                                <p>Vintage-inspired bridal makeup and styling</p>
                            </div>
                            <div>
                                <div class="vendor-price">$250 - $500</div>
                                <button class="btn">Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Budget Modal -->
    <div id="budgetModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeBudgetModal()">&times;</span>
            <h2>Add Budget Expense</h2>
            <form id="budgetForm">
                <div class="form-group">
                    <label>Category:</label>
                    <select id="budgetCategory">
                        <option value="venue">Venue</option>
                        <option value="catering">Catering</option>
                        <option value="photography">Photography</option>
                        <option value="decor">Decor & Flowers</option>
                        <option value="dress">Wedding Dress</option>
                        <option value="makeup">Makeup & Hair</option>
                        <option value="music">Music/DJ</option>
                        <option value="transportation">Transportation</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount:</label>
                    <input type="number" id="budgetAmount" placeholder="0.00" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <input type="text" id="budgetDescription" placeholder="Brief description">
                </div>
                <button type="button" class="btn" onclick="addBudgetItem()">Add Expense</button>
            </form>
        </div>
    </div>

    <!-- Guest Modal -->
    <div id="guestModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeGuestModal()">&times;</span>
            <h2>Add Guest</h2>
            <form id="guestForm">
                <div class="form-group">
                    <label>Guest Name:</label>
                    <input type="text" id="guestName" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" id="guestEmail" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label>Phone:</label>
                    <input type="tel" id="guestPhone" placeholder="Phone number">
                </div>
                <div class="form-group">
                    <label>Plus One:</label>
                    <select id="guestPlusOne">
                        <option value="no">No</option>
                        <option value="yes">Yes</option>
                    </select>
                </div>
                <button type="button" class="btn" onclick="addGuest()">Add Guest</button>
            </form>
        </div>
    </div>

    <!-- Custom Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTaskModal()">&times;</span>
            <h2>Add Custom Task</h2>
            <form id="taskForm">
                <div class="form-group">
                    <label>Task Name:</label>
                    <input type="text" id="taskName" placeholder="Task description" required>
                </div>
                <div class="form-group">
                    <label>Due Date:</label>
                    <input type="date" id="taskDate">
                </div>
                <div class="form-group">
                    <label>Priority:</label>
                    <select id="taskPriority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <button type="button" class="btn" onclick="addCustomTaskToList()">Add Task</button>
            </form>
        </div>
    </div>

    <script>
        // Global variables for data storage
        let weddingData = {
            profile: {
                brideName: '',
                groomName: '',
                weddingDate: '',
                totalBudget: 0,
                expectedGuests: 0
            },
            budget: {
                items: [],
                totalSpent: 0
            },
            guests: [],
            checklist: [],
            vendors: {
                booked: 0,
                total: 6
            }
        };

        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        // Navigation functionality
        function showSection(sectionName) {
            // Hide all sections
            const sections = document.querySelectorAll('.content-section');
            sections.forEach(section => section.classList.remove('active'));
            
            // Show selected section
            const targetSection = document.getElementById(sectionName + '-section');
            if (targetSection) {
                targetSection.classList.add('active');
            }
            
            // Update active nav link
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            
            const activeLink = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
            
            updateSectionContent(sectionName);
        }

        function toggleSubmenu(submenuId) {
            const submenu = document.getElementById(submenuId + '-submenu');
            const parentLink = document.querySelector(`[onclick="toggleSubmenu('${submenuId}')"]`);
            
            if (submenu) {
                submenu.classList.toggle('active');
                parentLink.classList.toggle('active');
            }
        }

        // Profile management
        function saveProfile() {
            const brideName = document.getElementById('brideName').value;
            const groomName = document.getElementById('groomName').value;
            const weddingDate = document.getElementById('weddingDate').value;
            const totalBudget = parseFloat(document.getElementById('totalBudget').value) || 0;
            const expectedGuests = parseInt(document.getElementById('expectedGuests').value) || 0;
            
            if (!brideName || !groomName || !weddingDate) {
                alert('Please fill in all required fields (Names and Wedding Date)');
                return;
            }
            
            // Update wedding data
            weddingData.profile = {
                brideName,
                groomName,
                weddingDate,
                totalBudget,
                expectedGuests
            };
            
            // Update UI elements
            updateDashboardHeader();
            updateStatCards();
            generateChecklist();
            generateTimeline();
            
            alert('Profile saved successfully!');
            showSection('dashboard');
        }

        function updateDashboardHeader() {
            const profile = weddingData.profile;
            if (profile.brideName && profile.groomName && profile.weddingDate) {
                document.getElementById('userName').textContent = `${profile.brideName} & ${profile.groomName}`;
                document.getElementById('userRole').textContent = `Wedding: ${new Date(profile.weddingDate).toLocaleDateString()}`;
                document.getElementById('userAvatar').textContent = '💕';
                
                document.getElementById('dashboardTitle').textContent = `Welcome Back, ${profile.brideName} & ${profile.groomName}! 💕`;
                document.getElementById('dashboardSubtitle').textContent = `Your wedding is on ${new Date(profile.weddingDate).toLocaleDateString()}. Let's make it perfect!`;
                
                // Show dashboard content, hide empty state
                document.getElementById('dashboardEmptyState').style.display = 'none';
                document.getElementById('dashboardContent').style.display = 'block';
                
                // Calculate days remaining
                const today = new Date();
                const weddingDate = new Date(profile.weddingDate);
                const timeDiff = weddingDate.getTime() - today.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
                document.getElementById('daysRemaining').textContent = daysDiff > 0 ? daysDiff : '0';
            }
        }

        function updateStatCards() {
            const profile = weddingData.profile;
            
            // Budget card
            document.getElementById('totalBudget').textContent = `$${profile.totalBudget.toLocaleString()}`;
            const budgetUsed = (weddingData.budget.totalSpent / profile.totalBudget) * 100;
            document.getElementById('budgetProgress').style.width = `${Math.min(budgetUsed, 100)}%`;
            
            // Guest card
            const totalGuests = weddingData.guests.length;
            const respondedGuests = weddingData.guests.filter(g => g.status !== 'pending').length;
            document.getElementById('totalGuests').textContent = `${respondedGuests}/${totalGuests}`;
            const guestProgress = totalGuests > 0 ? (respondedGuests / totalGuests) * 100 : 0;
            document.getElementById('guestProgress').style.width = `${guestProgress}%`;
            
            // Tasks card
            const completedTasks = weddingData.checklist.filter(task => task.completed).length;
            const totalTasks = weddingData.checklist.length;
            document.getElementById('completedTasks').textContent = `${completedTasks}/${totalTasks}`;
            const taskProgress = totalTasks > 0 ? (completedTasks / totalTasks) * 100 : 0;
            document.getElementById('taskProgress').style.width = `${taskProgress}%`;
            
            // Vendors card
            document.getElementById('bookedVendors').textContent = `${weddingData.vendors.booked}/${weddingData.vendors.total}`;
            const vendorProgress = (weddingData.vendors.booked / weddingData.vendors.total) * 100;
            document.getElementById('vendorProgress').style.width = `${vendorProgress}%`;
        }

        // Budget management
        function openBudgetModal() {
            document.getElementById('budgetModal').style.display = 'block';
        }

        function closeBudgetModal() {
            document.getElementById('budgetModal').style.display = 'none';
            document.getElementById('budgetForm').reset();
        }

        function addBudgetItem() {
            const category = document.getElementById('budgetCategory').value;
            const amount = parseFloat(document.getElementById('budgetAmount').value);
            const description = document.getElementById('budgetDescription').value;
            
            if (!amount || amount <= 0) {
                alert('Please enter a valid amount');
                return;
            }
            
            const budgetItem = {
                id: Date.now(),
                category,
                amount,
                description,
                date: new Date().toLocaleDateString()
            };
            
            weddingData.budget.items.push(budgetItem);
            weddingData.budget.totalSpent += amount;
            
            updateBudgetDisplay();
            updateStatCards();
            closeBudgetModal();
        }

        function updateBudgetDisplay() {
            const profile = weddingData.profile;
            const budget = weddingData.budget;
            
            if (profile.totalBudget > 0) {
                document.getElementById('budgetEmptyState').style.display = 'none';
                document.getElementById('budgetContent').style.display = 'block';
                
                document.getElementById('displayTotalBudget').textContent = `$${profile.totalBudget.toLocaleString()}`;
                document.getElementById('totalSpent').textContent = `$${budget.totalSpent.toLocaleString()}`;
                document.getElementById('remainingBudget').textContent = `$${(profile.totalBudget - budget.totalSpent).toLocaleString()}`;
                
                // Group budget items by category
                const categories = {};
                budget.items.forEach(item => {
                    if (!categories[item.category]) {
                        categories[item.category] = { total: 0, items: [] };
                    }
                    categories[item.category].total += item.amount;
                    categories[item.category].items.push(item);
                });
                
                const categoriesHtml = Object.entries(categories).map(([category, data]) => `
                    <div class="budget-item">
                        <div class="budget-category">${category.charAt(0).toUpperCase() + category.slice(1)}</div>
                        <div class="budget-amount">$${data.total.toLocaleString()}</div>
                    </div>
                `).join('');
                
                document.getElementById('budgetCategories').innerHTML = categoriesHtml;
            }
        }

        // Guest management
        function openGuestModal() {
            document.getElementById('guestModal').style.display = 'block';
        }

        function closeGuestModal() {
            document.getElementById('guestModal').style.display = 'none';
            document.getElementById('guestForm').reset();
        }

        function addGuest() {
            const name = document.getElementById('guestName').value;
            const email = document.getElementById('guestEmail').value;
            const phone = document.getElementById('guestPhone').value;
            const plusOne = document.getElementById('guestPlusOne').value === 'yes';
            
            if (!name) {
                alert('Please enter guest name');
                return;
            }
            
            const guest = {
                id: Date.now(),
                name,
                email,
                phone,
                plusOne,
                status: 'pending'
            };
            
            weddingData.guests.push(guest);
            updateGuestDisplay();
            updateStatCards();
            closeGuestModal();
        }

        function updateGuestDisplay() {
            if (weddingData.guests.length > 0) {
                document.getElementById('guestsEmptyState').style.display = 'none';
                document.getElementById('guestsContent').style.display = 'block';
                
                const totalInvited = weddingData.guests.length;
                const confirmed = weddingData.guests.filter(g => g.status === 'confirmed').length;
                const declined = weddingData.guests.filter(g => g.status === 'declined').length;
                const pending = weddingData.guests.filter(g => g.status === 'pending').length;
                const responded = confirmed + declined;
                
                document.getElementById('totalInvited').textContent = totalInvited;
                document.getElementById('totalResponded').textContent = responded;
                document.getElementById('confirmedGuests').textContent = confirmed;
                document.getElementById('declinedGuests').textContent = declined;
                document.getElementById('pendingGuests').textContent = pending;
                
                const guestsHtml = weddingData.guests.map(guest => `
                    <div class="guest-item">
                        <div>
                            <strong>${guest.name}</strong>
                            ${guest.plusOne ? ' (+1)' : ''}
                            <br>
                            <small>${guest.email || 'No email'}</small>
                        </div>
                        <div>
                            <span class="guest-status status-${guest.status}">${guest.status.charAt(0).toUpperCase() + guest.status.slice(1)}</span>
                            <select onchange="updateGuestStatus(${guest.id}, this.value)" style="margin-left: 10px;">
                                <option value="pending" ${guest.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="confirmed" ${guest.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                <option value="declined" ${guest.status === 'declined' ? 'selected' : ''}>Declined</option>
                            </select>
                        </div>
                    </div>
                `).join('');
                
                document.getElementById('guestsList').innerHTML = guestsHtml;
            }
        }

        function updateGuestStatus(guestId, newStatus) {
            const guest = weddingData.guests.find(g => g.id === guestId);
            if (guest) {
                guest.status = newStatus;
                updateGuestDisplay();
                updateStatCards();
            }
        }

        // Checklist management
        function generateChecklist() {
            if (!weddingData.profile.weddingDate) {
                alert('Please set your wedding date first');
                return;
            }
            
            const defaultTasks = [
                { task: "Book wedding venue", completed: false, priority: "high", dueDate: getDateBefore(365) },
                { task: "Hire wedding photographer", completed: false, priority: "high", dueDate: getDateBefore(300) },
                { task: "Choose and order wedding dress", completed: false, priority: "high", dueDate: getDateBefore(180) },
                { task: "Book catering service", completed: false, priority: "high", dueDate: getDateBefore(120) },
                { task: "Send wedding invitations", completed: false, priority: "medium", dueDate: getDateBefore(60) },
                { task: "Book makeup artist and hair stylist", completed: false, priority: "medium", dueDate: getDateBefore(90) },
                { task: "Order wedding cake", completed: false, priority: "medium", dueDate: getDateBefore(30) },
                { task: "Plan honeymoon", completed: false, priority: "low", dueDate: getDateBefore(45) },
                { task: "Buy wedding rings", completed: false, priority: "high", dueDate: getDateBefore(90) },
                {
                    task: "Finalize guest list", completed: false, priority: "high", dueDate: getDateBefore(30)
                }
            ];
            weddingData.checklist = defaultTasks;
            updateChecklistDisplay();
        }
        function getDateBefore(days) {
            const date = new Date();
            date.setDate(date.getDate() + days);
            return date.toISOString().split('T')[0]; // Return in YYYY-MM-DD format
        }
        function updateChecklistDisplay() {
            if (weddingData.checklist.length > 0) {
                document.getElementById('checklistEmptyState').style.display = 'none';
                document.getElementById('checklistContent').style.display = 'block';
                
                const checklistHtml = weddingData.checklist.map((task, index) => `
                    <div class="checklist-item">
                        <input type="checkbox" id="task-${index}" ${task.completed ? 'checked' : ''} onchange="toggleTaskCompletion(${index})">
                        <label for="task-${index}" class="${task.completed ? 'completed' : ''}">${task.task}</label>
                        <span class="task-priority priority-${task.priority}">${task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}</span>
                        <span class="task-due-date">Due: ${new Date(task.dueDate).toLocaleDateString()}</span>
                    </div>
                `).join('');
                
                document.getElementById('checklistItems').innerHTML = checklistHtml;
            }
        }
        function toggleTaskCompletion(index) {
            const task = weddingData.checklist[index];
            if (task) {
                task.completed = !task.completed;
                updateChecklistDisplay();
                updateStatCards();
            }
        }
        // Timeline generation
        function generateTimeline() {
            if (!weddingData.profile.weddingDate) {
                alert('Please set your wedding date first');
                return;
            }
            
            const timelineItems = [
                { date: getDateBefore(365), event: "Book wedding venue" },
                { date: getDateBefore(300), event: "Hire wedding photographer" },
                { date: getDateBefore(180), event: "Choose and order wedding dress" },
                { date: getDateBefore(120), event: "Book catering service" },
                { date: getDateBefore(90), event: "Book makeup artist and hair stylist" },
                { date: getDateBefore(60), event: "Send wedding invitations" },
                { date: getDateBefore(45), event: "Plan honeymoon" },
                { date: getDateBefore(30), event: "Finalize guest list" },
                { date: getDateBefore(30), event: "Order wedding cake" },
                { date: getDateBefore(15), event: "Buy wedding rings" }
            ];
            
            const timelineHtml = timelineItems.map(item => `
                <div class="timeline-item">
                    <div class="timeline-date">${new Date(item.date).toLocaleDateString()}</div>
                    <div class="timeline-event">${item.event}</div>
                </div>
            `).join('');
            
            document.getElementById('timelineItems').innerHTML = timelineHtml;
        }
        // Vendor management
        function updateSectionContent(sectionName) {
            switch (sectionName) {
                case 'dashboard':
                    updateDashboardHeader();
                    updateStatCards();
                    break;
                case 'budget':
                    updateBudgetDisplay();
                    break;
                case 'guests':
                    updateGuestDisplay();
                    break;
                case 'checklist':
                    updateChecklistDisplay();
                    break;
                case 'timeline':
                    generateTimeline();
                    break;
                case 'vendors':
                    // Vendor data is static in this example, but can be fetched from a server
                    break;
            }
        }
        // Custom task management
        function openTaskModal() {
            document.getElementById('taskModal').style.display = 'block';
        }
        function closeTaskModal() {
            document.getElementById('taskModal').style.display = 'none';
            document.getElementById('taskForm').reset();
        }
        function addCustomTaskToList() {
            const taskName = document.getElementById('taskName').value;
            const taskDate = document.getElementById('taskDate').value;
            const taskPriority = document.getElementById('taskPriority').value;
            
            if (!taskName) {
                alert('Please enter a task name');
                return;
            }
            
            const newTask = {
                id: Date.now(),
                task: taskName,
                completed: false,
                dueDate: taskDate || getDateBefore(30),
                priority: taskPriority
            };
            
            weddingData.checklist.push(newTask);
            updateChecklistDisplay();
            closeTaskModal();
        }
        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateDashboardHeader();
            updateStatCards();
            generateChecklist();
            generateTimeline();
        });
    </script>
</body>
</html>
