<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Today's Bookings</div>
            <div class="stat-value"><?= $bookingSummary['total_bookings'] ?? 0 ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-rupee-sign"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Today's Revenue</div>
            <div class="stat-value"><?= formatCurrency($bookingSummary['total_revenue'] ?? 0) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Today's Expenses</div>
            <div class="stat-value"><?= formatCurrency($expenseSummary['total_expense'] ?? 0) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon <?= $netProfit >= 0 ? 'success' : 'danger' ?>">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Net Profit Today</div>
            <div class="stat-value"><?= formatCurrency($netProfit) ?></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Quick Actions</h2>
    </div>
    <div class="quick-actions">
        <div class="action-grid">
            <a href="<?= baseUrl('bookings/create') ?>" class="action-item">
                <i class="fas fa-plus-circle"></i>
                <span>New Booking</span>
            </a>
            <a href="<?= baseUrl('staff/attendance') ?>" class="action-item">
                <i class="fas fa-user-check"></i>
                <span>Mark Attendance</span>
            </a>
            <a href="<?= baseUrl('income/create') ?>" class="action-item">
                <i class="fas fa-arrow-down"></i>
                <span>Add Income</span>
            </a>
            <a href="<?= baseUrl('expenses/create') ?>" class="action-item">
                <i class="fas fa-arrow-up"></i>
                <span>Add Expense</span>
            </a>
            <a href="<?= baseUrl('reports') ?>" class="action-item">
                <i class="fas fa-file-alt"></i>
                <span>View Reports</span>
            </a>
            <a href="<?= baseUrl('settlement') ?>" class="action-item">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Settlement</span>
            </a>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Bookings</h2>
        <a href="<?= baseUrl('bookings') ?>" class="btn btn-sm btn-primary">View All</a>
    </div>
    
    <?php if (empty($recentBookings)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h3>No Recent Bookings</h3>
        <p>Start by creating your first booking for today.</p>
        <a href="<?= baseUrl('bookings/create') ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Booking
        </a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Guest Name</th>
                    <th>Room</th>
                    <th>Staff</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentBookings as $booking): ?>
                <tr>
                    <td><?= formatDate($booking['date']) ?></td>
                    <td><?= e($booking['guest_name']) ?></td>
                    <td><?= e($booking['room_number']) ?></td>
                    <td><?= e($booking['staff_name'] ?? '-') ?></td>
                    <td><?= formatCurrency($booking['total_amount']) ?></td>
                    <td>
                        <span class="badge badge-<?= $booking['status'] === 'confirmed' ? 'success' : 'warning' ?>">
                            <?= ucfirst($booking['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="<?= baseUrl('bookings/' . $booking['id']) ?>" class="btn btn-sm btn-secondary" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Staff & Attendance Summary -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Staff Overview</h2>
            </div>
            <div class="staff-summary">
                <div class="summary-item">
                    <span class="summary-label">Total Active Staff</span>
                    <span class="summary-value"><?= $activeStaff ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Present Today</span>
                    <span class="summary-value text-success"><?= $todayAttendance ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Absent Today</span>
                    <span class="summary-value text-danger"><?= $activeStaff - $todayAttendance ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Monthly Overview</h2>
            </div>
            <div class="monthly-summary">
                <div class="summary-item">
                    <span class="summary-label">Total Bookings</span>
                    <span class="summary-value"><?= $monthlyBookings ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Month</span>
                    <span class="summary-value"><?= date('F Y') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard specific styles */
.quick-actions {
    padding: 0.5rem;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    text-decoration: none;
    color: var(--text-primary);
    transition: var(--transition);
    text-align: center;
}

.action-item:hover {
    background: var(--bg-tertiary);
    border-color: var(--primary-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.action-item i {
    font-size: 2rem;
    color: var(--primary-color);
}

.action-item span {
    font-size: 0.875rem;
    font-weight: 500;
}

.staff-summary,
.monthly-summary {
    padding: 0.5rem 0;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.summary-value {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
}

.text-success {
    color: var(--success-color) !important;
}

.text-danger {
    color: var(--danger-color) !important;
}

.row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .action-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }
    
    .action-item {
        padding: 1rem 0.5rem;
    }
    
    .action-item i {
        font-size: 1.5rem;
    }
}
</style>