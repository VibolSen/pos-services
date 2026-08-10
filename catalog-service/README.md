# Catalog Microservice (`catalog-service`)

The **Catalog Microservice** manages the central product catalog, SKUs, barcode lookups, categories & nested sub-categories, brand definitions, pricing, and bulk product import engines (Excel & Google Sheets).

---

## 1. Overview Specifications

- **Port**: `8002`
- **Database**: `catalog_db` (MySQL 8.0)
- **Primary Key Standard**: `uuidv4` (`char(36)`)
- **API Gateway Prefix**: `/api/v1/products`, `/api/v1/categories`, `/api/v1/brands`, `/api/v1/barcodes`

---

## 2. Key Responsibilities

1. **Product Management**: Create, update, list, and soft-delete catalog items (SKU, name, selling price, cost price, min reorder point, barcode).
2. **Bulk Product Import**: Process bulk product creation via `POST /api/v1/products/bulk` supporting CSV / Excel uploads and Google Sheets links.
3. **Category Management**: Unified main categories and nested sub-categories (`parent_id` linking).
4. **Barcode Lookup**: Instant barcode lookup API for fast POS barcode scanner integration.
5. **Catalog Sorting & Filtering**: Multi-criteria filters (`stock_status`, `category_id`, search) and dynamic sorting (`price`, `name`, `created_at`, `stock`).

---

## 3. Database Schema (`catalog_db`)

- **`products`**: Product catalog items (`id`, `sku`, `barcode`, `name`, `selling_price`, `cost_price`, `min_reorder_point`, `category_id`, `is_active`).
- **`categories`**: Main and nested sub-categories (`id`, `name`, `slug`, `parent_id`).
- **`brands`**: Brand definitions (`id`, `name`, `logo_url`).
- **`inventory_balances`**: Replicated stock balance cache for fast catalog stock queries.

---

## 4. API Endpoints Reference

| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/products` | Protected | List products with search, category filter, stock status, and sorting. |
| `POST` | `/api/v1/products` | Admin | Create single product and initialize stock balance. |
| `POST` | `/api/v1/products/bulk` | Admin | Bulk import products (Excel & Google Sheets data). |
| `PUT` | `/api/v1/products/{id}` | Admin | Update product details and pricing. |
| `DELETE` | `/api/v1/products/{id}` | Admin | Soft-delete product catalog item. |
| `GET` | `/api/v1/categories` | Protected | List main categories and sub-categories (`type=main` or `type=sub`). |
| `POST` | `/api/v1/categories` | Admin | Create category or nested sub-category. |
| `GET` | `/api/v1/barcodes/{code}` | Protected | Fetch product by barcode number. |

---

## 5. Development Commands

Run database migrations inside Docker:
```bash
docker exec pos-catalog-service php artisan migrate --force
```
