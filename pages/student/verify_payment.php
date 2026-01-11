<?php
// verify_payment.php

// ...existing code...

// Replace the payment check query with this:
$student_id = $_SESSION['student_id'];

// Get current semester
$semesterQuery = "SELECT id FROM semesters WHERE is_current = 1 LIMIT 1";
$semesterResult = $conn->query($semesterQuery);
$currentSemester = $semesterResult->fetch_assoc();
$semester_id = $currentSemester['id'] ?? 0;

// Check for existing payment
$paymentQuery = "SELECT p.*, s.semester_name, s.academic_year 
                 FROM payments p 
                 JOIN semesters s ON p.semester_id = s.id 
                 WHERE p.student_id = ? 
                 AND p.semester_id = ? 
                 AND p.status IN ('pending', 'verified', 'approved')
                 ORDER BY p.created_at DESC 
                 LIMIT 1";
$stmt = $conn->prepare($paymentQuery);
$stmt->bind_param("ii", $student_id, $semester_id);
$stmt->execute();
$paymentResult = $stmt->get_result();
$existingPayment = $paymentResult->fetch_assoc();

if ($existingPayment) {
    // Payment exists - show status instead of form
    $paymentStatus = $existingPayment['status'];
    $hasPayment = true;
} else {
    $hasPayment = false;
}

// ...existing code...
?>

<!-- In the HTML section, wrap the form with this condition -->
<?php if ($hasPayment): ?>
    <div class="alert alert-success">
        <h4><i class="fas fa-check-circle"></i> Payment Already Submitted</h4>
        <p>Your payment for <?= htmlspecialchars($existingPayment['semester_name'] . ' ' . $existingPayment['academic_year']) ?> has been submitted.</p>
        <p><strong>Status:</strong> <?= ucfirst($existingPayment['status']) ?></p>
        <p><strong>Amount:</strong> ₱<?= number_format($existingPayment['amount'], 2) ?></p>
        <p><strong>Submitted:</strong> <?= date('M d, Y h:i A', strtotime($existingPayment['created_at'])) ?></p>
    </div>
<?php else: ?>
    <!-- existing payment form here -->
<?php endif; ?>