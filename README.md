# BTS DISC 2.0 — Modern MVC Refactor

This project has been fully re-structured into a clean MVC architecture.

## Folder Structure

```
├── app
│   ├── core            # Framework kernel (Router, Controller, Model, Database, Security)
│   ├── controllers     # Application controllers
│   ├── models          # Database models / entities
│   └── views           # Blade-like PHP views (HTML templates)
│       └── auth
│           └── login.php
├── public              # Web root (DocumentRoot)
│   ├── index.php       # Front-controller — single entry point for all requests
│   └── assets
│       ├── css/style.css
│       └── js/script.js
├── config
│   └── config.php      # Non-public configuration (DB credentials, etc.)
└── uploads             # User-generated files (kept outside web root if possible)
```

> Point your web-server document-root to the **public/** directory.

## Getting Started

1. Copy **config/config.php.example** to **config/config.php** and enter your database credentials (already pre-filled for local usage).
2. Ensure PHP 8.1+ with PDO extension is enabled.
3. Configure your web-server (Apache/Nginx) to redirect all requests to **public/index.php** for pretty URLs.

## Security Highlights

* Hardened session cookies (HttpOnly & Secure flags, automatic regeneration).
* CSRF protection built-in (see `App\Core\Security`).
* All database operations use prepared statements via PDO.
* Output escaping via `htmlspecialchars()` in views to mitigate XSS.

## Performance & Optimisation

* Single minified global CSS/JS (split for production as needed).
* Lightweight Bootstrap 5 base ensuring responsive UI with minimal custom CSS.
* PSR-4 autoloading with opportunistic Composer support.

## Contribution

1. Fork / create a feature branch.
2. Write tests where applicable.
3. Create PR describing your changes.