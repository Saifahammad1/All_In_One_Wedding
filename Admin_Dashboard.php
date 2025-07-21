<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Admin';
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

        .logout-btn {
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

        .logout-btn:hover {
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
            <a href="Index.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Admin Control Center</h1>
            <p class="dashboard-subtitle">Manage your wedding platform efficiently</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number">147</div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-number">23</div>
                <div class="stat-label">Active Vendors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💍</div>
                <div class="stat-number">89</div>
                <div class="stat-label">Active Weddings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-number">$52k</div>
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
                    <button class="action-btn">
                        <span>👀</span> View All Users
                    </button>
                    <button class="action-btn">
                        <span>✅</span> Approve Vendors
                    </button>
                    <button class="action-btn">
                        <span>🚫</span> Manage Suspensions
                    </button>
                </div>
            </div>

            <div class="admin-section">
                <div class="section-header">
                    <div class="section-icon">🏢</div>
                    <h2 class="section-title">Vendor Control</h2>
                </div>
                <div class="action-buttons">
                    <button class="action-btn">
                        <span>📋</span> Vendor Applications
                    </button>
                    <button class="action-btn">
                        <span>⭐</span> Review Management
                    </button>
                    <button class="action-btn">
                        <span>📊</span> Vendor Analytics
                    </button>
                </div>
            </div>

            <div class="admin-section">
                <div class="section-header">
                    <div class="section-icon">💼</div>
                    <h2 class="section-title">Booking Management</h2>
                </div>
                <div class="action-buttons">
                    <button class="action-btn">
                        <span>📅</span> All Bookings
                    </button>
                    <button class="action-btn">
                        <span>⚡</span> Resolve Disputes
                    </button>
                    <button class="action-btn">
                        <span>💳</span> Payment Issues
                    </button>
                </div>
            </div>

            <div class="admin-section">
                <div class="section-header">
                    <div class="section-icon">📊</div>
                    <h2 class="section-title">Reports & Analytics</h2>
                </div>
                <div class="action-buttons">
                    <button class="action-btn">
                        <span>📈</span> Platform Statistics
                    </button>
                    <button class="action-btn">
                        <span>💰</span> Financial Reports
                    </button>
                    <button class="action-btn">
                        <span>🎯</span> User Engagement
                    </button>
                </div>
            </div>

            <div class="admin-section recent-activity">
                <div class="section-header">
                    <div class="section-icon">🕒</div>
                    <h2 class="section-title">Recent Platform Activity</h2>
                </div>
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon">👤</div>
                        <div class="activity-content">
                            <h4>New vendor registration</h4>
                            <p>Elegant Events submitted their application</p>
                        </div>
                        <div class="activity-time">2 hours ago</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">💍</div>
                        <div class="activity-content">
                            <h4>Wedding booking confirmed</h4>
                            <p>Sarah & John booked Dream Venue for June 2024</p>
                        </div>
                        <div class="activity-time">4 hours ago</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">⭐</div>
                        <div class="activity-content">
                            <h4>New 5-star review</h4>
                            <p>Perfect Photography received excellent feedback</p>
                        </div>
                        <div class="activity-time">6 hours ago</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">💳</div>
                        <div class="activity-content">
                            <h4>Payment processed</h4>
                            <p>$2,500 payment confirmed for Royal Catering</p>
                        </div>
                        <div class="activity-time">1 day ago</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add click handlers for action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.textContent.trim();
                alert(`Feature "${action}" will be implemented in the next phase.`);
            });
        });

        // Auto-refresh dashboard data every 30 seconds
        setInterval(() => {
            // This would typically fetch fresh data from the server
            console.log('Dashboard data refreshed');
        }, 30000);
    </script>
</body>
</html>