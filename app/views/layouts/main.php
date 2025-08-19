<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'BTS DISC 2.0') ?> - <?= e(Config::get('APP_NAME')) ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-chart-line"></i>
                    <span>BTS DISC 2.0</span>
                </div>
                <button class="sidebar-toggle mobile-only" id="sidebarToggle">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="<?= baseUrl('dashboard') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : '' ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= baseUrl('bookings') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'booking') !== false ? 'active' : '' ?>">
                            <i class="fas fa-calendar-check"></i>
                            <span>Bookings</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= baseUrl('staff') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'staff') !== false ? 'active' : '' ?>">
                            <i class="fas fa-users"></i>
                            <span>Staff</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= baseUrl('income') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'income') !== false ? 'active' : '' ?>">
                            <i class="fas fa-arrow-down"></i>
                            <span>Income</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= baseUrl('expenses') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'expense') !== false ? 'active' : '' ?>">
                            <i class="fas fa-arrow-up"></i>
                            <span>Expenses</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= baseUrl('attendance') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'attendance') !== false ? 'active' : '' ?>">
                            <i class="fas fa-user-check"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= baseUrl('settlement') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'settlement') !== false ? 'active' : '' ?>">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Settlement</span>
                        </a>
                    </li>
                    <?php if (hasRole(['admin', 'super_admin'])): ?>
                    <li>
                        <a href="<?= baseUrl('lock-dates') ?>" class="<?= strpos($_SERVER['REQUEST_URI'], 'lock') !== false ? 'active' : '' ?>">
                            <i class="fas fa-lock"></i>
                            <span>Lock Dates</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= e(username()) ?></div>
                        <div class="user-role"><?= e(ucfirst(userRole())) ?></div>
                    </div>
                </div>
                <a href="<?= baseUrl('logout') ?>" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="app-header">
                <button class="sidebar-toggle desktop-only" id="sidebarToggleDesktop">
                    <i class="fas fa-bars"></i>
                </button>
                <button class="sidebar-toggle mobile-only" id="sidebarToggleMobile">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h1 class="page-title"><?= e($title ?? 'Dashboard') ?></h1>
                
                <div class="header-actions">
                    <div class="current-date">
                        <i class="far fa-calendar"></i>
                        <span><?= date('l, F j, Y') ?></span>
                    </div>
                    
                    <?php if (hasRole(['admin', 'super_admin'])): ?>
                    <div class="db-switcher">
                        <form action="<?= baseUrl('switch-db') ?>" method="POST" class="db-switch-form">
                            <?= csrfField() ?>
                            <select name="db_mode" onchange="this.form.submit()">
                                <option value="current" <?= Session::get('db_mode') === 'current' ? 'selected' : '' ?>>Current Month</option>
                                <option value="previous" <?= Session::get('db_mode') === 'previous' ? 'selected' : '' ?>>Previous Month</option>
                            </select>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </header>
            
            <!-- Flash Messages -->
            <?php foreach (flash() as $type => $message): ?>
            <div class="alert alert-<?= e($type) ?>" id="flashMessage">
                <i class="fas fa-<?= $type === 'success' ? 'check-circle' : ($type === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                <span><?= e($message) ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endforeach; ?>
            
            <!-- Page Content -->
            <div class="content-wrapper">
                <?= $content ?>
            </div>
        </main>
    </div>
    
    <!-- Main JavaScript -->
    <script src="<?= asset('js/script.js') ?>"></script>
</body>
</html>