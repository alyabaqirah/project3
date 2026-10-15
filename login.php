<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - SmartStay</title>
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
      --font-size: 16px;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      font-size: var(--font-size);
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
      background: linear-gradient(to bottom right, #f8fafc, #f1f5f9);
      color: var(--foreground);
      line-height: 1.5;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .hidden {
      display: none !important;
    }

    .auth-container {
      width: 100%;
      max-width: 28rem;
    }

    .auth-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      font-size: 1rem;
      font-weight: 500;
      border-radius: var(--radius);
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
    }

    .btn-ghost {
      background-color: transparent;
      color: var(--foreground);
    }

    .btn-ghost:hover {
      background-color: rgba(0, 0, 0, 0.05);
    }

    .btn-primary {
      background-color: var(--primary);
      color: var(--primary-foreground);
      width: 100%;
    }

    .btn-primary:hover {
      opacity: 0.9;
    }

    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .card {
      background-color: var(--card);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    .card-header {
      padding: 1.5rem;
      border-bottom: 1px solid var(--border);
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    .card-description {
      font-size: 0.875rem;
      color: var(--muted-foreground);
    }

    .card-content {
      padding: 1.5rem;
    }

    .tabs-list {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      background-color: var(--muted);
      border-radius: var(--radius);
      padding: 0.25rem;
      margin-bottom: 1.5rem;
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

    .tabs-trigger:hover {
      background-color: rgba(255, 255, 255, 0.5);
    }

    .tabs-trigger.active {
      background-color: white;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    .form-group {
      margin-bottom: 1rem;
    }

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
      transition: all 0.2s;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(3, 2, 19, 0.1);
    }

    .form-info {
      padding: 0.75rem;
      border-radius: var(--radius);
      font-size: 0.875rem;
      margin-bottom: 1rem;
      line-height: 1.4;
    }

    .form-info-blue {
      background-color: #dbeafe;
      color: #1e3a8a;
      border-left: 3px solid #3b82f6;
    }

    .form-info-green {
      background-color: #d1fae5;
      color: #065f46;
      border-left: 3px solid #10b981;
    }

    .form-info-purple {
      background-color: #e9d5ff;
      color: #581c87;
      border-left: 3px solid #8b5cf6;
    }

    .form-error {
      background-color: #fee2e2;
      color: #991b1b;
      padding: 0.75rem;
      border-radius: var(--radius);
      font-size: 0.875rem;
      margin-top: 1rem;
      border-left: 3px solid #ef4444;
    }

    .form-success {
      background-color: #d1fae5;
      color: #065f46;
      padding: 0.75rem;
      border-radius: var(--radius);
      font-size: 0.875rem;
      margin-top: 1rem;
      border-left: 3px solid #10b981;
    }

    .flex {
      display: flex;
    }

    .items-center {
      align-items: center;
    }

    .justify-center {
      justify-content: center;
    }

    .gap-2 {
      gap: 0.5rem;
    }

    .mb-2 { margin-bottom: 0.5rem; }
    .mb-4 { margin-bottom: 1rem; }
    .mt-4 { margin-top: 1rem; }
    .mt-6 { margin-top: 1.5rem; }

    .text-3xl {
      font-size: 1.875rem;
    }

    .text-sm {
      font-size: 0.875rem;
    }

    .text-center {
      text-align: center;
    }

    .text-slate-600 {
      color: #475569;
    }

    .text-blue-600 {
      color: #2563eb;
    }

    .icon-sm {
      width: 1rem;
      height: 1rem;
    }

    .icon-lg {
      width: 2rem;
      height: 2rem;
    }

    input[type="radio"] {
      display: none;
    }

    .link-btn {
      background: none;
      border: none;
      cursor: pointer;
      text-decoration: underline;
      color: #2563eb;
      padding: 0;
      font-size: inherit;
    }
  </style>
</head>
<body>
  
  <div class="auth-container">
    <div class="auth-header">
      <a href="guest-home.html" class="btn btn-ghost mb-4">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Home
      </a>
      <div class="flex items-center justify-center gap-2 mb-2">
        <img src="smartstaylogo1.png" alt="SmartStay System" style="height: 2rem;">
      </div>
      <p class="text-slate-600">Sign in to your account</p>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Login</h3>
        <p class="card-description">Choose your role and enter your credentials</p>
      </div>
      <div class="card-content">
        <div class="tabs-list">
          <label class="tabs-trigger active">
            <input type="radio" name="login-role" value="member" checked>
            Member
          </label>
          <label class="tabs-trigger">
            <input type="radio" name="login-role" value="staff">
            Staff
          </label>
          <label class="tabs-trigger">
            <input type="radio" name="login-role" value="admin">
            Admin
          </label>
        </div>

        <form id="login-form">
          <div data-role-info="member" class="form-info form-info-blue">
            Login as a member to book rooms and manage your reservations
          </div>
          <div data-role-info="staff" class="form-info form-info-green hidden">
            Login as staff to manage rooms and bookings
          </div>
          <div data-role-info="admin" class="form-info form-info-purple hidden">
            Login as administrator for full system access
          </div>

          <div class="form-group">
            <label for="login-email" class="form-label">Email</label>
            <input type="email" id="login-email" class="form-input" placeholder="Enter your email" required>
          </div>

          <div class="form-group">
            <label for="login-password" class="form-label">Password</label>
            <input type="password" id="login-password" class="form-input" placeholder="Enter your password" required>
          </div>

          <div id="login-message" class="hidden"></div>

          <button type="submit" class="btn btn-primary mt-4">Log In</button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-sm text-slate-600">
            Don't have an account? 
            <a href="register.html" class="text-blue-600 link-btn">Register Here</a>
          </p>
        </div>
      </div>
    </div>
  </div>

  <script type="module">
    let currentRole = 'member';

    // Role tab switching
    document.querySelectorAll('input[name="login-role"]').forEach(radio => {
      radio.addEventListener('change', (e) => {
        currentRole = e.target.value;
        
        // Update active tab
        document.querySelectorAll('.tabs-trigger').forEach(t => t.classList.remove('active'));
        e.target.closest('.tabs-trigger').classList.add('active');
        
        // Show role info
        document.querySelectorAll('[data-role-info]').forEach(info => info.classList.add('hidden'));
        document.querySelector(`[data-role-info="${currentRole}"]`).classList.remove('hidden');
      });
    });

    // Handle login
    document.getElementById('login-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const submitBtn = e.target.querySelector('button[type="submit"]');
      const messageDiv = document.getElementById('login-message');
      
      const email = document.getElementById('login-email').value.trim();
      const password = document.getElementById('login-password').value;

      // Validate inputs
      if (!email || !password) {
        messageDiv.textContent = 'Please enter both email and password.';
        messageDiv.className = 'form-error';
        messageDiv.classList.remove('hidden');
        return;
      }

      // Disable submit button
      submitBtn.disabled = true;
      submitBtn.textContent = 'Logging in...';

      // Prepare form data
      const formData = new FormData();
      formData.append('email', email);
      formData.append('password', password);
      formData.append('role', currentRole);

      try {
        const response = await fetch('login_process.php', {
          method: 'POST',
          body: formData
        });

        // Check if response is OK
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get response text first
        const responseText = await response.text();
        
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (parseError) {
          console.error('Response was not valid JSON:', responseText);
          throw new Error('Server returned invalid response. Please check server configuration.');
        }

        if (result.success) {
          messageDiv.textContent = result.message;
          messageDiv.className = 'form-success';
          messageDiv.classList.remove('hidden');

          // Redirect to appropriate dashboard
          setTimeout(() => {
            window.location.href = result.redirect;
          }, 1000);
        } else {
          messageDiv.textContent = result.message;
          messageDiv.className = 'form-error';
          messageDiv.classList.remove('hidden');
          
          // Re-enable submit button
          submitBtn.disabled = false;
          submitBtn.textContent = 'Log In';
        }

      } catch (err) {
        console.error('Login error:', err);
        messageDiv.textContent = err.message || 'Server error. Please try again later.';
        messageDiv.className = 'form-error';
        messageDiv.classList.remove('hidden');
        
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Log In';
      }
    });
  </script>
</body>
</html>