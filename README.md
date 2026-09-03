<div align="center">

<img src="./images/logo.jpg" width="180" alt="VXM Logo">

# VXM

### TURN YOUR TIME INTO EARNINGS

**A futuristic digital earning platform built with PHP, MySQL, JavaScript, Bootstrap & M-Pesa integration.**

<br>

<a href="https://samsonnyaga474.github.io/VXM/">
<img src="https://img.shields.io/badge/EXPLORE%20LIVE%20FRONTEND-1769FF?style=for-the-badge&logo=googlechrome&logoColor=white" alt="Live Frontend">
</a>

<a href="#-how-vxm-works">
<img src="https://img.shields.io/badge/EXPLORE%20THE%20SYSTEM-7C2DFF?style=for-the-badge&logo=github&logoColor=white" alt="Explore System">
</a>

<br><br>

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-7952B3?style=flat-square&logo=bootstrap&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
![M-Pesa](https://img.shields.io/badge/M--Pesa-Daraja%20API-00A651?style=flat-square&logo=safaricom&logoColor=white)
![Status](https://img.shields.io/badge/status-active%20development-orange?style=flat-square)
![License](https://img.shields.io/badge/license-unspecified-lightgrey?style=flat-square)

<br>

<img src="./readme-assets/hero/vxm-hero.svg" width="100%" alt="VXM Futuristic Hero">

</div>

---

## 📚 Table of Contents

- [⚡ What is VXM?](#-what-is-vxm)
- [🌌 The VXM Experience](#-the-vxm-experience)
- [🧠 How VXM Works](#-how-vxm-works)
  - [👤 User System](#-user-system)
  - [💰 Earning System](#-earning-system)
  - [🛠️ Admin Panel](#️-admin-panel)
- [💳 M-Pesa Integration](#-m-pesa-integration)
- [💸 Withdrawals — How They Actually Work](#-withdrawals--how-they-actually-work)
- [🛡️ Security](#️-security)
- [⚠️ Security Considerations & Known Gaps](#️-security-considerations--known-gaps)
- [🏗️ Technology Stack](#️-technology-stack)
- [🗄️ Database](#️-database)
- [🚀 Getting Started (Local Setup)](#-getting-started-local-setup)
- [📂 Project Architecture](#-project-architecture)
- [📸 Interface Preview](#-interface-preview)
- [🧭 Roadmap](#-roadmap)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

---

## ⚡ What is VXM?

**VXM** is a full-stack digital earning platform designed around a simple concept:

> **Join → Choose a Level → Complete Tasks → Earn → Refer → Withdraw**

The platform provides users with a dashboard where they can manage their account, participate in daily tasks, track earnings, manage their wallet, view transactions, refer other users and request withdrawals.

VXM combines a modern futuristic interface with a PHP/MySQL backend, a full admin control panel, and an M-Pesa payment foundation.

---

## 🌌 The VXM Experience

<div align="center">

<img src="./readme-assets/diagrams/vxm-flow.svg" width="100%" alt="VXM User Flow">

</div>

### The journey

| Stage | What happens |
|---|---|
| **01 — Register** | Create a VXM account and receive a unique referral code |
| **02 — Join Level** | Select an available earning level (deposit required) |
| **03 — Complete Tasks** | Complete eligible daily tasks tied to the user's level |
| **04 — Earn** | Receive task rewards and referral bonuses into the wallet |
| **05 — Refer** | Invite other users with a personal referral link |
| **06 — Withdraw** | Request a withdrawal from the available wallet balance |

---

## 🧠 How VXM Works

<div align="center">

<img src="./readme-assets/diagrams/vxm-ecosystem.svg" width="100%" alt="VXM System Ecosystem">

</div>

### 👤 User System

Users can:

- Register and log in
- Select an earning level
- Access daily tasks
- Complete eligible tasks
- Track earnings
- View wallet balance
- View transaction history
- Manage referrals
- Receive in-app notifications
- Manage their account details
- Contact support and open support tickets
- Request withdrawals
- Reset forgotten passwords

---

### 💰 Earning System

VXM supports several earning mechanisms.

**Task Rewards**
Users receive rewards when eligible tasks are successfully completed. The system prevents duplicate task completion and enforces daily task limits per level.

**Referral Rewards**
Users can invite others through their personal referral link. Referral bonuses are calculated as a configurable percentage of a qualifying action (currently triggered on level purchase) rather than a fixed hardcoded amount.

**Wallet**
The wallet system maintains:

- Current balance
- Total earnings
- Total deposits
- Total withdrawals
- A full transaction ledger

Every wallet-affecting operation (task reward, referral bonus, deposit, withdrawal) runs inside a database transaction with row-level locking (`SELECT … FOR UPDATE`) on the user's balance, so concurrent requests can't corrupt the balance or double-spend it.

---

### 🛠️ Admin Panel

VXM includes a dedicated, authenticated admin control panel — separate from the regular user dashboard — for operating the platform day-to-day.

| Section | Purpose |
|---|---|
| 📊 **Dashboard** | Platform-wide overview |
| 👥 **Users** | View and manage registered users |
| 🎯 **Levels** | Manage earning levels |
| ✅ **Tasks** | Manage the daily task pool |
| 📱 **Deposits** | Review incoming M-Pesa / manual deposits |
| 💸 **Withdrawals** | Approve or reject pending withdrawal requests |
| 🔁 **Transactions** | Full ledger view across all users |
| 💬 **Support** | Respond to user support tickets |
| 🔗 **Referrals** | View referral relationships and payouts |

Every admin action is protected by server-side authorization (`require_admin()`), CSRF-token verification, and prepared statements — an admin session alone does not bypass those checks on any write action.

---

## 💳 M-Pesa Integration

VXM includes a working integration foundation for **Safaricom M-Pesa Daraja**.

The system supports:

- **STK Push** deposit initiation (Lipa Na M-Pesa Online) when live Daraja credentials are configured
- Sandbox and production configuration through environment variables
- Signed callback processing at a dedicated `mpesa/callback.php` endpoint
- Idempotent/duplicate-callback protection so the same payment can't be credited twice
- Automatic wallet crediting once a payment is confirmed by the callback (never on the client's say-so)
- A **development-only simulated deposit mode**, explicitly gated behind `VXM_ENV=development`, so the earning flow can be tested end-to-end without live Daraja credentials

The M-Pesa implementation is designed so payment credentials are stored through environment configuration (`.env`) rather than committed into the repository. Until real Daraja credentials are supplied, the platform runs on the simulated-deposit path for local development and testing.

---

## 💸 Withdrawals — How They Actually Work

VXM has a complete **withdrawal request → admin review → approve/reject** workflow:

1. A user requests a withdrawal from their available wallet balance (subject to a configurable minimum and fee).
2. The requested amount is held against the wallet immediately, so a user can't request the same funds twice.
3. An admin reviews the request in the **Withdrawals** section of the admin panel.
4. The admin **approves** (status → `approved`, wallet counters updated, user notified) or **rejects** (status → `rejected`, held funds released back to the user, optional reason recorded) the request.
5. Every state change is wrapped in a database transaction with row locking, and only an admin account (verified server-side) can perform it.

**Important, honest caveat:** approving a withdrawal in VXM updates its status and records — it does **not** currently trigger an automated M-Pesa payout (no B2C/Business-to-Customer disbursement is wired up yet). Sending the actual money to the user is a manual step outside the application today. Automated payouts are tracked in the [Roadmap](#-roadmap).

---

## 🛡️ Security

Security is built into the application architecture.

VXM includes:

- CSRF protection on authenticated PHP-rendered forms and admin actions
- Secure PHP sessions with a dedicated session name and lifetime
- Password hashing (`password_hash` / `password_verify`)
- Login rate limiting and temporary account lockout after repeated failed attempts
- Authentication guards (`require_login()`) on every protected page
- Admin authorization (`require_admin()`) on every admin page and action
- Server-side input validation
- Output escaping (`htmlspecialchars`) to mitigate XSS
- Prepared statements throughout (mitigates SQL injection)
- Database transactions and row-level locking on wallet operations
- Duplicate M-Pesa callback / transaction protection
- Environment-based configuration for secrets (`.env`, excluded via `.gitignore`)
- Baseline security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, HSTS in production)
- `.htaccess` rules blocking direct web access to `config/`, `includes/`, `storage/`, and `migrations/`

Sensitive configuration such as database credentials and payment credentials should remain inside `.env`, which is never committed to the repository.

---

## ⚠️ Security Considerations & Known Gaps

In the interest of accuracy, these are known limitations in the current codebase — not hidden, and worth knowing before any production deployment:

- The static marketing pages `login.html` and `register.html` submit directly to `login.php` / `register.php` without a CSRF token field, unlike the authenticated in-app forms.
- `contact.html` is currently a front-end-only form (JavaScript stub) with no server-side endpoint processing submissions yet.
- There is no CAPTCHA or IP-based rate limiting on the public registration endpoint (login already has rate limiting).
- Withdrawal "approval" is a status/ledger change only — see [Withdrawals](#-withdrawals--how-they-actually-work) — there is no automated M-Pesa B2C payout yet.
- No formal `LICENSE` file currently exists in the repository (see [License](#-license)).

These are tracked under [Roadmap](#-roadmap) as planned work, not implemented features.

---

## 🏗️ Technology Stack

<div align="center">

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Bootstrap](https://img.shields.io/badge/Bootstrap-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)

</div>

**Frontend**
- HTML5, CSS3, vanilla JavaScript
- Bootstrap 5
- Bootstrap Icons
- Google Fonts (Inter, Space Grotesk)

**Backend**
- PHP (procedural + small class-based services: `Wallet`, `Mpesa`, `Mail`)
- `mysqli` with prepared statements throughout
- Session-based authentication

**Database**
- MySQL / MariaDB (XAMPP-compatible), `utf8mb4` charset

**Payments**
- Safaricom M-Pesa Daraja API (STK Push + callback handling)

**Local Development**
- XAMPP (Apache + MySQL + PHP)

**Version Control**
- Git & GitHub

---

## 🗄️ Database

VXM ships with both a ready-to-import setup script and versioned migrations:

- `database/vxm_setup.sql` — full schema for a fresh install
- `migrations/001_schema.sql` — base schema (tables, keys, indexes)
- `migrations/002_integrity_constraints.sql` — additional integrity constraints

**Core tables**

| Table | Purpose |
|---|---|
| `users` | Accounts, wallet balances, referral codes |
| `levels` | Earning levels available to join |
| `tasks` | The pool of eligible daily tasks |
| `user_tasks` | Which user completed which task, and when |
| `earnings` | Task/referral earning records |
| `transactions` | Full wallet transaction ledger |
| `deposits` | M-Pesa / manual deposit records |
| `withdrawals` | Withdrawal requests and their status |
| `referrals` | Referral relationships and payouts |
| `password_resets` | Password-reset tokens |
| `notifications` | In-app user notifications |
| `support_tickets` / `support_messages` | Support ticketing system |
| `login_attempts` | Login rate-limiting records |
| `settings` | Platform-level configurable settings |

Database credentials are read from `.env` (or fall back to XAMPP defaults: host `localhost`, user `root`, empty password, database `vxm`) — see [Getting Started](#-getting-started-local-setup).

---

## 🚀 Getting Started (Local Setup)

VXM is built to run on a standard **XAMPP** stack.

1. **Clone the repository**
   ```bash
   git clone https://github.com/samsonnyaga474/VXM.git
   ```
2. **Place the project in `htdocs`**
   Copy (or clone directly into) `C:\xampp\htdocs\VXM` so it's served at `http://localhost/VXM`.
3. **Start Apache and MySQL** from the XAMPP Control Panel.
4. **Create the database**
   Open phpMyAdmin (`http://localhost/phpmyadmin`), create a database named `vxm`, and import `database/vxm_setup.sql` (or run the scripts in `migrations/` in order).
5. **Configure environment variables**
   Copy `.env.example` to `.env` in the project root and adjust values as needed. The default values already match a fresh XAMPP install (empty MySQL password, database name `vxm`).
   ```bash
   cp .env.example .env
   ```
6. **(Optional) Enable simulated deposits for local testing**
   Keep `ALLOW_SIMULATED_DEPOSITS=true` and `VXM_ENV=development` in `.env` to test the earning flow without live M-Pesa Daraja credentials.
7. **Open the project**
   Visit `http://localhost/VXM/index.html` in your browser.

> ⚠️ Never commit a real `.env` file. It's already excluded via `.gitignore`.

---

## 📂 Project Architecture

```text
VXM/
│
├── admin/                  # Admin control panel (auth-protected)
│   ├── _layout.php         # Shared admin sidebar/header layout
│   ├── deposits.php        # Review incoming deposits
│   ├── index.php           # Admin dashboard
│   ├── levels.php          # Manage earning levels
│   ├── referrals.php       # View referral relationships
│   ├── support.php         # Respond to support tickets
│   ├── tasks.php           # Manage the daily task pool
│   ├── transactions.php    # Full transaction ledger view
│   ├── users.php           # Manage registered users
│   └── withdrawals.php     # Approve / reject withdrawals
│
├── api/
│   └── deposit.php         # Deposit-related API endpoint
│
├── config/
│   └── config.php          # Central app config (.env loader + constants)
│
├── database/
│   └── vxm_setup.sql       # Full schema for a fresh install
│
├── includes/
│   ├── bootstrap.php       # Required at the top of every entry point
│   ├── db.php               # mysqli connection
│   ├── helpers.php          # CSRF, sessions, auth guards, formatting
│   ├── layout_user.php      # Shared user dashboard layout
│   ├── Mail.php              # Mail sending abstraction
│   ├── Mpesa.php             # M-Pesa Daraja STK Push + callback logic
│   └── Wallet.php            # Row-locked wallet credit/debit logic
│
├── migrations/
│   ├── 001_schema.sql
│   └── 002_integrity_constraints.sql
│
├── mpesa/
│   └── callback.php        # Daraja STK callback endpoint
│
├── readme-assets/
│   ├── hero/                # Hero banner SVG
│   ├── screenshots/          # Reserved for interface screenshots
│   ├── diagrams/             # Flow / ecosystem diagrams
│   └── icons/                 # Reserved for custom icons
│
├── storage/                 # Logs, mail output, rate-limit data (not web-accessible)
│
├── account.php              # User account/profile management
├── approve-withdrawal.php   # Admin: approve a withdrawal
├── complete-task.php        # User: mark a task complete
├── dashboard.php            # User dashboard
├── deposit.php               # User: initiate a deposit
├── levels.php                 # User: view/select earning levels
├── notifications.php         # User: in-app notifications
├── referrals.php              # User: referral management
├── reject-withdrawal.php     # Admin: reject a withdrawal
├── support.php                 # User: support tickets
├── tasks.php                    # User: daily tasks
├── transactions.php            # User: transaction history
├── withdraw.php                # User: request a withdrawal
├── withdraw-page.php           # Withdrawal request page/view
│
├── index.html / about.html / earn.html / levels.html / referrals.html
├── login.html / register.html / forgot-password.html
├── contact.html / privacy.html / terms.html   # Static marketing/legal pages
├── login.php / register.php / logout.php
├── forgot-password.php / reset-password.php
├── 404.php / 403.php / 500.php                 # Custom error pages
│
├── .env.example              # Template for local environment config
├── .gitignore
├── .htaccess
└── README.md
```

---

## 📸 Interface Preview

A `readme-assets/screenshots/` folder is reserved for interface screenshots, but none have been added yet.

> _Screenshots of the landing page, dashboard, wallet, and admin panel can be dropped into `readme-assets/screenshots/` and linked here once available._

---

## 🧭 Roadmap

**✅ Implemented**

- Registration, login, session-based authentication
- Earning levels, daily tasks, task-reward crediting
- Referral system with configurable percentage-based bonuses
- Wallet with transactional, row-locked balance updates
- Full transaction ledger
- M-Pesa STK Push deposits + signed callback crediting
- Development-mode simulated deposits
- Withdrawal request → admin approve/reject workflow
- Full admin panel (users, levels, tasks, deposits, withdrawals, transactions, support, referrals)
- In-app notifications
- Support ticketing system
- Password reset flow
- CSRF protection, rate-limited login, security headers

**🧩 Planned**

- Automated M-Pesa B2C payout on withdrawal approval
- CSRF token on the static `login.html` / `register.html` forms
- Backend endpoint for `contact.html`
- Rate limiting / CAPTCHA on registration
- Formal `LICENSE` file
- Interface screenshots in the README
- Automated test coverage

---

## 🤝 Contributing

This is currently a solo project under active development. If you'd like to report a bug or suggest an improvement:

1. Open an issue on [GitHub](https://github.com/samsonnyaga474/VXM/issues) describing the problem or idea.
2. For code changes, fork the repository, create a feature branch, and open a pull request against `main`.
3. Keep changes consistent with the existing patterns in `includes/helpers.php` (CSRF, auth guards, escaping) and use prepared statements for any database access.

---

## 📄 License

No license file is currently present in this repository. Until one is added, all rights are reserved by default — please reach out before reusing this code in another project.

<div align="center">

<br>

**VXM** — Turn your time into earnings.

</div>