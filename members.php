<?php
session_start();
if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartStay - Member Profile</title>
   <style>
    :root {
      --background: #ffffff;
      --foreground: #0a0a0a;
      --card: #ffffff;
      --primary: #030213;
      --primary-foreground: #ffffff;
      --muted: #ececf0;
      --muted-foreground: #717182;
      --border: rgba(0, 0, 0, 0.1);
      --input-background: #f3f3f5;
      --radius: 0.625rem;
      --success: #10b981;
      --danger: #ef4444;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
      background-color: #f8fafc;
      color: var(--foreground);
      line-height: 1.5;
      min-height: 100vh;
    }

    .hidden { display: none !important; }
    
    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 1rem;
    }

    .header {
      background-color: white;
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      z-index: 50;
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 0;
    }

    .header-logo {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .header-logo img {
      height: 3rem;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .header-user-info {
      text-align: right;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      border-radius: var(--radius);
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      white-space: nowrap;
    }

    .btn-primary {
      background-color: var(--primary);
      color: var(--primary-foreground);
    }

    .btn-primary:hover { opacity: 0.9; }

    .btn-outline {
      background-color: transparent;
      border: 1px solid var(--border);
      color: var(--foreground);
    }

    .btn-outline:hover { background-color: var(--accent); }

    .btn-success {
      background-color: var(--success);
      color: white;
    }

    .btn-success:hover { opacity: 0.9; }

    .btn-warning {
      background-color: #f59e0b;
      color: white;
    }

    .btn-warning:hover { opacity: 0.9; }

    .btn-danger {
      background-color: var(--danger);
      color: white;
    }

    .btn-danger:hover { opacity: 0.9; }

    .btn-sm {
      padding: 0.25rem 0.75rem;
      font-size: 0.75rem;
    }

    .card {
      background-color: var(--card);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .card-content {
      padding: 1.5rem;
    }

    .bg-slate-50 {
      background-color: #f8fafc;
    }

    .text-blue-600 {
      color: #2563eb;
    }

    .text-green-600 {
      color: #059669;
    }

    .text-red-600 {
      color: #dc2626;
    }

    .text-yellow-600 {
      color: #d97706;
    }

    /* Alerts */
    .alert-success {
      background-color: #d1fae5;
      border: 1px solid #a7f3d0;
      color: #065f46;
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
      margin-bottom: 1rem;
    }

    .alert-error {
      background-color: #fee2e2;
      border: 1px solid #fecaca;
      color: #dc2626;
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
      margin-bottom: 1rem;
    }

    .alert-info {
      background-color: #dbeafe;
      border: 1px solid #bfdbfe;
      color: #1e40af;
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
      margin-bottom: 1rem;
    }

    .card-header { padding: 1.5rem; }

    .card-title {
      font-size: 1.25rem;
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    .card-description {
      font-size: 0.875rem;
      color: var(--muted-foreground);
    }

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.625rem;
      font-size: 0.75rem;
      font-weight: 500;
      border-radius: 9999px;
      white-space: nowrap;
    }

    .badge-green { background-color: #10b981; color: white; }
    .badge-blue { background-color: #3b82f6; color: white; }
    .badge-yellow { background-color: #f59e0b; color: white; }
    .badge-red { background-color: #ef4444; color: white; }
    .badge-gray { background-color: #6b7280; color: white; }
    .badge-purple { background-color: #8b5cf6; color: white; }

    .grid {
      display: grid;
      gap: 1.5rem;
    }

    .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }

    @media (min-width: 768px) {
      .md-grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (min-width: 1024px) {
      .lg-grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    .tabs-list {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      background-color: var(--muted);
      border-radius: var(--radius);
      padding: 0.25rem;
      margin-bottom: 2rem;
    }

    .tabs-trigger {
      padding: 0.5rem 1rem;
      font-size: 1rem;
      font-weight: 500;
      border: none;
      background-color: transparent;
      border-radius: var(--radius);
      cursor: pointer;
      transition: all 0.2s;
    }

    .tabs-trigger:hover { background-color: rgba(255, 255, 255, 0.5); }
    .tabs-trigger.active {
      background-color: white;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    .room-image-container {
      aspect-ratio: 4/3;
      background-color: #e2e8f0;
      position: relative;
      overflow: hidden;
    }

    .room-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .room-badge {
      position: absolute;
      top: 0.5rem;
      right: 0.5rem;
    }

    .flex { display: flex; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .justify-end { justify-content: flex-end; }
    .gap-2 { gap: 0.5rem; }
    .gap-4 { gap: 1rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-4 { margin-bottom: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-4 { margin-top: 1rem; }
    .text-2xl { font-size: 1.5rem; }
    .text-xl { font-size: 1.25rem; }
    .text-lg { font-size: 1.125rem; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-slate-600 { color: #475569; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .icon-sm { width: 1rem; height: 1rem; }
    .icon-lg { width: 2rem; height: 2rem; }
    .space-y-4 > * + * { margin-top: 1rem; }

    .modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 100;
      padding: 1rem;
    }

    .modal-content {
      background-color: white;
      border-radius: var(--radius);
      padding: 1.5rem;
      max-width: 32rem;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
    }

    .form-group { margin-bottom: 1rem; }
    .form-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }

    .form-input {
      width: 100%;
      padding: 0.5rem 0.75rem;
      font-size: 1rem;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background-color: var(--input-background);
    }

    .form-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 2px rgba(3, 2, 19, 0.1);
    }

    .payment-options {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.75rem;
      margin: 1rem 0;
    }

    .payment-option {
      display: flex;
      align-items: center;
      padding: 1rem;
      border: 2px solid var(--border);
      border-radius: var(--radius);
      cursor: pointer;
      transition: all 0.2s;
    }

    .payment-option:hover {
      border-color: var(--primary);
      background-color: var(--muted);
    }

    .payment-option.selected {
      border-color: var(--primary);
      background-color: rgba(3, 2, 19, 0.05);
    }

    .payment-option-icon {
      font-size: 1.5rem;
      margin-right: 1rem;
    }

    .payment-option-text {
      flex: 1;
    }

    .payment-option-title {
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    .payment-option-description {
      font-size: 0.75rem;
      color: var(--muted-foreground);
    }

    .booking-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 1rem;
      flex-wrap: wrap;
    }

    .booking-details {
      background-color: var(--muted);
      border-radius: var(--radius);
      padding: 1rem;
      margin: 1rem 0;
    }

    .footer {
      background-color: #0f172a;
      color: white;
      padding: 2rem 0;
      margin-top: 3rem;
    }

    .footer-content {
      text-align: center;
    }

    .footer-title {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
      font-size: 1.25rem;
    }

    .footer-copyright {
      color: #94a3b8;
    }

    .payment-status {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
      font-weight: 500;
      border-radius: var(--radius);
      margin-left: 0.5rem;
    }

    .payment-paid {
      background-color: #d1fae5;
      color: #065f46;
    }

    .payment-unpaid {
      background-color: #fee2e2;
      color: #dc2626;
    }

    .payment-cash {
      background-color: #fef3c7;
      color: #92400e;
    }

    .payment-refunded {
      background-color: #f3e8ff;
      color: #6d28d9;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Member Profile</h2>

  <div id="alert"></div>

  <form id="profile-form">
    <div class="form-group">
      <label class="form-label">Full Name</label>
      <input type="text" name="name" id="profile-name" class="form-input" required>
    </div>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input type="email" name="email" id="profile-email" class="form-input" required>
    </div>
    <div class="form-group">
      <label class="form-label">Phone</label>
      <input type="tel" name="phone" id="profile-phone" class="form-input">
    </div>
    <button type="submit" class="btn btn-primary">Update Profile</button>
  </form>

  <hr style="margin:2rem 0;">

  <form id="password-form">
    <div class="form-group">
      <label class="form-label">Current Password</label>
      <input type="password" name="current_password" id="current-password" class="form-input" required>
    </div>
    <div class="form-group">
      <label class="form-label">New Password</label>
      <input type="password" name="new_password" id="new-password" class="form-input" required>
    </div>
    <div class="form-group">
      <label class="form-label">Confirm New Password</label>
      <input type="password" name="confirm_password" id="confirm-password" class="form-input" required>
    </div>
    <button type="submit" class="btn btn-primary">Change Password</button>
  </form>

  <hr style="margin:2rem 0;">

  <button id="logout-btn" class="btn btn-primary" style="background:#ef4444;">Logout</button>
</div>

<script>
  function fetchMember() {
    fetch('get_member.php')
      .then(res => res.json())
      .then(user => {
        document.getElementById('profile-name').value = user.name;
        document.getElementById('profile-email').value = user.email;
        document.getElementById('profile-phone').value = user.phone;
      });
  }

  fetchMember();

  document.getElementById('profile-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('update_member_profile.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(res => {
      showAlert(res.message, res.success ? 'success' : 'error');
    });
  });

  document.getElementById('password-form').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    fetch('change_member_password.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(res => {
      showAlert(res.message, res.success ? 'success' : 'error');
      if(res.success) this.reset();
    });
  });

  document.getElementById('logout-btn').addEventListener('click', function(){
    window.location.href = 'logout.php';
  });

  function showAlert(message, type){
    const alertDiv = document.getElementById('alert');
    alertDiv.innerHTML = `<div class="alert-${type}">${message}</div>`;
    setTimeout(()=> alertDiv.innerHTML='', 5000);
  }
</script>

</body>
</html>
