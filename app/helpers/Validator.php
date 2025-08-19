<?php
namespace App\Helpers;

class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public function validate($rules) {
        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            $rulesArray = explode('|', $fieldRules);
            
            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    private function applyRule($field, $value, $rule) {
        $params = [];
        
        if (strpos($rule, ':') !== false) {
            list($rule, $paramStr) = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }
        
        $methodName = 'validate' . ucfirst($rule);
        
        if (method_exists($this, $methodName)) {
            $this->$methodName($field, $value, $params);
        }
    }
    
    private function validateRequired($field, $value, $params) {
        if (is_null($value) || $value === '') {
            $this->addError($field, "$field is required");
        }
    }
    
    private function validateEmail($field, $value, $params) {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "$field must be a valid email address");
        }
    }
    
    private function validateMin($field, $value, $params) {
        $min = $params[0] ?? 0;
        
        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, "$field must be at least $min characters");
        } elseif (is_numeric($value) && $value < $min) {
            $this->addError($field, "$field must be at least $min");
        }
    }
    
    private function validateMax($field, $value, $params) {
        $max = $params[0] ?? PHP_INT_MAX;
        
        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, "$field must not exceed $max characters");
        } elseif (is_numeric($value) && $value > $max) {
            $this->addError($field, "$field must not exceed $max");
        }
    }
    
    private function validateNumeric($field, $value, $params) {
        if ($value && !is_numeric($value)) {
            $this->addError($field, "$field must be numeric");
        }
    }
    
    private function validateAlpha($field, $value, $params) {
        if ($value && !ctype_alpha($value)) {
            $this->addError($field, "$field must contain only letters");
        }
    }
    
    private function validateAlphanumeric($field, $value, $params) {
        if ($value && !ctype_alnum($value)) {
            $this->addError($field, "$field must contain only letters and numbers");
        }
    }
    
    private function validateDate($field, $value, $params) {
        $format = $params[0] ?? 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $value);
        
        if (!$d || $d->format($format) !== $value) {
            $this->addError($field, "$field must be a valid date");
        }
    }
    
    private function validateIn($field, $value, $params) {
        if ($value && !in_array($value, $params)) {
            $this->addError($field, "$field must be one of: " . implode(', ', $params));
        }
    }
    
    private function validateUnique($field, $value, $params) {
        // This would require database check
        // Implementation depends on your model structure
    }
    
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getFirstError($field = null) {
        if ($field) {
            return $this->errors[$field][0] ?? null;
        }
        
        foreach ($this->errors as $errors) {
            if (!empty($errors)) {
                return $errors[0];
            }
        }
        
        return null;
    }
}