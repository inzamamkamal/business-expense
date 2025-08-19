# BTS DISC 2.0 - Hotel Management System

A modern, secure, and optimized hotel management system built with PHP using MVC architecture.

## 🚀 Features

- **Booking Management**: Complete booking system with guest management and room allocation
- **Staff Management**: Staff records, attendance tracking, and commission calculation
- **Financial Management**: Income and expense tracking with detailed categorization
- **Settlement System**: Automated monthly settlement generation
- **Reporting**: Comprehensive reports for bookings, finances, and staff performance
- **Security**: CSRF protection, input validation, and secure session management
- **Modern UI**: Clean, responsive design with mobile support

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server with mod_rewrite enabled
- Modern web browser with JavaScript enabled

## 🛠️ Installation

1. **Clone or download the repository** to your web server

2. **Configure your web server** to point to the `public` directory as the document root

3. **Set up the environment**:
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and update your database credentials

4. **Import your database** schema (ensure your database exists)

5. **Set appropriate permissions**:
   ```bash
   chmod 755 -R .
   chmod 777 -R public/uploads
   chmod 777 -R logs
   ```

6. **Configure Apache** (if using Apache):
   Ensure `.htaccess` files are allowed and mod_rewrite is enabled

## 📁 Directory Structure

```
btsapp/
├── app/
│   ├── controllers/    # Application controllers
│   ├── models/         # Database models  
│   ├── views/          # View templates
│   ├── core/           # Core framework classes
│   ├── helpers/        # Helper functions
│   └── middlewares/    # Middleware classes
├── public/             # Public web root
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   ├── images/        # Images
│   ├── uploads/       # User uploads
│   └── index.php      # Entry point
├── config/            # Configuration files
├── logs/              # Application logs
├── backup/            # Backup of old files
└── .env               # Environment configuration
```

## 🔒 Security Features

- **CSRF Protection**: All forms include CSRF token validation
- **SQL Injection Prevention**: Prepared statements for all queries
- **XSS Protection**: Output escaping and content security headers
- **Session Security**: Secure session handling with regeneration
- **Input Validation**: Server-side validation for all user inputs
- **Access Control**: Role-based access control system

## 💻 Usage

1. **Access the application** through your web browser at your configured URL

2. **Login** with your credentials

3. **Navigate** using the intuitive sidebar menu:
   - Dashboard: Overview of daily operations
   - Bookings: Manage hotel bookings
   - Staff: Manage staff and attendance
   - Income/Expenses: Track finances
   - Reports: View detailed analytics

## 🎨 Features Highlights

### Clean Modern UI
- Responsive design that works on all devices
- Intuitive navigation with collapsible sidebar
- Real-time search and filtering
- Beautiful stat cards and data visualizations

### Powerful Functionality
- Quick actions for common tasks
- Bulk operations support
- Export capabilities for reports
- Date locking to prevent unauthorized changes

### Performance Optimized
- Lightweight and fast loading
- Optimized database queries
- Client-side caching
- Minified assets

## 🔧 Configuration

### Environment Variables
Key settings in `.env`:
- `DB_*`: Database connection settings
- `APP_URL`: Application base URL
- `SESSION_*`: Session configuration
- `APP_DEBUG`: Debug mode (set to false in production)

### User Roles
- **Super Admin**: Full system access
- **Admin**: Administrative functions
- **User**: Basic operations

## 📝 Maintenance

### Regular Tasks
1. **Backup database** regularly
2. **Clear old logs** from the logs directory
3. **Review locked dates** monthly
4. **Update user permissions** as needed

### Troubleshooting
- **500 Error**: Check PHP error logs and database connection
- **404 Error**: Verify .htaccess and mod_rewrite
- **Login Issues**: Clear browser cache and check session settings
- **Permission Errors**: Verify file/folder permissions

## 🚦 API Endpoints

The application follows RESTful conventions:
- `GET /bookings` - List bookings
- `POST /bookings` - Create booking
- `GET /bookings/{id}` - View booking
- `PUT /bookings/{id}` - Update booking
- `DELETE /bookings/{id}` - Delete booking

Similar patterns for other resources.

## 📄 License

Proprietary - All rights reserved

## 👥 Support

For support, please contact your system administrator or development team.

---

**Version**: 2.0  
**Last Updated**: <?= date('F Y') ?>