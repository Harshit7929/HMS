<?php
include('db.php');
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['payment_id'])) {
    header("Location: login.php");
    exit();
}

$paymentId = $_SESSION['payment_id'];
$error = '';
$success = '';

// Get payment details from database
$stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->bind_param("i", $paymentId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic validation
    $cardNumber = preg_replace('/\s+/', '', $_POST['card_number']);
    $expiryMonth = $_POST['expiry_month'];
    $expiryYear = $_POST['expiry_year'];
    $cvv = $_POST['cvv'];
    $cardHolderName = $_POST['card_holder_name'];
    
    $errors = [];
    
    // Validate card number (basic Luhn algorithm check)
    if (!preg_match('/^[0-9]{16}$/', $cardNumber)) {
        $errors[] = "Invalid card number";
    }
    
    // Validate expiry date
    $currentYear = date('Y');
    $currentMonth = date('m');
    if ($expiryYear < $currentYear || 
        ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
        $errors[] = "Card has expired";
    }
    
    // Validate CVV
    if (!preg_match('/^[0-9]{3,4}$/', $cvv)) {
        $errors[] = "Invalid CVV";
    }
    
    // Validate card holder name
    if (empty($cardHolderName)) {
        $errors[] = "Card holder name is required";
    }
    
    if (empty($errors)) {
        try {
            // Here you would typically integrate with a payment gateway
            // For demonstration, we'll simulate a successful payment
            
            $conn->begin_transaction();
            
            // Update payment status
            $updateQuery = "UPDATE payments SET 
                payment_status = 'completed',
                transaction_details = ?,
                updated_at = NOW()
                WHERE id = ?";
                
            $transactionDetails = json_encode([
                'card_type' => $_POST['card_type'],
                'card_number' => substr($cardNumber, -4),
                'card_holder' => $cardHolderName,
                'payment_time' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("si", $transactionDetails, $paymentId);
            
            if ($stmt->execute()) {
                $conn->commit();
                $success = "Payment processed successfully!";
                // Redirect to success page after 2 seconds
                header("refresh:2;url=payment_success.php");
            } else {
                throw new Exception("Error processing payment");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Card Payment - Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        .card-payment-form {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 24px;
            margin-right: 10px;
            color: #2980b9;
        }
        .card-header {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .expiry-date {
            display: flex;
            gap: 10px;
        }
        .cvv-info {
            cursor: pointer;
        }
        .card-type-selection {
            margin-bottom: 20px;
        }
        .card-type-option {
            margin-right: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="card-payment-form">
            <div class="card-header">
                <h3><i class="fas fa-credit-card card-icon"></i>Card Payment</h3>
                <p class="mb-0">Amount to pay: ₹<?php echo number_format($payment['amount'], 2); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="post" id="cardPaymentForm">
                <!-- Card Type Selection -->
                <div class="card-type-selection">
                    <label class="form-label">Select Card Type:</label>
                    <div>
                        <label class="card-type-option">
                            <input type="radio" name="card_type" value="visa" required> 
                            <i class="fab fa-cc-visa"></i> Visa
                        </label>
                        <label class="card-type-option">
                            <input type="radio" name="card_type" value="mastercard"> 
                            <i class="fab fa-cc-mastercard"></i> Mastercard
                        </label>
                        <label class="card-type-option">
                            <input type="radio" name="card_type" value="rupay"> 
                            RuPay
                        </label>
                    </div>
                </div>

                <!-- Card Number -->
                <div class="form-group">
                    <label class="form-label">Card Number</label>
                    <input type="text" class="form-control" name="card_number" 
                           placeholder="1234 5678 9012 3456" maxlength="19" required>
                </div>

                <!-- Card Holder Name -->
                <div class="form-group">
                    <label class="form-label">Card Holder Name</label>
                    <input type="text" class="form-control" name="card_holder_name" 
                           placeholder="Name as on card" required>
                </div>

                <!-- Expiry Date and CVV -->
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Expiry Date</label>
                        <div class="expiry-date">
                            <select name="expiry_month" class="form-select" required>
                                <option value="">Month</option>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>">
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="expiry_year" class="form-select" required>
                                <option value="">Year</option>
                                <?php 
                                $currentYear = date('Y');
                                for($i = $currentYear; $i <= $currentYear + 10; $i++): 
                                ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            CVV 
                            <i class="fas fa-question-circle cvv-info" 
                               title="3 or 4 digit security code on the back of your card"></i>
                        </label>
                        <input type="password" class="form-control" name="cvv" 
                               maxlength="4" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">
                    Pay ₹<?php echo number_format($payment['amount'], 2); ?>
                </button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Format card number with spaces
            $('input[name="card_number"]').on('input', function() {
                let value = $(this).val().replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                let newValue = '';
                for(let i = 0; i < value.length; i++) {
                    if(i > 0 && i % 4 === 0) {
                        newValue += ' ';
                    }
                    newValue += value[i];
                }
                $(this).val(newValue);
            });

            // Validate form before submission
            $('#cardPaymentForm').on('submit', function(e) {
                let cardNumber = $('input[name="card_number"]').val().replace(/\s+/g, '');
                if(cardNumber.length !== 16) {
                    alert('Please enter a valid 16-digit card number');
                    e.preventDefault();
                    return false;
                }

                let cvv = $('input[name="cvv"]').val();
                if(cvv.length < 3) {
                    alert('Please enter a valid CVV');
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>