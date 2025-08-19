/**
 * BTS DISC 2.0 - Main Application JavaScript
 * Modern, secure, and optimized JavaScript for the application
 */

class BTSApp {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeComponents();
        this.setupFormValidation();
        this.setupAjaxDefaults();
    }

    /**
     * Setup global event listeners
     */
    setupEventListeners() {
        document.addEventListener('DOMContentLoaded', () => {
            this.initializeDatePickers();
            this.initializeTooltips();
            this.initializeModals();
        });

        // Handle form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ajax-form')) {
                e.preventDefault();
                this.handleAjaxForm(e.target);
            }
        });

        // Handle delete confirmations
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('delete-btn') || e.target.closest('.delete-btn')) {
                e.preventDefault();
                this.confirmDelete(e.target.closest('.delete-btn') || e.target);
            }
        });

        // Handle modal triggers
        document.addEventListener('click', (e) => {
            if (e.target.dataset.toggle === 'modal') {
                e.preventDefault();
                this.showModal(e.target.dataset.target);
            }
        });

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                this.fadeOut(alert);
            });
        }, 5000);
    }

    /**
     * Initialize components
     */
    initializeComponents() {
        // Initialize any specific components here
        this.initializeTables();
        this.initializeCharts();
    }

    /**
     * Setup AJAX defaults
     */
    setupAjaxDefaults() {
        // Add CSRF token to all AJAX requests
        const originalFetch = window.fetch;
        window.fetch = (url, options = {}) => {
            if (options.method && options.method !== 'GET') {
                options.headers = options.headers || {};
                options.headers['X-CSRF-TOKEN'] = this.csrfToken;
            }
            return originalFetch(url, options);
        };
    }

    /**
     * Handle AJAX form submissions
     */
    async handleAjaxForm(form) {
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn.textContent;
        
        try {
            // Show loading state
            this.setLoadingState(submitBtn, true);
            
            const formData = new FormData(form);
            formData.append('csrf_token', this.csrfToken);
            
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                this.showAlert(result.message, 'success');
                form.reset();
                
                // Refresh data if needed
                if (form.dataset.refresh) {
                    this.refreshData(form.dataset.refresh);
                }
                
                // Close modal if form is in modal
                const modal = form.closest('.modal');
                if (modal) {
                    this.hideModal(modal);
                }
            } else {
                this.showAlert(result.message || 'An error occurred', 'danger');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showAlert('An error occurred while processing your request', 'danger');
        } finally {
            this.setLoadingState(submitBtn, false, originalText);
        }
    }

    /**
     * Set loading state for buttons
     */
    setLoadingState(button, loading, originalText = '') {
        if (loading) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner"></span> Processing...';
        } else {
            button.disabled = false;
            button.innerHTML = originalText || button.innerHTML.replace(/<span class="spinner"><\/span>\s*Processing\.\.\./, '');
        }
    }

    /**
     * Show confirmation dialog for delete actions
     */
    confirmDelete(button) {
        const message = button.dataset.message || 'Are you sure you want to delete this item?';
        const title = button.dataset.title || 'Confirm Delete';
        
        this.showConfirmDialog(title, message, async () => {
            try {
                const formData = new FormData();
                formData.append('csrf_token', this.csrfToken);
                
                // Add any data attributes as form data
                Object.keys(button.dataset).forEach(key => {
                    if (key !== 'message' && key !== 'title') {
                        formData.append(key, button.dataset[key]);
                    }
                });
                
                const response = await fetch(button.href || button.dataset.url, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    this.showAlert(result.message, 'success');
                    
                    // Remove the row or refresh data
                    const row = button.closest('tr');
                    if (row) {
                        this.fadeOut(row, () => row.remove());
                    } else if (button.dataset.refresh) {
                        this.refreshData(button.dataset.refresh);
                    }
                } else {
                    this.showAlert(result.message || 'Failed to delete item', 'danger');
                }
            } catch (error) {
                console.error('Delete error:', error);
                this.showAlert('An error occurred while deleting', 'danger');
            }
        });
    }

    /**
     * Show confirmation dialog
     */
    showConfirmDialog(title, message, onConfirm) {
        const modal = document.createElement('div');
        modal.className = 'modal show';
        modal.innerHTML = `
            <div class="modal-backdrop"></div>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${this.escapeHtml(title)}</h5>
                        <button type="button" class="btn-close" data-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${this.escapeHtml(message)}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger confirm-btn">Delete</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Handle modal events
        modal.querySelector('.confirm-btn').addEventListener('click', () => {
            onConfirm();
            this.hideModal(modal);
        });
        
        modal.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', () => this.hideModal(modal));
        });
        
        modal.querySelector('.modal-backdrop').addEventListener('click', () => {
            this.hideModal(modal);
        });
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'info') {
        const alertContainer = document.querySelector('.alert-container') || document.body;
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible`;
        alert.innerHTML = `
            ${this.escapeHtml(message)}
            <button type="button" class="btn-close" data-dismiss="alert"></button>
        `;
        
        alertContainer.insertBefore(alert, alertContainer.firstChild);
        
        // Auto-hide after 5 seconds
        setTimeout(() => this.fadeOut(alert), 5000);
        
        // Handle close button
        alert.querySelector('.btn-close').addEventListener('click', () => {
            this.fadeOut(alert);
        });
    }

    /**
     * Show modal
     */
    showModal(selector) {
        const modal = document.querySelector(selector);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Hide modal
     */
    hideModal(modal) {
        if (typeof modal === 'string') {
            modal = document.querySelector(modal);
        }
        
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
            
            // Remove dynamically created modals
            if (modal.parentElement === document.body) {
                setTimeout(() => modal.remove(), 300);
            }
        }
    }

    /**
     * Initialize modals
     */
    initializeModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            // Close modal when clicking backdrop
            modal.querySelector('.modal-backdrop')?.addEventListener('click', () => {
                this.hideModal(modal);
            });
            
            // Close modal with close buttons
            modal.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
                btn.addEventListener('click', () => this.hideModal(modal));
            });
        });
    }

    /**
     * Initialize date pickers
     */
    initializeDatePickers() {
        document.querySelectorAll('input[type="date"]').forEach(input => {
            if (!input.value) {
                input.value = new Date().toISOString().split('T')[0];
            }
        });
    }

    /**
     * Initialize tooltips
     */
    initializeTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    /**
     * Show tooltip
     */
    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            pointer-events: none;
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        this.currentTooltip = tooltip;
    }

    /**
     * Hide tooltip
     */
    hideTooltip() {
        if (this.currentTooltip) {
            this.currentTooltip.remove();
            this.currentTooltip = null;
        }
    }

    /**
     * Initialize tables
     */
    initializeTables() {
        document.querySelectorAll('.data-table').forEach(table => {
            this.makeTableSortable(table);
            this.makeTableSearchable(table);
        });
    }

    /**
     * Make table sortable
     */
    makeTableSortable(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const column = header.dataset.sort;
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const isAsc = header.classList.contains('sort-asc');
                
                // Remove sort classes from all headers
                headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
                
                // Add sort class to current header
                header.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
                
                // Sort rows
                rows.sort((a, b) => {
                    const aVal = a.cells[header.cellIndex].textContent.trim();
                    const bVal = b.cells[header.cellIndex].textContent.trim();
                    
                    if (isAsc) {
                        return bVal.localeCompare(aVal, undefined, { numeric: true });
                    } else {
                        return aVal.localeCompare(bVal, undefined, { numeric: true });
                    }
                });
                
                // Reorder rows in table
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    }

    /**
     * Make table searchable
     */
    makeTableSearchable(table) {
        const searchInput = table.parentElement.querySelector('.table-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    /**
     * Initialize charts (placeholder for future chart implementation)
     */
    initializeCharts() {
        // Chart initialization will go here when needed
        console.log('Charts initialized');
    }

    /**
     * Setup form validation
     */
    setupFormValidation() {
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Validate form
     */
    validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }
        });
        
        // Email validation
        form.querySelectorAll('input[type="email"]').forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                this.showFieldError(field, 'Please enter a valid email address');
                isValid = false;
            }
        });
        
        // Phone validation
        form.querySelectorAll('input[type="tel"]').forEach(field => {
            if (field.value && !this.isValidPhone(field.value)) {
                this.showFieldError(field, 'Please enter a valid phone number');
                isValid = false;
            }
        });
        
        return isValid;
    }

    /**
     * Show field error
     */
    showFieldError(field, message) {
        this.clearFieldError(field);
        
        field.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        
        field.parentElement.appendChild(errorDiv);
    }

    /**
     * Clear field error
     */
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentElement.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    /**
     * Validate email
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate phone
     */
    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
    }

    /**
     * Refresh data
     */
    async refreshData(target) {
        try {
            const response = await fetch(target);
            const html = await response.text();
            
            const targetElement = document.querySelector(target);
            if (targetElement) {
                targetElement.innerHTML = html;
            }
        } catch (error) {
            console.error('Failed to refresh data:', error);
        }
    }

    /**
     * Fade out element
     */
    fadeOut(element, callback) {
        element.style.transition = 'opacity 0.3s ease';
        element.style.opacity = '0';
        
        setTimeout(() => {
            element.style.display = 'none';
            if (callback) callback();
        }, 300);
    }

    /**
     * Fade in element
     */
    fadeIn(element) {
        element.style.display = '';
        element.style.opacity = '0';
        element.style.transition = 'opacity 0.3s ease';
        
        setTimeout(() => {
            element.style.opacity = '1';
        }, 10);
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format currency
     */
    formatCurrency(amount, currency = 'INR') {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    /**
     * Format date
     */
    formatDate(date, format = 'short') {
        return new Intl.DateTimeFormat('en-IN', {
            dateStyle: format
        }).format(new Date(date));
    }

    /**
     * Debounce function
     */
    debounce(func, wait) {
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

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Initialize the application
const app = new BTSApp();

// Export for use in other scripts
window.BTSApp = app;