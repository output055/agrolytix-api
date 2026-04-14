# 🌿 Agrolytix API — Backend

**Agrolytix API** is the Laravel 11 REST API powering the Agrolytix agribusiness inventory management system. It handles authentication, product management, point-of-sale operations, sales tracking, debt management, and financial reporting.

> **Frontend**: Paired with [`agrolytix`](../agrolytix) — Angular 21 SPA.

---

## 🛠 Tech Stack

| | |
|---|---|
| Framework | Laravel 11 |
| Auth | Laravel Sanctum (token-based) |
| Database | MySQL (`agrolytix_api`) |
| Port | `8001` (`php artisan serve --port=8001`) |

---

## 🚀 Getting Started

### Prerequisites
- PHP ≥ 8.2
- Composer
- MySQL (XAMPP or native)

### Install & Run
```bash
composer install
cp .env.example .env      # already configured for agrolytix_api DB
php artisan key:generate
php artisan migrate
php artisan db:seed        # seeds admin user
php artisan serve --port=8001
```

### Database
- DB Name: `agrolytix_api`
- DB User: `root` / no password (XAMPP default)
- Create the database in phpMyAdmin or via:
  ```sql
  CREATE DATABASE agrolytix_api;
  ```

---

## 👤 Roles

| Role | Permissions |
|---|---|
| **Admin** | Full access to all endpoints |
| **Worker** | POS (checkout), sales read, debt payment only |

---

## 📋 API Endpoints

### Auth
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/login` | Login — returns token + user |
| POST | `/api/logout` | Invalidate token |
| GET | `/api/user` | Get authenticated user |

### Dashboard
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/dashboard/stats` | Today's sales, revenue, profit, debts, low-stock count |

### Retail Inventory
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/products` | List all retail products |
| POST | `/api/products` | Create product (Admin) |
| PUT | `/api/products/{id}` | Update product (Admin) |
| DELETE | `/api/products/{id}` | Delete product (Admin) |
| PATCH | `/api/products/{id}/restock` | Add stock quantity (Admin) |

### Wholesale Inventory
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/wholesale-products` | List wholesale products |
| POST | `/api/wholesale-products` | Create (Admin) |
| PUT | `/api/wholesale-products/{id}` | Update (Admin) |
| DELETE | `/api/wholesale-products/{id}` | Delete (Admin) |
| PATCH | `/api/wholesale-products/{id}/restock` | Restock (Admin) |

### Retail POS
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/pos/retail/checkout` | Process retail checkout, deduct stock, save sales |

### Wholesale POS
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/pos/wholesale/checkout` | Process wholesale checkout, track client debt |

### Sales
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/sales/retail` | Retail sales history (filterable by date) |
| GET | `/api/sales/wholesale` | Wholesale sales history |
| POST | `/api/sales/wholesale/{id}/pay` | Record debt payment |

### Reversals
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/reversals` | Reverse a retail sale, restore stock (Admin) |
| GET | `/api/reversals` | List all reversals (Admin) |

### Clients
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/clients` | List wholesale clients (Admin) |
| POST | `/api/clients` | Add client (Admin) |
| PUT | `/api/clients/{id}` | Update client (Admin) |
| DELETE | `/api/clients/{id}` | Delete client (Admin) |

### Workers
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/workers` | List workers (Admin) |
| POST | `/api/workers` | Create worker account (Admin) |
| PUT | `/api/workers/{id}` | Update worker (Admin) |
| PATCH | `/api/workers/{id}/status` | Activate/deactivate (Admin) |
| DELETE | `/api/workers/{id}` | Remove worker (Admin) |

### Reports
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/reports/financial` | Revenue, profit, debts by date range (Admin) |

---

## 🗂 Database Schema

| Table | Purpose |
|---|---|
| `users` | Admin + worker accounts |
| `personal_access_tokens` | Sanctum tokens |
| `products` | Retail product catalog & stock |
| `product_units` | Unit/bulk pricing variants per product |
| `wholesale_products` | Wholesale product catalog & stock |
| `wholesale_product_units` | Unit pricing for wholesale |
| `retail_sales` | Retail checkout records |
| `retail_sale_items` | Line items per retail sale |
| `wholesale_sales` | Wholesale checkout records |
| `wholesale_sale_items` | Line items per wholesale sale |
| `client_sales` | Wholesale invoice headers |
| `clients` | Wholesale customer accounts |
| `reversals` | Reversed retail sale log |
| `debts` | Wholesale debt payment history |

---

## 📋 Feature Status

### ✅ Built & Functional
| Item | Status |
|---|---|
| Sanctum auth (login/logout/user) | ✅ Complete |
| `routes/api.php` | ✅ Complete |
| All 18 database migrations | ✅ Migrated |
| Admin user seeded | ✅ Complete |
| `ProductController` (CRUD + restock + units) | ✅ Complete |
| `WholesaleProductController` | ✅ Complete |
| `RetailPosController` (checkout, stock deduct) | ✅ Complete |
| `WholesalePosController` (checkout, debt) | ✅ Complete |
| `RetailSaleController` (history, date filter) | ✅ Complete |
| `WholesaleSaleController` (history + pay debt) | ✅ Complete |
| `ReversalController` (reverse + restore stock) | ✅ Complete |
| `ClientController` (CRUD) | ✅ Complete |
| `WorkerController` (CRUD + status) | ✅ Complete |
| `DashboardController` (today's KPIs) | ✅ Complete |
| `ReportController` (date-range financial) | ✅ Complete |
| All Models (11 models + relationships) | ✅ Complete |
| Unit/bulk pricing model (`product_units`) | ✅ Complete |
| CORS configured (allows all origins) | ✅ Complete |

### 📅 Planned (Future)
- [ ] DB seeder for sample products/clients/sales (for demo)
- [ ] Activity logging (who did what, when)
- [ ] Password reset / email verification

---

## 📦 Unit / Bulk Pricing Design

Each product has a `product_units` table with entries like:

```
| unit_name | quantity_in_base | price   | is_bulk | bulk_discount_pct |
|-----------|-----------------|---------|---------|------------------|
| Bottle    | 1               | 500.00  | false   | 0                |
| Box       | 12              | 5400.00 | true    | 10               |
```

At checkout, the POS sends the selected unit, and the API records the effective price and quantity deducted from base stock.
