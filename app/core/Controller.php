<?php
namespace App\Core;

class Controller
{
    protected function view(string $view, array $data = []): void
    {
        // Extract data into variables for the view
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        $viewPath = dirname(__DIR__) . "/views/{$view}.php";
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            http_response_code(404);
            echo "View not found: {$view}";
        }
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit();
    }
}