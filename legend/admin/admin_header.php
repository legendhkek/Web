<?php
require_once '../config.php';
require_once 'admin_auth.php';

// Get current user data
$current_user = getCurrentUser();

// If no current user, redirect to login
if (!$current_user) {
    header('Location: ../login.php?redirect=admin');
    exit;
}

// Get database instance
$db = Database::getInstance();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - LEGEND CHECKER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            background-color: #f8f9fa;
        }
        .main-container {
            display: flex;
            flex: 1;
        }
        .sidebar {
            width: 280px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .user-info {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .user-details {
            margin-left: 10px;
        }
        .user-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .user-role {
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="analytics.php">
                <i class="bi bi-shield-check"></i> LEGEND CHECKER Admin
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> 
                        <?php echo htmlspecialchars($current_user['display_name'] ?? $current_user['username'] ?? 'Admin'); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../dashboard.php">
                            <i class="bi bi-house"></i> Main Site
                        </a></li>
                        <li><a class="dropdown-item" href="admin_access.php">
                            <i class="bi bi-shield-check"></i> Admin Access
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

<div class="main-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="user-info">
            <div class="d-flex align-items-center">
                <?php if (!empty($current_user['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($current_user['avatar_url']); ?>" 
                         alt="Avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar bg-light d-flex align-items-center justify-content-center">
                        <i class="bi bi-person text-dark"></i>
                    </div>
                <?php endif; ?>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($current_user['display_name'] ?? 'Admin'); ?></div>
                    <div class="user-role"><?php echo getRoleBadge($current_user['role'] ?? 'admin'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Navigation -->
        <nav class="nav flex-column">
            <a class="nav-link text-white" href="analytics.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link text-white" href="user_management.php">
                <i class="bi bi-people"></i> User Management
            </a>
            <a class="nav-link text-white" href="credit_actions.php">
                <i class="bi bi-coin"></i> Credit Actions
            </a>
            <!-- System -->
                    <div class="nav-group mb-2">
                        <small class="text-muted text-uppercase fw-bold">System</small>
                        <?php if (isOwner()): ?>
                        <a class="nav-link text-white" href="system_config.php">
                            <i class="bi bi-gear"></i> System Config
                        </a>
                        <?php endif; ?>
                        <a class="nav-link text-white" href="bot_config.php">
                            <i class="bi bi-robot"></i> Bot Config
                        </a>
                        <a class="nav-link text-white" href="telegram_settings.php">
                            <i class="bi bi-telegram"></i> Telegram Settings
                        </a>
                        <a class="nav-link text-white" href="tool_config.php">
                            <i class="bi bi-tools"></i> Tool Config
                        </a>
                        <a class="nav-link text-white" href="daily_credit_config.php">
                            <i class="bi bi-coin"></i> Daily Credits
                        </a>
                    </div>

                    <!-- Generators -->
                    <div class="nav-group mb-2">
                        <small class="text-muted text-uppercase fw-bold">Generators</small>
                        <a class="nav-link text-white" href="premium_generator.php">
                            <i class="bi bi-gem"></i> Premium Generator
                        </a>
                        <a class="nav-link text-white" href="credit_generator.php">
                            <i class="bi bi-coin"></i> Credit Generator
                        </a>
                        <a class="nav-link text-white" href="claim_system.php">
                            <i class="bi bi-ticket-perforated"></i> Claim System
                        </a>
                        <a class="nav-link text-white" href="../credit_claim.php" target="_blank">
                            <i class="bi bi-globe"></i> User Claim Page
                        </a>
                    </div>
            <a class="nav-link text-white" href="presence_monitor.php">
                <i class="bi bi-activity"></i> Presence Monitor
            </a>
            <a class="nav-link text-white" href="audit_log.php">
                <i class="bi bi-journal-text"></i> Audit Log
            </a>
            <a class="nav-link text-white" href="error_logs.php">
                <i class="bi bi-bug"></i> Error Logs
            </a>
            <a class="nav-link text-white" href="support_tickets.php">
                <i class="bi bi-headset"></i> Support Tickets
            </a>
            <a class="nav-link text-white" href="broadcast.php">
                <i class="bi bi-megaphone"></i> Broadcast
            </a>
            <a class="nav-link text-white" href="recalculate_stats.php">
                <i class="bi bi-calculator"></i> Recalculate Stats
            </a>
            
            <?php if (isOwner()): ?>
            <hr class="border-light">
            <div class="px-3 text-white-50 small">OWNER ONLY</div>
            <a class="nav-link text-white" href="payment_config.php">
                <i class="bi bi-credit-card"></i> Payment Config
            </a>
            <a class="nav-link text-white" href="financial_reports.php">
                <i class="bi bi-graph-up"></i> Financial Reports
            </a>
            <a class="nav-link text-white" href="database_backup.php">
                <i class="bi bi-database"></i> Database Backup
            </a>
            <a class="nav-link text-white" href="role_management.php">
                <i class="bi bi-person-badge"></i> Role Management
            </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <!-- Main Content Area -->
    <div class="content">
