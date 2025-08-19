# BTS DISC 2.0 - Deployment Guide

## 🚀 Quick Start

### Method 1: Automated Deployment
```bash
php deploy.php
```

### Method 2: Manual Setup

1. **Configure Database**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

2. **Import Database Schema**
   ```bash
   mysql -u username -p database_name < database/schema.sql
   ```

3. **Set Permissions**
   ```bash
   chmod 755 public/uploads public/assets
   chmod 640 .env config/database.php
   ```

4. **Configure Web Server**
   - Point document root to `/public` directory
   - Ensure mod_rewrite is enabled (Apache)

## 🌐 Web Server Configuration

### Apache (.htaccess included)
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /path/to/bts-disc/public
    
    <Directory /path/to/bts-disc/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/bts-disc/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🔒 Security Checklist

- [ ] Change default admin password
- [ ] Set proper file permissions
- [ ] Configure SSL/HTTPS
- [ ] Enable firewall
- [ ] Regular security updates
- [ ] Database backup strategy

## 📊 Default Login

- **Username:** admin
- **Password:** admin123
- **Role:** Super Admin

⚠️ **Important:** Change the default password immediately after first login!

## 🛠️ Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `.env`
   - Ensure MySQL service is running
   - Check database exists and user has permissions

2. **Permission Denied**
   - Set proper file permissions: `chmod 755 public/uploads`
   - Ensure web server user can write to uploads directory

3. **404 Errors**
   - Verify web server document root points to `/public`
   - Ensure mod_rewrite is enabled (Apache)
   - Check .htaccess file exists in public directory

4. **Session Issues**
   - Verify PHP session configuration
   - Check session directory permissions
   - Ensure cookies are enabled

### Performance Optimization

1. **Enable OPcache**
   ```php
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=4000
   ```

2. **Database Optimization**
   - Regular OPTIMIZE TABLE commands
   - Monitor slow query log
   - Add indexes for frequently queried columns

3. **Caching**
   - Enable browser caching
   - Use CDN for static assets
   - Implement Redis/Memcached if needed

## 📁 File Structure

```
bts-disc-2.0/
├── app/
│   ├── controllers/     # Application controllers
│   ├── models/         # Data models
│   └── views/          # View templates
├── core/               # Framework core files
├── config/             # Configuration files
├── database/           # Database schema and migrations
├── public/             # Web accessible files
│   ├── assets/         # CSS, JS, images
│   ├── uploads/        # User uploaded files
│   └── index.php       # Application entry point
├── backup/             # Backup of old files
├── .env.example        # Environment template
├── .htaccess          # Apache configuration
├── deploy.php         # Deployment script
└── README.md          # Documentation
```

## 🔄 Updates and Maintenance

### Regular Tasks
- Database backups (daily)
- Log file rotation
- Security updates
- Performance monitoring

### Backup Strategy
```bash
# Database backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# File backup
tar -czf backup_files_$(date +%Y%m%d).tar.gz public/uploads/
```

## 📞 Support

For technical support and customization:
- **Website:** [SoluServ.in](https://www.SoluServ.in)
- **Email:** support@soluserv.in

---

**BTS DISC 2.0** - Professional Business Management System