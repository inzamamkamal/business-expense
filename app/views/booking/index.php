<?php $title = 'Party Bookings - BTS DISC 2.0'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Party Bookings</h1>
        <p class="text-muted mb-0">Manage event bookings and celebrations</p>
    </div>
    <button class="btn btn-primary" data-toggle="modal" data-target="#bookingModal">
        <i class="fas fa-plus me-2"></i>New Booking
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?= $filters['date'] ?>">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Customer name, phone, booking ID..." value="<?= $filters['search'] ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bookings Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Bookings List</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="refreshBookings()">
                <i class="fas fa-refresh"></i>
            </button>
            <button class="btn btn-sm btn-outline-success">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th data-sort="booking_id">Booking ID</th>
                        <th data-sort="customer_name">Customer</th>
                        <th data-sort="booking_date">Date & Time</th>
                        <th data-sort="total_person">Guests</th>
                        <th data-sort="advance_paid">Advance</th>
                        <th data-sort="event_type">Event Type</th>
                        <th data-sort="status">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No bookings found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td>
                            <span class="badge bg-primary"><?= Security::escape($booking['booking_id']) ?></span>
                        </td>
                        <td>
                            <div>
                                <div class="fw-semibold"><?= Security::escape($booking['customer_name']) ?></div>
                                <small class="text-muted"><?= Security::escape($booking['contact_number']) ?></small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <div><?= date('M j, Y', strtotime($booking['booking_date'])) ?></div>
                                <small class="text-muted"><?= date('g:i A', strtotime($booking['booking_time'])) ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $booking['total_person'] ?> guests</span>
                        </td>
                        <td>
                            <strong>₹<?= number_format($booking['advance_paid']) ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= Security::escape($booking['event_type']) ?></span>
                        </td>
                        <td>
                            <?php 
                            $statusClass = [
                                'active' => 'bg-warning',
                                'completed' => 'bg-success',
                                'cancelled' => 'bg-danger'
                            ][$booking['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($booking['status']) ?></span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewBooking('<?= $booking['booking_id'] ?>')" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($booking['status'] === 'active'): ?>
                                <button class="btn btn-sm btn-outline-success" onclick="completeBooking(<?= $booking['id'] ?>)" title="Mark Complete">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (Auth::isAdmin()): ?>
                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                        data-url="/bookings/delete" 
                                        data-booking_id="<?= $booking['id'] ?>"
                                        data-message="Are you sure you want to delete this booking?"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Booking Modal -->
<div class="modal" id="bookingModal" tabindex="-1">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Booking</h5>
                <button type="button" class="btn-close" data-dismiss="modal"></button>
            </div>
            <form class="ajax-form" action="/bookings/create" method="POST" data-refresh="bookings">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="customerName" class="form-label">Customer Name *</label>
                                <input type="text" class="form-control" id="customerName" name="customerName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contactNumber" class="form-label">Contact Number *</label>
                                <input type="tel" class="form-control" id="contactNumber" name="contactNumber" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bookingDate" class="form-label">Event Date *</label>
                                <input type="date" class="form-control" id="bookingDate" name="bookingDate" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bookingTime" class="form-label">Event Time *</label>
                                <input type="time" class="form-control" id="bookingTime" name="bookingTime" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="totalPerson" class="form-label">Total Guests *</label>
                                <input type="number" class="form-control" id="totalPerson" name="totalPerson" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="advancePaid" class="form-label">Advance Amount *</label>
                                <input type="number" class="form-control" id="advancePaid" name="advancePaid" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="paymentMethod" class="form-label">Payment Method *</label>
                                <select class="form-select" id="paymentMethod" name="paymentMethod" required>
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="upi">UPI</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="eventType" class="form-label">Event Type *</label>
                                <select class="form-select" id="eventType" name="eventType" required>
                                    <option value="">Select Event Type</option>
                                    <option value="birthday">Birthday Party</option>
                                    <option value="anniversary">Anniversary</option>
                                    <option value="wedding">Wedding</option>
                                    <option value="corporate">Corporate Event</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bookingType" class="form-label">Booking Type *</label>
                                <select class="form-select" id="bookingType" name="bookingType" required>
                                    <option value="">Select Type</option>
                                    <option value="full_day">Full Day</option>
                                    <option value="half_day">Half Day</option>
                                    <option value="evening">Evening Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ISDJ" class="form-label">DJ Required?</label>
                                <select class="form-select" id="ISDJ" name="ISDJ">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="takenBy" class="form-label">Taken By *</label>
                                <input type="text" class="form-control" id="takenBy" name="takenBy" value="<?= Security::escape(Auth::username()) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="specialRequests" class="form-label">Special Requests</label>
                        <textarea class="form-control" id="specialRequests" name="specialRequests" rows="3" placeholder="Any special requirements or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Booking Modal -->
<div class="modal" id="viewBookingModal" tabindex="-1">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="btn-close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bookingDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Set minimum date to today
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('bookingDate');
    if (dateInput) {
        dateInput.min = new Date().toISOString().split('T')[0];
    }
});

function viewBooking(bookingId) {
    const modal = document.getElementById('viewBookingModal');
    const detailsDiv = document.getElementById('bookingDetails');
    
    detailsDiv.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner"></div>
            <p class="text-muted mt-2">Loading booking details...</p>
        </div>
    `;
    
    BTSApp.showModal('#viewBookingModal');
    
    fetch('/bookings/details', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `booking_id=${encodeURIComponent(bookingId)}&csrf_token=${BTSApp.csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const booking = data.booking;
            detailsDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Customer Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Name:</strong></td><td>${BTSApp.escapeHtml(booking.customer_name)}</td></tr>
                            <tr><td><strong>Contact:</strong></td><td>${BTSApp.escapeHtml(booking.contact_number)}</td></tr>
                            <tr><td><strong>Booking ID:</strong></td><td><span class="badge bg-primary">${BTSApp.escapeHtml(booking.booking_id)}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Event Details</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Date:</strong></td><td>${new Date(booking.booking_date).toLocaleDateString()}</td></tr>
                            <tr><td><strong>Time:</strong></td><td>${booking.booking_time}</td></tr>
                            <tr><td><strong>Event Type:</strong></td><td><span class="badge bg-secondary">${BTSApp.escapeHtml(booking.event_type)}</span></td></tr>
                            <tr><td><strong>Guests:</strong></td><td><span class="badge bg-info">${booking.total_person}</span></td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6 class="text-primary">Payment Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Advance:</strong></td><td><strong>₹${parseInt(booking.advance_paid).toLocaleString()}</strong></td></tr>
                            <tr><td><strong>Method:</strong></td><td>${BTSApp.escapeHtml(booking.payment_method)}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-${booking.status === 'completed' ? 'success' : 'warning'}">${booking.status}</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Additional Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>DJ Required:</strong></td><td>${booking.is_dj ? 'Yes' : 'No'}</td></tr>
                            <tr><td><strong>Booking Type:</strong></td><td>${BTSApp.escapeHtml(booking.booking_type)}</td></tr>
                            <tr><td><strong>Taken By:</strong></td><td>${BTSApp.escapeHtml(booking.taken_by)}</td></tr>
                        </table>
                    </div>
                </div>
                ${booking.special_requests ? `
                <div class="mt-3">
                    <h6 class="text-primary">Special Requests</h6>
                    <p class="border p-3 rounded bg-light">${BTSApp.escapeHtml(booking.special_requests)}</p>
                </div>
                ` : ''}
            `;
        } else {
            detailsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.message || 'Failed to load booking details'}
                </div>
            `;
        }
    })
    .catch(error => {
        detailsDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                An error occurred while loading booking details
            </div>
        `;
    });
}

function completeBooking(bookingId) {
    BTSApp.showConfirmDialog(
        'Complete Booking',
        'Are you sure you want to mark this booking as completed?',
        async () => {
            try {
                const response = await fetch('/bookings/complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `booking_id=${bookingId}&csrf_token=${BTSApp.csrfToken}`
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    BTSApp.showAlert(result.message, 'success');
                    refreshBookings();
                } else {
                    BTSApp.showAlert(result.message || 'Failed to complete booking', 'danger');
                }
            } catch (error) {
                BTSApp.showAlert('An error occurred while completing the booking', 'danger');
            }
        }
    );
}

function refreshBookings() {
    location.reload();
}
</script>