<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

//edit profile dekat myprofile
$message = "";
if (isset($_POST['update_profile'])) {
    $fname = $_POST['full_name'];
    $phone = $_POST['phone'];
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
    $stmt->execute([$fname, $phone, $user_id]);

    $user['full_name'] = $fname;
    $user['phone'] = $phone;
    $message = "Profile updated successfully!";
}

$user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$rooms_stmt = $conn->query("
    SELECT room_name, room_type, price_per_night, image_url, max_guests, room_id,
    COUNT(*) as total_units,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units
    FROM rooms 
    GROUP BY room_name, room_type, price_per_night, image_url, max_guests
");
$room_categories = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);

$bookings_stmt = $conn->prepare("
    SELECT b.*, r.room_name, r.room_number 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.room_id 
    WHERE b.user_id = ? 
    ORDER BY b.created_at DESC
");
$bookings_stmt->execute([$user_id]);
$my_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_bookings = count($my_bookings);
$completed_stays = 0;
foreach ($my_bookings as $bk) {
    if ($bk['booking_status'] == 'checked_out' || $bk['booking_status'] == 'completed')
        $completed_stays++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartStay System - Member Dashboard</title>
    <link rel="stylesheet" href="MemberDashboard.css">
</head>

<body>

    <header class="header">
        <div class="container header-content">
            <div class="header-logo">
                <img src="smartstaylogo1.png" alt="Logo">
                <span style="font-weight: bold; font-size: 1.2rem;">SmartStay System</span>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="text-align: right;">
                    <p><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                    <p style="font-size: 0.75rem; color: var(--muted-foreground);">
                        <?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="tabs-list">
            <button class="tabs-trigger active" onclick="switchTab('browse', this)">Browse Rooms</button>
            <button class="tabs-trigger" onclick="switchTab('bookings', this)">My Bookings</button>
            <button class="tabs-trigger" onclick="switchTab('profile', this)">My Profile</button>
        </div>

        <div id="section-browse">
            <div class="grid md-grid-cols-2 lg-grid-cols-3">
                <?php foreach ($room_categories as $cat): ?>
                    <div class="card">
                        <div class="room-image-container">
                            <img src="<?php echo $cat['image_url']; ?>" class="room-image">
                            <div style="position: absolute; top: 10px; right: 10px;">
                               
                            </div>
                        </div>
                        <div class="card-content">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <h3 style="font-size: 1.25rem; font-weight: 500;"><?php echo $cat['room_name']; ?></h3>
                                <span class="badge badge-blue"><?php echo $cat['room_type']; ?></span>
                            </div>
                            <p style="font-size: 0.875rem; color: var(--muted-foreground);">Max:
                                <?php echo $cat['max_guests']; ?> guests</p>
                            <div
                                style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                                <span
                                    style="font-size: 1.5rem; font-weight: bold;">RM<?php echo $cat['price_per_night']; ?></span>
                                <?php if ($cat['available_units'] > 0): ?>
                                    <button class="btn btn-primary"
                                        onclick="openBooking('<?php echo $cat['room_type']; ?>', '<?php echo $cat['room_name']; ?>')">Book
                                        Now</button>
                                <?php else: ?>
                                    <button class="btn btn-outline" disabled style="color: var(--danger);">Fully Booked</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="section-bookings" class="hidden">
            <div class="grid grid-cols-1">
                <?php if (empty($my_bookings)): ?>
                    <div class="card">
                        <div style="padding: 3rem; text-align: center; color: var(--muted-foreground);">No bookings found.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($my_bookings as $b): ?>
                        <div class="card">
                            <div style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3 style="font-size: 1.1rem; font-weight: 500;"><?php echo $b['room_name']; ?></h3>
                                    <p style="font-size: 0.875rem; color: var(--muted-foreground);">
                                        Room No: <strong
                                            style="color: var(--success);"><?php echo $b['room_number']; ?></strong>
                                    </p>
                                    <p style="font-size: 0.875rem;"><?php echo date('d M Y', strtotime($b['check_in_date'])); ?>
                                        to <?php echo date('d M Y', strtotime($b['check_out_date'])); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <span class="badge badge-blue"><?php echo strtoupper($b['booking_status']); ?></span><br>

                                    <?php if ($b['refund_requested']): ?>
                                        <span class="badge"
                                            style="background-color: #f59e0b; margin-top: 5px; color: white;">REFUND:
                                            <?php echo strtoupper($b['refund_status']); ?></span>
                                    <?php elseif ($b['payment_status'] == 'paid'): ?>
                                        <a href="receipt.php?id=<?php echo $b['booking_id']; ?>" class="btn btn-outline"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.7rem; margin-top: 5px;">View Receipt /
                                            Refund</a>
                                    <?php elseif ($b['payment_status'] == 'cash_pending'): ?>
                                        <a href="receipt.php?id=<?php echo $b['booking_id']; ?>" class="btn btn-outline"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.7rem; margin-top: 5px;">View details /
                                            Cancel</a>
                                    <?php else: ?>
                                        <button class="btn btn-primary"
                                            style="padding: 0.25rem 0.5rem; font-size: 0.75rem; margin-top: 5px;"
                                            onclick="openPayment('<?php echo $b['booking_id']; ?>', '<?php echo $b['total_amount']; ?>')">Pay
                                            Now</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="section-profile" class="hidden">
            <?php if ($message): ?>
                <div
                    style="padding: 1rem; background: #dcfce7; color: #166534; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="grid md-grid-cols-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Profile Information</h3>
                        <p class="card-description">Update your personal account details</p>
                    </div>
                    <div class="card-content">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-input"
                                    value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-input"
                                    value="<?php echo htmlspecialchars($user['email']); ?>" readonly
                                    style="background-color: var(--muted); cursor: not-allowed;">
                                <small style="color: var(--muted-foreground);">Email cannot be changed.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-input"
                                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required
                                    placeholder="e.g. 0123456789">
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary"
                                style="width: 100%; margin-top: 1rem;">Save Changes</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Account Statistics</h3>
                        <p class="card-description">Your activity summary at SmartStay</p>
                    </div>
                    <div class="card-content">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                            <div
                                style="text-align: center; padding: 1.5rem; background-color: #f0f7ff; border-radius: var(--radius); border: 1px solid #dbeafe;">
                                <div style="font-size: 2rem; font-weight: bold; color: #2563eb;">
                                    <?php echo $total_bookings; ?></div>
                                <div
                                    style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">
                                    Total Bookings</div>
                            </div>
                            <div
                                style="text-align: center; padding: 1.5rem; background-color: #f0fdf4; border-radius: var(--radius); border: 1px solid #dcfce7;">
                                <div style="font-size: 2rem; font-weight: bold; color: #059669;">
                                    <?php echo $completed_stays; ?></div>
                                <div
                                    style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">
                                    Stays Completed</div>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <label class="form-label"
                                style="display: flex; align-items: center; gap: 0.75rem; color: #475569;">
                                <input type="checkbox" checked disabled> Email Notifications
                            </label>
                            <label class="form-label"
                                style="display: flex; align-items: center; gap: 0.75rem; color: #475569;">
                                <input type="checkbox" disabled> SMS Updates (Premium Members Only)
                            </label>
                            <div
                                style="margin-top: 1rem; padding: 1rem; background: #f8fafc; border-radius: var(--radius); border: 1px solid var(--border);">
                                <p style="font-size: 0.85rem; color: var(--muted-foreground);">
                                    <strong>Member Since:</strong>
                                    <?php echo date('d M Y', strtotime($user['created_at'] ?? 'now')); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="booking-modal" class="modal hidden">
            <div class="modal-content">
                <h3 id="modal-title" style="margin-bottom: 1rem; font-weight: 500;">Book Room</h3>
                <form id="booking-form">
                    <input type="hidden" name="room_type" id="modal-room-type">
                    <div class="form-group">
                        <label class="form-label">Check-in Date</label>
                        <input type="date" name="check_in" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Check-out Date</label>
                        <input type="date" name="check_out" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Number of Guests</label>
                        <input type="number" name="guests" class="form-input" min="1" value="1" required>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Confirm Booking</button>
                        <button type="button" class="btn btn-outline"
                            onclick="closeModal('booking-modal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="payment-modal" class="modal hidden">
            <div class="modal-content">
                <h3 style="margin-bottom: 1rem;">Make Payment</h3>
                <div
                    style="background: var(--muted); padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
                    <p>Booking ID: <strong id="p-id"></strong></p>
                    <p>Total Amount: <strong id="p-amt" style="color: var(--success);"></strong></p>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <button class="btn btn-outline" style="justify-content: flex-start; gap: 1rem;"
                        onclick="processPay('online_banking')">🌐 Online Banking</button>
                    <button class="btn btn-outline" style="justify-content: flex-start; gap: 1rem;"
                        onclick="processPay('cash')">💵 Cash at Hotel</button>
                </div>
                <button class="btn btn-outline" style="width: 100%; margin-top: 1rem;"
                    onclick="closeModal('payment-modal')">Cancel</button>
            </div>
        </div>



        <script>
            let activeBookingId = null;

            function switchTab(section, btn) {
                document.getElementById('section-browse').classList.add('hidden');
                document.getElementById('section-bookings').classList.add('hidden');
                document.getElementById('section-profile').classList.add('hidden');
                document.querySelectorAll('.tabs-trigger').forEach(t => t.classList.remove('active'));
                document.getElementById('section-' + section).classList.remove('hidden');
                btn.classList.add('active');
            }

            function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

            function openBooking(type, name) {
                document.getElementById('modal-room-type').value = type;
                document.getElementById('modal-title').innerText = "Book " + name;
                document.getElementById('booking-modal').classList.remove('hidden');
                const today = new Date().toISOString().split('T')[0];
                document.getElementsByName('check_in')[0].min = today;
                document.getElementsByName('check_out')[0].min = today;
            }

            document.getElementById('booking-form').addEventListener('submit', async function (e) {
                e.preventDefault();
                const res = await fetch('book_room.php', { method: 'POST', body: new FormData(this) });
                const data = await res.json();
                alert(data.message);
                if (data.status === 'success') location.reload();
            });

            function openPayment(id, amt) {
                activeBookingId = id;
                document.getElementById('p-id').innerText = id;
                document.getElementById('p-amt').innerText = "RM" + amt;
                document.getElementById('payment-modal').classList.remove('hidden');
            }

            async function processPay(method) {
                const fd = new FormData();
                fd.append('booking_id', activeBookingId);
                fd.append('method', method);
                const res = await fetch('process_payment.php', { method: 'POST', body: fd });
                const data = await res.json();
                alert(data.message);
                location.reload();
            }
        </script>

       

        <?php include 'chat_widget.html'; ?>
        <script>
            // Include the DateConstraint.js script
            const script = document.createElement('script');
            script.src = 'DateConstraint.js';
            document.head.appendChild(script);
        </script>
</body>
<footer class="footer">
    <p>SmartStay System © 2026</p>
</footer>

</html>