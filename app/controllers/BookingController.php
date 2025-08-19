<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Booking;
use App\Models\Staff;
use App\Models\Lock;
use App\Helpers\Validator;
use App\Helpers\Security;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CSRFMiddleware;

class BookingController extends Controller {
    private $bookingModel;
    private $staffModel;
    private $lockModel;
    
    public function __construct($params) {
        parent::__construct($params);
        
        $this->bookingModel = new Booking();
        $this->staffModel = new Staff();
        $this->lockModel = new Lock();
    }
    
    protected function before() {
        AuthMiddleware::check();
        CSRFMiddleware::validate();
    }
    
    /**
     * List bookings
     */
    public function index() {
        $filters = [
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
            'staff_id' => $_GET['staff_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        
        $bookings = $this->bookingModel->getFilteredBookings($filters);
        $staff = $this->staffModel->getActiveStaff();
        
        $this->renderWithLayout('booking/index', [
            'title' => 'Bookings',
            'bookings' => $bookings,
            'staff' => $staff,
            'filters' => $filters
        ]);
    }
    
    /**
     * Show create booking form
     */
    public function create() {
        $staff = $this->staffModel->getActiveStaff();
        
        $this->renderWithLayout('booking/create', [
            'title' => 'Create Booking',
            'staff' => $staff
        ]);
    }
    
    /**
     * Store new booking
     */
    public function store() {
        // Validate input
        $validator = new Validator($_POST);
        $valid = $validator->validate([
            'date' => 'required|date',
            'guest_name' => 'required|min:3|max:100',
            'phone' => 'required|numeric',
            'staff_id' => 'required|numeric',
            'service_type' => 'required',
            'room_number' => 'required',
            'check_in' => 'required',
            'check_out' => 'required',
            'total_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,upi,bank_transfer'
        ]);
        
        if (!$valid) {
            setFlash('error', $validator->getFirstError());
            $this->redirect(baseUrl('bookings/create'));
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($_POST['date'])) {
            setFlash('error', 'Cannot create booking for locked date');
            $this->redirect(baseUrl('bookings/create'));
        }
        
        // Prepare data
        $data = [
            'date' => $_POST['date'],
            'guest_name' => Security::sanitize($_POST['guest_name']),
            'phone' => Security::sanitize($_POST['phone']),
            'email' => Security::sanitize($_POST['email'] ?? ''),
            'staff_id' => $_POST['staff_id'],
            'service_type' => $_POST['service_type'],
            'room_number' => $_POST['room_number'],
            'check_in' => $_POST['check_in'],
            'check_out' => $_POST['check_out'],
            'adults' => $_POST['adults'] ?? 1,
            'children' => $_POST['children'] ?? 0,
            'total_amount' => $_POST['total_amount'],
            'advance_amount' => $_POST['advance_amount'] ?? 0,
            'payment_method' => $_POST['payment_method'],
            'payment_status' => $_POST['payment_status'] ?? 'pending',
            'status' => 'confirmed',
            'reference_no' => 'BK' . date('YmdHis') . rand(100, 999),
            'created_by' => userId(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Calculate commission
        $staff = $this->staffModel->findById($_POST['staff_id']);
        if ($staff && $staff['commission_rate']) {
            $data['commission_amount'] = $data['total_amount'] * ($staff['commission_rate'] / 100);
        }
        
        // Create booking
        try {
            $bookingId = $this->bookingModel->create($data);
            setFlash('success', 'Booking created successfully');
            $this->redirect(baseUrl('bookings/' . $bookingId));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to create booking: ' . $e->getMessage());
            $this->redirect(baseUrl('bookings/create'));
        }
    }
    
    /**
     * Show booking details
     */
    public function show() {
        $id = $this->routeParams['id'] ?? 0;
        $booking = $this->bookingModel->getBookingWithStaff($id);
        
        if (!$booking) {
            setFlash('error', 'Booking not found');
            $this->redirect(baseUrl('bookings'));
        }
        
        $this->renderWithLayout('booking/show', [
            'title' => 'Booking Details',
            'booking' => $booking
        ]);
    }
    
    /**
     * Show edit form
     */
    public function edit() {
        $id = $this->routeParams['id'] ?? 0;
        $booking = $this->bookingModel->findById($id);
        
        if (!$booking) {
            setFlash('error', 'Booking not found');
            $this->redirect(baseUrl('bookings'));
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($booking['date'])) {
            setFlash('error', 'Cannot edit booking for locked date');
            $this->redirect(baseUrl('bookings/' . $id));
        }
        
        $staff = $this->staffModel->getActiveStaff();
        
        $this->renderWithLayout('booking/edit', [
            'title' => 'Edit Booking',
            'booking' => $booking,
            'staff' => $staff
        ]);
    }
    
    /**
     * Update booking
     */
    public function update() {
        $id = $this->routeParams['id'] ?? 0;
        
        // Similar validation as store method
        // ... validation code ...
        
        // Update booking
        try {
            $this->bookingModel->update($id, $data);
            setFlash('success', 'Booking updated successfully');
            $this->redirect(baseUrl('bookings/' . $id));
        } catch (\Exception $e) {
            setFlash('error', 'Failed to update booking: ' . $e->getMessage());
            $this->redirect(baseUrl('bookings/' . $id . '/edit'));
        }
    }
    
    /**
     * Delete booking
     */
    public function delete() {
        AuthMiddleware::checkRole(['admin', 'super_admin']);
        
        $id = $this->routeParams['id'] ?? 0;
        $booking = $this->bookingModel->findById($id);
        
        if (!$booking) {
            $this->json(['status' => 'error', 'message' => 'Booking not found'], 404);
        }
        
        // Check if date is locked
        if ($this->lockModel->isDateLocked($booking['date'])) {
            $this->json(['status' => 'error', 'message' => 'Cannot delete booking for locked date']);
        }
        
        try {
            $this->bookingModel->delete($id);
            $this->json(['status' => 'success', 'message' => 'Booking deleted successfully']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => 'Failed to delete booking']);
        }
    }
}