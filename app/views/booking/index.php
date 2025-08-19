<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="<?= baseUrl('bookings') ?>" class="filter-form">
        <div class="filter-group">
            <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="form-control" placeholder="From Date">
        </div>
        <div class="filter-group">
            <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="form-control" placeholder="To Date">
        </div>
        <div class="filter-group">
            <select name="staff_id" class="form-control">
                <option value="">All Staff</option>
                <?php foreach ($staff as $member): ?>
                <option value="<?= $member['id'] ?>" <?= $filters['staff_id'] == $member['id'] ? 'selected' : '' ?>>
                    <?= e($member['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="confirmed" <?= $filters['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" value="<?= e($filters['search']) ?>" class="form-control" placeholder="Search bookings...">
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="<?= baseUrl('bookings') ?>" class="btn btn-secondary">
            <i class="fas fa-times"></i> Clear
        </a>
    </form>
    
    <a href="<?= baseUrl('bookings/create') ?>" class="btn btn-success">
        <i class="fas fa-plus"></i> New Booking
    </a>
</div>

<!-- Bookings Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Bookings List</h2>
        <div class="card-actions">
            <button class="btn btn-sm btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <?php if (empty($bookings)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <h3>No Bookings Found</h3>
        <p>No bookings match your current filters.</p>
        <a href="<?= baseUrl('bookings/create') ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create First Booking
        </a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table" data-datatable id="bookingsTable">
            <thead>
                <tr>
                    <th data-sortable>Date</th>
                    <th data-sortable>Reference</th>
                    <th data-sortable>Guest Name</th>
                    <th>Phone</th>
                    <th data-sortable>Room</th>
                    <th data-sortable>Staff</th>
                    <th>Check In/Out</th>
                    <th data-sortable>Amount</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?= formatDate($booking['date']) ?></td>
                    <td>
                        <span class="text-mono"><?= e($booking['reference_no']) ?></span>
                    </td>
                    <td>
                        <strong><?= e($booking['guest_name']) ?></strong>
                        <?php if ($booking['email']): ?>
                        <br><small class="text-muted"><?= e($booking['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($booking['phone']) ?></td>
                    <td>
                        <span class="badge badge-primary"><?= e($booking['room_number']) ?></span>
                    </td>
                    <td><?= e($booking['staff_name'] ?? '-') ?></td>
                    <td>
                        <small>
                            <i class="fas fa-sign-in-alt text-success"></i> <?= e($booking['check_in']) ?><br>
                            <i class="fas fa-sign-out-alt text-danger"></i> <?= e($booking['check_out']) ?>
                        </small>
                    </td>
                    <td>
                        <strong><?= formatCurrency($booking['total_amount']) ?></strong>
                        <?php if ($booking['advance_amount'] > 0): ?>
                        <br><small class="text-muted">Adv: <?= formatCurrency($booking['advance_amount']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $booking['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                            <?= ucfirst($booking['payment_status']) ?>
                        </span>
                        <br><small><?= e($booking['payment_method']) ?></small>
                    </td>
                    <td>
                        <span class="badge badge-<?= $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                            <?= ucfirst($booking['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="<?= baseUrl('bookings/' . $booking['id']) ?>" class="btn btn-sm btn-icon" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (!isDateLocked($pdo, $booking['date'])): ?>
                            <a href="<?= baseUrl('bookings/' . $booking['id'] . '/edit') ?>" class="btn btn-sm btn-icon" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if (hasRole(['admin', 'super_admin'])): ?>
                            <a href="<?= baseUrl('bookings/' . $booking['id'] . '/delete') ?>" 
                               class="btn btn-sm btn-icon text-danger" 
                               data-confirm-delete="Are you sure you want to delete this booking?"
                               data-method="POST"
                               title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-bar {
    background: var(--bg-primary);
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-form {
    display: flex;
    gap: 1rem;
    flex: 1;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 0 1 auto;
}

.filter-group .form-control {
    min-width: 150px;
}

.text-mono {
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .filter-bar {
        flex-direction: column;
    }
    
    .filter-form {
        width: 100%;
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-group .form-control {
        width: 100%;
    }
}
</style>