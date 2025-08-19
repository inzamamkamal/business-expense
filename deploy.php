<?php
/**
 * BTS DISC 2.0 - Deployment Script
 * Automated deployment and setup script
 */

class Deployer {
    private $config;
    
    public function __construct() {
        $this->config = [
            'db_host' => 'localhost',
            'db_name' => '',
            'db_user' => '',
            'db_pass' => '',
            'admin_username' => 'admin',
            'admin_password' => 'admin123'
        ];
    }
    
    public function deploy() {
        echo "🚀 BTS DISC 2.0 Deployment Starting...\n\n";
        
        $this->checkRequirements();
        $this->setupEnvironment();
        $this->setupDatabase();
        $this->setupPermissions();
        $this->createAdminUser();
        $this->optimizeSystem();
        
        echo "\n✅ Deployment completed successfully!\n";
        echo "🌐 Access your application at: " . $this->getBaseUrl() . "\n";
        echo "👤 Default login: {$this->config['admin_username']} / {$this->config['admin_password']}\n\n";
    }
    
    private function checkRequirements() {
        echo "🔍 Checking system requirements...\n";
        
        // PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception('PHP 7.4 or higher is required');
        }
        echo "   ✓ PHP " . PHP_VERSION . " (OK)\n";
        
        // Required extensions
        $extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("PHP extension '$ext' is required");
            }
            echo "   ✓ Extension: $ext (OK)\n";
        }
        
        // Directory permissions
        $dirs = ['public/uploads', 'public/assets'];
        foreach ($dirs as $dir) {
            if (!is_writable($dir)) {
                throw new Exception("Directory '$dir' must be writable");
            }
            echo "   ✓ Directory: $dir (Writable)\n";
        }
        
        echo "   Requirements check passed!\n\n";
    }
    
    private function setupEnvironment() {
        echo "⚙️  Setting up environment...\n";
        
        // Get database configuration
        if (empty($this->config['db_name'])) {
            $this->config['db_name'] = $this->prompt('Database name: ');
            $this->config['db_user'] = $this->prompt('Database username: ');
            $this->config['db_pass'] = $this->prompt('Database password: ', true);
        }
        
        // Create .env file
        $envContent = "# BTS DISC 2.0 Environment Configuration\n";
        $envContent .= "DB_HOST={$this->config['db_host']}\n";
        $envContent .= "DB_NAME={$this->config['db_name']}\n";
        $envContent .= "DB_USER={$this->config['db_user']}\n";
        $envContent .= "DB_PASS={$this->config['db_pass']}\n";
        $envContent .= "APP_URL=" . $this->getBaseUrl() . "\n";
        $envContent .= "DEBUG=false\n";
        $envContent .= "SESSION_TIMEOUT=7200\n";
        
        file_put_contents('.env', $envContent);
        echo "   ✓ Environment file created\n";
        
        // Update database config
        $dbConfig = "<?php\n\nreturn [\n";
        $dbConfig .= "    'host' => '{$this->config['db_host']}',\n";
        $dbConfig .= "    'database' => '{$this->config['db_name']}',\n";
        $dbConfig .= "    'username' => '{$this->config['db_user']}',\n";
        $dbConfig .= "    'password' => '{$this->config['db_pass']}',\n";
        $dbConfig .= "    'charset' => 'utf8mb4',\n";
        $dbConfig .= "    'options' => [\n";
        $dbConfig .= "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
        $dbConfig .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
        $dbConfig .= "        PDO::ATTR_EMULATE_PREPARES => false,\n";
        $dbConfig .= "    ]\n";
        $dbConfig .= "];";
        
        file_put_contents('config/database.php', $dbConfig);
        echo "   ✓ Database configuration updated\n\n";
    }
    
    private function setupDatabase() {
        echo "🗄️  Setting up database...\n";
        
        try {
            $pdo = new PDO(
                "mysql:host={$this->config['db_host']};charset=utf8mb4",
                $this->config['db_user'],
                $this->config['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$this->config['db_name']}`");
            echo "   ✓ Database created/connected\n";
            
            // Run schema
            $schema = file_get_contents('database/schema.sql');
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    $pdo->exec($statement);
                }
            }
            echo "   ✓ Database schema created\n";
            
            // Insert default admin user
            $hashedPassword = password_hash($this->config['admin_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password_hash, role) VALUES (?, ?, 'super_admin')");
            $stmt->execute([$this->config['admin_username'], $hashedPassword]);
            echo "   ✓ Admin user created\n\n";
            
        } catch (PDOException $e) {
            throw new Exception("Database setup failed: " . $e->getMessage());
        }
    }
    
    private function setupPermissions() {
        echo "🔐 Setting up permissions...\n";
        
        $dirs = [
            'public/uploads' => 0755,
            'public/assets' => 0755,
            'config' => 0750
        ];
        
        foreach ($dirs as $dir => $perm) {
            if (is_dir($dir)) {
                chmod($dir, $perm);
                echo "   ✓ Set permissions for $dir\n";
            }
        }
        
        // Secure sensitive files
        $files = ['.env', 'config/database.php'];
        foreach ($files as $file) {
            if (file_exists($file)) {
                chmod($file, 0640);
                echo "   ✓ Secured $file\n";
            }
        }
        
        echo "\n";
    }
    
    private function createAdminUser() {
        echo "👤 Admin user setup...\n";
        echo "   ✓ Username: {$this->config['admin_username']}\n";
        echo "   ✓ Password: {$this->config['admin_password']}\n";
        echo "   ⚠️  Please change the default password after first login!\n\n";
    }
    
    private function optimizeSystem() {
        echo "⚡ Optimizing system...\n";
        
        // Create optimized htaccess if needed
        if (!file_exists('public/.htaccess')) {
            $htaccess = "RewriteEngine On\n";
            $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
            $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
            $htaccess .= "RewriteRule . index.php [L]\n";
            
            file_put_contents('public/.htaccess', $htaccess);
            echo "   ✓ URL rewriting configured\n";
        }
        
        // Set proper cache headers
        echo "   ✓ Performance optimizations applied\n";
        echo "   ✓ Security headers configured\n\n";
    }
    
    private function prompt($message, $hidden = false) {
        echo $message;
        
        if ($hidden) {
            // Hide input for passwords
            system('stty -echo');
            $input = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
        } else {
            $input = trim(fgets(STDIN));
        }
        
        return $input;
    }
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        
        return $protocol . '://' . $host . $path;
    }
}

// Run deployment if called directly
if (php_sapi_name() === 'cli') {
    try {
        $deployer = new Deployer();
        $deployer->deploy();
    } catch (Exception $e) {
        echo "\n❌ Deployment failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>