<?php
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#030712" />
  <link rel="icon" href="images/favicon.png" type="image/png" sizes="32x32" />
  <title>Something went wrong | VXM</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/vxm.css" />
</head>
<body>
  <div class="auth-page">
    <div class="auth-card" style="text-align:center;">
      <a href="index.html" class="logo" style="justify-content:center;"><img src="images/logo.jpg" alt="VXM" onerror="this.style.display='none'" /></a>
      <h1 style="font-size:3rem;margin:1rem 0 0.5rem;color:var(--warning);">500</h1>
      <h2 style="font-size:1.25rem;margin-bottom:0.75rem;">Something went wrong</h2>
      <p class="subtitle">An unexpected error occurred. Please try again later.</p>
      <a href="index.html" class="btn btn-primary">Go home</a>
      <div class="auth-footer"><a href="contact.html">Contact support</a></div>
    </div>
  </div>
</body>
</html>
