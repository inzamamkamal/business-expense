<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= Security::generateCsrfToken() ?>">
    <title><?= $title ?? 'BTS DISC 2.0 Application' ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <?= $additionalCSS ?? '' ?>
</head>
<body class="<?= Auth::role() === 'user' ? 'user-theme' : 'admin-theme' ?>">
    
    <!-- Navigation -->
    <?php if (Auth::isLoggedIn()): ?>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="/dashboard">
                <img src="/assets/images/logo.png" alt="BTS DISC 2.0" height="32" class="me-2">
                <span>BTS DISC 2.0</span>
            </a>
            
            <div class="navbar-nav d-flex flex-row">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?= Security::escape(Auth::username()) ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="dropdown-header">
                            <small class="text-muted">Role: <?= Security::escape(ucfirst(Auth::role())) ?></small>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/profile">
                            <i class="fas fa-user me-2"></i>Profile
                        </a>
                        <a class="dropdown-item" href="/settings">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="/logout">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Alert Container -->
    <div class="alert-container container-fluid mt-3">
        <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible">
            <?= Security::escape($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-dismiss="alert"></button>
        </div>
        <?php 
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        endif; 
        ?>
    </div>
    
    <!-- Main Content -->
    <main class="container-fluid py-4">
        <?= $content ?>
    </main>
    
    <!-- Footer -->
    <?php if (Auth::isLoggedIn()): ?>
    <footer class="bg-light py-4 mt-5">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        &copy; <?= date('Y') ?> BTS DISC 2.0 Application. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-muted">
                        Powered by <a href="https://www.SoluServ.in" target="_blank" class="text-primary">SoluServ.in</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center" style="z-index: 9999;">
        <div class="bg-white p-4 rounded-lg text-center">
            <div class="spinner mb-3"></div>
            <p class="mb-0">Processing...</p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="/assets/js/app.js"></script>
    
    <?= $additionalJS ?? '' ?>
    
    <script>
        // Initialize page-specific functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus first input
            const firstInput = document.querySelector('input:not([type="hidden"]):not([readonly]):not([disabled])');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Initialize dropdowns
            document.querySelectorAll('.dropdown-toggle').forEach(dropdown => {
                dropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    const menu = this.nextElementSibling;
                    if (menu && menu.classList.contains('dropdown-menu')) {
                        menu.classList.toggle('show');
                    }
                });
            });
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.matches('.dropdown-toggle')) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
            });
        });
        
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
            // You can add error reporting here
        });
        
        // Global unhandled promise rejection handler
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
            // You can add error reporting here
        });
    </script>
</body>
</html>