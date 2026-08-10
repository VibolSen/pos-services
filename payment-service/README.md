# Payment Microservice (`payment-service`)

The **Payment Microservice** handles payment processing (Cash, ABA KHQR, Credit Card), KHQR payment status webhooks/callbacks, payment reconciliation, sales returns, and refund processing.

---

## 1. Overview Specifications

- **Port**: `8005`
- **Database**: `payment_db` (MySQL 8.0)
- **Primary Key Standard**: `uuidv4` (`char(36)`)
- **API Gateway Prefix**: `/api/v1/payments`, `/api/v1/payment-callbacks`, `/api/v1/refunds`

---

## 2. Key Responsibilities

1. **Multi-Tender Payments**: Process tender payments for orders (Cash, ABA PayWay / KHQR, Credit Card).
2. **ABA KHQR Gateway Integration**: Generate KHQR QR strings and handle payment confirmation webhooks (`POST /api/v1/payment-callbacks/aba`).
3. **Payment Status Queries**: Check transaction status for pending QR payments.
4. **Sales Returns & Refunds**: Process partial or full refunds against completed sales transactions.

---

## 3. Database Schema (`payment_db`)

- **`payments`**: Payment transaction headers (`id`, `sale_id`, `payment_method`, `amount`, `currency`, `status`, `transaction_reference`, `paid_at`).
- **`payment_callbacks`**: Raw webhook payload audit log for external payment gateway callbacks.
- **`refunds`**: Sales refund records (`id`, `payment_id`, `amount`, `reason`, `refunded_by`).

---

## 4. API Endpoints Reference

| Method | Endpoint | Access | Description |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/v1/payments` | Cashier | Process tender payment for checkout transaction. |
| `GET` | `/api/v1/payments/{id}/status` | Cashier | Query status of pending KHQR or card payment. |
| `POST` | `/api/v1/payment-callbacks/aba` | Public Callback | Webhook callback handler for ABA PayWay / KHQR notifications. |
| `POST` | `/api/v1/refunds` | Supervisor/Manager | Process refund for returned items. |

---

## 5. Development Commands

Run database migrations inside Docker:
```bash
docker exec pos-payment-service php artisan migrate --force
```
