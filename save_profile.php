<?php
require_once 'config.php';
function saveProfile() {
    global $link;
    
    // Debug: Check if we receive the POST data
    error_log("POST data received: " . print_r($_POST, true));
    
    // Debug: Check database connection
    if (!$link) {
        error_log("Database connection failed!");
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Debug: Check if user is logged in
    if (!isLoggedIn()) {
        error_log("User not logged in");
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        return;
    }

    $userId = getCurrentUserId();
    error_log("Current User ID: " . $userId);
    
    // Check if required POST data exists
    if (!isset($_POST['brideName']) || !isset($_POST['groomName']) || !isset($_POST['weddingDate'])) {
        error_log("Missing required POST data");
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        return;
    }
    
    $brideName = sanitize_input($_POST['bride_name']);
    $groomName = sanitize_input($_POST['groom_name']);
    $weddingDate = sanitize_input($_POST['wedding_date']);
    $totalBudget = (float) sanitize_input($_POST['total_budget'] ?? '0');
    $expectedGuests = (int) sanitize_input($_POST['expected_guests'] ?? '0');

    error_log("Data to save - Bride: $brideName, Groom: $groomName, Date: $weddingDate");
    
    // Test if profiles table exists
    $test_query = "SHOW TABLES LIKE 'profiles'";
    $result = mysqli_query($link, $test_query);
    if (mysqli_num_rows($result) == 0) {
        error_log("Profiles table does not exist!");
        echo json_encode(['success' => false, 'message' => 'Profiles table does not exist']);
        return;
    }

    // Check if profile already exists for the user
    $sql_check = "SELECT id FROM profiles WHERE user_id = ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
        mysqli_stmt_bind_param($stmt_check, "i", $userId);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) == 1) {
            // Update existing profile
            error_log("Updating existing profile for user: $userId");
            $sql = "UPDATE profiles SET bride_name = ?, groom_name = ?, wedding_date = ?, total_budget = ?, expected_guests = ? WHERE user_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssdii", $brideName, $groomName, $weddingDate, $totalBudget, $expectedGuests, $userId);
                if (mysqli_stmt_execute($stmt)) {
                    error_log("Profile updated successfully");
                    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
                } else {
                    error_log("Error updating profile: " . mysqli_error($link));
                    echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . mysqli_error($link)]);
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Error preparing update statement: " . mysqli_error($link));
                echo json_encode(['success' => false, 'message' => 'Error preparing update statement']);
            }
        } else {
            // Insert new profile
            error_log("Inserting new profile for user: $userId");
            $sql = "INSERT INTO profiles (user_id, bride_name, groom_name, wedding_date, total_budget, expected_guests) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "isssdi", $userId, $brideName, $groomName, $weddingDate, $totalBudget, $expectedGuests);
                if (mysqli_stmt_execute($stmt)) {
                    error_log("Profile inserted successfully");
                    echo json_encode(['success' => true, 'message' => 'Profile saved successfully.']);
                } else {
                    error_log("Error inserting profile: " . mysqli_error($link));
                    echo json_encode(['success' => false, 'message' => 'Error saving profile: ' . mysqli_error($link)]);
                }
                mysqli_stmt_close($stmt);
            } else {
                error_log("Error preparing insert statement: " . mysqli_error($link));
                echo json_encode(['success' => false, 'message' => 'Error preparing insert statement']);
            }
        }
        mysqli_stmt_close($stmt_check);
    } else {
        error_log("Error preparing check statement: " . mysqli_error($link));
        echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($link)]);
    }
}
?>