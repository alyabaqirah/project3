<?php
// Include your database connection
include 'db_connection.php'; 
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-calculate the same variables from your report page logic
    $selected_year = $_POST['report_year'] ?? date('Y');
    $selected_month = $_POST['report_month'] ?? '';
    $report_period = $_POST['report_period'] ?? '';
    
    // ... [Insert the query logic from your previous snippet here to get $stat_report, $report_transactions, etc.] ...

    try {
        $insert_sql = "INSERT INTO financial_reports (
            report_type, period_start, period_end, total_revenue, 
            total_bookings, total_rooms_booked, average_daily_rate, 
            occupancy_rate, generated_by_user_id, generated_at, report_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($insert_sql);
        $stmt->execute([
            $report_period ?: 'annual',
            "$selected_year-" . ($selected_month ?: '01') . "-01",
            date('Y-m-d'),
            $stat_report,
            count($report_transactions),
            null, // total_rooms_booked
            (count($report_transactions) > 0) ? ($stat_report / count($report_transactions)) : 0,
            null, // occupancy_rate
            $_SESSION['user_id'] ?? null,
            date('Y-m-d H:i:s'),
            json_encode(['transactions' => $report_transactions, 'cancelled' => $cancelled_count])
        ]);

        echo "Success";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>