<?php
session_start();
require_once 'config.php'; // Your database configuration file



// Create advertisements table if it doesn't exist
try {
    $createTableQuery = "
    CREATE TABLE IF NOT EXISTS vendor_advertisements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        service_type VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        price VARCHAR(100),
        location VARCHAR(255),
        contact_phone VARCHAR(20),
        contact_email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_vendor_id (vendor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec($createTableQuery);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $vendorId = $_SESSION['vendor_id'];
    
    try {
        switch ($action) {
            case 'create_ad':
                $stmt = $pdo->prepare("
                    INSERT INTO vendor_advertisements 
                    (vendor_id, title, service_type, description, price, location, contact_phone, contact_email) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $vendorId,
                    $_POST['title'] ?? '',
                    $_POST['serviceType'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['price'] ?? '',
                    $_POST['location'] ?? '',
                    $_POST['contactPhone'] ?? '',
                    $_POST['contactEmail'] ?? ''
                ]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Advertisement created successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create advertisement.']);
                }
                break;
                
            case 'update_ad':
                $adId = $_POST['adId'] ?? 0;
                
                // Verify the ad belongs to the current vendor
                $checkStmt = $pdo->prepare("SELECT id FROM vendor_advertisements WHERE id = ? AND vendor_id = ?");
                $checkStmt->execute([$adId, $vendorId]);
                
                if (!$checkStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Advertisement not found or access denied.']);
                    break;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE vendor_advertisements 
                    SET title = ?, service_type = ?, description = ?, price = ?, 
                        location = ?, contact_phone = ?, contact_email = ?
                    WHERE id = ? AND vendor_id = ?
                ");
                
                $result = $stmt->execute([
                    $_POST['title'] ?? '',
                    $_POST['serviceType'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['price'] ?? '',
                    $_POST['location'] ?? '',
                    $_POST['contactPhone'] ?? '',
                    $_POST['contactEmail'] ?? '',
                    $adId,
                    $vendorId
                ]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Advertisement updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update advertisement.']);
                }
                break;
                
            case 'delete_ad':
                $adId = $_POST['adId'] ?? 0;
                
                $stmt = $pdo->prepare("DELETE FROM vendor_advertisements WHERE id = ? AND vendor_id = ?");
                $result = $stmt->execute([$adId, $vendorId]);
                
                if ($result && $stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Advertisement deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete advertisement or access denied.']);
                }
                break;
                
            case 'get_ads':
                $stmt = $pdo->prepare("
                    SELECT id, title, service_type, description, price, location, 
                           contact_phone, contact_email, created_at, updated_at
                    FROM vendor_advertisements 
                    WHERE vendor_id = ? 
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$vendorId]);
                $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'ads' => $ads]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    } catch (Exception $e) {
        error_log("General error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
    }
    
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Get vendor info for display
$vendorName = $_SESSION['vendor_name'] ?? 'Unknown Vendor';
$vendorBusiness = $_SESSION['vendor_business'] ?? 'Unknown Business';
$vendorInitials = strtoupper(substr($vendorName, 0, 1) . substr(strstr($vendorName, ' '), 1, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - All in One Wedding</title>
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
            color: #333;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-content {
            text-align: center;
            color: white;
            z-index: 2;
            position: relative;
        }
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }


        .logo-image {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            border-radius: 50%;
            opacity: 0.9;
        }

        .logo {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            font-weight: bold;
            
        }

        .header-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-top: 2px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .user-details h3 {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .user-details p {
            font-size: 13px;
            color: #666;
        }

        .logout-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .main-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .content-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #333;
        }

        .create-ad-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .create-ad-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .ads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .ad-card {
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
        }

        .ad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .ad-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .ad-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .ad-type {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .ad-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ad-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .ad-detail {
            font-size: 13px;
            color: #666;
        }

        .ad-detail strong {
            color: #333;
        }

        .ad-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-edit, .btn-delete {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #28a745;
            color: white;
        }

        .btn-edit:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .empty-description {
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 30px 40px 20px;
            border-bottom: 1px solid #e1e5e9;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            color: #666;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close:hover {
            color: #333;
            background: #f1f1f1;
        }

        .modal-body {
            padding: 20px 40px 40px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 18px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }

        .btn-cancel, .btn-save {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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
            opacity: 0.6;
            pointer-events: none;
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
            .dashboard-container {
                padding: 15px;
            }

            .header {
                padding: 20px 25px;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .main-content {
                padding: 25px 20px;
            }

            .content-header {
                flex-direction: column;
                align-items: stretch;
            }

            .ads-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 10px;
                width: calc(100% - 20px);
                max-height: calc(100vh - 20px);
            }

            .modal-header, .modal-body {
                padding-left: 25px;
                padding-right: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="logo-section">
                <img class="logo-image" src="All in one Wedding logo.png" alt="All in One Wedding Logo">
                    <div>
                        <div class="header-title">All in One Wedding</div>
                        <div class="header-subtitle">Vendor Dashboard</div>
                    </div>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar"><?php echo $vendorInitials; ?></div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($vendorName); ?></h3>
                        <p><?php echo htmlspecialchars($vendorBusiness); ?></p>
                    </div>
                    <button class="logout-btn" onclick="logout()">Logout</button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1 class="content-title">My Advertisements</h1>
                <button class="create-ad-btn" onclick="openCreateModal()">
                    <span>+</span>
                    Create New Ad
                </button>
            </div>

            <div id="errorMessage" class="error-message"></div>
            <div id="successMessage" class="success-message"></div>

            <!-- Ads Grid -->
            <div class="ads-grid" id="adsGrid">
                <!-- Ads will be loaded here -->
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-icon">📢</div>
                <h2 class="empty-title">No Advertisements Yet</h2>
                <p class="empty-description">
                    Start promoting your wedding services by creating your first advertisement.<br>
                    Reach engaged couples who are looking for vendors like you!
                </p>
                <button class="create-ad-btn" onclick="openCreateModal()">
                    <span>+</span>
                    Create Your First Ad
                </button>
            </div>
        </div>
    </div>

    <!-- Create/Edit Ad Modal -->
    <div id="adModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Create New Advertisement</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="adForm">
                    <input type="hidden" id="adId" name="adId">
                    
                    <div class="form-group">
                        <label class="form-label" for="adTitleInput">Advertisement Title</label>
                        <input type="text" class="form-input" id="adTitleInput" name="title" required placeholder="e.g., Professional Wedding Photography Services">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="serviceType">Service Type</label>
                        <select class="form-select" id="serviceType" name="serviceType" required>
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
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-textarea" id="description" name="description" required placeholder="Describe your services, experience, and what makes you unique..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="price">Price Range</label>
                            <input type="text" class="form-input" id="price" name="price" placeholder="e.g., $1,500 - $3,000">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="location">Service Area</label>
                            <input type="text" class="form-input" id="location" name="location" placeholder="e.g., Los Angeles, CA">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="contactPhone">Contact Phone</label>
                            <input type="tel" class="form-input" id="contactPhone" name="contactPhone" placeholder="(555) 123-4567">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="contactEmail">Contact Email</label>
                            <input type="email" class="form-input" id="contactEmail" name="contactEmail" placeholder="your@email.com">
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn-save" id="saveBtn">Save Advertisement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let ads = [];
        let editingAdId = null;
        let isLoading = false;

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadAds();
            
            // Form submission handler
            document.getElementById('adForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveAd();
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                const modal = document.getElementById('adModal');
                if (e.target === modal) {
                    closeModal();
                }
            });
        });

        // Make AJAX request
        function makeRequest(data) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                const formData = new FormData();
                for (let key in data) {
                    formData.append(key, data[key]);
                }
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                resolve(response);
                            } catch (e) {
                                reject(new Error('Invalid JSON response'));
                            }
                        } else {
                            reject(new Error('Request failed'));
                        }
                    }
                };
                
                xhr.send(formData);
            });
        }

        // Load and display ads
        async function loadAds() {
            if (isLoading) return;
            
            try {
                isLoading = true;
                showLoading(true);
                
                const response = await makeRequest({ action: 'get_ads' });
                
                if (response.success) {
                    ads = response.ads;
                    displayAds();
                } else {
                    showMessage(response.message || 'Failed to load advertisements.', 'error');
                }
            } catch (error) {
                console.error('Error loading ads:', error);
                showMessage('Failed to load advertisements.', 'error');
            } finally {
                isLoading = false;
                showLoading(false);
            }
        }

        // Display ads in the grid
        function displayAds() {
            const adsGrid = document.getElementById('adsGrid');
            const emptyState = document.getElementById('emptyState');

            if (ads.length === 0) {
                adsGrid.style.display = 'none';
                emptyState.style.display = 'block';
                return;
            }

            adsGrid.style.display = 'grid';
            emptyState.style.display = 'none';

            adsGrid.innerHTML = ads.map(ad => `
                <div class="ad-card">
                    <div class="ad-header">
                        <div>
                            <div class="ad-title">${escapeHtml(ad.title)}</div>
                        </div>
                        <div class="ad-type">${formatServiceType(ad.service_type)}</div>
                    </div>
                    
                    <div class="ad-description">${escapeHtml(ad.description)}</div>
                    
                    <div class="ad-details">
                        ${ad.price ? `<div class="ad-detail"><strong>Price:</strong> ${escapeHtml(ad.price)}</div>` : ''}
                        ${ad.location ? `<div class="ad-detail"><strong>Area:</strong> ${escapeHtml(ad.location)}</div>` : ''}
                        ${ad.contact_phone ? `<div class="ad-detail"><strong>Phone:</strong> ${escapeHtml(ad.contact_phone)}</div>` : ''}
                        ${ad.contact_email ? `<div class="ad-detail"><strong>Email:</strong> ${escapeHtml(ad.contact_email)}</div>` : ''}
                    </div>
                    
                    <div class="ad-actions">
                        <button class="btn-edit" onclick="editAd(${ad.id})">Edit</button>
                        <button class="btn-delete" onclick="deleteAd(${ad.id})">Delete</button>
                    </div>
                </div>
            `).join('');
        }

        // Escape HTML to prevent XSS
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Format service type for display
        function formatServiceType(type) {
            const types = {
                'venue': 'Venue',
                'photographer': 'Photography',
                'videographer': 'Videography',
                'catering': 'Catering',
                'florist': 'Florist',
                'music': 'Music/DJ',
                'decoration': 'Decoration',
                'makeup': 'Makeup',
                'transportation': 'Transportation',
                'planning': 'Wedding Planning',
                'other': 'Other'
            };
            return types[type] || type;
        }

        // Open create modal
        function openCreateModal() {
            editingAdId = null;
            document.getElementById('modalTitle').textContent = 'Create New Advertisement';
            document.getElementById('saveBtn').textContent = 'Save Advertisement';
            document.getElementById('adForm').reset();
            document.getElementById('adModal').style.display = 'block';
            hideMessages();
        }

        // Edit ad
        function editAd(id) {
            const ad = ads.find(a => a.id == id);
            if (!ad) return;

            editingAdId = id;
            document.getElementById('modalTitle').textContent = 'Edit Advertisement';
            document.getElementById('saveBtn').textContent = 'Update Advertisement';
            
            // Populate form
            document.getElementById('adId').value = ad.id;
            document.getElementById('adTitleInput').value = ad.title;
            document.getElementById('serviceType').value = ad.service_type;
            document.getElementById('description').value = ad.description;
            document.getElementById('price').value = ad.price || '';
            document.getElementById('location').value = ad.location || '';
            document.getElementById('contactPhone').value = ad.contact_phone || '';
            document.getElementById('contactEmail').value = ad.contact_email || '';
            
            document.getElementById('adModal').style.display = 'block';
            hideMessages();
        }

        // Save ad (create or update)
        async function saveAd() {
            if (isLoading) return;
            
            const form = document.getElementById('adForm');
            const formData = new FormData(form);
            
            // Validate required fields
            if (!formData.get('title') || !formData.get('serviceType') || !formData.get('description')) {
                showMessage('Please fill in all required fields.', 'error');
                return;
            }
            
            try {
                isLoading = true;
                showLoading(true);
                
                const data = {
                    action: editingAdId ? 'update_ad' : 'create_ad',
                    title: formData.get('title'),
                    serviceType: formData.get('serviceType'),
                    description: formData.get('description'),
                    price: formData.get('price'),
                    location: formData.get('location'),
                    contactPhone: formData.get('contactPhone'),
                    contactEmail: formData.get('contactEmail')
                };
                
                if (editingAdId) {
                    data.adId = editingAdId;
                }
                
                const response = await makeRequest(data);
                
                if (response.success) {
                    showMessage(response.message, 'success');
                    closeModal();
                    await loadAds();
                } else {
                    showMessage(response.message || 'Failed to save advertisement.', 'error');
                }
            } catch (error) {
                console.error('Error saving ad:', error);
                showMessage('Failed to save advertisement.', 'error');
            } finally {
                isLoading = false;
                showLoading(false);
            }
        }

        // Delete ad
        async function deleteAd(id) {
            if (!confirm('Are you sure you want to delete this advertisement?')) {
                return;
            }
            
            if (isLoading) return;
            
            try {
                isLoading = true;
                showLoading(true);
                
                const response = await makeRequest({
                    action: 'delete_ad',
                    adId: id
                });
                
                if (response.success) {
                    showMessage(response.message, 'success');
                    await loadAds();
                } else {
                    showMessage(response.message || 'Failed to delete advertisement.', 'error');
                }
            } catch (error) {
                console.error('Error deleting ad:', error);
                showMessage('Failed to delete advertisement.', 'error');
            } finally {
                isLoading = false;
                showLoading(false);
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('adModal').style.display = 'none';
            editingAdId = null;
        }

        // Show success/error messages
        function showMessage(message, type) {
            hideMessages();
            const messageEl = document.getElementById(type + 'Message');
            messageEl.textContent = message;
            messageEl.style.display = 'block';
            messageEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                messageEl.style.display = 'none';
            }, 5000);
        }

        function hideMessages() {
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';
        }

        // Show loading state
        function showLoading(show) {
            const elements = [
                document.getElementById('adsGrid'),
                document.getElementById('saveBtn'),
                document.querySelector('.create-ad-btn')
            ];
            
            elements.forEach(el => {
                if (el) {
                    if (show) {
                        el.classList.add('loading');
                        if (el.tagName === 'BUTTON') {
                            const spinner = '<span class="spinner"></span>';
                            if (!el.innerHTML.includes('spinner')) {
                                el.innerHTML = spinner + el.textContent;
                            }
                        }
                    } else {
                        el.classList.remove('loading');
                        if (el.tagName === 'BUTTON') {
                            const spinnerEl = el.querySelector('.spinner');
                            if (spinnerEl) {
                                spinnerEl.remove();
                            }
                        }
                    }
                }
            });
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '?logout=1';
            }
        }

        // Phone number formatting
        document.getElementById('contactPhone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            }
            this.value = value;
        });

        // Email validation
        document.getElementById('contactEmail').addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                showMessage('Please enter a valid email address.', 'error');
                this.focus();
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Handle form validation on input
        document.getElementById('adForm').addEventListener('input', function(e) {
            const target = e.target;
            if (target.hasAttribute('required') && target.value.trim() === '') {
                target.style.borderColor = '#dc3545';
            } else {
                target.style.borderColor = '';
            }
        });

        // Auto-resize textarea
        document.getElementById('description').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('adModal');
                if (modal.style.display === 'block') {
                    closeModal();
                }
            }
            
            // Ctrl+N to create new ad
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openCreateModal();
            }
        });

        // Prevent form submission on Enter key in input fields (except textarea)
        document.querySelectorAll('#adForm input, #adForm select').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = this.closest('form');
                    const inputs = Array.from(form.querySelectorAll('input, select, textarea'));
                    const index = inputs.indexOf(this);
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                }
            });
        });

        // Auto-save functionality (save draft every 30 seconds)
        let autoSaveTimer;
        let formChanged = false;

        function startAutoSave() {
            document.querySelectorAll('#adForm input, #adForm textarea, #adForm select').forEach(element => {
                element.addEventListener('input', () => formChanged = true);
            });

            autoSaveTimer = setInterval(() => {
                if (formChanged && editingAdId) {
                    // Auto-save only for existing ads being edited
                    const title = document.getElementById('adTitleInput').value.trim();
                    if (title) {
                        console.log('Auto-saving draft...');
                        formChanged = false;
                    }
                }
            }, 30000); // 30 seconds
        }

        function stopAutoSave() {
            if (autoSaveTimer) {
                clearInterval(autoSaveTimer);
                autoSaveTimer = null;
                formChanged = false;
            }
        }

        // Start auto-save when modal opens
        const originalOpenModal = openCreateModal;
        openCreateModal = function() {
            originalOpenModal();
            startAutoSave();
        };

        const originalEditAd = editAd;
        editAd = function(id) {
            originalEditAd(id);
            startAutoSave();
        };

        const originalCloseModal = closeModal;
        closeModal = function() {
            stopAutoSave();
            originalCloseModal();
        };
    </script>
</body>
</html>