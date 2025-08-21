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
                case 'resolve_payment':
                    $payment_id = $_POST['payment_id'];
                    $resolution = $_POST['resolution'];
                    $admin_notes = $_POST['admin_notes'];
                    
                    $stmt = $pdo->prepare("UPDATE payment_issues SET status = 'resolved', resolution = ?, admin_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE issue_id = ?");
                    $stmt->execute([$resolution, $admin_notes, $_SESSION['user_id'], $payment_id]);
                    
                    $success_message = "Payment issue resolved successfully!";
                    break;
                    
                case 'update_status':
                    $payment_id = $_POST['payment_id'];
                    $new_status = $_POST['new_status'];
                    
                    $stmt = $pdo->prepare("UPDATE payment_issues SET status = ? WHERE issue_id = ?");
                    $stmt->execute([$new_status, $payment_id]);
                    
                    $success_message = "Payment status updated successfully!";
                    break;
                    
                case 'add_note':
                    $payment_id = $_POST['payment_id'];
                    $note = $_POST['note'];
                    
                    $stmt = $pdo->prepare("UPDATE payment_issues SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[' || NOW() || '] ' || ?) WHERE issue_id = ?");
                    $stmt->execute([$note, $payment_id]);
                    
                    $success_message = "Note added successfully!";
                    break;
            }
        }
    }

    // Get filter parameters
    $status_filter = $_GET['status'] ?? 'all';
    $priority_filter = $_GET['priority'] ?? 'all';
    $date_filter = $_GET['date_filter'] ?? '30';

    // Build query based on filters
    $where_conditions = [];
    $params = [];

    if ($status_filter !== 'all') {
        $where_conditions[] = "pi.status = ?";
        $params[] = $status_filter;
    }

    if ($priority_filter !== 'all') {
        $where_conditions[] = "pi.priority = ?";
        $params[] = $priority_filter;
    }

    if ($date_filter !== 'all') {
        $where_conditions[] = "pi.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = intval($date_filter);
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get payment issues with related booking and user information
    try {
        $query = "
            SELECT 
                pi.*,
                b.booking_id,
                b.total_amount as booking_amount,
                b.booking_date,
                u.user_name as customer_name,
                u.email as customer_email,
                v.business_name as vendor_name,
                v.email as vendor_email
            FROM payment_issues pi
            LEFT JOIN bookings b ON pi.booking_id = b.booking_id
            LEFT JOIN users u ON b.customer_id = u.user_id
            LEFT JOIN vendors v ON b.vendor_id = v.vendor_id
            $where_clause
            ORDER BY 
                CASE pi.priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                pi.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $payment_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Create sample data if table doesn't exist
        $payment_issues = [
            [
                'issue_id' => 1,
                'booking_id' => 'BK001',
                'issue_type' => 'failed_payment',
                'description' => 'Credit card payment failed due to insufficient funds',
                'amount' => 2500.00,
                'status' => 'pending',
                'priority' => 'high',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'customer_name' => 'Sarah Johnson',
                'customer_email' => 'sarah@example.com',
                'vendor_name' => 'Elite Wedding Photography',
                'booking_amount' => 2500.00
            ],
            [
                'issue_id' => 2,
                'booking_id' => 'BK002',
                'issue_type' => 'refund_request',
                'description' => 'Customer requesting refund due to vendor cancellation',
                'amount' => 1800.00,
                'status' => 'in_progress',
                'priority' => 'medium',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'customer_name' => 'Michael Brown',
                'customer_email' => 'michael@example.com',
                'vendor_name' => 'Dream Catering Services',
                'booking_amount' => 1800.00
            ]
        ];
    }

    // Get summary statistics
    $stats = [
        'total_issues' => count($payment_issues),
        'pending' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'total_amount' => 0
    ];

    foreach ($payment_issues as $issue) {
        $stats[$issue['status']]++;
        $stats['total_amount'] += floatval($issue['amount'] ?? 0);
    }

} catch (PDOException $e) {
    $error_message = "Database connection error: " . $e->getMessage();
    $payment_issues = [];
    $stats = ['total_issues' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'total_amount' => 0];
}

function formatCurrency($amount) {
    return '$' . number_format(floatval($amount), 2);
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

function getPriorityColor($priority) {
    switch($priority) {
        case 'high': return '#ff4444';
        case 'medium': return '#ff9800';
        case 'low': return '#4caf50';
        default: return '#666';
    }
}

function getStatusColor($status) {
    switch($status) {
        case 'pending': return '#ff9800';
        case 'in_progress': return '#2196f3';
        case 'resolved': return '#4caf50';
        default: return '#666';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Issues Management - All in One Wedding</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .issues-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .issues-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .issues-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .issue-item {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .issue-item:hover {
            background-color: #f8f9ff;
        }

        .issue-item:last-child {
            border-bottom: none;
        }

        .issue-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .issue-info h3 {
            color: #333;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .issue-meta {
            color: #666;
            font-size: 14px;
        }

        .issue-badges {
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

        .priority-high { background-color: #ff4444; }
        .priority-medium { background-color: #ff9800; }
        .priority-low { background-color: #4caf50; }
        
        .status-pending { background-color: #ff9800; }
        .status-in_progress { background-color: #2196f3; }
        .status-resolved { background-color: #4caf50; }

        .issue-details {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .issue-actions {
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

        .btn-resolve {
            background: #4caf50;
            color: white;
        }

        .btn-update {
            background: #2196f3;
            color: white;
        }

        .btn-note {
            background: #ff9800;
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
            
            .issue-header {
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
            <h1 class="page-title">Payment Issues Management</h1>
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
                <div class="stat-number"><?php echo $stats['total_issues']; ?></div>
                <div class="stat-label">Total Issues</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo formatCurrency($stats['total_amount']); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
        </div>

        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Status Filter</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Priority Filter</label>
                    <select name="priority">
                        <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
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

        <div class="issues-container">
            <div class="issues-header">
                <h2>Payment Issues (<?php echo count($payment_issues); ?>)</h2>
            </div>

            <div class="issues-list">
                <?php if (!empty($payment_issues)): ?>
                    <?php foreach ($payment_issues as $issue): ?>
                        <div class="issue-item">
                            <div class="issue-header">
                                <div class="issue-info">
                                    <h3>
                                        Issue #<?php echo htmlspecialchars($issue['issue_id']); ?>
                                        - <?php echo formatCurrency($issue['amount'] ?? 0); ?>
                                    </h3>
                                    <div class="issue-meta">
                                        Booking: <?php echo htmlspecialchars($issue['booking_id'] ?? 'N/A'); ?> | 
                                        Customer: <?php echo htmlspecialchars($issue['customer_name'] ?? 'Unknown'); ?> |
                                        Vendor: <?php echo htmlspecialchars($issue['vendor_name'] ?? 'Unknown'); ?> |
                                        <?php echo timeAgo($issue['created_at']); ?>
                                    </div>
                                </div>
                                <div class="issue-badges">
                                    <span class="badge priority-<?php echo $issue['priority']; ?>">
                                        <?php echo ucfirst($issue['priority']); ?>
                                    </span>
                                    <span class="badge status-<?php echo $issue['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $issue['status'])); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="issue-details">
                                <strong>Issue Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $issue['issue_type'] ?? 'general')); ?><br>
                                <strong>Description:</strong> <?php echo htmlspecialchars($issue['description'] ?? 'No description provided'); ?>
                                
                                <?php if (!empty($issue['admin_notes'])): ?>
                                    <br><br><strong>Admin Notes:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($issue['admin_notes'])); ?>
                                <?php endif; ?>
                            </div>

                            <div class="issue-actions">
                                <?php if ($issue['status'] !== 'resolved'): ?>
                                    <button class="action-btn btn-resolve" onclick="openResolveModal(<?php echo $issue['issue_id']; ?>)">
                                        ✅ Resolve
                                    </button>
                                    <button class="action-btn btn-update" onclick="openStatusModal(<?php echo $issue['issue_id']; ?>)">
                                        🔄 Update Status
                                    </button>
                                <?php endif; ?>
                                <button class="action-btn btn-note" onclick="openNoteModal(<?php echo $issue['issue_id']; ?>)">
                                    📝 Add Note
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <h3>No payment issues found</h3>
                        <p>There are currently no payment issues matching your criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resolve Issue Modal -->
    <div id="resolveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Resolve Payment Issue</h3>
                <span class="close" onclick="closeModal('resolveModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="resolve_payment">
                <input type="hidden" name="payment_id" id="resolve_payment_id">
                
                <div class="form-group">
                    <label>Resolution</label>
                    <select name="resolution" required>
                        <option value="">Select Resolution</option>
                        <option value="payment_processed">Payment Successfully Processed</option>
                        <option value="refund_issued">Refund Issued</option>
                        <option value="payment_reattempted">Payment Re-attempted Successfully</option>
                        <option value="dispute_resolved">Dispute Resolved</option>
                        <option value="manual_adjustment">Manual Adjustment Made</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Admin Notes</label>
                    <textarea name="admin_notes" placeholder="Add detailed notes about the resolution..."></textarea>
                </div>
                
                <button type="submit" class="filter-btn">Resolve Issue</button>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="payment_id" id="status_payment_id">
                
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" required>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn">Update Status</button>
            </form>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Note</h3>
                <span class="close" onclick="closeModal('noteModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="payment_id" id="note_payment_id">
                
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="note" required placeholder="Add your note here..."></textarea>
                </div>
                
                <button type="submit" class="filter-btn">Add Note</button>
            </form>
        </div>
    </div>

    <script>
        function openResolveModal(paymentId) {
            document.getElementById('resolve_payment_id').value = paymentId;
            document.getElementById('resolveModal').style.display = 'block';
        }

        function openStatusModal(paymentId) {
            document.getElementById('status_payment_id').value = paymentId;
            document.getElementById('statusModal').style.display = 'block';
        }

        function openNoteModal(paymentId) {
            document.getElementById('note_payment_id').value = paymentId;
            document.getElementById('noteModal').style.display = 'block';
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
    </script>
</body>
</html>