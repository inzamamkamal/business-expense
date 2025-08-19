# BTS DISC 2.0 Application

A modern, secure, and professional business management system built with PHP following MVC architecture principles.

## Features

### 🔐 Security
- CSRF protection on all forms
- XSS prevention with input/output sanitization
- SQL injection prevention with prepared statements
- Secure session management
- Rate limiting for login attempts
- Input validation and sanitization

### 🎨 Modern UI/UX
- Clean, responsive design with Bootstrap-inspired components
- Mobile-first approach
- Dark/light theme support
- Intuitive user interface
- Modern typography and spacing
- Smooth animations and transitions

### 📊 Core Modules
- **Dashboard** - Overview with statistics and quick actions
- **Party Bookings** - Event management and booking system
- **Staff Management** - Employee records and information
- **Attendance** - Staff attendance tracking
- **Income Management** - Revenue tracking and reporting
- **Expense Management** - Business expense recording
- **Settlement** - Staff payment and settlement tracking

### 🏗️ Architecture
- **MVC Pattern** - Clean separation of concerns
- **RESTful API** - AJAX-powered interactions
- **Database Layer** - Secure PDO-based data access
- **Authentication** - Role-based access control
- **Routing** - Clean URL structure
- **Template System** - Reusable view components

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled

### Setup Steps

1. **Clone/Download** the project files to your web server
2. **Configure Database** - Update `config/database.php` or create `.env` file
3. **Set Permissions** - Ensure `public/uploads/` is writable
4. **Configure Web Server** - Point document root to `/public` directory
5. **Import Database** - Create required tables (see database schema)
6. **Access Application** - Visit your domain to start using the system

### Environment Configuration

Create a `.env` file in the root directory:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_username
DB_PASS=your_password

# Application Configuration
APP_URL=http://yourdomain.com
DEBUG=false

# Security
SESSION_TIMEOUT=7200
```

### Web Server Configuration

#### Apache
The included `.htaccess` file handles URL rewriting and security headers.

#### Nginx
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/project/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Handle PHP files
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Handle static files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Handle routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Deny access to sensitive files
    location ~ /\.(env|htaccess|gitignore) {
        deny all;
    }
}
```

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super_admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Bookings Table
```sql
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(15) NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    total_person INT NOT NULL,
    advance_paid DECIMAL(10,2) NOT NULL,
    final_amount DECIMAL(10,2) NULL,
    event_type VARCHAR(50) NOT NULL,
    booking_type VARCHAR(20) NOT NULL,
    is_dj BOOLEAN DEFAULT FALSE,
    payment_method VARCHAR(20) NOT NULL,
    taken_by VARCHAR(50) NOT NULL,
    special_requests TEXT,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL
);
```

### Staff Table
```sql
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(15) NOT NULL,
    position VARCHAR(50) NOT NULL,
    salary DECIMAL(10,2) DEFAULT 0,
    hire_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Attendance Table
```sql
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'half_day') NOT NULL,
    check_in_time TIME NULL,
    check_out_time TIME NULL,
    notes TEXT,
    marked_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    UNIQUE KEY unique_staff_date (staff_id, date)
);
```

### Income Table
```sql
CREATE TABLE income (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    payment_method VARCHAR(20) DEFAULT 'cash',
    received_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Expenses Table
```sql
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL,
    description TEXT,
    vendor VARCHAR(100),
    payment_method VARCHAR(20) DEFAULT 'cash',
    approved_by VARCHAR(50) NOT NULL,
    receipt_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Settlements Table
```sql
CREATE TABLE settlements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    settlement_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('salary', 'bonus', 'advance', 'deduction') NOT NULL,
    description TEXT,
    payment_method VARCHAR(20) DEFAULT 'cash',
    processed_by VARCHAR(50) NOT NULL,
    status ENUM('completed', 'pending') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);
```

## Usage

### Default Login
- Username: `admin`
- Password: `admin123`

### User Roles
- **Super Admin** - Full system access
- **Admin** - Most features except user management
- **User** - Limited access to bookings, attendance, and expenses

### Key Features

#### Dashboard
- Quick overview of system statistics
- Recent activity feed
- Quick action buttons
- Role-based content display

#### Bookings Management
- Create new party bookings
- Track event details and customer information
- Manage advance payments and final settlements
- Filter and search bookings
- Export booking data

#### Staff Management
- Add/edit staff members
- Track employment details
- Manage staff status (active/inactive)
- View staff statistics

#### Attendance Tracking
- Daily attendance marking
- Bulk attendance operations
- Attendance reports and statistics
- Staff-wise attendance summaries

#### Financial Management
- Income tracking with categorization
- Expense management with approval workflow
- Staff settlement processing
- Financial reports and summaries

## Security Considerations

### Input Validation
- All user inputs are validated and sanitized
- Type checking for numeric values
- Date/time format validation
- Email and phone number validation

### Authentication & Authorization
- Secure session management
- Role-based access control
- Session timeout handling
- CSRF token validation

### Database Security
- Prepared statements prevent SQL injection
- Input sanitization before database operations
- Secure database connection configuration

### File Security
- Upload directory outside web root
- File type validation
- Size limits on uploads
- Secure file naming

## Performance Optimizations

### Frontend
- Minified CSS and JavaScript
- Image optimization
- Browser caching headers
- Gzip compression

### Backend
- Database query optimization
- Efficient data pagination
- Minimal database calls
- Proper indexing strategies

### Caching
- Static asset caching
- Database query result caching
- Session data optimization

## Customization

### Theming
- CSS custom properties for easy color changes
- Responsive design breakpoints
- Component-based styling
- Dark/light theme support

### Extending Functionality
- Add new modules following MVC pattern
- Extend existing models with additional methods
- Create custom controllers for new features
- Add new views with consistent styling

### Configuration
- Environment-based configuration
- Feature toggles
- Customizable business rules
- Flexible reporting parameters

## Support

For technical support and customization services, contact:
- **Website**: [SoluServ.in](https://www.SoluServ.in)
- **Email**: support@soluserv.in

## License

This software is proprietary and confidential. Unauthorized copying, distribution, or modification is strictly prohibited.

---

**BTS DISC 2.0** - Professional Business Management System  
*Powered by SoluServ.in*