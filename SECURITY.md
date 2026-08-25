# Security

- Report issues privately to the repository owner; do not open a public PoC for payment or OTP flaws.
- Production hosts must use HTTPS.
- OTP codes are HMAC-SHA256 hashed; raw codes are never stored.
- Public menu REST is cacheable; cart, checkout, OTP, kitchen are `private, no-store`.
- Rate limits: OTP 3/10min per number + 8/10min per IP; reservations 10/10min; coupons 20/10min.
- Do not log payment gateway secrets. Tokens in chat must be revoked.
- `flavor_core_remove_data` defaults to off so accidental uninstall keeps orders.
