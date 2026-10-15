<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

if (isset($_POST['update_room'])) {
  $rid = $_POST['room_id'];
  $st = $_POST['status'];
  $stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE room_id = ?");
  $stmt->execute([$st, $rid]);
  $message = "Room physical status updated successfully!";
}

if (isset($_POST['update_booking'])) {
  $bid = $_POST['booking_id'];
  $st = $_POST['status'];
  $stmt = $conn->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?");
  $stmt->execute([$st, $bid]);
  $message = "Booking status updated!";
}

if (isset($_POST['update_profile'])) {
  $fname = $_POST['full_name'];
  $phone = $_POST['phone'];
  $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
  $stmt->execute([$fname, $phone, $user_id]);
  $_SESSION['full_name'] = $fname;
  $message = "Profile updated!";
}

if (isset($_GET['paid_id'])) {
  $booking_id = $_GET['paid_id'];
  try {
    // Update the payment status to paid
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $stmt2 = $conn->prepare("UPDATE payments SET payment_status = 'paid' WHERE booking_id = ?");
    $stmt2->execute([$booking_id]);

    // Optional: Update the booking status to confirmed automatically if you wish
    // $stmt2 = $conn->prepare("UPDATE bookings SET booking_status = 'confirmed' WHERE booking_id = ?");
    // $stmt2->execute([$booking_id]);

    header("Location: admin-dashboard.php?msg=paid_success");
    exit();
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage();
  }
}

$rooms_managed = $conn->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$total_hotel_bookings = $conn->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

$staff_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$staff_stmt->execute([$user_id]);
$staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);

$total_rooms = $conn->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$available_rooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();
$occupied_rooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'")->fetchColumn();
$pending_bookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'")->fetchColumn();

$rooms_list = $conn->query("
    SELECT 
        r.room_id, r.room_number, r.room_name, r.room_type, r.price_per_night, r.status AS physical_status, 
        GROUP_CONCAT(
            IF(b.booking_status NOT IN ('cancelled', 'refunded', 'checked_out') AND b.check_out_date >= CURRENT_DATE,
                CONCAT(u.full_name, ' (', DATE_FORMAT(b.check_in_date, '%d/%m'), '-', DATE_FORMAT(b.check_out_date, '%d/%m'), ')'),
                NULL
            ) ORDER BY b.check_in_date ASC SEPARATOR '||'
        ) as all_bookings,
        (SELECT COUNT(*) FROM bookings b2 
         WHERE b2.room_id = r.room_id 
         AND CURRENT_DATE BETWEEN b2.check_in_date AND b2.check_out_date 
         AND b2.booking_status NOT IN ('cancelled', 'refunded', 'checked_out')) as is_occupied_today
    FROM rooms r 
    LEFT JOIN bookings b ON r.room_id = b.room_id
    LEFT JOIN users u ON b.user_id = u.user_id
    GROUP BY r.room_id, r.room_number, r.room_name, r.room_type, r.price_per_night, r.status
    ORDER BY r.room_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

$bookings_list = $conn->query("
    SELECT b.*, u.full_name as guest_name, r.room_name, r.room_number 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.user_id 
    LEFT JOIN rooms r ON b.room_id = r.room_id 
    ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$staff_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$staff_stmt->execute([$user_id]);
$staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard - SmartStay</title>
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
      --radius: 0.625rem;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, sans-serif;
      background-color: #f8fafc;
      color: var(--foreground);
      line-height: 1.5;
    }

    .hidden {
      display: none !important;
    }

    .container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 1rem;
    }

    .header {
      background: white;
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      z-index: 50;
      padding: 1rem 0;
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      border-radius: var(--radius);
      border: none;
      cursor: pointer;
      font-weight: 500;
      text-decoration: none;
      font-size: 0.8rem;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
    }

    .card {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .tabs-list {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      background: var(--muted);
      padding: 0.25rem;
      border-radius: var(--radius);
      margin: 2rem 0;
    }

    .tabs-trigger {
      padding: 0.5rem;
      border: none;
      background: transparent;
      cursor: pointer;
      border-radius: var(--radius);
    }

    .tabs-trigger.active {
      background: white;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      font-weight: bold;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.875rem;
    }

    .table th,
    .table td {
      padding: 1rem;
      border-bottom: 1px solid var(--border);
      text-align: left;
    }

    .badge {
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      font-size: 0.75rem;
      color: white;
    }

    .badge-green {
      background: #10b981;
    }

    .badge-blue {
      background: #3b82f6;
    }

    .badge-red {
      background: #ef4444;
    }

    .badge-yellow {
      background: #f59e0b;
    }

    .form-input {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      margin-bottom: 1rem;
    }

    .footer {
      background: #0f172a;
      color: white;
      padding: 2rem 0;
      text-align: center;
      margin-top: 3rem;
    }

    .alert {
      padding: 1rem;
      background: #dcfce7;
      color: #166534;
      border-radius: var(--radius);
      margin-bottom: 1rem;
      border: 1px solid #bbf7d0;
    }
  </style>
</head>

<body>

  <header class="header">
    <div class="container header-content">
      <div style="display:flex; align-items:center; gap:0.5rem;">
        <img src="smartstaylogo1.png" style="height:3rem;">
        <span style="font-weight:bold;">Staff Portal</span>
      </div>
      <div style="display:flex; align-items:center; gap:1rem;">
        <div style="text-align:right;">
          <p><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></p>
          <p style="font-size:0.75rem; color:gray;"><?php echo htmlspecialchars($staff['email']); ?></p>
        </div>
        <a href="logout.php" class="btn btn-outline">Logout</a>
      </div>
    </div>
  </header>

  <div class="container py-8">

    <?php if ($message): ?>
      <div class="alert"><?php echo $message; ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 2rem;">
      <div class="card">
        <p style="color:gray; font-size:0.8rem;">Total Rooms</p>
        <h3><?php echo $total_rooms; ?></h3>
      </div>
      <div class="card">
        <p style="color:gray; font-size:0.8rem;">Available Today</p>
        <h3 style="color:green;"><?php echo $available_rooms; ?></h3>
      </div>
      <div class="card">
        <p style="color:gray; font-size:0.8rem;">Occupied Today</p>
        <h3 style="color:blue;"><?php echo $occupied_rooms; ?></h3>
      </div>
      <div class="card">
        <p style="color:gray; font-size:0.8rem;">Pending Bookings</p>
        <h3 style="color:orange;"><?php echo $pending_bookings; ?></h3>
      </div>
    </div>

    <div class="tabs-list">
      <button class="tabs-trigger active" onclick="showTab('rooms', this)">Room Status</button>
      <button class="tabs-trigger" onclick="showTab('bookings', this)">Booking Management</button>
      <button class="tabs-trigger" onclick="showTab('profile', this)">My Profile</button>
    </div>

    <div id="tab-rooms" class="tab-content">
      <div class="card">
        <table class="table">
          <thead>
            <tr>
              <th>Room No</th>
              <th>Room Info</th>
              <th>Schedule / Guests</th>
              <th>Live Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rooms_list as $r): ?>
              <tr>
                <td><strong><?php echo $r['room_number']; ?></strong></td>
                <td>
                  <div style="font-weight: bold;"><?php echo htmlspecialchars($r['room_name']); ?></div>
                  <div style="font-size: 0.75rem; color: #777;"><?php echo htmlspecialchars($r['room_type']); ?></div>
                </td>
                <td>
                  <?php if (!empty($r['all_bookings'])): ?>
                    <?php
                    $booking_items = explode('||', $r['all_bookings']);
                    foreach ($booking_items as $item):
                      $is_today = (strpos($r['all_bookings'], $item) !== false && $r['is_occupied_today'] > 0);
                      ?>
                      <div
                        style="font-size: 0.8rem; background: <?php echo $is_today ? '#e0f2fe' : '#f3f4f6'; ?>; padding: 4px 8px; border-radius: 4px; margin-bottom: 4px; border-left: 3px solid <?php echo $is_today ? '#0284c7' : '#9ca3af'; ?>;">
                        <?php echo htmlspecialchars($item); ?>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span style="color: #ccc; font-size: 0.8rem;">— No Upcoming Bookings —</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  $p_status = strtolower($r['physical_status']);
                  $status_text = 'AVAILABLE';
                  $badge_class = 'badge-green';

                  if ($p_status == 'maintenance') {
                    $status_text = 'MAINTENANCE';
                    $badge_class = 'badge-red';
                  } elseif ($r['is_occupied_today'] > 0) {
                    $status_text = 'OCCUPIED';
                    $badge_class = 'badge-blue';
                  }
                  ?>
                  <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                </td>
                <td>
                  <form method="POST" style="display:flex; gap:5px;">
                    <input type="hidden" name="room_id" value="<?php echo $r['room_id']; ?>">
                    <select name="status" class="btn btn-outline" style="padding:0.2rem; font-size:0.7rem;">
                      <option value="available" <?php echo $r['physical_status'] == 'available' ? 'selected' : ''; ?>>Ready
                      </option>
                      <option value="maintenance" <?php echo $r['physical_status'] == 'maintenance' ? 'selected' : ''; ?>>
                        Repair
                      </option>
                    </select>
                    <button type="submit" name="update_room" class="btn btn-primary">Save</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab-bookings" class="tab-content hidden">
      <div class="card">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Guest</th>
              <th>Room Info</th>
              <th>Check-In / Out</th>
              <th>Payment Type</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bookings_list as $b): ?>
              <tr>
                <td>#<?php echo $b['booking_id']; ?></td>
                <td><?php echo htmlspecialchars($b['guest_name']); ?></td>
                <td>
                  <strong>No: <?php echo htmlspecialchars($b['room_number']); ?></strong><br>
                  <small style="color: gray;"><?php echo htmlspecialchars($b['room_name']); ?></small>
                </td>
                <td>
                  <div style="font-size: 0.85rem;">
                    🛫 <?php echo date('d M', strtotime($b['check_in_date'])); ?>
                    ➡️ 🛬 <?php echo date('d M', strtotime($b['check_out_date'])); ?>
                  </div>
                </td>
                <td><?php echo ucfirst(strtolower($b['payment_method'])); ?></td>
                <td>
                  <?php
                  $b_status = $b['booking_status'];
                  $cls = 'badge-blue';
                  if ($b_status == 'pending' || $b_status == 'pending_approval')
                    $cls = 'badge-yellow';
                  if ($b_status == 'cancelled')
                    $cls = 'badge-red';
                  if ($b_status == 'confirmed' || $b_status == 'completed')
                    $cls = 'badge-green';
                  ?>
                  <span class="badge <?php echo $cls; ?>"><?php echo strtoupper($b_status); ?></span>
                </td>
                <td>
                  <form method="POST" style="display:flex; gap:5px;">
                    <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                    <select name="status" class="btn btn-outline" style="padding:0.2rem; font-size:0.7rem;">
                      <option value="pending" <?php echo $b['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending
                      </option>
                      <option value="confirmed" <?php echo $b['booking_status'] == 'confirmed' ? 'selected' : ''; ?>>
                        Confirmed
                      </option>
                      <option value="completed" <?php echo $b['booking_status'] == 'completed' ? 'selected' : ''; ?>>
                        Completed
                      </option>
                      <option value="checked_out" <?php echo $b['booking_status'] == 'checked_out' ? 'selected' : ''; ?>>
                        Checked
                        Out</option>
                      <option value="cancelled" <?php echo $b['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>
                        Cancelled
                      </option>
                    </select>
                    <button type="submit" name="update_booking" class="btn btn-primary">Update</button>
                    <?php if (strtolower($b['payment_method']) == 'cash' && $b['booking_status'] !== 'cancelled' && $b['payment_status'] == 'cash_pending'): ?>
                      <a href="staff-dashboard.php?paid_id=<?php echo $b['booking_id']; ?>" class="btn"
                        style="background-color: #10b981; color: white; font-size:0.7rem; padding: 5px 10px; text-decoration: none; border-radius: 4px;"
                        onclick="return confirm('Sahkan bayaran tunai diterima?')">
                        Mark as Paid
                      </a>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab-profile" class="tab-content hidden">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: start;">

        <div class="card">
          <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 1.1rem; font-weight: bold;">Profile Information</h3>
            <p style="font-size: 0.8rem; color: var(--muted-foreground);">Your personal account details</p>
          </div>
          <div style="padding: 1.5rem;">
            <form method="POST">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-input"
                  value="<?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" value="<?php echo htmlspecialchars($staff['email'] ?? ''); ?>"
                  readonly style="background: var(--muted); cursor: not-allowed;">
              </div>
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-input"
                  value="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>" required>
              </div>
              <button type="submit" name="update_profile" class="btn btn-primary"
                style="width: 100%; margin-top: 1rem;">Save Changes</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 1.1rem; font-weight: bold;">Account Statistics</h3>
            <p style="font-size: 0.8rem; color: var(--muted-foreground);">System activity summary</p>
          </div>
          <div style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
              <div
                style="text-align: center; padding: 1rem; background-color: #f0f7ff; border-radius: var(--radius); border: 1px solid #dbeafe;">
                <div style="font-size: 1.5rem; font-weight: bold; color: #2563eb;"><?php echo $rooms_managed; ?></div>
                <div style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Rooms
                  Managed</div>
              </div>
              <div
                style="text-align: center; padding: 1rem; background-color: #f0fdf4; border-radius: var(--radius); border: 1px solid #dcfce7;">
                <div style="font-size: 1.5rem; font-weight: bold; color: #059669;"><?php echo $total_hotel_bookings; ?>
                </div>
                <div style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 600;">System
                  Bookings</div>
              </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
              <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #475569;">
                <input type="checkbox" checked disabled> System Access Level: <strong>Staff</strong>
              </label>
              <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #475569;">
                <input type="checkbox" checked disabled> Email Notifications Enabled
              </label>
              <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #475569;">
                <input type="checkbox" checked disabled> Logged in via Secure Staff Portal
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <footer class="footer">
      <p>© 2026 SmartStay System Management</p>
    </footer>

    <script>
      function showTab(id, btn) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.add('hidden'));
        document.getElementById('tab-' + id).classList.remove('hidden');
        document.querySelectorAll('.tabs-trigger').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      }
    </script>
</body>

</html>