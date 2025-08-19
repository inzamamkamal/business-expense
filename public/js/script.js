// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initSidebar();
    initModals();
    initTooltips();
    initFormValidation();
    initDataTables();
    initDatePickers();
    initCharts();
    initDeleteConfirmation();
    initFlashMessages();
});

// Sidebar Toggle
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleDesktop = document.getElementById('sidebarToggleDesktop');
    const toggleMobile = document.getElementById('sidebarToggleMobile');
    const closeMobile = document.getElementById('sidebarToggle');
    
    if (toggleDesktop) {
        toggleDesktop.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
    
    if (toggleMobile) {
        toggleMobile.addEventListener('click', function() {
            sidebar.classList.add('active');
        });
    }
    
    if (closeMobile) {
        closeMobile.addEventListener('click', function() {
            sidebar.classList.remove('active');
        });
    }
    
    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
    }
    
    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && 
            sidebar.classList.contains('active') && 
            !sidebar.contains(e.target) && 
            !toggleMobile.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    });
}

// Modal Management
function initModals() {
    // Open modal
    document.querySelectorAll('[data-modal-trigger]').forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal-trigger');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    // Close modal
    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(closer => {
        closer.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close on outside click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.active');
            if (activeModal) {
                activeModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });
}

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const text = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = text;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
            tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateInput(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    validateInput(this);
                }
            });
        });
    });
}

function validateForm(form) {
    const inputs = form.querySelectorAll('[required], [data-validate-rules]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateInput(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateInput(input) {
    const value = input.value.trim();
    const rules = input.getAttribute('data-validate-rules');
    let isValid = true;
    let errorMessage = '';
    
    // Required validation
    if (input.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'This field is required';
    }
    
    // Email validation
    if (isValid && input.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
    }
    
    // Number validation
    if (isValid && input.type === 'number' && value) {
        const min = parseFloat(input.getAttribute('min'));
        const max = parseFloat(input.getAttribute('max'));
        const numValue = parseFloat(value);
        
        if (!isNaN(min) && numValue < min) {
            isValid = false;
            errorMessage = `Value must be at least ${min}`;
        } else if (!isNaN(max) && numValue > max) {
            isValid = false;
            errorMessage = `Value must not exceed ${max}`;
        }
    }
    
    // Custom rules
    if (isValid && rules) {
        const ruleList = rules.split('|');
        for (const rule of ruleList) {
            if (rule === 'phone' && value) {
                const phoneRegex = /^\d{10,15}$/;
                if (!phoneRegex.test(value.replace(/\D/g, ''))) {
                    isValid = false;
                    errorMessage = 'Please enter a valid phone number';
                    break;
                }
            }
        }
    }
    
    // Show/hide error
    const formGroup = input.closest('.form-group');
    const errorElement = formGroup ? formGroup.querySelector('.form-error') : null;
    
    if (!isValid) {
        input.classList.add('error');
        if (errorElement) {
            errorElement.textContent = errorMessage;
            errorElement.style.display = 'block';
        } else if (formGroup) {
            const error = document.createElement('div');
            error.className = 'form-error';
            error.textContent = errorMessage;
            formGroup.appendChild(error);
        }
    } else {
        input.classList.remove('error');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }
    
    return isValid;
}

// Data Tables
function initDataTables() {
    const tables = document.querySelectorAll('[data-datatable]');
    
    tables.forEach(table => {
        // Add search functionality
        const searchInput = document.querySelector(`[data-table-search="${table.id}"]`);
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterTable(table, this.value);
            });
        }
        
        // Add sorting
        const headers = table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(table, this);
            });
        });
    });
}

function filterTable(table, searchTerm) {
    const rows = table.querySelectorAll('tbody tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

function sortTable(table, header) {
    const columnIndex = Array.from(header.parentNode.children).indexOf(header);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isAscending = header.classList.contains('sort-asc');
    
    // Remove sort classes from other headers
    table.querySelectorAll('th').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    
    // Sort rows
    rows.sort((a, b) => {
        const aValue = a.children[columnIndex].textContent;
        const bValue = b.children[columnIndex].textContent;
        
        // Try to parse as number
        const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? bNum - aNum : aNum - bNum;
        }
        
        // Sort as text
        return isAscending ? 
            bValue.localeCompare(aValue) : 
            aValue.localeCompare(bValue);
    });
    
    // Update DOM
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicator
    header.classList.toggle('sort-asc', !isAscending);
    header.classList.toggle('sort-desc', isAscending);
}

// Date Pickers
function initDatePickers() {
    const datePickers = document.querySelectorAll('input[type="date"]');
    
    datePickers.forEach(picker => {
        // Set max date to today if specified
        if (picker.hasAttribute('data-max-today')) {
            picker.max = new Date().toISOString().split('T')[0];
        }
        
        // Set default value if specified
        if (picker.hasAttribute('data-default-today') && !picker.value) {
            picker.value = new Date().toISOString().split('T')[0];
        }
    });
}

// Charts (placeholder for chart initialization)
function initCharts() {
    // Initialize any charts on the page
    // This would integrate with a charting library like Chart.js
}

// Delete Confirmation
function initDeleteConfirmation() {
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-confirm-delete]')) {
            e.preventDefault();
            
            const message = e.target.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
            
            if (confirm(message)) {
                const url = e.target.getAttribute('href') || e.target.getAttribute('data-url');
                const method = e.target.getAttribute('data-method') || 'DELETE';
                
                if (method === 'GET') {
                    window.location.href = url;
                } else {
                    // Send AJAX request
                    fetch(url, {
                        method: method,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Remove row or reload page
                            const row = e.target.closest('tr');
                            if (row) {
                                row.remove();
                            } else {
                                location.reload();
                            }
                            
                            showNotification(data.message || 'Item deleted successfully', 'success');
                        } else {
                            showNotification(data.message || 'Failed to delete item', 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('An error occurred', 'error');
                    });
                }
            }
        }
    });
}

// Flash Messages
function initFlashMessages() {
    const flashMessages = document.querySelectorAll('#flashMessage');
    
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 300);
        }, 5000);
    });
}

// Show Notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Insert after header
    const header = document.querySelector('.app-header');
    if (header && header.nextSibling) {
        header.parentNode.insertBefore(notification, header.nextSibling);
    } else {
        document.body.appendChild(notification);
    }
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// AJAX Form Submit
function submitForm(form, callback) {
    const formData = new FormData(form);
    const url = form.action;
    const method = form.method || 'POST';
    
    // Show loading
    const submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
    
    fetch(url, {
        method: method,
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (callback) {
            callback(data);
        } else if (data.redirect) {
            window.location.href = data.redirect;
        } else if (data.message) {
            showNotification(data.message, data.status || 'info');
        }
    })
    .catch(error => {
        showNotification('An error occurred', 'error');
    })
    .finally(() => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'Submit';
        }
    });
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatCurrency(amount, symbol = '₹') {
    return symbol + ' ' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatDate(date, format = 'DD/MM/YYYY') {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    
    return format
        .replace('DD', day)
        .replace('MM', month)
        .replace('YYYY', year);
}

// Export functions for use in other scripts
window.BTS = {
    showNotification,
    submitForm,
    formatCurrency,
    formatDate,
    debounce
};