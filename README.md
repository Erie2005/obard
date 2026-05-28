# Stock Management System

A simple, secure web application for shopkeepers to manage their product inventory and stock movements.

## Features

- **Authentication**: Secure login and registration with password hashing (bcrypt)
- **Product Management**: Add, view, edit, and delete products
- **Stock In/Out**: Record stock-in (from suppliers) and stock-out (to customers) transactions
- **Dashboard**: Overview with statistics, low stock alerts, and recent activity
- **Validation**: Client-side and server-side input validation
- **Security**: Helmet.js headers, session-based auth, SQL injection prevention, XSS protection

## Database Tables

| Table | Description |
|-------|-------------|
| `shopkeeper` | Store owner accounts (login credentials) |
| `product` | Product catalog with pricing and stock levels |
| `product_in` | Stock-in records (goods received from suppliers) |
| `product_out` | Stock-out records (goods sold to customers) |

## Tech Stack

- **Backend**: Node.js, Express.js
- **Database**: SQLite (via better-sqlite3)
- **Security**: Helmet.js, bcryptjs, express-session, express-validator
- **Frontend**: Vanilla HTML/CSS/JavaScript, Font Awesome icons

## Setup

```bash
# Install dependencies
npm install

# Start the server
npm start

# Development mode (auto-restart)
npm run dev
```

The app runs at **http://localhost:3000**

## Default Login

| Field | Value |
|-------|-------|
| Email | admin@shop.com |
| Password | Admin@123 |

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login |
| POST | `/api/auth/register` | Register new account |
| POST | `/api/auth/logout` | Logout |

### Products
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/products` | List all products |
| GET | `/api/products/:id` | Get single product |
| POST | `/api/products` | Add new product |
| PUT | `/api/products/:id` | Update product |
| DELETE | `/api/products/:id` | Delete product |

### Stock
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/stock/in` | Record stock-in |
| POST | `/api/stock/out` | Record stock-out |
| GET | `/api/stock/records` | Get all stock records |
| GET | `/api/stock/in` | Get stock-in records |
| GET | `/api/stock/out` | Get stock-out records |
| GET | `/api/stock/dashboard` | Dashboard statistics |
