# Shift Microservice (`shift-service`)

The **Shift Microservice** controls POS register shift lifecycles, opening cash floats, mid-shift cash drawer movements (cash in / cash out), shift closing, cash drawer reconciliation, and shift variance auditing.

---

## 1. Overview Specifications

- **Port**: `8004`
- **Database**: `shift_db` (MySQL 8.0)
- **Primary Key Standard**: `uuidv4` (`char(36)`)
- **API Gateway Prefix**: `/api/v1/shifts`

---

## 2. Key Responsibilities

1. **Shift Lifecycle Management**: Open register shifts (`POST /api/v1/shifts/open`) and close shifts (`POST /api/v1/shifts/close`).
2. **Opening Cash Float**: Record and verify starting cash float per register session.
3. **Cash Drawer Movements**: Audit mid-shift cash drops, petty cash payouts, or float top-ups (`POST /api/v1/shifts/cash-movement`).
4. **Shift Cash Reconciliation**: Calculate expected cash total vs counted cash and record overage/shortage variances.

---

## 3. Database Schema (`shift_db`)

- **`register_shifts`**: Shift session records (`id`, `user_id`, `outlet_id`, `register_name`, `status`, `opening_float`, `closing_cash_counted`, `expected_cash`, `cash_variance`, `opened_at`, `closed_at`).
- **`cash_movements`**: Mid-shift drawer movements (`id`, `shift_id`, `user_id`, `type`, `amount`, `reason`, `notes`).

---

## 4. API Endpoints Reference

| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/shifts/active` | Cashier | Retrieve currently active shift session for user. |
| `POST` | `/api/v1/shifts/open` | Cashier | Open new register shift with starting cash float. |
| `POST` | `/api/v1/shifts/cash-movement` | Cashier | Record cash drop (cash out) or float top-up (cash in). |
| `POST` | `/api/v1/shifts/close` | Cashier/Manager | Close shift, submit counted cash, and audit variance. |

---

## 5. Development Commands

Run database migrations inside Docker:
```bash
docker exec pos-shift-service php artisan migrate --force
```
