\# VXM — Digital Earning Platform



> A premium PHP/MySQL digital earning platform built around daily tasks, referrals, wallet management, M-Pesa payments, withdrawals, support, and administration.



\[!\[Live Frontend](https://img.shields.io/badge/Live-Frontend-success?style=for-the-badge)](https://samsonnyaga474.github.io/VXM/)

\[!\[PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge\&logo=php\&logoColor=white)](https://www.php.net/)

\[!\[MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge\&logo=mysql\&logoColor=white)](https://www.mysql.com/)



VXM is a full-stack PHP/MySQL platform designed around a simple earning workflow:



\*\*REGISTER → JOIN LEVEL → COMPLETE DAILY TASKS → EARN → REFER → WITHDRAW\*\*



The public-facing frontend is deployed through GitHub Pages, while the PHP/MySQL application runs through a PHP-capable server such as XAMPP.



\---



\## 🌐 Live Frontend



\*\*Public website:\*\*



https://samsonnyaga474.github.io/VXM/



> \*\*Important:\*\* GitHub Pages hosts the static frontend only. PHP, MySQL, authentication, wallet operations, M-Pesa processing, admin tools, and other server-side functionality require a PHP/MySQL environment such as XAMPP or production hosting.



\---



\## ✨ Overview



VXM combines a modern frontend with a PHP/MySQL application backend.



The platform includes:



\- User registration and login

\- Configurable earning levels

\- Daily task system

\- Task reward processing

\- Referral system

\- Wallet and transaction ledger

\- M-Pesa deposit integration

\- Withdrawal requests

\- Manual withdrawal administration

\- Notifications

\- Support tickets and messages

\- Password reset flow

\- Login protection and rate limiting

\- CSRF protection

\- Admin dashboard and management tools

\- Centralized configuration

\- Development and production environment controls



\---



\## 🔄 Core Platform Flow



```mermaid

flowchart LR

&#x20;   A\[Register] --> B\[Login]

&#x20;   B --> C\[Deposit]

&#x20;   C --> D\[Wallet]

&#x20;   D --> E\[Activate Level]

&#x20;   E --> F\[Daily Tasks]

&#x20;   F --> G\[Task Rewards]

&#x20;   G --> H\[Wallet]

&#x20;   E --> I\[Referral System]

&#x20;   I --> J\[Referral Bonus]

&#x20;   J --> H

&#x20;   H --> K\[Withdrawal Request]

&#x20;   K --> L\[Admin Review]

&#x20;   L --> M\[Manual Processing]

