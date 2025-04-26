<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['selected_room'])) {
    echo "No room selected for booking.";
    exit();}
$cancelOldBookingsQuery = "UPDATE room_bookings SET status = 'cancelled' 
                         WHERE status = 'pending' AND booking_date < DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
$conn->query($cancelOldBookingsQuery);
$selectedRoom = $_SESSION['selected_room'];
$userEmail = $_SESSION['user']['email'];
$checkPendingQuery = "SELECT * FROM room_bookings 
                    WHERE status = 'pending' AND room_number = ? 
                    AND hostel_name = ? AND sharing_type = ?"; 
$checkPendingStmt = $conn->prepare($checkPendingQuery);
$checkPendingStmt->bind_param("iss", $selectedRoom['room_number'], $selectedRoom['hostel_name'], $selectedRoom['sharing_type']);
$checkPendingStmt->execute();
$pendingResult = $checkPendingStmt->get_result();
if ($pendingResult->num_rows > 0) {
    $pendingBooking = $pendingResult->fetch_assoc();
    if ($pendingBooking['user_email'] != $userEmail) {
        echo "This room is currently being booked by another user. Please select a different room.";
        exit();}
}
$roomQuery = "SELECT * FROM rooms WHERE hostel_name = ? AND room_number = ?";
$roomStmt = $conn->prepare($roomQuery);
$roomStmt->bind_param("si", $selectedRoom['hostel_name'], $selectedRoom['room_number']);
$roomStmt->execute();
$roomResult = $roomStmt->get_result();
$roomDetails = $roomResult->fetch_assoc();
if (!$roomDetails) {die("Room details not found. Please try again.");}
$feeQuery = "SELECT fee FROM fee_structure WHERE room_type = ?  AND sharing_type = ? AND duration = ?";
$roomType = ($roomDetails['is_ac'] == 1) ? 'AC' : 'Non-AC';
$sharingType = (int) $roomDetails['sharing_type'];
$duration = $selectedRoom['stay_period'] . ' months';
$feeStmt = $conn->prepare($feeQuery);
$feeStmt->bind_param("sis", $roomType, $sharingType, $duration);
$feeStmt->execute();
$feeResult = $feeStmt->get_result();
$feeData = $feeResult->fetch_assoc();
if (!$feeData) {die("Fee details not found. Please try again.");}
$roomFee = $feeData['fee'];
$messFeeQuery = "SELECT fee FROM fee_structure WHERE room_type = 'Mess' AND duration = ?";
$messFeeStmt = $conn->prepare($messFeeQuery);
$messFeeStmt->bind_param("s", $duration);
$messFeeStmt->execute();
$messFeeResult = $messFeeStmt->get_result();
$messFeeData = $messFeeResult->fetch_assoc();
if (!$messFeeData) {die("Mess fee details not found. Please try again.");}
$messFee = $messFeeData['fee'];
$totalFee = $roomFee + $messFee;
$minPaymentRequired = 50000;
$paymentType = "booking";
$bookingId = isset($selectedRoom['booking_id']) ? $selectedRoom['booking_id'] : null;
$dueDate = date('Y-m-d', strtotime('+30 days'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amountToPay = isset($_POST['amount_to_pay']) ? floatval($_POST['amount_to_pay']) : $totalFee;
    $conn->begin_transaction();
    try {
        $checkRoomQuery = "SELECT available_beds FROM rooms WHERE id = ? FOR UPDATE";
        $checkRoomStmt = $conn->prepare($checkRoomQuery);
        $checkRoomStmt->bind_param("i", $roomDetails['id']);
        $checkRoomStmt->execute();
        $checkRoomResult = $checkRoomStmt->get_result();
        $currentRoomStatus = $checkRoomResult->fetch_assoc();
        if (!$currentRoomStatus || $currentRoomStatus['available_beds'] <= 0) {
            throw new Exception("This room is no longer available. Please select another room.");}
        $checkBalanceQuery = "SELECT balance FROM account WHERE regNo = (SELECT regNo FROM student_signup WHERE email = ?)";
        $checkBalanceStmt = $conn->prepare($checkBalanceQuery);
        $checkBalanceStmt->bind_param("s", $userEmail);
        $checkBalanceStmt->execute();
        $balanceResult = $checkBalanceStmt->get_result();
        $userAccount = $balanceResult->fetch_assoc();
        if (!$userAccount || $userAccount['balance'] < $amountToPay) {
            throw new Exception("Insufficient balance to make this payment. Please add funds to your account.");}
        if (isset($selectedRoom['booking_id'])) {
            $bookingId = $selectedRoom['booking_id'];
            $updateBookingQuery = "UPDATE room_bookings SET status = 'confirmed', total_fee = ? WHERE id = ?";
            $updateBookingStmt = $conn->prepare($updateBookingQuery);
            $updateBookingStmt->bind_param("di", $totalFee, $bookingId);
            if (!$updateBookingStmt->execute()) {
                throw new Exception("Failed to update room booking: " . $updateBookingStmt->error);}
        } else {
            $insertBookingQuery = "INSERT INTO room_bookings (
                user_email, hostel_name, room_number, floor, is_ac, sharing_type, stay_period, total_fee,
                status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')";
            $isAc = $roomDetails['is_ac'];
            $sharingTypeStr = $roomDetails['sharing_type'];
            $floor = $roomDetails['floor'];
            $insertBookingStmt = $conn->prepare($insertBookingQuery);
            $insertBookingStmt->bind_param(
                "siiisisd", $userEmail, $selectedRoom['hostel_name'], $selectedRoom['room_number'], 
                $floor, $isAc, $sharingTypeStr, $selectedRoom['stay_period'],$totalFee);
            if (!$insertBookingStmt->execute()) {throw new Exception("Failed to create room booking: " . $insertBookingStmt->error);}
            $bookingId = $conn->insert_id;}
        $paymentMethod = $_POST['payment_method'];
        $transactionId = uniqid();
        $card_number = $_POST['card_number'] ?? null;
        $card_expiry = $_POST['card_expiry'] ?? null;
        $card_cvc = $_POST['card_cvc'] ?? null;
        $upi_id = $_POST['upi_id'] ?? null;
        $bank_name = $_POST['bank_name'] ?? null;
        $account_number = $_POST['account_number'] ?? null;
        $ifsc_code = $_POST['ifsc_code'] ?? null;
        $insertPaymentQuery = "INSERT INTO payment_details (
            user_email, booking_id, amount, payment_method, payment_status, transaction_id, card_number, 
            card_expiry, card_cvc,upi_id, bank_name, account_number, ifsc_code) VALUES (?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertPaymentStmt = $conn->prepare($insertPaymentQuery);
        $insertPaymentStmt->bind_param(
            "siisssssssss", $userEmail, $bookingId, $amountToPay, $paymentMethod, $transactionId, 
            $card_number, $card_expiry, $card_cvc, $upi_id, $bank_name, $account_number, $ifsc_code);
        if (!$insertPaymentStmt->execute()) {throw new Exception("Failed to process payment: " . $insertPaymentStmt->error);}
        $amountDue = $totalFee - $amountToPay;
        if ($amountDue > 0) {
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            $insertDueQuery = "INSERT INTO fee_dues (
                booking_id,user_email,total_fee,amount_paid,amount_due,due_date,status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $insertDueStmt = $conn->prepare($insertDueQuery);
            $insertDueStmt->bind_param("isddds",$bookingId,$userEmail,$totalFee,$amountToPay,$amountDue,$dueDate);
            if (!$insertDueStmt->execute()) {throw new Exception("Failed to record fee dues: " . $insertDueStmt->error);}}
        $updateAccountQuery = "UPDATE account SET balance = balance - ? WHERE regNo = (SELECT regNo FROM student_signup WHERE email = ?)";
        $updateAccountStmt = $conn->prepare($updateAccountQuery);
        $updateAccountStmt->bind_param("ds", $amountToPay, $userEmail);
        if (!$updateAccountStmt->execute()) {throw new Exception("Failed to update account balance: " . $updateAccountStmt->error);}
        $newAvailableBeds = max(0, $currentRoomStatus['available_beds'] - 1);
        $roomStatus = ($newAvailableBeds === 0) ? 'Occupied' : 'Available';
        $updateRoomQuery = "UPDATE rooms SET available_beds = ?, status = ? WHERE id = ? AND available_beds > 0";
        $updateRoomStmt = $conn->prepare($updateRoomQuery);
        $updateRoomStmt->bind_param("isi", $newAvailableBeds, $roomStatus, $roomDetails['id']);
        if (!$updateRoomStmt->execute() || $updateRoomStmt->affected_rows === 0) {
            throw new Exception("Failed to update room status. The room may no longer be available.");}
        $conn->commit();
        $_SESSION['payment_info'] = [
            'amount_paid' => $amountToPay,'total_fee' => $totalFee,'amount_due' => $totalFee - $amountToPay,'transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,'booking_id' => $bookingId,'payment_type' => $paymentType];
        unset($_SESSION['selected_room']);
        header("Location: success.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $bookingError = "Payment failed: " . $e->getMessage();
    }
}?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- <link rel="stylesheet" href="css/payment.css"> -->
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Poppins", sans-serif; }
      body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
      .payment-container { background: #fff; border-radius: 20px; box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1); width: 100%; max-width: 800px; overflow: hidden; display: flex; flex-direction: column; }
      .payment-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 30px; border-radius: 20px 20px 0 0; position: relative; }
      .payment-header h2 { font-size: 28px; font-weight: 600; margin-bottom: 5px; }
      .payment-header p { opacity: 0.8; font-size: 14px; }
      .header-logo { position: absolute; top: 20px; right: 30px; height: 40px; width: auto; }
      .payment-body { padding: 30px; display: flex; flex-direction: column; gap: 30px; }
      .payment-steps { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
      .payment-steps::before { content: ""; position: absolute; top: 15px; left: 0; right: 0; height: 2px; background: #e1e5ee; z-index: 1; }
      .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; flex: 1; }
      .step-circle { width: 30px; height: 30px; border-radius: 50%; background: white; border: 2px solid #e1e5ee; display: flex; 
        align-items: center; justify-content: center; margin-bottom: 8px; font-weight: 500; color: #667eea; transition: all 0.3s ease; }
      .step-title { font-size: 12px; color: #667eea; font-weight: 500; }
      .step.active .step-circle { background: #667eea; border-color: #667eea; color: white; }
      .step.completed .step-circle { background: #667eea; border-color: #667eea; color: white; }
      .step.completed .step-circle::after { content: "✓"; font-size: 14px; }
      .fee-summary { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
      .fee-summary h3 { color: #474747; margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; }
      .fee-summary h3 i { margin-right: 10px; color: #667eea; }
      .fee-table { width: 100%; border-collapse: collapse; }
      .fee-table th, .fee-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
      .fee-table th { color: #667eea; font-weight: 600; }
      .fee-table tr:last-child td, .fee-table tr:last-child th { border-top: 2px solid #ddd; border-bottom: none; font-weight: 700; color: #333; }
      .payment-methods { margin-bottom: 20px; }
      .payment-methods h3 { color: #474747; margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; }
      .payment-methods h3 i { margin-right: 10px; color: #667eea; }
      .payment-option-buttons { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
      .payment-option-button { flex: 1; min-width: 120px; padding: 15px; background: white; border: 2px solid #eee; border-radius: 12px; 
        display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: all 0.3s ease; }
      .payment-option-button:hover { border-color: #c7d2fe; background: #f5f7ff; }
      .payment-option-button.active { border-color: #667eea; background: #f0f4ff; }
      .payment-option-button i { font-size: 24px; margin-bottom: 8px; color: #667eea; }
      .payment-option-button span { font-size: 14px; font-weight: 500; color: #444; }
      .address-form { display: none; }
      .payment-details { display: none; background: #f8f9fa; border-radius: 15px; padding: 20px; margin-top: 20px; }
      .form-group { margin-bottom: 20px; }
      .form-group label { display: block; font-weight: 500; margin-bottom: 8px; color: #333; }
      .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e1e5ee; border-radius: 10px; font-size: 15px; transition: border-color 0.3s ease; }
      .form-control:focus { outline: none; border-color: #667eea; }
      .input-with-icon { position: relative; }
      .input-with-icon i { position: absolute; top: 50%; transform: translateY(-50%); left: 15px; color: #667eea; }
      .input-with-icon input { padding-left: 45px; }
      .form-row { display: flex; gap: 15px; }
      .form-row .form-group { flex: 1; }
      .upi-qr-container { display: flex; flex-direction: column; align-items: center; gap: 20px; }
      .qr-code { width: 200px; height: 200px; background: white; border-radius: 10px; padding: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); 
        display: flex; align-items: center; justify-content: center; }
      .qr-code img { max-width: 100%; }
      .qr-scanner-btn { display: flex; align-items: center; gap: 10px; background: #667eea; color: white; border: none; 
        padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 500; transition: background 0.3s ease; }
      .qr-scanner-btn:hover { background: #5a6ce0; }
      .btn { padding: 14px 28px; border-radius: 12px; font-size: 16px; font-weight: 500; cursor: pointer; display: flex; align-items: center; transition: all 0.3s ease; }
      .btn i { margin-right: 10px; }
      .primary-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; font-weight: 600; flex: 2; justify-content: center; box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); }
      .primary-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
      .secondary-btn { background: #f8f9fa; color: #667eea; border: 2px solid #e1e5ee; flex: 1; text-decoration: none; justify-content: center; }
      .secondary-btn:hover { background: #f0f4ff; border-color: #667eea; }
      .nav-buttons { display: flex; justify-content: space-between; margin-top: 30px; }
      .back-button { background: #f8f9fa; color: #667eea; border: 2px solid #e1e5ee; padding: 12px 25px; border-radius: 12px; 
        font-size: 16px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; }
      .back-button:hover { background: #f0f4ff; border-color: #667eea; }
      .next-button, .submit-button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; 
        padding: 12px 25px; border-radius: 12px; font-size: 16px; font-weight: 500; cursor: pointer; transition: transform 0.3s ease, 
        box-shadow 0.3s ease; box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); }
      .next-button:hover, .submit-button:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
      .security-buttons { display: flex; gap: 15px; margin-top: 20px; }
      .pay-securely-button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 14px 28px; border-radius: 12px; 
        font-size: 16px; font-weight: 600; cursor: pointer; flex: 2; transition: transform 0.3s ease, box-shadow 0.3s ease; box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); }
      .pay-securely-button:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
      .cancel-button { background: #f8f9fa; color: #667eea; border: 2px solid #e1e5ee; padding: 14px 28px; border-radius: 12px; 
        font-size: 16px; font-weight: 500; cursor: pointer; flex: 1; transition: all 0.3s ease; }
      .cancel-button:hover { background: #f0f4ff; border-color: #667eea; }
      .form-actions { display: flex; justify-content: space-between; margin-top: 30px; gap: 15px; }
      .alert { padding: 15px; border-radius: 10px; margin-top: 20px; display: flex; align-items: center; }
      .alert i { margin-right: 10px; font-size: 20px; }
      .alert-danger { background-color: #fee2e2; color: #b91c1c; }
      .alert-success { background-color: #dcfce7; color: #166534; }
      .order-summary { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
      .order-summary h3 { color: #474747; margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; }
      .order-summary h3 i { margin-right: 10px; color: #667eea; }
      .order-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
      .order-item:last-child { border-bottom: none; }
      .order-total { display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px solid #ddd; font-weight: 700; color: #333; }
      .confirmation-page { display: none; text-align: center; padding: 20px; }
      .confirmation-icon { font-size: 60px; color: #4ade80; margin-bottom: 20px; }
      .confirmation-message h3 { font-size: 24px; color: #333; margin-bottom: 10px; }
      .confirmation-message p { color: #666; margin-bottom: 30px; }
      .transaction-details { background: #f8f9fa; border-radius: 15px; padding: 20px; margin: 20px 0; text-align: left; }
      .transaction-details .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
      .transaction-details .detail-row:last-child { border-bottom: none; }
      .transaction-details .detail-label { font-weight: 500; color: #667eea; }
      .support-info { background: #f0f4ff; border-radius: 15px; padding: 15px; margin-top: 20px; text-align: center; }
      .support-info p { margin-bottom: 10px; }
      .support-info .support-email { color: #667eea; font-weight: 500; }
      .security-info { display: flex; flex-direction: column; align-items: center; margin-top: 25px; padding: 15px; background: #f8f9fa; border-radius: 15px; }
      .security-item { display: flex; align-items: center; margin-bottom: 8px; color: #474747; font-size: 14px; }
      .security-item:last-child { margin-bottom: 0; }
      .security-item i { color: #667eea; margin-right: 8px; font-size: 16px; }
      .payment-footer { background: #f8f9fa; padding: 20px 30px; border-radius: 0 0 20px 20px; margin-top: 30px; }
      .security-badges { display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; }
      .security-badges div { display: flex; align-items: center; color: #474747; font-size: 14px; }
      .security-badges i { color: #667eea; margin-right: 8px; font-size: 16px; }
      @media (max-width: 768px) {
        .payment-container { border-radius: 15px; }
        .header-logo { position: static; display: block; margin: 15px auto 0; }
        .form-row { flex-direction: column; gap: 0; }
        .payment-option-buttons { flex-direction: column; }
        .payment-option-button { flex-direction: row; justify-content: flex-start; gap: 15px; }
        .payment-option-button i { margin-bottom: 0; }
        .nav-buttons, .form-actions, .security-buttons { flex-direction: column; gap: 15px; }
        .back-button, .next-button, .submit-button, .btn, .pay-securely-button, .cancel-button { width: 100%; }
        .step-title { display: none; }
        .security-badges { flex-direction: column; gap: 15px; align-items: center; }}
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h2><i class="fas fa-credit-card"></i> Complete Your Payment</h2>
            <p>Secure payment for your hostel booking</p>
        </div>
        <div class="payment-body">
            <div class="fee-summary">
                <h3><i class="fas fa-receipt"></i> Fee Summary</h3>
                <table class="fee-table">
                    <tr> <th>Item</th> <th>Amount</th></tr>
                    <tr>
                        <td>
                            <div>Room Fee</div>
                            <small><?php echo htmlspecialchars($roomType); ?> <?php echo htmlspecialchars($roomDetails['sharing_type']); ?> - <?php echo htmlspecialchars($selectedRoom['stay_period']); ?> Months</small>
                        </td>
                        <td>₹<?php echo htmlspecialchars(number_format($roomFee, 2)); ?></td>
                    </tr>
                    <tr>
                        <td>
                            <div>Mess Fee</div>
                            <small><?php echo htmlspecialchars($selectedRoom['stay_period']); ?> Months</small>
                        </td>
                        <td>₹<?php echo htmlspecialchars(number_format($messFee, 2)); ?></td>
                    </tr>
                    <tr>
                        <th>Total Amount</th>
                        <th>₹<?php echo htmlspecialchars(number_format($totalFee, 2)); ?></th>
                    </tr>
                </table>
            </div>
            <?php if (isset($bookingError)): ?>
                <div class="alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($bookingError); ?>
                </div>
            <?php endif; ?>
            <form method="post" id="payment-form">
                <div class="payment-amount-section">
                    <h3><i class="fas fa-money-bill-wave"></i> Enter Payment Amount</h3>
                    <p>Please enter how much you'd like to pay now. You must pay a minimum of Rs 50,000.</p>
                    <div class="form-group">
                        <label for="amount_to_pay">Amount to Pay (₹)</label>
                        <div class="input-with-icon">
                            <i class="fas fa-rupee-sign"></i>
                            <input type="number" id="amount_to_pay" name="amount_to_pay" class="form-control" 
                                  min="0" max="<?php echo htmlspecialchars($totalFee); ?>" value="<?php echo htmlspecialchars($totalFee); ?>" step="100">
                        </div>
                    </div>
                    <div class="payment-note">
                        <i class="fas fa-info-circle"></i> Any remaining amount will be added to your dues and must be paid within 30 days.
                    </div>
                    <div class="dues-info" id="dues_info">
                        <strong>You will pay:</strong> <span id="payment_amount">₹<?php echo htmlspecialchars(number_format($totalFee, 2)); ?></span><br>
                        <strong>Remaining dues:</strong> <span id="dues_amount">₹0.00</span><br>
                        <strong>Due date:</strong> <?php echo date('d M Y', strtotime($dueDate)); ?>
                    </div>
                </div>
                <div class="payment-methods">
                    <h3><i class="fas fa-wallet"></i> Select Payment Method</h3>
                    <div class="payment-option-buttons">
                        <div class="payment-option-button active" data-method="Credit_Card">
                            <i class="far fa-credit-card"></i>
                            <span>Credit Card</span>
                        </div>
                        <div class="payment-option-button" data-method="Debit_Card">
                            <i class="fas fa-credit-card"></i>
                            <span>Debit Card</span>
                        </div>
                        <div class="payment-option-button" data-method="Net_Banking">
                            <i class="fas fa-university"></i>
                            <span>Net Banking</span>
                        </div>
                        <div class="payment-option-button" data-method="UPI">
                            <i class="fas fa-mobile-alt"></i>
                            <span>UPI</span>
                        </div>
                    </div>
                    <input type="hidden" name="payment_method" id="payment_method" value="Credit_Card">
                    <div class="payment-form-section" id="card_payment_section">
                        <div class="form-group">
                            <label for="card_number">Card Number</label>
                            <div class="input-with-icon">
                                <i class="far fa-credit-card"></i>
                                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="card_expiry">Expiry Date</label>
                                <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5" class="form-control">
                            </div>
                            <div class="form-group half">
                                <label for="card_cvc">CVC</label>
                                <input type="text" id="card_cvc" name="card_cvc" placeholder="123" maxlength="4" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="payment-form-section" id="upi_payment_section" style="display:none;">
                        <div class="form-group">
                            <label for="upi_id">UPI ID</label>
                            <div class="input-with-icon">
                                <i class="fas fa-at"></i>
                                <input type="text" id="upi_id" name="upi_id" placeholder="yourname@upi" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="payment-form-section" id="netbanking_payment_section" style="display:none;">
                        <div class="form-group">
                            <label for="bank_name">Select Bank</label>
                            <select id="bank_name" name="bank_name" class="form-control">
                                <option value="">Select your bank</option>
                                <option value="SBI">State Bank of India</option>
                                <option value="HDFC">HDFC Bank</option>
                                <option value="ICICI">ICICI Bank</option>
                                <option value="Axis">Axis Bank</option>
                                <option value="PNB">Punjab National Bank</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="account_number">Account Number</label>
                            <input type="text" id="account_number" name="account_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="ifsc_code">IFSC Code</label>
                            <input type="text" id="ifsc_code" name="ifsc_code" class="form-control">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn primary-btn" id="payment-button"><i class="fas fa-lock"></i> Pay Securely</button>
                        <a href="dashboard.php" class="btn secondary-btn"><i class="fas fa-arrow-left"></i> Cancel</a>
                    </div>
                </div>
            </form>
        </div>
        <div class="payment-footer">
            <div class="security-badges">
                <div><i class="fas fa-lock"></i> Secured by SSL</div>
                <div><i class="fas fa-shield-alt"></i> 256-bit encryption</div>
                <div><i class="fas fa-user-shield"></i> Privacy protected</div>
            </div>
        </div>
    </div>
    <script> 
    document.addEventListener('DOMContentLoaded', function() {
        const paymentOptions = document.querySelectorAll('.payment-option-button');
        const paymentMethodInput = document.getElementById('payment_method');
        const cardSection = document.getElementById('card_payment_section');
        const upiSection = document.getElementById('upi_payment_section');
        const netbankingSection = document.getElementById('netbanking_payment_section');
        paymentOptions.forEach(option => {
            option.addEventListener('click', function() {
                paymentOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                const method = this.getAttribute('data-method');
                paymentMethodInput.value = method;
                if (method === 'Credit_Card' || method === 'Debit_Card') {
                    cardSection.style.display = 'block';
                    upiSection.style.display = 'none';
                    netbankingSection.style.display = 'none';
                } else if (method === 'UPI') {
                    cardSection.style.display = 'none';
                    upiSection.style.display = 'block';
                    netbankingSection.style.display = 'none';
                } else if (method === 'Net_Banking') {
                    cardSection.style.display = 'none';
                    upiSection.style.display = 'none';
                    netbankingSection.style.display = 'block';
                }
            });
        });
        const cardNumberInput = document.getElementById('card_number');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s+/g, '');
                if (value.length > 0) { value = value.match(new RegExp('.{1,4}', 'g')).join(' ');}
                e.target.value = value;
            });
        }
        const expiryInput = document.getElementById('card_expiry');
        if (expiryInput) {
            expiryInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 2) {value = value.substring(0, 2) + '/' + value.substring(2, 4);}
                e.target.value = value;
            });
        }
        const amountInput = document.getElementById('amount_to_pay');
        const duesInfo = document.getElementById('dues_info');
        const paymentAmountDisplay = document.getElementById('payment_amount');
        const duesAmountDisplay = document.getElementById('dues_amount');
        if (amountInput && duesInfo) {
            amountInput.addEventListener('input', function() {
                const totalFee = <?php echo $totalFee; ?>;
                const paymentAmount = parseFloat(this.value) || 0;
                const duesAmount = Math.max(0, totalFee - paymentAmount);
                paymentAmountDisplay.textContent = '₹' + paymentAmount.toFixed(2);
                duesAmountDisplay.textContent = '₹' + duesAmount.toFixed(2);
                if (duesAmount > 0) {duesInfo.classList.add('has-dues');} 
                else {duesInfo.classList.remove('has-dues');}
            });
        }
        const form = document.getElementById('payment-form');
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const selectedMethod = paymentMethodInput.value;
            const amount = parseFloat(amountInput.value) || 0;
            const minPayment = <?php echo $minPaymentRequired; ?>;
            if (amount < minPayment) {
                alert('pay a minimum fee of Rs50,000.');
                isValid = false;}
            if (selectedMethod === 'Credit_Card' || selectedMethod === 'Debit_Card') {
                const cardNumber = cardNumberInput.value.replace(/\s+/g, '');
                const cardExpiry = expiryInput.value;
                const cardCvc = document.getElementById('card_cvc').value;
                if (cardNumber.length < 16) {
                    alert('Please enter a valid card number.');
                    isValid = false;}
                if (cardExpiry.length < 5) {
                    alert('Please enter a valid expiry date (MM/YY).');
                    isValid = false;}
                if (cardCvc.length < 3) {
                    alert('Please enter a valid CVC code.');
                    isValid = false;}
            } else if (selectedMethod === 'UPI') {
                const upiId = document.getElementById('upi_id').value;
                if (!upiId || !upiId.includes('@')) {
                    alert('Please enter a valid UPI ID.');
                    isValid = false;}
            } else if (selectedMethod === 'Net_Banking') {
                const bankName = document.getElementById('bank_name').value;
                const accountNumber = document.getElementById('account_number').value;
                const ifscCode = document.getElementById('ifsc_code').value;
                if (!bankName) {
                    alert('Please select your bank.');
                    isValid = false;}
                if (!accountNumber) {
                    alert('Please enter your account number.');
                    isValid = false;}
                if (!ifscCode) {
                    alert('Please enter your IFSC code.');
                    isValid = false;}
            }
            if (!isValid) {e.preventDefault();}
        });
    });
    </script>
</body>
</html>