<?php

class BookingController extends Controller {
    private $bookingModel;
    
    public function __construct() {
        parent::__construct();
        $this->bookingModel = new Booking();
    }
    
    /**
     * Show bookings page
     */
    public function index() {
        $page = (int)($_GET['page'] ?? 1);
        $filters = [
            'date' => Security::sanitizeInput($_GET['date'] ?? ''),
            'status' => Security::sanitizeInput($_GET['status'] ?? ''),
            'search' => Security::sanitizeInput($_GET['search'] ?? '')
        ];
        
        $bookings = $this->bookingModel->getBookings($page, 20, $filters);
        
        $data = [
            'bookings' => $bookings,
            'filters' => $filters,
            'current_page' => $page,
            'csrf_token' => Security::generateCsrfToken()
        ];
        
        $this->view('booking/index', $data);
    }
    
    /**
     * Create new booking (AJAX)
     */
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid request method'], 405);
        }
        
        $required = ['customerName', 'contactNumber', 'bookingDate', 'bookingTime', 'totalPerson', 'advancePaid', 'takenBy', 'paymentMethod', 'eventType', 'ISDJ', 'bookingType'];
        $data = $this->getPostData($required);
        
        // Validate data
        if (!Security::validateDate($data['bookingDate'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid date format']);
        }
        
        if (!Security::validateTime($data['bookingTime'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid time format']);
        }
        
        if (!Security::validateNumeric($data['advancePaid'], 0)) {
            $this->json(['status' => 'error', 'message' => 'Invalid advance payment amount']);
        }
        
        if (!Security::validateNumeric($data['totalPerson'], 1)) {
            $this->json(['status' => 'error', 'message' => 'Invalid number of persons']);
        }
        
        try {
            $bookingId = $this->bookingModel->createBooking($data);
            if ($bookingId) {
                $this->json(['status' => 'success', 'message' => 'Booking created successfully', 'booking_id' => $bookingId]);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to create booking']);
            }
        } catch (Exception $e) {
            error_log("Booking creation error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while creating the booking']);
        }
    }
    
    /**
     * Get booking details (AJAX)
     */
    public function details() {
        $bookingId = Security::sanitizeInput($_POST['booking_id'] ?? '');
        
        if (empty($bookingId)) {
            $this->json(['status' => 'error', 'message' => 'Booking ID required']);
        }
        
        $booking = $this->bookingModel->findByBookingId($bookingId);
        
        if (!$booking) {
            $this->json(['status' => 'error', 'message' => 'Booking not found']);
        }
        
        $this->json(['status' => 'success', 'booking' => $booking]);
    }
    
    /**
     * Complete booking (AJAX)
     */
    public function complete() {
        $this->requireRole(['admin', 'super_admin']);
        
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $finalAmount = (float)($_POST['final_amount'] ?? 0);
        $notes = Security::sanitizeInput($_POST['notes'] ?? '');
        
        if (!$bookingId) {
            $this->json(['status' => 'error', 'message' => 'Invalid booking ID']);
        }
        
        if (!Security::validateNumeric($finalAmount, 0)) {
            $this->json(['status' => 'error', 'message' => 'Invalid final amount']);
        }
        
        try {
            $result = $this->bookingModel->completeBooking($bookingId, $finalAmount, $notes);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Booking completed successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to complete booking']);
            }
        } catch (Exception $e) {
            error_log("Booking completion error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while completing the booking']);
        }
    }
    
    /**
     * Delete booking (AJAX)
     */
    public function delete() {
        $this->requireRole(['admin', 'super_admin']);
        
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        
        if (!$bookingId) {
            $this->json(['status' => 'error', 'message' => 'Invalid booking ID']);
        }
        
        try {
            $result = $this->bookingModel->delete($bookingId);
            if ($result) {
                $this->json(['status' => 'success', 'message' => 'Booking deleted successfully']);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to delete booking']);
            }
        } catch (Exception $e) {
            error_log("Booking deletion error: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'An error occurred while deleting the booking']);
        }
    }
    
    /**
     * Get bookings list (AJAX)
     */
    public function list() {
        $page = (int)($_POST['page'] ?? 1);
        $filters = [
            'date' => Security::sanitizeInput($_POST['date'] ?? ''),
            'status' => Security::sanitizeInput($_POST['status'] ?? ''),
            'search' => Security::sanitizeInput($_POST['search'] ?? '')
        ];
        
        $bookings = $this->bookingModel->getBookings($page, 20, $filters);
        
        $this->json(['status' => 'success', 'bookings' => $bookings]);
    }
}