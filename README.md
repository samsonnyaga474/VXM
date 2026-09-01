# VXM — Digital Earning Platform

Premium PHP/MySQL platform for levels, daily tasks, referrals, wallet ledger, M-Pesa deposits, withdrawals, support tickets, notifications, and admin tools.

## Business configuration (central)

All key values live in `config/config.php` and `.env`:

| Setting | Value |
|---------|-------|
| Site name | VXM |
| Domain | vxm.co.ke |
| Support email | fam500473@gmail.com |
| Support phone | 0715385073 |
| M-Pesa (Send Money) | 0715385073 |
| Starter | KES 500 · target daily ~KES 20 |
| Growth | KES 1,500 · target daily ~KES 60 |
| Pro | KES 3,500 · target daily ~KES 140 |
| Referral | 5% of qualifying level purchase |
| Min deposit | KES 100 |
| Min withdrawal | KES 200 |
| Withdrawal fee | KES 20 |

These are configurable business settings, **not guaranteed investment returns**.

## Requirements

- PHP 8.0+ (mysqli, curl, json, openssl)
- MySQL 5.7+ / MariaDB 10.3+
- HTTPS in production (required for real M-Pesa callbacks)

## XAMPP / phpMyAdmin setup

1. Place the project folder in `htdocs` (e.g. `C:\xampp\htdocs\VXM` or `/opt/lampp/htdocs/VXM`).
2. Start Apache + MySQL in XAMPP.
3. Open phpMyAdmin → create database `vxm` (utf8mb4).
4. Import `database/vxm_setup.sql` into the `vxm` database.
5. Edit `.env` if needed (defaults work for local XAMPP with empty MySQL password).
6. Visit `http://localhost/VXM/`
7. Default admin (after import):  
   - Email: `admin@vxm.local`  
   - Password: `Admin@123`  
   Change this password immediately after first login.

## Security notes

- Simulated M-Pesa deposits work **only** in development when `ALLOW_SIMULATED_DEPOSITS` is true.
- Production without M-Pesa credentials rejects deposit attempts (fail closed).
- CSRF is required on state-changing forms.
- Login is rate-limited.
- Wallet changes go through the `Wallet` ledger service only.
- Withdrawals are **manual** (admin approval). Approving does **not** automatically send M-Pesa money unless B2C is configured and implemented.

## Referral rule

Default: bonus is paid when the referred user **activates a level** (`on_level_purchase`).  
Bonus amount = 5% of the level price purchased.

## External configuration still needed for production

- Domain + SSL (HTTPS)
- Hosting / VPS
- Real M-Pesa Daraja production credentials (consumer key/secret, shortcode, passkey, callback URL)
- SMTP credentials for password-reset emails (or keep `MAIL_DRIVER=log` for testing)
- Update `APP_URL` and `VXM_ENV=production` in `.env`
- Set `ALLOW_SIMULATED_DEPOSITS=false` in production

## Git

```bash
cd VXM
git init
git add .
git commit -m "VXM complete working platform with central business config"
git branch -M main
git remote add origin https://github.com/YOUR_USER/VXM.git
git push -u origin main
```

Do **not** commit the real `.env` file with secrets.
