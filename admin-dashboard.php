<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- ACTIONS LOGIC ---
$profile_msg = "";
if (isset($_POST['update_admin_profile'])) {
    $fname = $_POST['full_name'];
    $phone = $_POST['phone'];
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
    if ($stmt->execute([$fname, $phone, $user_id])) {
        $_SESSION['full_name'] = $fname;
        $profile_msg = "Profile updated successfully!";
    }
}

// --- APPROVE PENDING ACCOUNT ---
if (isset($_GET['approve_account'])) {
    $approve_user_id = $_GET['approve_account'];
    $tab = $_GET['tab'] ?? '';
    try {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $stmt->execute([$approve_user_id]);
        $redirect_url = 'admin-dashboard.php?msg=account_approved';
        if ($tab) $redirect_url .= '&tab=' . $tab;
        header("Location: $redirect_url");
        exit();
    } catch (Exception $e) {
        echo "Error approving account: " . $e->getMessage();
    }
}

// --- REJECT PENDING ACCOUNT ---
if (isset($_GET['reject_account'])) {
    $reject_user_id = $_GET['reject_account'];
    $tab = $_GET['tab'] ?? '';
    try {
        // Delete the pending account
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$reject_user_id]);
        $redirect_url = 'admin-dashboard.php?msg=account_rejected';
        if ($tab) $redirect_url .= '&tab=' . $tab;
        header("Location: $redirect_url");
        exit();
    } catch (Exception $e) {
        echo "Error rejecting account: " . $e->getMessage();
    }
}

// --- UPDATE BOOKING STATUS ---
if (isset($_POST['update_booking'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['status'];

    try {
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET booking_status = ? 
            WHERE booking_id = ?
        ");
        $stmt->execute([$new_status, $booking_id]);

        header("Location: admin-dashboard.php?msg=booking_updated");
        exit();
    } catch (Exception $e) {
        echo "Error updating booking: " . $e->getMessage();
    }
}

// --- UPDATE USER DETAILS ---
if (isset($_POST['update_user'])) {
    $uid = $_POST['user_id'];
    $name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("
        UPDATE users 
        SET full_name = ?, phone = ?, role = ? 
        WHERE user_id = ?
    ");
    $stmt->execute([$name, $phone, $role, $uid]);

    header("Location: admin-dashboard.php?msg=user_updated");
    exit();
}

//TOGGLE ACTIVE INACTIVE
if (isset($_GET['toggle_user'])) {
    $uid = $_GET['toggle_user'];

    $stmt = $conn->prepare("
        UPDATE users 
        SET status = IF(status='active','inactive','active') 
        WHERE user_id = ?
    ");
    $stmt->execute([$uid]);

    header("Location: admin-dashboard.php?msg=status_changed");
    exit();
}

//DELETE USER EXCEPT ADMIN
if (isset($_GET['delete_user'])) {
    $uid = $_GET['delete_user'];

    // Prevent deleting yourself
    if ($uid != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$uid]);
    }

    header("Location: admin-dashboard.php?msg=user_deleted");
    exit();
}

if (isset($_GET['approve_id'])) {
    $booking_id = $_GET['approve_id'];
    try {
        $conn->beginTransaction();
        $stmt_get = $conn->prepare("SELECT user_id, room_id, total_amount FROM bookings WHERE booking_id = ?");
        $stmt_get->execute([$booking_id]);
        $booking = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            $refund_amount = (float) $booking['total_amount'];
            $negative_amount = 0 - $refund_amount;

            $stmt1 = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', payment_status = 'refunded', refund_status = 'completed' WHERE booking_id = ?");
            $stmt1->execute([$booking_id]);

            $refund_txn_id = "REF-APP-" . strtoupper(uniqid());
            $stmt2 = $conn->prepare("INSERT INTO payments (booking_id, user_id, payment_method, payment_status, transaction_id, amount_paid, payment_date) VALUES (?, ?, 'refund', 'refunded', ?, ?, CURRENT_TIMESTAMP)");
            $stmt2->execute([$booking_id, $booking['user_id'], $refund_txn_id, $negative_amount]);

            $conn->commit();
            header("Location: admin-dashboard.php?msg=approved");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}

if (isset($_GET['reject_id'])) {
    $booking_id = $_GET['reject_id'];
    $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'confirmed', refund_requested = 0, refund_status = 'rejected' WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    header("Location: admin-dashboard.php?msg=rejected");
    exit();
}

if (isset($_GET['cancel_id'])) {
    $booking_id = $_GET['cancel_id'];
    try {
        $conn->beginTransaction();
        $stmt1 = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', refund_status = 'completed' WHERE booking_id = ?");
        $stmt1->execute([$booking_id]);
        $stmt2 = $conn->prepare("UPDATE payments SET payment_status = 'unpaid' WHERE booking_id = ?");
        $stmt2->execute([$booking_id]);
        $conn->commit();
        header("Location: admin-dashboard.php?msg=cancelled");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}

if (isset($_GET['refund_id'])) {
    $booking_id = $_GET['refund_id'];
    try {
        $conn->beginTransaction();
        $stmt_get = $conn->prepare("SELECT user_id, total_amount FROM bookings WHERE booking_id = ?");
        $stmt_get->execute([$booking_id]);
        $booking = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            $negative_amount = 0 - (float) $booking['total_amount'];
            $stmt1 = $conn->prepare("UPDATE bookings SET payment_status = 'refunded', refund_status = 'completed' WHERE booking_id = ?");
            $stmt1->execute([$booking_id]);

            $refund_txn_id = "REF-MAN-" . strtoupper(uniqid());
            $insert_pay = $conn->prepare("INSERT INTO payments (booking_id, user_id, payment_method, payment_status, transaction_id, amount_paid, payment_date) VALUES (?, ?, 'refund', 'refunded', ?, ?, CURRENT_TIMESTAMP)");
            $insert_pay->execute([$booking_id, $booking['user_id'], $refund_txn_id, $negative_amount]);

            $conn->commit();
            header("Location: admin-dashboard.php?msg=refunded");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}

if (isset($_GET['paid_id'])) {
    $booking_id = $_GET['paid_id'];
    try {
        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $stmt2 = $conn->prepare("UPDATE payments SET payment_status = 'paid' WHERE booking_id = ?");
        $stmt2->execute([$booking_id]);

        header("Location: admin-dashboard.php?msg=paid_success");
        exit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

// --- DATA FETCHING ---
$stat_revenue = $conn->query("SELECT SUM(amount_paid) FROM payments")->fetchColumn() ?: 0;
$stat_bookings = $conn->query("SELECT count(*) FROM bookings")->fetchColumn();
$stat_members = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$stat_rooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();

// Get pending accounts count
$pending_accounts = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

// Get pending accounts list
$pending_list = $conn->query("SELECT user_id, full_name, email, phone, role, staff_id, admin_id, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$rooms_list = $conn->query("
    SELECT r.room_id, r.room_number, r.room_name, r.room_type, r.price_per_night, r.status AS physical_status, 
    GROUP_CONCAT(IF(b.booking_status NOT IN ('cancelled', 'refunded', 'checked_out') AND b.check_out_date >= CURRENT_DATE, CONCAT(u.full_name, ' (', DATE_FORMAT(b.check_in_date, '%d/%m'), '-', DATE_FORMAT(b.check_out_date, '%d/%m'), ')'), NULL) ORDER BY b.check_in_date ASC SEPARATOR '||') as all_bookings,
    (SELECT COUNT(*) FROM bookings b2 WHERE b2.room_id = r.room_id AND CURRENT_DATE BETWEEN b2.check_in_date AND b2.check_out_date AND b2.booking_status NOT IN ('cancelled', 'refunded', 'checked_out')) as is_occupied_today
    FROM rooms r LEFT JOIN bookings b ON r.room_id = b.room_id LEFT JOIN users u ON b.user_id = u.user_id
    GROUP BY r.room_id, r.room_number, r.room_name, r.room_type, r.price_per_night, r.status ORDER BY r.room_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

$bookings_list = $conn->query("SELECT b.*, u.full_name as guest_name, r.room_name, r.room_number FROM bookings b LEFT JOIN users u ON b.user_id = u.user_id LEFT JOIN rooms r ON b.room_id = r.room_id ORDER BY b.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$users_list = $conn->query("SELECT * FROM users WHERE status != 'pending' ORDER BY role ASC")->fetchAll(PDO::FETCH_ASSOC);
$payments_list = $conn->query("SELECT p.*, u.full_name, b.total_amount FROM payments p JOIN users u ON p.user_id = u.user_id JOIN bookings b ON p.booking_id = b.booking_id ORDER BY p.payment_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$admin_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$admin_stmt->execute([$user_id]);
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// --- REPORT LOGIC ---
$selected_year = $_GET['report_year'] ?? date('Y');
$selected_month = $_GET['report_month'] ?? '';
$report_period = $_GET['report_period'] ?? '';
$report_title = "Annual Report for $selected_year";

$query_where = " WHERE 1=1";
$params = [];

if ($report_period == 'today') {
    $query_where .= " AND DATE(p.payment_date) = CURDATE()";
    $report_title = "Today's Report (" . date('d M Y') . ")";
} elseif ($report_period == 'this_month') {
    $query_where .= " AND MONTH(p.payment_date) = MONTH(CURRENT_DATE()) AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())";
    $report_title = "This Month's Report (" . date('F Y') . ")";
} elseif (!empty($selected_month)) {
    $query_where .= " AND YEAR(p.payment_date) = ? AND MONTH(p.payment_date) = ?";
    $params = [$selected_year, $selected_month];
    $report_title = date("F", mktime(0, 0, 0, $selected_month, 10)) . " $selected_year Report";
} else {
    $query_where .= " AND YEAR(p.payment_date) = ?";
    $params = [$selected_year];
}

$stmt_rev = $conn->prepare("SELECT SUM(amount_paid) FROM payments p " . $query_where . " AND payment_status = 'paid' and booking_id NOT IN (SELECT booking_id FROM payments WHERE payment_status = 'refunded')");
$stmt_rev->execute($params);
$stat_report = $stmt_rev->fetchColumn() ?: 0;

$stmt_items = $conn->prepare("SELECT p.*, u.full_name, r.room_number FROM payments p JOIN users u ON p.user_id = u.user_id JOIN bookings b ON p.booking_id = b.booking_id JOIN rooms r ON b.room_id = r.room_id" . $query_where . " AND booking_status != 'paid' AND b.payment_status != 'cash_pending' ORDER BY payment_date DESC");
$stmt_items->execute($params);
$report_transactions = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$booking_where = ($report_period == 'today') ? " AND DATE(created_at) = CURDATE()" : (($report_period == 'this_month') ? " AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())" : (!empty($selected_month) ? " AND YEAR(created_at) = ? AND MONTH(created_at) = ?" : " AND YEAR(created_at) = ?"));
$stmt_cancel = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE 1=1" . $booking_where . " AND booking_status = 'cancelled'");
$stmt_cancel->execute($params);
$cancelled_count = $stmt_cancel->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SmartStay</title>
    <link rel="stylesheet" href="AdminDashboard.css">
</head>

<body>
    <header class="header">
        <div class="container header-content">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <img src="smartstaylogo1.png" alt="Logo" style="height:3rem;">
                <span style="font-weight:bold;">Admin Panel</span>
            </div>
            <div style="display:flex; align-items:center; gap:1rem;">
                <div style="text-align:right;">
                    <p><strong><?php echo htmlspecialchars($admin['full_name']); ?></strong></p>
                    <p style="font-size:0.75rem; color:gray;">System Administrator</p>
                </div>
                <?php if ($pending_accounts > 0): ?>
                <span style="background: #ef4444; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: bold;">
                    <?php echo $pending_accounts; ?> Pending Approval<?php echo $pending_accounts > 1 ? 's' : ''; ?>
                </span>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; border-left: 3px solid; 
                <?php 
                if ($_GET['msg'] == 'account_approved') {
                    echo 'background: #d1fae5; color: #065f46; border-color: #10b981;';
                } elseif ($_GET['msg'] == 'account_rejected') {
                    echo 'background: #fee2e2; color: #991b1b; border-color: #ef4444;';
                } elseif ($_GET['msg'] == 'booking_updated' || $_GET['msg'] == 'user_updated' || $_GET['msg'] == 'status_changed') {
                    echo 'background: #dbeafe; color: #1e3a8a; border-color: #3b82f6;';
                } elseif ($_GET['msg'] == 'user_deleted') {
                    echo 'background: #fee2e2; color: #991b1b; border-color: #ef4444;';
                } else {
                    echo 'background: #dbeafe; color: #1e3a8a; border-color: #3b82f6;';
                }
                ?>">
                <?php
                if ($_GET['msg'] == 'account_approved') echo '✅ Account approved successfully!';
                elseif ($_GET['msg'] == 'account_rejected') echo '❌ Account rejected and removed.';
                elseif ($_GET['msg'] == 'booking_updated') echo 'Booking status updated.';
                elseif ($_GET['msg'] == 'user_updated') echo 'User details updated.';
                elseif ($_GET['msg'] == 'status_changed') echo 'User status changed.';
                elseif ($_GET['msg'] == 'user_deleted') echo 'User deleted.';
                ?>
            </div>
        <?php endif; ?>

        <div class="tabs-list no-print">
            <button class="tabs-trigger active" onclick="showTab('overview', this)">Overview</button>
            <button class="tabs-trigger" onclick="showTab('rooms', this)">Room Status</button>
            <button class="tabs-trigger" onclick="showTab('bookings', this)">Bookings</button>
            <button class="tabs-trigger" onclick="showTab('financial', this)">Transactions</button>
            <button class="tabs-trigger" onclick="showTab('reports', this)">Reports</button>
            <button class="tabs-trigger" onclick="showTab('users', this)">
                Users<?php if ($pending_accounts > 0): ?> <span style="background: #ef4444; color: white; padding: 0.15rem 0.5rem; border-radius: 10px; font-size: 0.7rem; margin-left: 0.25rem;"><?php echo $pending_accounts; ?></span><?php endif; ?>
            </button>
            <button class="tabs-trigger" onclick="showTab('profile', this)">Profile</button>
        </div>

        <div id="tab-overview" class="tab-content">
            <div class="grid md-grid-cols-4">
                <div class="card">
                    <p class="stat-label">Total Revenue</p>
                    <p class="stat-value">RM<?php echo number_format($stat_revenue, 2); ?></p>
                </div>
                <div class="card">
                    <p class="stat-label">Total Bookings</p>
                    <p class="stat-value"><?php echo $stat_bookings; ?></p>
                </div>
                <div class="card">
                    <p class="stat-label">Active Members</p>
                    <p class="stat-value"><?php echo $stat_members; ?></p>
                </div>
                <div class="card">
                    <p class="stat-label">Available Rooms</p>
                    <p class="stat-value"><?php echo $stat_rooms; ?></p>
                </div>
            </div>
            <?php if ($pending_accounts > 0): ?>
            <div class="card" style="margin-top: 2rem; border-left: 4px solid #f59e0b; background: #fffbeb;">
                <div style="padding: 1.5rem;">
                    <h3 style="color: #92400e; margin-bottom: 0.5rem;">⚠️ Pending Account Approvals</h3>
                    <p style="color: #78350f; font-size: 0.9rem; margin-bottom: 1rem;">
                        You have <strong><?php echo $pending_accounts; ?></strong> account<?php echo $pending_accounts > 1 ? 's' : ''; ?> waiting for your approval. Check the Users section to review and approve them.
                    </p>
                    <button onclick="showTab('users', document.querySelectorAll('.tabs-trigger')[5])" class="btn btn-primary" style="background: #f59e0b;">
                        Review Pending Accounts
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($pending_accounts > 0): ?>
        <div id="tab-approvals" class="tab-content hidden">
        </div>
        <?php endif; ?>

        <div id="tab-rooms" class="tab-content hidden">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Room No</th>
                            <th>Room Info</th>
                            <th>Schedule / Guests</th>
                            <th>Price</th>
                            <th>Live Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms_list as $r): ?>
                            <tr>
                                <td><strong><?php echo $r['room_number']; ?></strong></td>
                                <td>
                                    <div style="font-weight: bold;"><?php echo htmlspecialchars($r['room_name']); ?></div>
                                    <div style="font-size: 0.75rem; color: #777;">
                                        <?php echo htmlspecialchars($r['room_type']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($r['all_bookings'])): ?>
                                        <p style="font-size: 0.7rem; color: #2563eb; font-weight: bold; margin-bottom: 5px;">
                                            BOOKED DATES:</p>
                                        <?php
                                        $booking_items = explode('||', $r['all_bookings']);
                                        $today_str = date('d/m');
                                        foreach ($booking_items as $item):
                                            $is_specific_active = (strpos($item, $today_str) !== false);
                                            ?>
                                            <div style="font-size: 0.8rem; 
                                            background: <?php echo $is_specific_active ? '#e0f2fe' : '#ffffff'; ?>; 
                                            padding: 6px; border-radius: 4px; margin-bottom: 4px; 
                                            border: 1px solid <?php echo $is_specific_active ? '#0284c7' : '#ddd'; ?>;
                                            display: flex; justify-content: space-between; align-items: center;">
                                                <span>👤 <?php echo htmlspecialchars($item); ?></span>
                                                <?php if ($is_specific_active): ?>
                                                    <span style="font-size: 0.6rem; font-weight: bold; color: #0284c7; background: #bae6fd; padding: 2px 4px; border-radius: 3px;">
                                                        CURRENTLY IN
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: #22c55e; font-size: 0.8rem; font-weight: bold;">✅ Fully Available</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: bold;">RM<?php echo number_format($r['price_per_night'], 2); ?></td>
                                <td><span class="badge <?php echo ($r['physical_status'] == 'maintenance') ? 'badge-red' : (($r['is_occupied_today'] > 0) ? 'badge-blue' : 'badge-green'); ?>"><?php echo ($r['physical_status'] == 'maintenance') ? 'MAINTENANCE' : (($r['is_occupied_today'] > 0) ? 'OCCUPIED TODAY' : 'AVAILABLE TODAY'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-bookings" class="tab-content hidden">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Guest Name</th>
                            <th>Room Info</th>
                            <th>Check-In / Out</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings_list as $b): ?>
                            <tr>
                                <td>#<?php echo $b['booking_id']; ?></td>
                                <td><?php echo htmlspecialchars($b['guest_name']); ?></td>
                                <td><strong>No: <?php echo htmlspecialchars($b['room_number']); ?></strong><br><small style="color: gray;"><?php echo htmlspecialchars($b['room_name']); ?></small></td>
                                <td>
                                    <div style="font-size: 0.85rem;">🛫 <?php echo date('d M Y', strtotime($b['check_in_date'])); ?><br>🛬 <?php echo date('d M Y', strtotime($b['check_out_date'])); ?></div>
                                </td>
                                <td style="font-weight: bold;">RM<?php echo number_format($b['total_amount'], 2); ?></td>
                                <td><span class="badge <?php echo ($b['booking_status'] == 'pending_approval' || $b['booking_status'] == 'pending') ? 'badge-yellow' : (($b['booking_status'] == 'cancelled') ? 'badge-red' : 'badge-blue'); ?>"><?php echo strtoupper($b['booking_status']); ?></span></td>
                                <td>
                                    <form method="POST" style="display:flex; gap:5px; align-items:center; margin-bottom:5px;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                        <select name="status" class="btn btn-outline" style="padding:0.3rem; font-size:0.7rem;">
                                            <option value="pending" <?= $b['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?= $b['booking_status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="completed" <?= $b['booking_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="checked_out" <?= $b['booking_status'] == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                                            <option value="cancelled" <?= $b['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_booking" class="btn btn-primary" onclick="return confirm('Update booking status?')">Update</button>
                                        <?php if ($b['booking_status'] == 'cancelled' && $b['payment_status'] !== 'refunded' && $b['payment_status'] !== 'cash_pending' && $b['booking_status'] !== 'checked_out'): ?>
                                            <a href="admin-dashboard.php?refund_id=<?php echo $b['booking_id']; ?>" class="btn btn-warning" style="background-color: orange; color: white; font-size:0.7rem; padding: 5px 10px; text-decoration: none; border-radius: 4px; text-align:center;" onclick="return confirm('Process refund for this booking?')">Process Refund</a>
                                        <?php endif; ?>
                                        <br>
                                        <?php if (strtolower($b['payment_method']) == 'cash' && $b['booking_status'] !== 'cancelled' && $b['payment_status'] == 'cash_pending'): ?>
                                            <a href="admin-dashboard.php?paid_id=<?php echo $b['booking_id']; ?>" class="btn" style="background-color: #10b981; color: white; font-size:0.7rem; padding: 5px 10px; text-decoration: none; border-radius: 4px;" onclick="return confirm('Confirm cash payment received?')">Mark as Paid</a>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-financial" class="tab-content hidden">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments_list as $p): ?>
                            <tr>
                                <td><?php echo $p['transaction_id']; ?></td>
                                <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                                <?php
                                $amountStyle = 'green';
                                if ($p['amount_paid'] < 0) {
                                    $amountStyle = 'red';
                                } elseif (strtolower($p['payment_method']) == 'cash') {
                                    $amountStyle = 'orange';
                                }
                                ?>
                                <td style="color:<?php echo $amountStyle; ?>; font-weight:bold;">RM<?php echo number_format($p['amount_paid'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo (empty($p['payment_method'])) ? 'badge-red' : 'badge-blue'; ?>">
                                        <?php echo !empty($p['payment_method']) ? strtoupper($p['payment_method']) : 'REFUNDED'; ?>
                                    </span>
                                </td>
                                <td><?php echo $p['payment_date']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-reports" class="tab-content hidden">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 class="report-header"><?php echo $report_title; ?></h2>
                <button onclick="window.print()" class="btn btn-success no-print">🖨️ Print Financial Statement</button>
            </div>
            <div class="card no-print" style="margin-bottom: 2rem; background: #f8fafc;">
                <form method="GET" action="admin-dashboard.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; padding: 1.5rem; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <input type="hidden" name="tab" value="reports">
                    <div style="flex: 1; min-width: 100px;">
                        <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Year</label>
                        <select name="report_year" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer;">
                            <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                                <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Month</label>
                        <select name="report_month" class="form-control" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer;">
                            <option value="">Full Year</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.5rem; font-weight: 600; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Filter</button>
                    <div style="display: flex; gap: 8px; border-left: 1px solid #e2e8f0; padding-left: 12px; height: 40px;">
                        <a href="admin-dashboard.php?tab=reports&report_period=today" class="btn btn-outline" style="font-size: 0.8rem; border-color: #cbd5e1; color: #475569;">Today</a>
                        <a href="admin-dashboard.php?tab=reports&report_period=this_month" class="btn btn-outline" style="font-size: 0.8rem; border-color: #cbd5e1; color: #475569;">This Month</a>
                    </div>
                </form>
            </div>
            <div class="grid md-grid-cols-3" style="margin-bottom: 2rem;">
                <div class="card" style="border-top: 4px solid var(--success);">
                    <p class="stat-label">Net Revenue</p>
                    <p class="stat-value" style="color: var(--success);">RM<?php echo number_format($stat_report, 2); ?></p>
                    <p style="font-size: 0.75rem; color: gray;">Total after refunds</p>
                </div>
                <div class="card" style="border-top: 4px solid #ef4444;">
                    <p class="stat-label">Cancellations</p>
                    <p class="stat-value" style="color: #ef4444;"><?php echo $cancelled_count; ?></p>
                    <p style="font-size: 0.75rem; color: gray;">Bookings revoked</p>
                </div>
                <div class="card" style="border-top: 4px solid var(--primary);">
                    <p class="stat-label">Total Transactions</p>
                    <p class="stat-value"><?php echo count($report_transactions); ?></p>
                    <p style="font-size: 0.75rem; color: gray;">Cash in/out entries</p>
                </div>
            </div>
            <div class="table-container">
                <h3 style="padding: 1rem; border-bottom: 1px solid #eee;">Transaction Breakdown</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference ID</th>
                            <th>Customer</th>
                            <th>Room</th>
                            <th>Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_transactions as $tx): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($tx['payment_date'])); ?></td>
                                <td><small><?php echo $tx['transaction_id']; ?></small></td>
                                <td><?php echo htmlspecialchars($tx['full_name']); ?></td>
                                <td><?php echo $tx['room_number'] ? htmlspecialchars($tx['room_number']) : 'N/A'; ?></td>
                                <td>
                                    <?php
                                    if ($tx['amount_paid'] < 0) {
                                        $badge_class = 'badge-red';
                                        $badge_text = 'REFUNDED';
                                    } elseif (strtolower($tx['payment_method']) == 'cash' && $tx['payment_status'] == 'cash_pending') {
                                        $badge_class = 'badge-yellow';
                                        $badge_text = 'CASH PENDING';
                                    } else {
                                        $badge_class = 'badge-green';
                                        $badge_text = 'PAID';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                </td>
                                <td style="font-weight:bold; color: <?php
                                if ($tx['amount_paid'] < 0) {
                                    echo '#ef4444';
                                } elseif ($badge_text == 'CASH PENDING') {
                                    echo '#f59e0b';
                                } else {
                                    echo '#10b981';
                                }
                                ?>;">RM<?php echo number_format($tx['amount_paid'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-users" class="tab-content hidden">
            <?php if ($pending_accounts > 0): ?>
            <div style="margin-bottom: 2rem; padding: 1.5rem; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 0.5rem;">
                <h3 style="color: #92400e; font-size: 1.25rem; margin-bottom: 1rem;">⚠️ Pending Account Approvals (<?php echo $pending_accounts; ?>)</h3>
                <div class="table-container" style="background: white; border-radius: 0.5rem;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Registration Date</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_list as $pending): ?>
                                <tr style="background: #fffbeb;">
                                    <td><?php echo date('d M Y, H:i', strtotime($pending['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($pending['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($pending['email']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['phone']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $pending['role'] == 'staff' ? 'badge-green' : 'badge-purple'; ?>" style="text-transform: uppercase;">
                                            <?php echo $pending['role']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php 
                                            if ($pending['role'] == 'staff') {
                                                echo $pending['staff_id'];
                                            } elseif ($pending['role'] == 'admin') {
                                                echo $pending['admin_id'];
                                            }
                                            ?>
                                        </strong>
                                    </td>
                                    <td style="display: flex; gap: 0.5rem;">
                                        <a href="admin-dashboard.php?approve_account=<?php echo $pending['user_id']; ?>&tab=users" 
                                           class="btn btn-primary" 
                                           style="background: #10b981; font-size: 0.75rem; padding: 0.5rem 1rem;"
                                           onclick="return confirm('Approve this <?php echo $pending['role']; ?> account for <?php echo htmlspecialchars($pending['full_name']); ?>?')">
                                            ✓ Approve
                                        </a>
                                        <a href="admin-dashboard.php?reject_account=<?php echo $pending['user_id']; ?>&tab=users" 
                                           class="btn btn-danger" 
                                           style="font-size: 0.75rem; padding: 0.5rem 1rem;"
                                           onclick="return confirm('Reject and delete this account? This action cannot be undone.')">
                                            ✗ Reject
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #1e293b;">Active Users</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $u): ?>
                            <tr>
                                <form method="POST" class="user-form">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <td>
                                        <input type="text" name="full_name" value="<?= htmlspecialchars($u['full_name']) ?>" class="form-control user-input" style="font-size:0.75rem;" disabled>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <select name="role" class="form-control user-input" style="font-size:0.75rem;" disabled>
                                            <option value="member" <?= $u['role'] == 'member' ? 'selected' : '' ?>>Member</option>
                                            <option value="staff" <?= $u['role'] == 'staff' ? 'selected' : '' ?>>Staff</option>
                                            <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="badge <?= $u['status'] == 'active' ? 'badge-green' : 'badge-red' ?>">
                                            <?= strtoupper($u['status']) ?>
                                        </span>
                                    </td>
                                    <td style="display:flex; gap:5px; flex-wrap:wrap;">
                                        <button type="button" class="btn btn-outline edit-btn" style="font-size:0.7rem;" onclick="enableEdit(this)">Edit</button>
                                        <button type="submit" name="update_user" class="btn btn-primary save-btn hidden" style="font-size:0.7rem;">Save</button>
                                        <button type="button" class="btn btn-outline cancel-btn hidden" style="font-size:0.7rem;" onclick="cancelEdit(this)">Cancel</button>
                                        <a href="admin-dashboard.php?toggle_user=<?= $u['user_id'] ?>" class="btn btn-outline" style="font-size:0.7rem;">
                                            <?= $u['status'] == 'active' ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                        <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                            <a href="admin-dashboard.php?delete_user=<?= $u['user_id'] ?>" class="btn btn-danger" style="font-size:0.7rem;" onclick="return confirm('Delete this user permanently?')">Delete</a>
                                        <?php endif; ?>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-profile" class="tab-content hidden">
            <?php if ($profile_msg): ?>
                <div style="padding: 1rem; background: #dcfce7; color: #166534; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                    <?php echo $profile_msg; ?>
                </div>
            <?php endif; ?>
            <div class="grid md-grid-cols-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Administrator Details</h3>
                        <p class="card-description">Update personal information</p>
                    </div>
                    <div class="card-content">
                        <form method="POST">
                            <div class="form-group">
                                <label style="display:block; font-weight:bold; font-size:0.85rem; margin-bottom:5px;">Full Name</label>
                                <input type="text" name="full_name" class="form-control" style="width:100%; padding:0.75rem; border:1px solid #ddd; border-radius:var(--radius);" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label style="display:block; font-weight:bold; font-size:0.85rem; margin-bottom:5px;">Email</label>
                                <input type="email" class="form-control" style="width:100%; padding:0.75rem; border:1px solid #ddd; border-radius:var(--radius); background:#f9f9f9;" value="<?php echo htmlspecialchars($admin['email']); ?>" readonly>
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label style="display:block; font-weight:bold; font-size:0.85rem; margin-bottom:5px;">Phone</label>
                                <input type="text" name="phone" class="form-control" style="width:100%; padding:0.75rem; border:1px solid #ddd; border-radius:var(--radius);" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
                            </div>
                            <button type="submit" name="update_admin_profile" class="btn btn-primary" style="width: 100%;">Save Changes</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">System Statistics</h3>
                        <p class="card-description">Overall hotel database summary</p>
                    </div>
                    <div class="card-content">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                            <div style="text-align: center; padding: 1rem; background-color: #f0f7ff; border-radius: 0.5rem; border: 1px solid #dbeafe;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: #2563eb;"><?php echo $stat_rooms; ?></div>
                                <div style="font-size: 0.7rem; color: #64748b; text-transform: uppercase;">Available Rooms</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background-color: #f0fdf4; border-radius: 0.5rem; border: 1px solid #dcfce7;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: #059669;"><?php echo $stat_bookings; ?></div>
                                <div style="font-size: 0.7rem; color: #64748b; text-transform: uppercase;">Total Bookings</div>
                            </div>
                        </div>
                        <div style="border-top: 1px solid #eee; padding-top: 1rem;">
                            <p style="font-size: 0.85rem; color: #475569; margin-bottom: 5px;"><strong>Total Revenue:</strong> RM<?php echo number_format($stat_revenue, 2); ?></p>
                            <p style="font-size: 0.85rem; color: #475569;"><strong>Members:</strong> <?php echo $stat_members; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="footer">
        <p>© 2026 SmartStay Hotel Management System</p>
    </footer>
    <script>
        function showTab(t, n) {
            document.querySelectorAll(".tab-content").forEach(e => e.classList.add("hidden"));
            document.getElementById("tab-" + t).classList.remove("hidden");
            document.querySelectorAll(".tabs-trigger").forEach(e => e.classList.remove("active"));
            n.classList.add("active");
        }
        
        document.addEventListener("DOMContentLoaded", function () {
            let t = new URLSearchParams(window.location.search);
            
            // Check if we need to open a specific tab
            if (t.has('tab')) {
                let tabName = t.get('tab');
                let tabButtons = document.querySelectorAll('.tabs-trigger');
                tabButtons.forEach((btn, index) => {
                    if (btn.textContent.toLowerCase().includes(tabName)) {
                        showTab(tabName, btn);
                    }
                });
            } else if (t.has("report_year") || t.has("report_period")) {
                let n = document.querySelector('button[onclick*="reports"]');
                n && showTab("reports", n);
            }
        });
        
        function enableEdit(btn) {
            const row = btn.closest('tr');
            row.querySelectorAll('.user-input').forEach(i => i.disabled = false);
            row.querySelector('.edit-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
            row.querySelector('.cancel-btn').classList.remove('hidden');
        }
        
        function cancelEdit(btn) {
            location.reload();
        }
    </script>
</body>
</html>