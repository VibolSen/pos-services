# Inventory Microservice (`inventory-service`)

The **Inventory Microservice** handles stock level tracking, append-only stock movement ledger audit trails, purchase order receiving, stock adjustments & wastage write-offs, and inter-outlet stock transfers (`dispatched` &rarr; `received`).

---

## 1. Overview Specifications

- **Port**: `8003`
- **Database**: `inventory_db` (MySQL 8.0)
- **Primary Key Standard**: `uuidv4` (`char(36)`)
- **API Gateway Prefix**: `/api/v1/inventory`

---

## 2. Key Responsibilities

1. **Stock Balances**: Track on-hand, reserved, and available stock quantities per outlet.
2. **Append-Only Stock Ledger**: Record immutable audit logs (`inventory_movements`) for stock receives, sales, adjustments, returns, and transfers.
3. **Purchase Order Receiving**: Add new stock inventory from Purchase Orders (`POST /api/v1/inventory/receive`).
4. **Stock Adjustments & Spoilage**: Record manual stock counts, spoilage, or damage adjustments (`POST /api/v1/inventory/adjust`).
5. **Inter-Outlet Stock Transfers**: Dispatch stock transfers from Source Outlet to Target Outlet (`POST /api/v1/inventory/transfers`) and confirm destination receipt (`POST /api/v1/inventory/transfers/{id}/receive`).

---

## 3. Database Schema (`inventory_db`)

- **`inventory_balances`**: Real-time stock counts (`id`, `outlet_id`, `product_id`, `on_hand`, `reserved`, `available`).
- **`inventory_movements`**: Immutable stock audit ledger (`id`, `outlet_id`, `product_id`, `user_id`, `movement_type`, `quantity`, `unit_cost`, `reference_type`, `reference_id`, `notes`).
- **`stock_transfers`**: Inter-outlet transfer headers (`id`, `transfer_number`, `from_outlet_id`, `to_outlet_id`, `user_id`, `status`, `dispatched_at`, `received_at`, `notes`).
- **`stock_transfer_lines`**: Transfer item details (`id`, `transfer_id`, `product_id`, `quantity`).

---

## 4. API Endpoints Reference

| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/inventory/balances` | Protected | Fetch current stock balances per outlet with low stock indicators. |
| `GET` | `/api/v1/inventory/movements` | Protected | List stock movement ledger audit logs. |
| `POST` | `/api/v1/inventory/receive` | Clerk/Admin | Receive stock from Purchase Order. |
| `POST` | `/api/v1/inventory/adjust` | Clerk/Admin | Perform stock count adjustment or write off spoilage. |
| `GET` | `/api/v1/inventory/transfers` | Protected | List stock transfers with status filter (`dispatched`, `received`). |
| `GET` | `/api/v1/inventory/transfers/{id}` | Protected | Fetch detailed line items of stock transfer. |
| `POST` | `/api/v1/inventory/transfers` | Clerk/Admin | Dispatch stock transfer from Source Outlet to Target Outlet. |
| `POST` | `/api/v1/inventory/transfers/{id}/receive` | Clerk/Admin | Confirm receipt of stock transfer at destination outlet. |

---

## 5. Development Commands

Run database migrations inside Docker:
```bash
docker exec pos-inventory-service php artisan migrate --force
```
