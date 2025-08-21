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
    
    // Get filter parameters
    $status_filter = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Build query conditions
    $where_conditions = ["u.user_type = 'vendor'"];
    $params = [];
    
    if ($status_filter !== 'all') {
        if ($status_filter === 'pending') {
            $where_conditions[] = "(u.status = 'pending' OR v.status = 'pending')";
        } else {
            $where_conditions[] = "u.status = ?";
            $params[] = $status_filter;
        }
    }
    
    if ($search) {
        $where_conditions[] = "(u.user_name LIKE ? OR u.email LIKE ? OR v.business_name LIKE ? OR v.business_type LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(u.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(u.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get applications with pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 15;
    $offset = ($page - 1) * $limit;
    
    $count_query = "
        SELECT COUNT(*) 
        FROM users u
        LEFT JOIN vendors v ON u.user_id = v.user_id
        $where_clause
    ";
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_applications = $stmt->fetchColumn();
    $total_pages = ceil($total_applications / $limit);
    
    $query = "
        SELECT 
            u.user_id,
            u.user_name,
            u.email,
            u.phone,
            u.status as user_status,
            u.created_at,
            v.business_name,
            v.business_type,
            v.business_description,
            v.city,
            v.state,
            v.years_experience,
            v.status as vendor_status,
            v.admin_notes,
            v.approved_at,
            v.approved_by,
            v.reviewed_at,
            v.reviewed_by,
            admin.user_name as reviewed_by_name
        FROM users u
        LEFT JOIN vendors v ON u.user_id = v.user_id
        LEFT JOIN users admin ON v.reviewed_by = admin.user_id OR v.approved_by = admin.user_id
        $where_clause
        ORDER BY u.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $stats_query = "
        SELECT 
            COUNT(CASE WHEN u.status = 'pending' OR v.status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN u.status = 'active' THEN 1 END) as approved,
            COUNT(CASE WHEN u.status = 'rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN v.status = 'info_requested' THEN 1 END) as info_requested,
            COUNT(CASE WHEN u.status = 'suspended' THEN 1 END) as suspended
        FROM users u
        LEFT JOIN vendors v ON u.user_id = v.user_id
        WHERE u.user_type = 'vendor'
    ";
    
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $applications = [];
    $total_applications = 0;
    $total_pages = 1;
    $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'info_requested' => 0, 'suspended' => 0];
}

function timeAgo($datetime) {
    if (empty($datetime)) return 'Not set';
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hrs ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}

function getStatusColor($status) {
    switch ($status) {
        case 'active': return '#4caf50';
        case 'pending': return '#ff9800';
        case 'rejected': return '#f44336';
        case 'suspended': return '#9c27b0';
        case 'info_requested': return '#2196f3';
        default: return '#666';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Applications - Admin Dashboard</title>
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-2px);
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

        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .filters-row {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }

        .filter-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            height: 42px;
        }

        .applications-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .applications-table {
            width: 100%;
            border-collapse: collapse;
        }

        .applications-table th,
        .applications-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .applications-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .applications-table tr:hover {
            background: #f8f9fa;
        }

        .vendor-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .vendor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .vendor-details h4 {
            color: #333;
            margin-bottom: 3px;
            font-size: 16px;
        }

        .vendor-details p {
            color: #666;
            font-size: 13px;
            margin: 2px 0;
        }

        .business-info {
            max-width: 200px;
        }

        .business-info h5 {
            color: #333;
            margin-bottom: 3px;
            font-size: 14px;
        }

        .business-info p {
            color: #666;
            font-size: 12px;
            margin: 1px 0;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: transform 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .btn-view {
            background: #2196f3;
            color: white;
        }

        .btn-approve {
            background: #4caf50;
            color: white;
        }

        .btn-reject {
            background: #f44336;
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 5px;
            color: #667eea;
            font-weight: 500;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .current {
            background: #667eea;
            color: white;
        }

        .no-applications {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-applications h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .export-btn {
            background: #4caf50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .admin-notes {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            color: #666;
        }

        .admin-notes:hover {
            white-space: normal;
            overflow: visible;
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

        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }
            
            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group select,
            .filter-group input {
                min-width: 100%;
            }
            
            .applications-container {
                overflow-x: auto;
            }
            
            .applications-table {
                min-width: 800px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 3px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <a href="Admin_Dashboard.php" class="back-btn">← Back to Dashboard</a>
            <h1 class="page-title">Vendor Applications</h1>
        </div>
        <a href="#" class="export-btn" onclick="exportApplications()">📊 Export Data</a>
    </nav>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stats-card" onclick="filterByStatus('pending')" style="cursor: pointer;">
                <div class="stats-number" style="color: #ff9800;"><?php echo $stats['pending']; ?></div>
                <div class="stats-label">Pending Review</div>
            </div>
            <div class="stats-card" onclick="filterByStatus('active')" style="cursor: pointer;">
                <div class="stats-number" style="color: #4caf50;"><?php echo $stats['approved']; ?></div>
                <div class="stats-label">Approved</div>
            </div>
            <div class="stats-card" onclick="filterByStatus('rejected')" style="cursor: pointer;">
                <div class="stats-number" style="color: #f44336;"><?php echo $stats['rejected']; ?></div>
                <div class="stats-label">Rejected</div>
            </div>
            <div class="stats-card" onclick="filterByStatus('info_requested')" style="cursor: pointer;">
                <div class="stats-number" style="color: #2196f3;"><?php echo $stats['info_requested']; ?></div>
                <div class="stats-label">Info Requested</div>
            </div>
            <div class="stats-card" onclick="filterByStatus('suspended')" style="cursor: pointer;">
                <div class="stats-number" style="color: #9c27b0;"><?php echo $stats['suspended']; ?></div>
                <div class="stats-label">Suspended</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" id="statusFilter">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="info_requested" <?php echo $status_filter === 'info_requested' ? 'selected' : ''; ?>>Info Requested</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Business name, email, type..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="filter-btn">🔍 Filter</button>
                <a href="vendor_applications.php" class="filter-btn" style="background: #666; text-decoration: none; display: inline-flex; align-items: center;">Clear</a>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="applications-container">
            <div class="table-header">
                <h2>Vendor Applications (<?php echo number_format($total_applications); ?> total)</h2>
            </div>

            <?php if (!empty($applications)): ?>
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Business Details</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>Last Action</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <div class="vendor-info">
                                        <div class="vendor-avatar">
                                            <?php echo strtoupper(substr($app['user_name'], 0, 2)); ?>
                                        </div>
                                        <div class="vendor-details">
                                            <h4><?php echo htmlspecialchars($app['user_name']); ?></h4>
                                            <p>ID: <?php echo $app['user_id']; ?></p>
                                            <?php if ($app['years_experience']): ?>
                                                <p><?php echo $app['years_experience']; ?> years exp.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="business-info">
                                        <h5><?php echo htmlspecialchars($app['business_name'] ?: 'Not provided'); ?></h5>
                                        <p><strong>Type:</strong> <?php echo htmlspecialchars($app['business_type'] ?: 'Not specified'); ?></p>
                                        <?php if ($app['city'] && $app['state']): ?>
                                            <p><strong>Location:</strong> <?php echo htmlspecialchars($app['city'] . ', ' . $app['state']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($app['business_description']): ?>
                                            <p title="<?php echo htmlspecialchars($app['business_description']); ?>">
                                                <strong>Description:</strong> <?php echo htmlspecialchars(substr($app['business_description'], 0, 50)) . (strlen($app['business_description']) > 50 ? '...' : ''); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></p>
                                        <?php if ($app['phone']): ?>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($app['phone']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $status = $app['vendor_status'] ?: $app['user_status'];
                                    $status_color = getStatusColor($status);
                                    ?>
                                    <span class="status-badge" style="background-color: <?php echo $status_color; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                    </span>
                                    <?php if ($app['admin_notes']): ?>
                                        <div class="admin-notes" title="<?php echo htmlspecialchars($app['admin_notes']); ?>">
                                            📝 <?php echo htmlspecialchars(substr($app['admin_notes'], 0, 30)) . '...'; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                                        <br>
                                        <small style="color: #666;"><?php echo timeAgo($app['created_at']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($app['approved_at']): ?>
                                        <div>
                                            <small style="color: #4caf50;">✅ Approved</small><br>
                                            <small><?php echo timeAgo($app['approved_at']); ?></small>
                                            <?php if ($app['reviewed_by_name']): ?>
                                                <br><small>by <?php echo htmlspecialchars($app['reviewed_by_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($app['reviewed_at']): ?>
                                        <div>
                                            <small style="color: #ff9800;">📝 Reviewed</small><br>
                                            <small><?php echo timeAgo($app['reviewed_at']); ?></small>
                                            <?php if ($app['reviewed_by_name']): ?>
                                                <br><small>by <?php echo htmlspecialchars($app['reviewed_by_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <small style="color: #999;">No action yet</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="vendor_details.php?id=<?php echo $app['user_id']; ?>" class="action-btn btn-view">
                                            👁 View Details
                                        </a>
                                        <?php if ($status === 'pending' || $status === 'info_requested'): ?>
                                            <a href="approve_vendors.php#vendor_<?php echo $app['user_id']; ?>" class="action-btn btn-approve">
                                                ✅ Review
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status === 'active'): ?>
                                            <a href="manage_users.php?filter=vendor&search=<?php echo urlencode($app['email']); ?>" class="action-btn" style="background: #9c27b0; color: white;">
                                                ⚙ Manage
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">← Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-applications">
                    <h3>No applications found</h3>
                    <p>No vendor applications match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Filter by status when clicking stats cards
        function filterByStatus(status) {
            const statusSelect = document.getElementById('statusFilter');
            statusSelect.value = status;
            statusSelect.form.submit();
        }

        // Auto-submit form when filters change
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Export functionality
        function exportApplications() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'export_vendor_applications.php?' + params.toString();
        }

        // Enhanced tooltip for truncated content
        document.querySelectorAll('.admin-notes, .business-info p[title]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                if (this.scrollWidth > this.clientWidth || this.getAttribute('title')) {
                    this.style.position = 'relative';
                    this.style.zIndex = '1000';
                }
            });
        });
    </script>
</body>
</html>