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

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'approve_review':
                    $review_id = $_POST['review_id'];
                    $stmt = $pdo->prepare("UPDATE reviews SET status = 'approved', moderated_by = ?, moderated_at = NOW() WHERE review_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $review_id]);
                    $success_message = "Review approved successfully!";
                    break;
                    
                case 'reject_review':
                    $review_id = $_POST['review_id'];
                    $reason = $_POST['reason'];
                    $stmt = $pdo->prepare("UPDATE reviews SET status = 'rejected', rejection_reason = ?, moderated_by = ?, moderated_at = NOW() WHERE review_id = ?");
                    $stmt->execute([$reason, $_SESSION['user_id'], $review_id]);
                    $success_message = "Review rejected successfully!";
                    break;
                    
                case 'flag_inappropriate':
                    $review_id = $_POST['review_id'];
                    $stmt = $pdo->prepare("UPDATE reviews SET status = 'flagged', moderated_by = ?, moderated_at = NOW() WHERE review_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $review_id]);
                    $success_message = "Review flagged as inappropriate!";
                    break;
                    
                case 'respond_to_review':
                    $review_id = $_POST['review_id'];
                    $admin_response = $_POST['admin_response'];
                    $stmt = $pdo->prepare("UPDATE reviews SET admin_response = ?, response_date = NOW() WHERE review_id = ?");
                    $stmt->execute([$admin_response, $review_id]);
                    $success_message = "Response added to review!";
                    break;
            }
        }
    }

    // Get filter parameters
    $status_filter = $_GET['status'] ?? 'all';
    $rating_filter = $_GET['rating'] ?? 'all';
    $date_filter = $_GET['date_filter'] ?? '30';

    // Build query based on filters
    $where_conditions = [];
    $params = [];

    if ($status_filter !== 'all') {
        $where_conditions[] = "r.status = ?";
        $params[] = $status_filter;
    }

    if ($rating_filter !== 'all') {
        $where_conditions[] = "r.rating = ?";
        $params[] = intval($rating_filter);
    }

    if ($date_filter !== 'all') {
        $where_conditions[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = intval($date_filter);
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get reviews with related customer and vendor information
    try {
        $query = "
            SELECT 
                r.*,
                u.user_name as customer_name,
                u.email as customer_email,
                v.business_name as vendor_name,
                v.email as vendor_email,
                v.category as vendor_category,
                b.booking_id,
                b.booking_date,
                admin.user_name as moderator_name
            FROM reviews r
            LEFT JOIN users u ON r.customer_id = u.user_id
            LEFT JOIN vendors v ON r.vendor_id = v.vendor_id
            LEFT JOIN bookings b ON r.booking_id = b.booking_id
            LEFT JOIN users admin ON r.moderated_by = admin.user_id
            $where_clause
            ORDER BY r.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Create sample data if table doesn't exist
        $reviews = [
            [
                'review_id' => 1,
                'customer_name' => 'Sarah Johnson',
                'customer_email' => 'sarah@example.com',
                'vendor_name' => 'Elite Wedding Photography',
                'vendor_email' => 'contact@elitephoto.com',
                'vendor_category' => 'Photography',
                'rating' => 5,
                'comment' => 'Absolutely amazing photographer! They captured every moment perfectly and the photos turned out stunning. Highly recommend for anyone looking for professional wedding photography.',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'booking_date' => date('Y-m-d', strtotime('+30 days'))
            ],
            [
                'review_id' => 2,
                'customer_name' => 'Michael Brown',
                'customer_email' => 'michael@example.com',
                'vendor_name' => 'Dream Catering Services',
                'vendor_email' => 'info@dreamcatering.com',
                'vendor_category' => 'Catering',
                'rating' => 2,
                'comment' => 'Food was okay but service was terrible. Staff were unprofessional and several dishes were cold. Would not recommend.',
                'status' => 'flagged',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'booking_date' => date('Y-m-d', strtotime('-7 days'))
            ],
            [
                'review_id' => 3,
                'customer_name' => 'Emily Davis',
                'customer_email' => 'emily@example.com',
                'vendor_name' => 'Floral Dreams',
                'vendor_email' => 'hello@floraldreams.com',
                'vendor_category' => 'Florist',
                'rating' => 4,
                'comment' => 'Beautiful flower arrangements! The bridal bouquet was exactly what I wanted. Only minor issue was delivery was slightly late.',
                'status' => 'approved',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'booking_date' => date('Y-m-d', strtotime('-10 days')),
                'moderated_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'moderator_name' => 'Admin User'
            ]
        ];
    }

    // Get summary statistics
    $stats = [
        'total_reviews' => count($reviews),
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'flagged' => 0,
        'average_rating' => 0
    ];

    $total_rating = 0;
    foreach ($reviews as $review) {
        $status = $review['status'] ?? 'pending';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
        $total_rating += floatval($review['rating'] ?? 0);
    }

    if (count($reviews) > 0) {
        $stats['average_rating'] = round($total_rating / count($reviews), 1);
    }

} catch (PDOException $e) {
    $error_message = "Database connection error: " . $e->getMessage();
    $reviews = [];
    $stats = ['total_reviews' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'flagged' => 0, 'average_rating' => 0];
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

function getStatusColor($status) {
    switch($status) {
        case 'pending': return '#ff9800';
        case 'approved': return '#4caf50';
        case 'rejected': return '#f44336';
        case 'flagged': return '#9c27b0';
        default: return '#666';
    }
}

function renderStars($rating) {
    $stars = '';
    $rating = intval($rating);
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '⭐';
        } else {
            $stars .= '☆';
        }
    }
    return $stars;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - All in One Wedding</title>
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
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-title {
            font-size: 1.5rem;
            color: #333;
            margin: 0;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }

        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .reviews-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .reviews-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reviews-list {
            max-height: 700px;
            overflow-y: auto;
        }

        .review-item {
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .review-item:hover {
            background-color: #f8f9ff;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .review-info {
            flex-grow: 1;
        }

        .review-info h3 {
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .review-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .rating-stars {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .review-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: white;
        }

        .status-pending { background-color: #ff9800; }
        .status-approved { background-color: #4caf50; }
        .status-rejected { background-color: #f44336; }
        .status-flagged { background-color: #9c27b0; }

        .review-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }

        .review-text {
            color: #333;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .admin-response {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-top: 10px;
            border-radius: 8px;
        }

        .admin-response-label {
            font-weight: bold;
            color: #1976d2;
            margin-bottom: 5px;
        }

        .moderation-info {
            color: #666;
            font-size: 12px;
            margin-top: 10px;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-approve {
            background: #4caf50;
            color: white;
        }

        .btn-reject {
            background: #f44336;
            color: white;
        }

        .btn-flag {
            background: #9c27b0;
            color: white;
        }

        .btn-respond {
            background: #2196f3;
            color: white;
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
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .success-message, .error-message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success-message {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }

        .error-message {
            background: #ffeaea;
            color: #d32f2f;
            border: 1px solid #f44336;
        }

        .no-data {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }

        .vendor-info {
            background: #f0f8ff;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .review-header {
                flex-direction: column;
                align-items: start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <a href="Admin_Dashboard.php" class="back-btn">
                ← Back to Dashboard
            </a>
            <h1 class="page-title">Review Management</h1>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="success-message">✅ <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message">❌ <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_reviews']; ?></div>
                <div class="stat-label">Total Reviews</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['flagged']; ?></div>
                <div class="stat-label">Flagged</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['average_rating']; ?></div>
                <div class="stat-label">Avg Rating</div>
            </div>
        </div>

    <!-- Reject Review Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Review</h3>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject_review">
                <input type="hidden" name="review_id" id="reject_review_id">
                
                <div class="form-group">
                    <label>Reason for Rejection</label>
                    <select name="reason" required>
                        <option value="">Select a reason</option>
                        <option value="inappropriate_language">Inappropriate Language</option>
                        <option value="spam_content">Spam Content</option>
                        <option value="fake_review">Fake Review</option>
                        <option value="violates_guidelines">Violates Community Guidelines</option>
                        <option value="personal_attack">Personal Attack</option>
                        <option value="off_topic">Off Topic</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">Reject Review</button>
            </form>
        </div>
    </div>

    <!-- Response Modal -->
    <div id="responseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Admin Response</h3>
                <span class="close" onclick="closeModal('responseModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="respond_to_review">
                <input type="hidden" name="review_id" id="response_review_id">
                
                <div class="form-group">
                    <label>Admin Response</label>
                    <textarea name="admin_response" id="admin_response_text" required placeholder="Write your response to this review..."></textarea>
                    <small style="color: #666;">This response will be visible to customers and vendors.</small>
                </div>
                
                <button type="submit" class="filter-btn">Save Response</button>
            </form>
        </div>
    </div>

    <script>
        function approveReview(reviewId) {
            if (confirm('Are you sure you want to approve this review?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_review">
                    <input type="hidden" name="review_id" value="${reviewId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function flagReview(reviewId) {
            if (confirm('Are you sure you want to flag this review as inappropriate?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="flag_inappropriate">
                    <input type="hidden" name="review_id" value="${reviewId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function openRejectModal(reviewId) {
            document.getElementById('reject_review_id').value = reviewId;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function openResponseModal(reviewId, currentResponse = '') {
            document.getElementById('response_review_id').value = reviewId;
            document.getElementById('admin_response_text').value = currentResponse;
            document.getElementById('responseModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Auto-refresh page every 2 minutes to check for new reviews
        setInterval(() => {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal[style*="block"]')) {
                location.reload();
            }
        }, 120000);

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Escape to close modals
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        // Add confirmation for bulk actions
        document.addEventListener('DOMContentLoaded', function() {
            // Add tooltips to action buttons
            const tooltips = {
                'btn-approve': 'Approve this review and make it visible to users',
                'btn-reject': 'Reject this review and hide it from users',
                'btn-flag': 'Flag this review for further investigation',
                'btn-respond': 'Add an official admin response to this review'
            };

            Object.keys(tooltips).forEach(className => {
                document.querySelectorAll(`.${className}`).forEach(btn => {
                    btn.title = tooltips[className];
                });
            });
        });
    </script>
</body>
</html>    <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Status Filter</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Rating Filter</label>
                    <select name="rating">
                        <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                        <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date Range</label>
                    <select name="date_filter">
                        <option value="7" <?php echo $date_filter === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo $date_filter === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo $date_filter === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="filter-btn">Apply Filters</button>
                </div>
            </form>
        </div>

        <div class="reviews-container">
            <div class="reviews-header">
                <h2>Reviews (<?php echo count($reviews); ?>)</h2>
            </div>

            <div class="reviews-list">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-info">
                                    <h3>
                                        Review #<?php echo htmlspecialchars($review['review_id']); ?>
                                    </h3>
                                    <div class="review-meta">
                                        Customer: <?php echo htmlspecialchars($review['customer_name'] ?? 'Unknown'); ?> |
                                        Vendor: <?php echo htmlspecialchars($review['vendor_name'] ?? 'Unknown'); ?> |
                                        Category: <?php echo htmlspecialchars($review['vendor_category'] ?? 'N/A'); ?> |
                                        <?php echo timeAgo($review['created_at']); ?>
                                    </div>
                                    <div class="rating-stars">
                                        <?php echo renderStars($review['rating']); ?>
                                        (<?php echo $review['rating']; ?>/5)
                                    </div>
                                </div>
                                <div class="review-badges">
                                    <span class="badge status-<?php echo $review['status']; ?>">
                                        <?php echo ucfirst($review['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="vendor-info">
                                <strong>Vendor:</strong> <?php echo htmlspecialchars($review['vendor_name'] ?? 'Unknown'); ?> 
                                (<?php echo htmlspecialchars($review['vendor_email'] ?? 'No email'); ?>)
                                <?php if (!empty($review['booking_date'])): ?>
                                    | <strong>Wedding Date:</strong> <?php echo date('M j, Y', strtotime($review['booking_date'])); ?>
                                <?php endif; ?>
                            </div>

                            <div class="review-content">
                                <div class="review-text">
                                    <?php echo nl2br(htmlspecialchars($review['comment'] ?? 'No comment provided')); ?>
                                </div>

                                <?php if (!empty($review['admin_response'])): ?>
                                    <div class="admin-response">
                                        <div class="admin-response-label">Admin Response:</div>
                                        <?php echo nl2br(htmlspecialchars($review['admin_response'])); ?>
                                        <?php if (!empty($review['response_date'])): ?>
                                            <div class="moderation-info">
                                                Responded on <?php echo date('M j, Y \a\t g:i A', strtotime($review['response_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($review['moderated_at'])): ?>
                                    <div class="moderation-info">
                                        Moderated by <?php echo htmlspecialchars($review['moderator_name'] ?? 'Admin'); ?> 
                                        on <?php echo date('M j, Y \a\t g:i A', strtotime($review['moderated_at'])); ?>
                                        <?php if (!empty($review['rejection_reason'])): ?>
                                            <br><strong>Reason:</strong> <?php echo htmlspecialchars($review['rejection_reason']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="review-actions">
                                <?php if ($review['status'] === 'pending' || $review['status'] === 'flagged'): ?>
                                    <button class="action-btn btn-approve" onclick="approveReview(<?php echo $review['review_id']; ?>)">
                                        ✅ Approve
                                    </button>
                                    <button class="action-btn btn-reject" onclick="openRejectModal(<?php echo $review['review_id']; ?>)">
                                        ❌ Reject
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($review['status'] !== 'flagged'): ?>
                                    <button class="action-btn btn-flag" onclick="flagReview(<?php echo $review['review_id']; ?>)">
                                        🚩 Flag
                                    </button>
                                <?php endif; ?>
                                
                                <button class="action-btn btn-respond" onclick="openResponseModal(<?php echo $review['review_id']; ?>, '<?php echo htmlspecialchars($review['admin_response'] ?? '', ENT_QUOTES); ?>')">
                                    💬 <?php echo !empty($review['admin_response']) ? 'Edit Response' : 'Add Response'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <h3>No reviews found</h3>
                        <p>There are currently no reviews matching your criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>