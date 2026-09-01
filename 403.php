<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#030712" />
  <title>Access denied | VXM</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/vxm.css" />
</head>
<body>
  <div class="auth-page">
    <div class="auth-card" style="text-align:center;">
      <a href="index.html" class="logo" style="justify-content:center;"><span>VXM</span></a>
      <h1 style="font-size:3rem;margin:1rem 0 0.5rem;color:var(--danger);">403</h1>
      <h2 style="font-size:1.25rem;margin-bottom:0.75rem;">Access denied</h2>
      <p class="subtitle">You do not have permission to view this resource.</p>
      <a href="index.html" class="btn btn-primary">Go home</a>
      <div class="auth-footer"><a href="login.html">Sign in</a></div>
    </div>
  </div>
</body>
</html>
