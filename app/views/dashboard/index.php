<?php $title = 'Dashboard - BTS DISC 2.0'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, <?= Security::escape($user['username']) ?>!</p>
    </div>
    <div class="text-end">
        <small class="text-muted">
            <i class="fas fa-clock me-1"></i>
            <?= date('l, F j, Y - g:i A') ?>
        </small>
    </div>
</div>

<!-- Quick Stats Cards -->
<div class="row mb-4">
    <?php if (isset($stats['bookings'])): ?>
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">Total Bookings</h6>
                        <h4 class="mb-0"><?= $stats['bookings']['total_bookings'] ?? 0 ?></h4>
                        <small class="opacity-75">This month</small>
                    </div>
                    <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($stats['attendance'])): ?>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">Present Today</h6>
                        <h4 class="mb-0"><?= $stats['attendance']['present_today'] ?>/<?= $stats['attendance']['total_staff'] ?></h4>
                        <small class="opacity-75">Staff members</small>
                    </div>
                    <i class="fas fa-user-check fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (Auth::isAdmin() && isset($stats['income'])): ?>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">Monthly Income</h6>
                        <h4 class="mb-0">₹<?= number_format(array_sum(array_column($stats['income'], 'total_income')) ?? 0) ?></h4>
                        <small class="opacity-75">This month</small>
                    </div>
                    <i class="fas fa-chart-line fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-1">Monthly Expenses</h6>
                        <h4 class="mb-0">₹<?= number_format(array_sum(array_column($stats['expenses'] ?? [], 'total_amount')) ?? 0) ?></h4>
                        <small class="opacity-75">This month</small>
                    </div>
                    <i class="fas fa-chart-pie fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Main Navigation Tiles -->
<div class="dashboard-grid">
    
    <?php if (Auth::isAdmin()): ?>
    <a href="/staff" class="dashboard-tile green">
        <div class="tile-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="tile-title">STAFF MANAGEMENT</div>
        <div class="tile-description">Manage staff records and information</div>
    </a>
    <?php endif; ?>
    
    <a href="/bookings" class="dashboard-tile blue">
        <div class="tile-icon">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="tile-title">PARTY BOOKINGS</div>
        <div class="tile-description">Plan and manage celebrations</div>
    </a>
    
    <a href="/attendance" class="dashboard-tile teal">
        <div class="tile-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="tile-title">ATTENDANCE</div>
        <div class="tile-description">Track staff attendance records</div>
    </a>
    
    <a href="/expenses" class="dashboard-tile orange">
        <div class="tile-icon">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="tile-title">EXPENSES</div>
        <div class="tile-description">Record business expenses</div>
    </a>
    
    <?php if (Auth::isAdmin()): ?>
    <a href="/income" class="dashboard-tile green">
        <div class="tile-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="tile-title">INCOME</div>
        <div class="tile-description">Add income & view reports</div>
    </a>
    
    <a href="/settlements" class="dashboard-tile blue">
        <div class="tile-icon">
            <i class="fas fa-handshake"></i>
        </div>
        <div class="tile-title">SETTLEMENTS</div>
        <div class="tile-description">Handle staff settlements</div>
    </a>
    <?php endif; ?>
    
</div>

<!-- Recent Activity -->
<div class="row mt-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Activity</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="refreshActivity()">
                    <i class="fas fa-refresh"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="recentActivity">
                    <div class="text-center py-4">
                        <div class="spinner"></div>
                        <p class="text-muted mt-2">Loading recent activity...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/bookings" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>New Booking
                    </a>
                    <a href="/attendance" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Mark Attendance
                    </a>
                    <a href="/expenses" class="btn btn-warning">
                        <i class="fas fa-plus me-2"></i>Add Expense
                    </a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="/income" class="btn btn-info">
                        <i class="fas fa-plus me-2"></i>Add Income
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <div class="mb-2">
                        <strong>Version:</strong> 2.0.0
                    </div>
                    <div class="mb-2">
                        <strong>Last Login:</strong><br>
                        <?= date('M j, Y g:i A') ?>
                    </div>
                    <div class="mb-2">
                        <strong>Role:</strong> <?= Security::escape(ucfirst($user['role'])) ?>
                    </div>
                </small>
            </div>
        </div>
    </div>
</div>

<script>
function refreshActivity() {
    const activityDiv = document.getElementById('recentActivity');
    activityDiv.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner"></div>
            <p class="text-muted mt-2">Refreshing...</p>
        </div>
    `;
    
    // Simulate loading recent activity
    setTimeout(() => {
        activityDiv.innerHTML = `
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar-plus text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-semibold">New booking created</div>
                        <small class="text-muted">Customer: John Doe - Event on ${new Date().toLocaleDateString()}</small>
                    </div>
                    <small class="text-muted">2 min ago</small>
                </div>
                <div class="list-group-item d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-user-check text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-semibold">Attendance marked</div>
                        <small class="text-muted">5 staff members present today</small>
                    </div>
                    <small class="text-muted">15 min ago</small>
                </div>
                <div class="list-group-item d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-receipt text-warning"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="fw-semibold">Expense added</div>
                        <small class="text-muted">Kitchen supplies - ₹2,500</small>
                    </div>
                    <small class="text-muted">1 hour ago</small>
                </div>
            </div>
        `;
    }, 1000);
}

// Load recent activity on page load
document.addEventListener('DOMContentLoaded', refreshActivity);
</script>