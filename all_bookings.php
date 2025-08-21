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
    
    // Handle status updates
    if ($_POST['action'] ?? '' === 'update_status') {
        $booking_id = $_POST['booking_id'] ?? 0;
        $new_status = $_POST['new_status'] ?? '';
        
        if ($booking_id && $new_status) {
            $stmt = $pdo->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE booking_id = ?");
            $stmt->execute([$new_status, $booking_id]);
            $success_message = "Booking status updated successfully!";
        }
    }
    
    // Get filter parameters
    $status_filter = $_GET['status'] ?? '';
    $vendor_filter = $_GET['vendor'] ?? '';
    $customer_filter = $_GET['customer'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build query with filters
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "b.status = ?";
        $params[] = $status_filter;
    }
    
    if ($vendor_filter) {
        $where_conditions[] = "v.business_name LIKE ?";
        $params[] = "%$vendor_filter%";
    }
    
    if ($customer_filter) {
        $where_conditions[] = "u.user_name LIKE ?";
        $params[] = "%$customer_filter%";
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(b.event_date) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(b.event_date) <= ?";
        $params[] = $date_to;
    }
    
    if ($search) {
        $where_conditions[] = "(b.booking_id LIKE ? OR u.user_name LIKE ? OR v.business_name LIKE ? OR b.event_location LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get bookings with pagination
    $page = $_GET['page'] ?? 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $bookings_query = "
        SELECT 
            b.*,
            u.user_name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone,
            v.business_name,
            v.vendor_id,
            v.category,
            COALESCE(b.total_amount, b.amount, 0) as booking_amount
        FROM bookings b 
        LEFT JOIN users u ON b.customer_id = u.user_id 
        LEFT JOIN vendors v ON b.vendor_id = v.vendor_id 
        $where_clause
        ORDER BY b.created_at DESC 
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($bookings_query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) 
        FROM bookings b 
        LEFT JOIN users u ON b.customer_id = u.user_id 
        LEFT JOIN vendors v ON b.vendor_id = v.vendor_id 
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_bookings = $count_stmt->fetchColumn();
    $total_pages = ceil($total_bookings / $per_page);
    
    // Get statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            COALESCE(SUM(CASE WHEN status IN ('confirmed', 'completed') THEN COALESCE(total_amount, amount, 0) ELSE 0 END), 0) as total_revenue
        FROM bookings b
    ";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $bookings = [];
    $stats = [
        'total_bookings' => 0,
        'confirmed_bookings' => 0,
        'pending_bookings' => 0,
        'cancelled_bookings' => 0,
        'completed_bookings' => 0,
        'total_revenue' => 0
    ];
}

function formatCurrency($amount) {
    return '$' . number_format(floatval($amount), 2);
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge status-pending">Pending</span>',
        'confirmed' => '<span class="status-badge status-confirmed">Confirmed</span>',
        'completed' => '<span class="status-badge status-completed">Completed</span>',
        'cancelled' => '<span class="status-badge status-cancelled">Cancelled</span>',
        'refunded' => '<span class="status-badge status-refunded">Refunded</span>'
    ];
    
    return $badges[$status] ?? '<span class="status-badge status-unknown">' . ucfirst($status) . '</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - Admin Dashboard</title>
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
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
        }

        .back-btn:hover {
            transform: translateY(-2px);
        }

        .page-title {
            font-size: 1.5rem;
            color: #333;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
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
            font-size: 1.8rem;
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .bookings-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.3rem;
            color: #333;
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .bookings-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .bookings-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-refunded {
            background: #e2e3e5;
            color: #383d41;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 5px;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: white;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination a:hover {
            background: #f5f5f5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            margin: 10% auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.3rem;
            color: #333;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-bookings {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
            }
            
            .container {
                padding: 0 20px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .bookings-table {
                font-size: 14px;
            }
            
            .bookings-table th,
            .bookings-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <a href="Admin_Dashboard.php" class="back-btn">← Back to Dashboard</a>
            <h1 class="page-title">All Bookings Management</h1>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            ✅ <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            ❌ <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_bookings']); ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['confirmed_bookings']); ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['pending_bookings']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['cancelled_bookings']); ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['completed_bookings']); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Booking ID, customer, vendor...">
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="vendor">Vendor</label>
                        <input type="text" id="vendor" name="vendor" value="<?php echo htmlspecialchars($vendor_filter); ?>" placeholder="Vendor name">
                    </div>
                    <div class="filter-group">
                        <label for="customer">Customer</label>
                        <input type="text" id="customer" name="customer" value="<?php echo htmlspecialchars($customer_filter); ?>" placeholder="Customer name">
                    </div>
                    <div class="filter-group">
                        <label for="date_from">Event Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">Event Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="all_bookings.php" class="btn btn-secondary">🔄 Clear</a>
                </div>
            </form>
        </div>

        <div class="bookings-table-container">
            <div class="table-header">
                <h3 class="table-title">Booking Records (<?php echo number_format($total_bookings); ?> total)</h3>
                <button onclick="exportBookings()" class="btn btn-secondary">📊 Export CSV</button>
            </div>
            
            <?php if (!empty($bookings)): ?>
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Vendor</th>
                        <th>Event Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td>#<?php echo $booking['booking_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($booking['customer_name'] ?? 'Unknown'); ?></strong><br>
                            <small><?php echo htmlspecialchars($booking['customer_email'] ?? ''); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($booking['business_name'] ?? 'Unknown Vendor'); ?></strong><br>
                            <small><?php echo htmlspecialchars($booking['category'] ?? ''); ?></small>
                        </td>
                        <td>
                            <?php echo $booking['event_date'] ? date('M j, Y', strtotime($booking['event_date'])) : 'Not set'; ?>
                            <?php if ($booking['event_location']): ?>
                                <br><small><?php echo htmlspecialchars($booking['event_location']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formatCurrency($booking['booking_amount']); ?></td>
                        <td><?php echo getStatusBadge($booking['status']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-small btn-view" onclick="viewBooking(<?php echo $booking['booking_id']; ?>)">View</button>
                                <button class="btn btn-small btn-edit" onclick="editBookingStatus(<?php echo $booking['booking_id']; ?>, '<?php echo $booking['status']; ?>')">Edit Status</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-bookings">
                <h3>No bookings found</h3>
                <p>No bookings match your current filters. Try adjusting your search criteria.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">← Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div id="editStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Booking Status</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="booking_id" id="modalBookingId">
                
                <div class="form-group">
                    <label for="modalStatus">New Status</label>
                    <select name="new_status" id="modalStatus" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editBookingStatus(bookingId, currentStatus) {
            document.getElementById('modalBookingId').value = bookingId;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('editStatusModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editStatusModal').style.display = 'none';
        }
        
        function viewBooking(bookingId) {
            alert('View booking details for booking #' + bookingId + '\n\n(This would open a detailed view in a real application)');
        }
        
        function exportBookings() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('export', 'csv');
            window.location.href = currentUrl.toString();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editStatusModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>