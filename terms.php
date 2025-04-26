<?php
// Start the session to maintain user data across pages
session_start();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['agree']) && $_POST['agree'] == '1') {
        // User agreed to terms, redirect to payment page
        $_SESSION['agreed_to_terms'] = true;
        header("Location: payment.php");
        exit;
    } else {
        // User did not check the agreement box
        $error_message = "You must agree to the Terms and Conditions to proceed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Terms and Conditions</h1>
        </header>
        
        <main>
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="terms-container">
                <div class="terms-content">
                    <h2>Terms of Service Agreement</h2>
                    
                    <h3>1. Introduction</h3>
                    <p>Welcome to our service. By using our services, you agree to comply with and be bound by the following terms and conditions of use.</p>
                    
                    <h3>2. Acceptance of Terms</h3>
                    <p>By accessing or using our website, you agree to these Terms and Conditions. If you do not agree to all the terms and conditions, you may not access the website or use any services.</p>
                    
                    <h3>3. User Accounts</h3>
                    <p>To access certain features of the website, you may be required to register for an account. You are responsible for maintaining the confidentiality of your account information, including your password, and for all activity that occurs under your account.</p>
                    
                    <h3>4. Privacy Policy</h3>
                    <p>Your use of our services is also governed by our Privacy Policy, which outlines how we collect, use, and protect your personal information.</p>
                    
                    <h3>5. Intellectual Property</h3>
                    <p>The content, organization, graphics, design, compilation, and other matters related to the website are protected by copyright, trademark, and other intellectual property rights.</p>
                    
                    <h3>6. Prohibited Uses</h3>
                    <p>You may use our website for lawful purposes only. You agree not to use the website for any illegal or unauthorized purpose.</p>
                    
                    <h3>7. Limitation of Liability</h3>
                    <p>In no event shall we be liable for any direct, indirect, incidental, special, or consequential damages arising out of or in any way connected with the use of our services.</p>
                    
                    <h3>8. Changes to Terms</h3>
                    <p>We reserve the right to modify these terms at any time. We will provide notice of any material changes to the terms by posting the new terms on the website.</p>
                    
                    <h3>9. Governing Law</h3>
                    <p>These terms shall be governed by and construed in accordance with the laws of the jurisdiction in which the company operates.</p>
                    
                    <h3>10. Contact Information</h3>
                    <p>If you have any questions about these Terms and Conditions, please contact us.</p>
                </div>
            </div>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="agreement-form">
                <div class="checkbox-container">
                    <input type="checkbox" id="agree" name="agree" value="1">
                    <label for="agree">I have read and agree to the Terms and Conditions</label>
                </div>
                
                <div class="button-container">
                    <button type="submit" class="proceed-button">Agree and Proceed</button>
                </div>
            </form>
        </main>
        
        <footer>
            <p>&copy; <?php echo date("Y"); ?> Your Company Name. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>