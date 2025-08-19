<?php
namespace App\Core;

class Config {
    private static $config = [];
    
    public static function load() {
        $envFile = dirname(dirname(__DIR__)) . '/.env';
        
        if (!file_exists($envFile)) {
            throw new \Exception('.env file not found');
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }
            
            self::$config[$name] = $value;
            putenv("$name=$value");
        }
    }
    
    public static function get($key, $default = null) {
        return self::$config[$key] ?? $default;
    }
    
    public static function all() {
        return self::$config;
    }
}