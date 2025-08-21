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
    
    // Handle actions
    if ($_POST['action'] ?? false) {
        $action = $_POST['action'];
        $user_id = $_POST['user_id'] ?? 0;
        
        switch ($action) {
            case 'suspend':
                $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $message = "User suspended successfully";
                break;
                
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $message = "User activated successfully";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $message = "User deleted successfully";
                break;
        }
    }
    
    // Get filter parameters
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? 'all';
    
    // Build query
    $where_conditions = [];
    $params = [];
    
    if ($filter !== 'all') {
        $where_conditions[] = "user_type = ?";
        $params[] = $filter;
    }
    
    if ($search) {
        $where_conditions[] = "(user_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get users with pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $count_query = "SELECT COUNT(*) FROM users $where_clause";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $limit);
    
    $query = "SELECT user_id, user_name, email, phone, user_type, status, created_at, last_login 
              FROM users $where_clause 
              ORDER BY created_at DESC 
              LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary stats
    $stats = [];
    $stats_query = "SELECT user_type, status, COUNT(*) as count FROM users GROUP BY user_type, status";
    $stmt = $pdo->query($stats_query);
    $stats_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats_data as $row) {
        $stats[$row['user_type']][$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $users = [];
    $total_users = 0;
    $total_pages = 1;
}

function timeAgo($datetime) {
    if (empty($datetime)) return 'Never';
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
    <title>Manage Users - Admin Dashboard</title>
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

        .users-table-container {
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

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background: #e8f5e8;
            color: #4caf50;
        }

        .status-suspended {
            background: #ffeaea;
            color: #f44336;
        }

        .status-pending {
            background: #fff3e0;
            color: #ff9800;
        }

        .user-type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .type-customer {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-vendor {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .type-admin {
            background: #e8f5e8;
            color: #388e3c;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: transform 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .btn-suspend {
            background: #ff9800;
            color: white;
        }

        .btn-activate {
            background: #4caf50;
            color: white;
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-view {
            background: #2196f3;
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

        .no-users {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        @media (max-width: 768px) {
            .filters-row {
                flex-direction: column;
            }
            
            .filter-group select,
            .filter-group input {
                min-width: 100%;
            }
            
            .users-table-container {
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <a href="Admin_Dashboard.php" class="back-btn">← Back to Dashboard</a>
            <h1 class="page-title">Manage Users</h1>
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
                <div class="stats-number"><?php echo array_sum($stats['customer'] ?? [0]); ?></div>
                <div class="stats-label">Total Customers</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo array_sum($stats['vendor'] ?? [0]); ?></div>
                <div class="stats-label">Total Vendors</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo ($stats['customer']['active'] ?? 0) + ($stats['vendor']['active'] ?? 0); ?></div>
                <div class="stats-label">Active Users</div>
            </div>
            <div class="stats-card">
                <div class="stats-number"><?php echo ($stats['customer']['suspended'] ?? 0) + ($stats['vendor']['suspended'] ?? 0); ?></div>
                <div class="stats-label">Suspended Users</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>User Type</label>
                    <select name="filter">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="customer" <?php echo $filter === 'customer' ? 'selected' : ''; ?>>Customers</option>
                        <option value="vendor" <?php echo $filter === 'vendor' ? 'selected' : ''; ?>>Vendors</option>
                        <option value="admin" <?php echo $filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="filter-btn">🔍 Filter</button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <div class="table-header">
                <h2>Users (<?php echo number_format($total_users); ?> total)</h2>
            </div>

            <?php if (!empty($users)): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['user_name']); ?></strong>
                                        <br>
                                        <small style="color: #666;">ID: <?php echo $user['user_id']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                        <?php if ($user['phone']): ?>
                                            <br><small><?php echo htmlspecialchars($user['phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="user-type-badge type-<?php echo $user['user_type']; ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo $user['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td><?php echo timeAgo($user['last_login']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Suspend this user?')">
                                                <input type="hidden" name="action" value="suspend">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" class="action-btn btn-suspend">⏸ Suspend</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" class="action-btn btn-activate">▶ Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="user_details.php?id=<?php echo $user['user_id']; ?>" class="action-btn btn-view">👁 View</a>
                                        
                                        <?php if ($user['user_type'] !== 'admin'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user? This action cannot be undone!')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" class="action-btn btn-delete">🗑 Delete</button>
                                            </form>
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
                            <a href="?page=<?php echo $page-1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">← Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-users">
                    <h3>No users found</h3>
                    <p>Try adjusting your filters or check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit form when filters change
        document.querySelectorAll('select[name="filter"], select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Confirm actions
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;
                let message = '';
                
                switch(action) {
                    case 'suspend':
                        message = 'Are you sure you want to suspend this user?';
                        break;
                    case 'delete':
                        message = 'Are you sure you want to delete this user? This action cannot be undone!';
                        break;
                }
                
                if (message && !confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>