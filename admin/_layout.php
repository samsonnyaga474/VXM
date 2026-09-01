<?php
function admin_header(string $title, string $active = ''): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title) ?> · Admin | VXM</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="../assets/css/vxm.css" />
  <link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body class="dash-body">
<div class="dash-shell">
  <aside class="dash-sidebar" id="dashSidebar">
    <div class="sidebar-brand"><a href="index.php" class="nav-logo"><span>VXM Admin</span></a></div>
    <nav class="sidebar-nav">
      <a href="index.php" class="<?= $active==='dashboard'?'active':'' ?>"><i class="bi bi-grid"></i> Dashboard</a>
      <a href="users.php" class="<?= $active==='users'?'active':'' ?>"><i class="bi bi-people"></i> Users</a>
      <a href="levels.php" class="<?= $active==='levels'?'active':'' ?>"><i class="bi bi-layers"></i> Levels</a>
      <a href="tasks.php" class="<?= $active==='tasks'?'active':'' ?>"><i class="bi bi-check2-square"></i> Tasks</a>
      <a href="deposits.php" class="<?= $active==='deposits'?'active':'' ?>"><i class="bi bi-phone"></i> Deposits</a>
      <a href="withdrawals.php" class="<?= $active==='withdrawals'?'active':'' ?>"><i class="bi bi-cash-stack"></i> Withdrawals</a>
      <a href="transactions.php" class="<?= $active==='transactions'?'active':'' ?>"><i class="bi bi-arrow-left-right"></i> Transactions</a>
      <a href="support.php" class="<?= $active==='support'?'active':'' ?>"><i class="bi bi-chat-dots"></i> Support</a>
      <a href="referrals.php" class="<?= $active==='referrals'?'active':'' ?>"><i class="bi bi-share"></i> Referrals</a>
      <a href="../dashboard.php"><i class="bi bi-box-arrow-left"></i> Back to app</a>
    </nav>
  </aside>
  <div class="dash-content">
    <header class="dash-topbar">
      <button type="button" class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
      <h1 class="topbar-title"><?= e($title) ?></h1>
    </header>
    <main class="dash-main">
<?php
}

function admin_footer(): void {
?>
    </main>
  </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script>
(function(){
  const t=document.getElementById('sidebarToggle'),s=document.getElementById('dashSidebar'),o=document.getElementById('sidebarOverlay');
  function c(){s.classList.remove('open');o.classList.remove('show');}
  t?.addEventListener('click',()=>{s.classList.toggle('open');o.classList.toggle('show');});
  o?.addEventListener('click',c);
})();
</script>
</body></html>
<?php
}
