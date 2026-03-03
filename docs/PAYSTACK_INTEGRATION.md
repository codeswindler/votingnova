# Paystack for STK Push / Mobile Money

**Paystack is now integrated.** Set `PAYMENT_PROVIDER=paystack` in `.env` to use Paystack for STK-style payments; leave unset or `mpesa` to use M-Pesa Daraja. If Paystack is selected and the charge fails, the system falls back to M-Pesa automatically.

---

## 1. What you need from Paystack

1. **Paystack account**  
   Sign up at [paystack.com](https://paystack.com) and complete verification.

2. **API keys**  
   Dashboard → Settings → API Keys & Webhooks:
   - **Secret Key** (starts with `sk_live_` or `sk_test_`)
   - **Public Key** (for frontend; backend uses Secret Key)

3. **Enable Mobile Money**  
   Dashboard → Preferences → enable **Mobile Money** (and M-PESA if you use it in Kenya).

4. **Webhook URL**  
   You’ll register a URL on your server, e.g.  
   `https://voting.novotechafrica.co.ke/api/paystack-webhook.php`

---

## 2. How Paystack fits your current flow

Your app today:

- **Initiate:** phone + amount + a **reference** (session id).
- **Store:** that reference in `ussd_sessions.checkout_request_id` (USSD) or `web_vote_sessions.checkout_request_id` (web).
- **Callback:** when payment completes, you get the **reference** back, look up that session, then create vote + send SMS.

With Paystack you do the same idea:

- **Initiate:** call Paystack **Charge API** (mobile money) with phone, amount, and a **reference** you choose.
- **Store:** the same **reference** in `ussd_sessions.checkout_request_id` or `web_vote_sessions.checkout_request_id` (so existing vote-creation logic can stay the same).
- **Webhook:** Paystack sends a webhook with **reference** and status; you update the transaction, then run the **same** “find session by reference → create vote → send SMS” logic you use for M-Pesa.

So you need:

- A **Paystack config** (e.g. from `.env`).
- A **Paystack service** that:
  - Initiates a charge (like `initiateSTKPush`).
  - Returns the **reference** you store in `checkout_request_id`.
- A **Paystack webhook endpoint** that:
  - Verifies `x-paystack-signature`.
  - Finds the transaction by **reference**, updates status.
  - Looks up `ussd_sessions` / `web_vote_sessions` by `checkout_request_id = reference`.
  - Creates the vote and sends SMS (same as M-Pesa callback).
- Optional: a **payments/transactions** table for Paystack (or reuse one table with a `provider` column).

---

## 3. .env and config

Add to `.env`:

```env
# Paystack (if using Paystack for STK / mobile money)
PAYSTACK_SECRET_KEY=sk_live_xxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_live_xxxxxxxx
PAYSTACK_WEBHOOK_SECRET=whsec_xxxxxxxx
```

- **PAYSTACK_SECRET_KEY** – used for Charge API and to verify webhooks.
- **PAYSTACK_PUBLIC_KEY** – only if you ever charge from the browser.
- **PAYSTACK_WEBHOOK_SECRET** – from Dashboard → Settings → API Keys & Webhooks → Webhook URL → “Secret”. Used to verify `x-paystack-signature`.

Create e.g. `config/paystack.php` that reads these with `getenv('PAYSTACK_...')`.

---

## 4. Paystack Charge API (initiate)

- **Endpoint:** `POST https://api.paystack.co/charge`
- **Headers:** `Authorization: Bearer YOUR_SECRET_KEY`, `Content-Type: application/json`
- **Body:** includes `email`, `amount` (in subunits, e.g. 1000 = 10.00 KES), and `mobile_money` (phone, etc.). Include a **reference** so you can match the webhook to a session.

You’ll get a response with a **reference** (or reuse the one you sent). That **reference** is what you store in `ussd_sessions.checkout_request_id` or `web_vote_sessions.checkout_request_id`, and what the webhook will send back.

---

## 5. Paystack webhook

- Paystack sends **POST** to your webhook URL with JSON body.
- **Verify** the request using the **x-paystack-signature** header (HMAC SHA512 of body with your webhook secret).
- In the payload, get **reference** and **status** (e.g. `success`).
- Then:
  1. Update your Paystack transaction row (or generic payments table) for that reference (e.g. set status = completed).
  2. Look up `ussd_sessions` by `checkout_request_id = reference`; if not found, look up `web_vote_sessions` by `checkout_request_id = reference`.
  3. If found and payment success: create vote, update nominee count, send SMS (same as in `MpesaService::processCallback`).
- **Return 200 OK** as soon as you’ve accepted the request (before heavy work if possible) so Paystack doesn’t retry unnecessarily.

---

## 6. Code structure (what to add)

| Piece | Purpose |
|-------|--------|
| `config/paystack.php` | Load Paystack keys and webhook secret from env. |
| `includes/paystack-service.php` | `initiateCharge(phone, amount, reference)` → call Charge API, return reference (or the one you passed). Optionally create a DB row (e.g. in `paystack_transactions` or a generic table). |
| `api/paystack-webhook.php` | Verify signature, parse body, update transaction by reference, then run same “find session → create vote → send SMS” logic as M-Pesa. |
| DB | Either a `paystack_transactions` table (reference, phone, amount, status, …) or one `payment_transactions` table with a `provider` column. |
| USSD / Web | Where you currently call `MpesaService::initiateSTKPush`, add a branch (or config) to call `PaystackService::initiateCharge` instead when using Paystack, and still store the returned reference in `checkout_request_id` so one callback path works for both. |

You can keep M-Pesa and Paystack behind a single “payment provider” setting (e.g. `PAYMENT_PROVIDER=mpesa` or `paystack`) and only call the right service and callback.

---

## 7. Summary checklist

- [ ] Paystack account, Mobile Money (and M-PESA if Kenya) enabled.
- [ ] Secret key and webhook secret in `.env`; `config/paystack.php` added.
- [ ] `includes/paystack-service.php`: initiate charge, return reference, store transaction if needed.
- [ ] `api/paystack-webhook.php`: verify signature, update transaction, then same vote + SMS logic as M-Pesa using `checkout_request_id` = reference.
- [ ] USSD and web flows: when provider is Paystack, call Paystack service and store reference in `ussd_sessions` / `web_vote_sessions` as `checkout_request_id`.
- [ ] Register webhook URL in Paystack dashboard and test with a small amount.

If you want, the next step can be concrete code for `paystack-service.php` and `paystack-webhook.php` wired to your existing vote and SMS logic.
