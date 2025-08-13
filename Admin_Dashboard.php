<?php
require_once 'config.php'; // Include your database config
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Admin';

try {
    // Initialize stats array
    $stats = [
        'customers' => 0,
        'vendors' => 0,
        'weddings' => 0,
        'revenue' => 0
    ];
    
    // Check if tables exist and fetch data accordingly
    
    // Total Customers
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    // Active Vendors
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor' AND status = 'active'");
        $stats['vendors'] = $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        // Try alternative approaches
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor'");
            $stats['vendors'] = $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e2) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM vendors");
                $stats['vendors'] = $stmt->fetchColumn() ?: 0;
            } catch (PDOException $e3) {
                // Keep default 0
            }
        }
    }
    
    // Active Weddings/Bookings
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed', 'active', 'booked')");
        $stats['weddings'] = $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
            $stats['weddings'] = $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e2) {
            // Keep default 0
        }
    }
    
    // Monthly Revenue
    try {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM bookings 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE()) 
            AND status IN ('confirmed', 'completed', 'paid')
        ");
        $stats['revenue'] = $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        try {
            $stmt = $pdo->query("
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM bookings 
                WHERE MONTH(booking_date) = MONTH(CURRENT_DATE()) 
                AND YEAR(booking_date) = YEAR(CURRENT_DATE())
            ");
            $stats['revenue'] = $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e2) {
            // Keep default 0
        }
    }
    
    // Recent Activity
    $activities = [];
    
    // Try to get recent vendor registrations
    try {
        $stmt = $pdo->query("
            SELECT 'vendor_registration' as type, 
                   user_name, 
                   created_at,
                   user_id
            FROM users 
            WHERE user_type = 'vendor' 
            ORDER BY created_at DESC 
            LIMIT 3
        ");
        $vendor_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $activities = array_merge($activities, $vendor_registrations);
    } catch (PDOException $e) {
        // Table structure might be different
    }
    
    // Try to get recent bookings
    try {
        $stmt = $pdo->query("
            SELECT 'booking' as type,
                   b.*,
                   u.user_name as customer_name,
                   v.business_name as vendor_name
            FROM bookings b 
            LEFT JOIN users u ON b.customer_id = u.user_id 
            LEFT JOIN vendors v ON b.vendor_id = v.vendor_id 
            ORDER BY b.created_at DESC 
            LIMIT 3
        ");
        $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $activities = array_merge($activities, $recent_bookings);
    } catch (PDOException $e) {
        // Try simpler query
        try {
            $stmt = $pdo->query("
                SELECT 'booking' as type,
                       booking_id,
                       customer_id,
                       vendor_id,
                       status,
                       created_at
                FROM bookings 
                ORDER BY created_at DESC 
                LIMIT 3
            ");
            $simple_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $activities = array_merge($activities, $simple_bookings);
        } catch (PDOException $e2) {
            // Keep activities empty if bookings table doesn't exist
        }
    }
    
    // Try to get recent reviews
    try {
        $stmt = $pdo->query("
            SELECT 'review' as type,
                   r.*,
                   u.user_name,
                   v.business_name 
            FROM reviews r 
            LEFT JOIN users u ON r.customer_id = u.user_id 
            LEFT JOIN vendors v ON r.vendor_id = v.vendor_id 
            ORDER BY r.created_at DESC 
            LIMIT 3
        ");
        $recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $activities = array_merge($activities, $recent_reviews);
    } catch (PDOException $e) {
        // Reviews table might not exist yet
    }
    
    // Sort all activities by date
    if (!empty($activities)) {
        usort($activities, function($a, $b) {
            $time_a = strtotime($a['created_at'] ?? '1970-01-01');
            $time_b = strtotime($b['created_at'] ?? '1970-01-01');
            return $time_b - $time_a;
        });
        
        $activities = array_slice($activities, 0, 8);
    }
    
} catch (PDOException $e) {
    // Log error for debugging
    error_log("Dashboard database error: " . $e->getMessage());
    
    // Set default values
    $stats = [
        'customers' => 0,
        'vendors' => 0,
        'weddings' => 0,
        'revenue' => 0
    ];
    $activities = [];
}

// Helper functions
function formatCurrency($amount) {
    $amount = floatval($amount);
    if ($amount >= 1000000) {
        return '$' . number_format($amount / 1000000, 1) . 'M';
    } elseif ($amount >= 1000) {
        return '$' . number_format($amount / 1000, 1) . 'K';
    } else {
        return '$' . number_format($amount, 2);
    }
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

// Get database info for debugging (optional)
$db_info = [];
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $db_info['tables'] = $tables;
    $db_info['total_tables'] = count($tables);
} catch (PDOException $e) {
    $db_info['tables'] = [];
    $db_info['total_tables'] = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - All in One Wedding</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .user-details h3 {
            color: #333;
            font-size: 16px;
        }

        .user-details p {
            color: #666;
            font-size: 14px;
        }

        .nav-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .logout-btn, .refresh-btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
        }

        .refresh-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .logout-btn:hover, .refresh-btn:hover {
            transform: translateY(-2px);
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
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
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
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

        .admin-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .admin-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .section-icon {
            font-size: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .recent-activity {
            grid-column: 1 / -1;
        }

        .activity-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .activity-content h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .activity-content p {
            color: #666;
            font-size: 14px;
        }

        .activity-time {
            margin-left: auto;
            color: #999;
            font-size: 12px;
        }

        .no-data {
            text-align: center;
            color: #999;
            padding: 20px;
            font-style: italic;
        }

        .database-status {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .database-status.error {
            background: #ffeaea;
            border-color: #f44336;
        }

        .last-updated {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
            }
            
            .dashboard-container {
                padding: 0 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .admin-sections {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($user_name); ?></h3>
                <p>System Administrator</p>
            </div>
        </div>
        <div class="nav-actions">
            <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
            <a href="index.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Admin Control Center</h1>
            <p class="dashboard-subtitle">Manage your wedding platform efficiently</p>
        </div>

        <?php if ($db_info['total_tables'] > 0): ?>
        <div class="database-status">
            ✅ Database Connected - <?php echo $db_info['total_tables']; ?> tables found
        </div>
        <?php else: ?>
        <div class="database-status error">
            ⚠️ Database connection issue or no tables found
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo number_format($stats['customers']); ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-number"><?php echo number_format($stats['vendors']); ?></div>
                <div class="stat-label">Active Vendors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💍</div>
                <div class="stat-number"><?php echo number_format($stats['weddings']); ?></div>
                <div class="stat-label">Active Weddings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-number"><?php echo formatCurrency($stats['revenue']); ?></div>
                <div class="stat-label">Monthly Revenue</div>
            </div>
        </div>

        <div class="admin-sections">
            <div class="admin-section">
                <div class="section-header">
                    <div class="section-icon">👥</div>
                    <h2 class="section-title">User Management</h2>
                </div>
                <div class="action-buttons">
                    <a href="manage_users.php" class="action-btn">
                        <span>👀</span> View All Users
                    </a>
                    <a href="approve_vendors.php" class="action-btn">
                        <span>✅</span> Approve Vendors
                    </a>
                    <a href="manage_suspensions.php" class="action-btn">
                        <span>🚫</span> Manage Suspensions
                    </a>
                </div>
            </div>

            <div class="admin-section">
                <div class="section-header">
                    <div class="section-icon">🏢</div>
                    <h2 class="section-title">Vendor Control</h2>
                </div>
                <div class="action-buttons">
                    <a href="vendor_applications.php" class="action-btn">
                        <span>📋</span> Vendor Applications
                    </a>
                    <a href="review_management.php" class="action-btn">
                        <span>⭐</span> Review Management
                    </a>
                    <a href="vendor_analytics.php" class="action-btn">
                        <span>📊</span> Vendor Analytics
                    </a>
                </div>
            </div>

            <div class="admin-section">
                <div class="section-header">
                    <div class="section-icon">💼</div>
                    <h2 class="section-title">Booking Management</h2>
                </div>
                <div class="action-buttons">
                    <a href="all_bookings.php" class="action-btn">
                        <span>📅</span> All Bookings
                    </a>
                    <a href="resolve_disputes.php" class="action-btn">
                        <span>⚡</span> Resolve Disputes
                    </a>
                    <a href="payment_issues.php" class="action-btn">
                        <span>💳</span> Payment Issues
                    </a>
                </div>
            </div>

            <div class="admin-section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Reports & Analytics</h2>
                </div>
                <div class="action-buttons">
                    <a href="platform_statistics.php" class="action-btn">
                        <span>📈</span> Platform Statistics
                    </a>
                    <a href="financial_reports.php" class="action-btn">
                        <span>💰</span> Financial Reports
                    </a>
                    <a href="user_engagement.php" class="action-btn">
                        <span>🎯</span> User Engagement
                    </a>
                </div>
            </div>

            <div class="admin-section recent-activity">
                <div class="section-header">
                    <div class="section-icon">🕒</div>
                    <h2 class="section-title">Recent Platform Activity</h2>
                </div>
                <div class="activity-list">
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <?php if ($activity['type'] === 'vendor_registration'): ?>
                                    <div class="activity-icon">👤</div>
                                    <div class="activity-content">
                                        <h4>New vendor registration</h4>
                                        <p><?php echo htmlspecialchars($activity['user_name'] ?? 'New Vendor'); ?> submitted their application</p>
                                    </div>
                                <?php elseif ($activity['type'] === 'booking'): ?>
                                    <div class="activity-icon">💍</div>
                                    <div class="activity-content">
                                        <h4>Wedding booking <?php echo htmlspecialchars($activity['status'] ?? 'created'); ?></h4>
                                        <p><?php echo htmlspecialchars($activity['customer_name'] ?? 'A customer'); ?> booked <?php echo htmlspecialchars($activity['vendor_name'] ?? 'a service'); ?></p>
                                    </div>
                                <?php elseif ($activity['type'] === 'review'): ?>
                                    <div class="activity-icon">⭐</div>
                                    <div class="activity-content">
                                        <h4>New <?php echo $activity['rating'] ?? '5'; ?>-star review</h4>
                                        <p><?php echo htmlspecialchars($activity['business_name'] ?? 'A vendor'); ?> received feedback from <?php echo htmlspecialchars($activity['user_name'] ?? 'a customer'); ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="activity-time"><?php echo timeAgo($activity['created_at'] ?? ''); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">No recent activity found. Data will appear here as your platform grows.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="last-updated">
            Last updated: <?php echo date('M j, Y \a\t g:i A'); ?>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
        
        // Show loading state when navigating
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('javascript:')) {
                    this.style.opacity = '0.7';
                    this.innerHTML = '⏳ Loading...';
                }
            });
        });

        // Add click handlers for stat cards (optional)
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function() {
                const label = this.querySelector('.stat-label').textContent.toLowerCase();
                if (label.includes('customer')) {
                    window.location.href = 'manage_users.php?filter=customer';
                } else if (label.includes('vendor')) {
                    window.location.href = 'manage_users.php?filter=vendor';
                } else if (label.includes('wedding')) {
                    window.location.href = 'all_bookings.php';
                } else if (label.includes('revenue')) {
                    window.location.href = 'financial_reports.php';
                }
            });
        });
    </script>
</body>
</html>