# Sales Microservice (`sales-service`)

The **Sales Microservice** powers the core idempotent POS checkout engine, cart holding & resuming, sales transactions storage, line item detail auditing, and admin sales analytics dashboards.

---

## 1. Overview Specifications

- **Port**: `8006`
- **Database**: `sales_db` (MySQL 8.0)
- **Primary Key Standard**: `uuidv4` (`char(36)`)
- **API Gateway Prefix**: `/api/v1/sales`, `/api/v1/carts`, `/api/v1/admin/dashboard`

---

## 2. Key Responsibilities

1. **Idempotent Checkout Engine**: Create completed sale records (`POST /api/v1/sales`) with unique transaction numbers (`POS-YYYYMMDD-XXXX`), tax calculation, discounts, and item breakdowns.
2. **Cart Holding & Resuming**: Hold draft customer carts (`POST /api/v1/carts/hold`), list held carts (`GET /api/v1/carts/held`), and resume or delete held carts.
3. **Admin Sales Analytics**: Provide executive revenue metrics, total sales count, payment method breakdown, and top-selling products for dashboard reporting (`GET /api/v1/admin/dashboard/summary`).

---

## 3. Database Schema (`sales_db`)

- **`sales`**: Completed sales transaction headers (`id`, `invoice_number`, `outlet_id`, `shift_id`, `cashier_id`, `subtotal`, `discount_amount`, `tax_amount`, `grand_total`, `payment_status`, `completed_at`).
- **`sale_items`**: Individual line items (`id`, `sale_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `subtotal`).
- **`held_carts`**: Held/draft carts (`id`, `customer_name`, `cart_data_json`, `held_by`, `created_at`).

---

## 4. API Endpoints Reference

| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/sales` | Cashier | Execute idempotent POS checkout and record sale. |
| `GET` | `/api/v1/sales` | Protected | List historical sales records with date & outlet filters. |
| `GET` | `/api/v1/sales/{id}` | Protected | Fetch sale invoice details & line items. |
| `POST` | `/api/v1/carts/hold` | Cashier | Hold active cart for later resumption. |
| `GET` | `/api/v1/carts/held` | Cashier | List currently held customer carts. |
| `POST` | `/api/v1/carts/held/{id}/resume` | Cashier | Resume held cart into POS drawer. |
| `DELETE` | `/api/v1/carts/held/{id}` | Cashier | Delete held cart. |
| `GET` | `/api/v1/admin/dashboard/summary` | Protected | Executive sales summary, metrics, and top sellers. |

---

## 5. Development Commands

Run database migrations inside Docker:
```bash
docker exec pos-sales-service php artisan migrate --force
```
