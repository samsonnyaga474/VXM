<?php
/**
 * Shared layout helpers for authenticated user pages.
 * Call layout_header($title, $active) then layout_footer() at the end.
 */

function layout_header(string $title, string $active = 'dashboard'): void {
    $userName = e($_SESSION['full_name'] ?? 'User');
    $isAdmin = !empty($_SESSION['is_admin']);
    $csrf = csrf_token();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#030712" />
  <title><?= e($title) ?> | VXM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="assets/css/vxm.css" />
  <link rel="stylesheet" href="assets/css/dashboard.css" />
</head>
<body class="dash-body">
  <div class="dash-shell">
    <!-- Sidebar -->
    <aside class="dash-sidebar" id="dashSidebar">
      <div class="sidebar-brand">
        <a href="dashboard.php" class="nav-logo">
          <img src="images/logo.jpg" alt="" onerror="this.style.display='none'" />
          <span>VXM</span>
        </a>
      </div>
      <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
        <a href="tasks.php" class="<?= $active === 'tasks' ? 'active' : '' ?>"><i class="bi bi-check2-square"></i> Tasks</a>
        <a href="levels.php" class="<?= $active === 'levels' ? 'active' : '' ?>"><i class="bi bi-layers"></i> Levels</a>
        <a href="deposit.php" class="<?= $active === 'deposit' ? 'active' : '' ?>"><i class="bi bi-phone"></i> Top Up</a>
        <a href="referrals.php" class="<?= $active === 'referrals' ? 'active' : '' ?>"><i class="bi bi-people"></i> Referrals</a>
        <a href="transactions.php" class="<?= $active === 'transactions' ? 'active' : '' ?>"><i class="bi bi-arrow-left-right"></i> Transactions</a>
        <a href="withdraw-page.php" class="<?= $active === 'withdraw' ? 'active' : '' ?>"><i class="bi bi-cash-stack"></i> Withdraw</a>
        <a href="support.php" class="<?= $active === 'support' ? 'active' : '' ?>"><i class="bi bi-chat-dots"></i> Support</a>
        <a href="notifications.php" class="<?= $active === 'notifications' ? 'active' : '' ?>"><i class="bi bi-bell"></i> Notifications</a>
        <a href="account.php" class="<?= $active === 'account' ? 'active' : '' ?>"><i class="bi bi-person"></i> Account</a>
        <?php if ($isAdmin): ?>
        <a href="admin/" class="admin-link"><i class="bi bi-shield-lock"></i> Admin</a>
        <?php endif; ?>
      </nav>
      <div class="sidebar-footer">
        <div class="sidebar-user">
          <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
          <div>
            <div class="user-name"><?= $userName ?></div>
            <a href="logout.php" class="logout-link">Sign out</a>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <div class="dash-content">
      <header class="dash-topbar">
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
          <i class="bi bi-list"></i>
        </button>
        <h1 class="topbar-title"><?= e($title) ?></h1>
        <div class="topbar-actions">
          <a href="deposit.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Top Up</a>
        </div>
      </header>
      <main class="dash-main">
<?php
}

function layout_footer(): void {
    ?>
      </main>
    </div>
  </div>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <script>
    (function(){
      const toggle = document.getElementById('sidebarToggle');
      const sidebar = document.getElementById('dashSidebar');
      const overlay = document.getElementById('sidebarOverlay');
      function close(){ sidebar.classList.remove('open'); overlay.classList.remove('show'); }
      toggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
      });
      overlay?.addEventListener('click', close);
    })();
  </script>
</body>
</html>
<?php
}
