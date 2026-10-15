<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: member-dashboard.php");
    exit();
}
$booking_id = $_GET['id'];

$stmt = $conn->prepare("
    SELECT b.*, r.room_name, r.room_type, r.price_per_night, u.full_name, u.email 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.room_id 
    JOIN users u ON b.user_id = u.user_id 
    WHERE b.booking_id = ? AND b.user_id = ?
");
$stmt->execute([$booking_id, $user_id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$b) {
    die("Resit tidak dijumpai.");
}

$check_in = new DateTime($b['check_in_date']);
$check_out = new DateTime($b['check_out_date']);
$nights = $check_out->diff($check_in)->format("%a");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartStay System - Payment Receipt</title>
<link rel="stylesheet" href="receipt.css">
</head>
<body>

  <header class="header">
    <div class="container header-content">
      <div style="display:flex; align-items:center; gap:0.5rem;">
        <img src="smartstaylogo1.png" alt="Logo" style="height:3rem;">
        <span style="font-weight:bold;">Payment Receipt</span>
      </div>
      <a href="member-dashboard.php" class="btn btn-outline">← Back to Dashboard</a>
    </div>
  </header>

  <div class="container">
    <div class="card">
      <div class="card-content">
        <div class="receipt-header">
          <div class="receipt-title">PAYMENT RECEIPT</div>
          <div style="color: var(--muted-foreground);">SmartStay Hotels & Resorts</div>
        </div>

        <div class="receipt-info">
          <div>
            <p class="receipt-label">Receipt Number</p>
            <p class="receipt-value"><?php echo $b['transaction_id'] ?: $b['booking_id']; ?></p>
            <br>
            <p class="receipt-label">Booking ID</p>
            <p class="receipt-value">#<?php echo $b['booking_id']; ?></p>
            <br>
            <p class="receipt-label">Payment Date</p>
            <p class="receipt-value"><?php echo $b['payment_date'] ?: 'N/A'; ?></p>
          </div>
          <div>
            <p class="receipt-label">Customer Name</p>
            <p class="receipt-value"><?php echo htmlspecialchars($b['full_name']); ?></p>
            <br>
            <p class="receipt-label">Customer Email</p>
            <p class="receipt-value"><?php echo htmlspecialchars($b['email']); ?></p>
            <br>
            <p class="receipt-label">Status</p>
            <span class="status-badge <?php echo $b['payment_status']=='paid' ? 'status-paid' : 'status-refunded'; ?>">
                <?php echo strtoupper($b['payment_status']); ?>
            </span>
          </div>
        </div>

        <div class="receipt-details">
          <h3 style="margin-bottom: 1rem;">Booking Details</h3>
          <div class="receipt-details-grid">
            <div><p class="receipt-label">Room Type</p><p class="receipt-value"><?php echo $b['room_name']; ?></p></div>
            <div><p class="receipt-label">Duration</p><p class="receipt-value"><?php echo $nights; ?> Nights</p></div>
            <div><p class="receipt-label">Check-in</p><p class="receipt-value"><?php echo date('d M Y', strtotime($b['check_in_date'])); ?></p></div>
            <div><p class="receipt-label">Check-out</p><p class="receipt-value"><?php echo date('d M Y', strtotime($b['check_out_date'])); ?></p></div>
          </div>
          <div style="margin-top: 2rem; border-top: 1px solid #ddd; padding-top: 1rem; text-align: right;">
            <p class="receipt-label">Total Amount Paid</p>
            <p style="font-size: 1.5rem; font-weight: 700; color: var(--success);">RM <?php echo number_format($b['total_amount'], 2); ?></p>
          </div>
        </div>

        <?php if ($b['refund_requested']): ?>
        <div class="refund-info-card">
            <h4 style="color: #92400e; margin-bottom: 0.5rem;">Cancellation Information</h4>
            <p><strong>Status:</strong> <?php echo strtoupper($b['refund_status']); ?></p>
            <p><strong>Reason:</strong> <?php echo htmlspecialchars($b['refund_reason']); ?></p>
            <p><strong>Requested on:</strong> <?php echo date('d M Y', strtotime($b['refund_request_date'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="receipt-actions" style="text-align: center; margin-top: 2rem; display: flex; justify-content: center; gap: 1rem;">
          <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
          
          <?php if (!$b['refund_requested'] && $b['payment_status'] == 'paid'): ?>
            <button class="btn btn-danger" onclick="requestRefund()">Request Refund</button>
                      <?php else : ?>
            <button class="btn btn-danger" onclick="requestCancel()">Request cancel</button>
          <?php endif; ?>

          <a href="member-dashboard.php" class="btn btn-outline">Done</a>
        </div>

      </div>
    </div>
  </div>

  <script>
    async function requestRefund() {
        const reason = prompt("Please State a Reason for Cancelation");
        if (!reason) return;

        if (confirm("Are you sure you want to request a refund? The booking will be cancelled immediately.")) {
            const fd = new FormData();
            fd.append('booking_id', <?php echo $b['booking_id']; ?>);
            fd.append('reason', reason);

            const res = await fetch('process_refund.php', { method: 'POST', body: fd });
            const data = await res.json();
            alert(data.message);
            if(data.status === 'success') location.reload();
        }
    }

        async function requestCancel() {
        const reason = prompt("Please State a Reason for Cancelation");


        if (confirm("Are you sure you want to request a cancellation ?")) {
            const fd = new FormData();
            fd.append('booking_id', <?php echo $b['booking_id']; ?>);
            fd.append('reason', reason);

            const res = await fetch('process_cancel.php', { method: 'POST', body: fd });
            const data = await res.json();
            alert(data.message);
            if(data.status === 'success') location.reload();
        }
    }
  </script>

</body>
</html>