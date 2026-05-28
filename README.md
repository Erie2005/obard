# Stock Management System

A simple, secure web application for shopkeepers to manage their product inventory and stock movements. Built with **PHP** and styled with **Tailwind CSS**.

## Features

- **Authentication**: Secure login & registration with `password_hash()` (bcrypt), session management, and CSRF protection
- **Product Management**: Add, view, edit, and delete products with validation
- **Stock In/Out**: Record stock-in (from suppliers) and stock-out (to customers) with automatic quantity updates
- **Dashboard**: Overview with statistics, low stock alerts, and recent activity
- **Search & Filter**: Search products and filter stock records by type
- **Responsive Design**: Mobile-friendly with Tailwind CSS
- **Security**: CSRF tokens, XSS prevention (`htmlspecialchars`), prepared statements (SQL injection prevention), session regeneration

## Database Tables

| Table | Description |
|-------|-------------|
| `shopkeeper` | Store owner accounts (login credentials) |
| `product` | Product catalog with pricing and stock levels |
| `product_in` | Stock-in records (goods received from suppliers) |
| `product_out` | Stock-out records (goods sold to customers) |

## Tech Stack

- **Backend**: PHP 8.x (PDO for database)
- **Database**: SQLite (zero configuration)
- **Frontend**: Tailwind CSS (CDN), Font Awesome icons
- **Security**: CSRF tokens, bcrypt passwords, prepared statements

## Setup

```bash
# Clone the repository
git clone https://github.com/Erie2005/stock-management-system.git
cd stock-management-system

# Start the PHP development server
php -S localhost:8000

# Open in browser
# http://localhost:8000
```

> **Requirements**: PHP 8.0+ with `sqlite3` and `pdo_sqlite` extensions enabled.

## Default Login

| Field | Value |
|-------|-------|
| Email | `admin@shop.com` |
| Password | `Admin@123` |

## Project Structure

```
stock-management-system/
├── index.php              # Main router (entry point)
├── config/
│   ├── database.php       # Database connection & schema
│   └── helpers.php        # Helper functions (auth, validation, CSRF)
├── includes/
│   ├── header.php         # HTML head with Tailwind CSS
│   ├── sidebar.php        # Navigation sidebar
│   ├── alert.php          # Flash message component
│   └── footer.php         # HTML footer
├── pages/
│   ├── login.php          # Login page
│   ├── register.php       # Registration page
│   ├── dashboard.php      # Dashboard with stats
│   ├── products.php       # Product CRUD
│   └── stock.php          # Stock in/out records
├── data/                  # SQLite database (auto-created, gitignored)
├── .gitignore
└── README.md
```
