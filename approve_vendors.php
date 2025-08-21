<?php
require_once 'config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Admin';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle approval/rejection actions
    if ($_POST['action'] ?? false) {
        $action = $_POST['action'];
        $vendor_id = $_POST['vendor_id'] ?? 0;
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        switch ($action) {
            case 'approve':
                // Update vendor status
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND user_type = 'vendor'");
                $stmt->execute([$vendor_id]);
                
                // Update vendor profile if exists
                $stmt = $pdo->prepare("UPDATE vendors SET status = 'approved', admin_notes = ?, approved_at = NOW(), approved_by = ? WHERE user_id = ?");
                $stmt->execute([$admin_notes, $_SESSION['user_id'], $vendor_id]);
                
                $message = "Vendor approved successfully";
                break;
                
            case 'reject':
                // Update vendor status
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE user_id = ? AND user_type = 'vendor'");
                $stmt->execute([$vendor_id]);
                
                // Update vendor profile if exists
                $stmt = $pdo->prepare("UPDATE vendors SET status = 'rejected', admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE user_id = ?");
                $stmt->execute([$admin_notes, $_SESSION['user_id'], $vendor_id]);
                
                $message = "Vendor rejected";
                break;
                
            case 'request_info':
                // Request more information
                $stmt = $pdo->prepare("UPDATE vendors SET status = 'info_requested', admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE user_id = ?");
                $stmt->execute([$admin_notes, $_SESSION['user_id'], $vendor_id]);
                
                $message = "Additional information requested from vendor";
                break;
        }
    }
    
    // Get pending vendors with their details
    $query = "
        SELECT 
            u.user_id,
            u.user_name,
            u.email,
            u.phone,
            u.created_at,
            v.business_name,
            v.business_type,
            v.business_description,
            v.address,
            v.city,
            v.state,
            v.zip_code,
            v.website,
            v.portfolio_images,
            v.certifications,
            v.years_experience,
            v.pricing_info,
            v.status as vendor_status,
            v.admin_notes,
            v.created_at as application_date
        FROM users u
        LEFT JOIN vendors v ON u.user_id = v.user_id
        WHERE u.user_type = 'vendor' 
        AND (u.status = 'pending' OR v.status IN ('pending', 'info_requested'))
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $pending_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary stats
    $stats_query = "
        SELECT 
            COUNT(CASE WHEN u.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN v.status = 'info_requested' THEN 1 END) as info_requested,
            COUNT(CASE WHEN u.status = 'active' THEN 1 END) as approved_count,
            COUNT(CASE WHEN u.status = 'rejected' THEN 1 END) as rejected_count
        FROM users u
        LEFT JOIN vendors v ON u.user_id = v.user_id
        WHERE u.user_type = 'vendor'
    ";
    
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $pending_vendors = [];
    $stats = ['pending_count' => 0, 'info_requested' => 0, 'approved_count' => 0, 'rejected_count' => 0];
}

function timeAgo($datetime) {
    if (empty($datetime)) return 'Unknown';
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hrs ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Vendors - Admin Dashboard</title>
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
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
        }

        .back-btn:hover {
            transform: translateY(-2px);
        }

        .page-title {
            color: #333;
            font-size: 1.5rem;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .stats-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .pending-vendors {
            display: grid;
            gap: 25px;
        }

        .vendor-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .vendor-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .vendor-info h3 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .vendor-info p {
            opacity: 0.9;
            font-size: 14px;
        }

        .application-date {
            text-align: right;
            font-size: 14px;
            opacity: 0.9;
        }

        .vendor-content {
            padding: 25px;
        }

        .vendor-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 25px;
        }

        .detail-section h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .detail-item {
            margin-bottom: 10px;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
            display: inline-block;
            min-width: 120px;
        }

        .detail-value {
            color: #666;
        }

        .business-description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            color: #333;
            line-height: 1.6;
        }

        .portfolio-images {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
        }

        .portfolio-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e0e0e0;
        }

        .action-section {
            border-top: 1px solid #e0e0e0;
            padding-top: 20px;
            margin-top: 20px;
        }

        .action-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notes-textarea {
            width: 100%;
            min-height: 80px;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .btn-approve {
            background: #4caf50;
            color: white;
        }

        .btn-reject {
            background: #f44336;
            color: white;
        }

        .btn-info {
            background: #ff9800;
            color: white;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3e0;
            color: #ff9800;
        }

        .status-info-requested {
            background: #e3f2fd;
            color: #1976d2;
        }

        .previous-notes {
            background: #fff3e0;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #ff9800;
        }

        .previous-notes h5 {
            color: #e65100;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .previous-notes p {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }

        .message {
            background: #e8f5e8;
            color: #4caf50;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error {
            background: #ffeaea;
            color: #f44336;
        }

        .no-vendors {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .no-vendors h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .no-vendors p {
            color: #666;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }
            
            .vendor-details {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .vendor-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <a href="Admin_Dashboard.php" class="back-btn">← Back to Dashboard</a>
            <h1 class="page-title">Approve Vendors</h1>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['pending_count']; ?></div>
                <div class="stats-label">Pending Applications</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['info_requested']; ?></div>
                <div class="stats-label">Info Requested</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['approved_count']; ?></div>
                <div class="stats-label">Approved Vendors</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo $stats['rejected_count']; ?></div>
                <div class="stats-label">Rejected Applications</div>
            </div>
        </div>

        <!-- Pending Vendors -->
        <?php if (!empty($pending_vendors)): ?>
            <div class="pending-vendors">
                <?php foreach ($pending_vendors as $vendor): ?>
                    <div class="vendor-card">
                        <div class="vendor-header">
                            <div class="vendor-info">
                                <h3><?php echo htmlspecialchars($vendor['business_name'] ?: $vendor['user_name']); ?></h3>
                                <p><?php echo htmlspecialchars($vendor['business_type']); ?></p>
                                <p>Contact: <?php echo htmlspecialchars($vendor['user_name']); ?> • <?php echo htmlspecialchars($vendor['email']); ?></p>
                            </div>
                            <div class="application-date">
                                <div>Applied: <?php echo timeAgo($vendor['application_date']); ?></div>
                                <span class="status-badge status-<?php echo str_replace(' ', '-', $vendor['vendor_status'] ?: 'pending'); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $vendor['vendor_status'] ?: 'pending')); ?>
                                </span>
                            </div>
                        </div>

                        <div class="vendor-content">
                            <div class="vendor-details">
                                <div class="detail-section">
                                    <h4>📋 Basic Information</h4>
                                    <div class="detail-item">
                                        <span class="detail-label">Business Name:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($vendor['business_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Business Type:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($vendor['business_type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Experience:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($vendor['years_experience']); ?> years</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Website:</span>
                                        <span class="detail-value">
                                            <?php if ($vendor['website']): ?>
                                                <a href="<?php echo htmlspecialchars($vendor['website']); ?>" target="_blank" style="color: #667eea;">
                                                    <?php echo htmlspecialchars($vendor['website']); ?>
                                                </a>
                                            <?php else: ?>
                                                Not provided
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="detail-section">
                                    <h4>📍 Location & Contact</h4>
                                    <div class="detail-item">
                                        <span class="detail-label">Address:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($vendor['address']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">City:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($vendor['city']); ?>, <?php echo htmlspecialchars($vendor['state']); ?> <?php echo htmlspecialchars($vendor['zip_code']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Phone:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($vendor['phone']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Email:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($vendor['email']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($vendor['business_description']): ?>
                                <div class="business-description">
                                    <strong>Business Description:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($vendor['business_description'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($vendor['pricing_info']): ?>
                                <div class="business-description">
                                    <strong>Pricing Information:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($vendor['pricing_info'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($vendor['certifications']): ?>
                                <div class="business-description">
                                    <strong>Certifications & Qualifications:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($vendor['certifications'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($vendor['portfolio_images']): ?>
                                <div>
                                    <strong>Portfolio Images:</strong>
                                    <div class="portfolio-images">
                                        <?php 
                                        $images = explode(',', $vendor['portfolio_images']);
                                        foreach ($images as $image): 
                                            if (trim($image)):
                                        ?>
                                            <img src="<?php echo htmlspecialchars(trim($image)); ?>" 
                                                 alt="Portfolio Image" 
                                                 class="portfolio-image"
                                                 onerror="this.style.display='none'">
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($vendor['admin_notes'] && $vendor['vendor_status'] === 'info_requested'): ?>
                                <div class="previous-notes">
                                    <h5>Previous Admin Notes:</h5>
                                    <p><?php echo nl2br(htmlspecialchars($vendor['admin_notes'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="action-section">
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="vendor_id" value="<?php echo $vendor['user_id']; ?>">
                                    
                                    <div>
                                        <label for="admin_notes_<?php echo $vendor['user_id']; ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">Admin Notes:</label>
                                        <textarea 
                                            name="admin_notes" 
                                            id="admin_notes_<?php echo $vendor['user_id']; ?>"
                                            class="notes-textarea" 
                                            placeholder="Add notes for your decision (optional for approval, required for rejection/info request)..."></textarea>
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <button type="submit" name="action" value="approve" class="action-btn btn-approve">
                                            ✅ Approve Vendor
                                        </button>
                                        <button type="submit" name="action" value="reject" class="action-btn btn-reject" 
                                                onclick="return confirm('Are you sure you want to reject this vendor application?')">
                                            ❌ Reject Application
                                        </button>
                                        <button type="submit" name="action" value="request_info" class="action-btn btn-info">
                                            📝 Request More Info
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-vendors">
                <h3>🎉 All caught up!</h3>
                <p>There are no pending vendor applications at this time.</p>
                <p>New applications will appear here when vendors submit them.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Validate forms before submission
        document.querySelectorAll('.action-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = e.submitter.value;
                const notes = this.querySelector('[name="admin_notes"]').value.trim();
                
                if ((action === 'reject' || action === 'request_info') && !notes) {
                    e.preventDefault();
                    alert('Please provide notes explaining your decision.');
                    this.querySelector('[name="admin_notes"]').focus();
                    return false;
                }
                
                if (action === 'approve') {
                    return confirm('Are you sure you want to approve this vendor? They will be able to receive bookings immediately.');
                }
                
                return true;
            });
        });

        // Auto-expand textareas
        document.querySelectorAll('.notes-textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>
</body>
</html>