<?php
session_start();

if (!isset($_SESSION['owner_logged_in']) || $_SESSION['owner_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once "../connection.php";
require_once "../includes/restaurant_config.php";

$success = '';
$error = '';

$owner_config_file = '../config/owner_settings.json';
$restaurant_config_file = '../config/restaurant_config.json';

$default_owner_settings = [
    'owner_name' => 'Abebe Kebede',
    'owner_phone' => '0712272260',
    'owner_email' => 'abebekeb@gmail.com',
    'owner_password' => 'owner123'
];

function loadOwnerSettings($config_file, $default_settings) {
    if (file_exists($config_file)) {
        $settings = json_decode(file_get_contents($config_file), true);
        return array_merge($default_settings, $settings);
    }
    return $default_settings;
}

function saveSettings($config_file, $settings) {
    $config_dir = dirname($config_file);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }
    return file_put_contents($config_file, json_encode($settings, JSON_PRETTY_PRINT));
}

$owner_settings = loadOwnerSettings($owner_config_file, $default_owner_settings);
$restaurant_settings = getRestaurantSettings();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_owner_info') {
        $new_name = trim($_POST['owner_name'] ?? '');
        $new_phone = trim($_POST['owner_phone'] ?? '');
        $new_email = trim($_POST['owner_email'] ?? '');
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($current_password !== $owner_settings['owner_password']) {
            $error = "Current password is incorrect.";
        } elseif (empty($new_name) || empty($new_phone) || empty($new_email)) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!preg_match('/^(\+?251[79]\d{8}|0[79]\d{8})$/', $new_phone)) {
            $error = "Please enter a valid Ethiopian phone number.";
        } elseif (!empty($new_password) && strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters long.";
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
           
            $owner_settings['owner_name'] = $new_name;
            $owner_settings['owner_phone'] = $new_phone;
            $owner_settings['owner_email'] = $new_email;
            
            if (!empty($new_password)) {
                $owner_settings['owner_password'] = $new_password;
            }
            
            if (saveSettings($owner_config_file, $owner_settings)) {
              
                $_SESSION['owner_name'] = $new_name;
                $_SESSION['owner_email'] = $new_email;
                $_SESSION['owner_phone'] = $new_phone;
                
                $success = "Owner information updated successfully!";
            } else {
                $error = "Failed to save settings. Please try again.";
            }
        }
    }
    
    if ($action === 'update_restaurant_info') {
        $restaurant_name = trim($_POST['restaurant_name'] ?? '');
        $restaurant_phone = trim($_POST['restaurant_phone'] ?? '');
        $restaurant_email = trim($_POST['restaurant_email'] ?? '');
        $restaurant_location = trim($_POST['restaurant_location'] ?? '');
        $restaurant_description = trim($_POST['restaurant_description'] ?? '');
        $opening_time = trim($_POST['opening_time'] ?? '');
        $closing_time = trim($_POST['closing_time'] ?? '');
        
        if (empty($restaurant_name) || empty($restaurant_location)) {
            $error = "Please fill in restaurant name and location.";
        } elseif (!empty($restaurant_email) && !filter_var($restaurant_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid restaurant email address.";
        } elseif (!empty($restaurant_phone) && !preg_match('/^(\+?251[79]\d{8}|0[79]\d{8})$/', $restaurant_phone)) {
            $error = "Please enter a valid Ethiopian phone number for restaurant.";
        } else {
           
            $restaurant_settings['restaurant_name'] = $restaurant_name;
            $restaurant_settings['phone'] = $restaurant_phone;
            $restaurant_settings['email'] = $restaurant_email;
            $restaurant_settings['location'] = $restaurant_location;
            $restaurant_settings['description'] = $restaurant_description;
            $restaurant_settings['opening_time'] = $opening_time;
            $restaurant_settings['closing_time'] = $closing_time;
            
            if (saveSettings($restaurant_config_file, $restaurant_settings)) {
                $success = "Restaurant information updated successfully! Changes will appear on the about page.";
            } else {
                $error = "Failed to save restaurant settings. Please try again.";
            }
        }
    }
    
    if ($action === 'update_payment_accounts') {
        $cbe_account = trim($_POST['cbe_account'] ?? '');
        $telebirr_account = trim($_POST['telebirr_account'] ?? '');
        
        if (empty($cbe_account) || empty($telebirr_account)) {
            $error = "Please fill in both payment account numbers.";
        } elseif (!preg_match('/^\d{13}$/', $cbe_account)) {
            $error = "CBE account number must be exactly 13 digits.";
        } elseif (!preg_match('/^(\+?251[79]\d{8}|0[79]\d{8})$/', $telebirr_account)) {
            $error = "TeleBirr account must be a valid Ethiopian phone number.";
        } else {
            $owner_settings['cbe_account'] = $cbe_account;
            $owner_settings['telebirr_account'] = $telebirr_account;
            
            if (saveSettings($owner_config_file, $owner_settings)) {
                
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }
                
                $success = "Payment account information updated successfully! All payment methods will now use the new account numbers.";
            } else {
                $error = "Failed to save payment settings. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Settings - Restaurant Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(to right, #faf3e0 0%, #e9dbb4ff); 
            min-height: 100vh;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            background: #3f2a1d;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
        }

        .back-btn {
            background: #6d6764ff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .owner-profile {
            text-align: center;
            margin-bottom: 30px;
        }

        .owner-avatar {
            width: 100px;
            height: 100px;
            background: #3f2a1d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2.5rem;
        }

        .profile-info h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .profile-info p {
            color: #666;
            margin-bottom: 5px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #3f2a1d;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3f2a1d;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            color: #666;
            font-size: 0.85rem;
            margin-top: 5px;
            display: block;
        }

        .btn {
            background: #6d6764ff;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #495057);
        }

        .btn-secondary:hover {
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 2px solid rgba(40, 167, 69, 0.2);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 2px solid rgba(220, 53, 69, 0.2);
        }

        .info-note {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #6d6764ff;
        }

        .info-note i {
            margin-right: 8px;
        }

        .account-display {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .account-display h4 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .account-display p {
            color: #666;
            margin-bottom: 5px;
            font-family: monospace;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .settings-card {
                padding: 20px;
            }

            .owner-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-cog"></i> Owner Settings</h1>
            </div>
            <div class="header-right">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="settings-card">
            <div class="owner-profile">
                <div class="owner-avatar">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($owner_settings['owner_name']) ?></h2>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($owner_settings['owner_email']) ?></p>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($owner_settings['owner_phone']) ?></p>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="settings-card">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_owner_info">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-user-edit"></i>
                        <span>Update Owner Information</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="owner_name">Full Name *</label>
                            <input type="text" id="owner_name" name="owner_name" 
                                   value="<?= htmlspecialchars($owner_settings['owner_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="owner_phone">Phone Number *</label>
                            <input type="tel" id="owner_phone" name="owner_phone" 
                                   value="<?= htmlspecialchars($owner_settings['owner_phone']) ?>" 
                                   placeholder="0912345678 or +251912345678" required>
                           
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="owner_email">Email Address *</label>
                        <input type="email" id="owner_email" name="owner_email" 
                               value="<?= htmlspecialchars($owner_settings['owner_email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" 
                               placeholder="Enter current password to confirm changes" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password (Optional)</label>
                            <input type="password" id="new_password" name="new_password" 
                                   placeholder="Leave blank to keep current password" minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password">
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update Owner Information
                    </button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_restaurant_info">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-store"></i>
                        <span>Restaurant Information</span>
                    </div>

                    <div class="info-note">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Changes made here will automatically update your restaurant's about page and all public information.
                    </div>

                    <div class="form-group">
                        <label for="restaurant_name">Restaurant Name *</label>
                        <input type="text" id="restaurant_name" name="restaurant_name" 
                               value="<?= htmlspecialchars($restaurant_settings['restaurant_name']) ?>" required>
                        <small>This will appear as your restaurant's main title</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="restaurant_phone">Restaurant Phone</label>
                            <input type="tel" id="restaurant_phone" name="restaurant_phone" 
                                   value="<?= htmlspecialchars($restaurant_settings['phone']) ?>" 
                                   placeholder="0912345678 or +251912345678">
                            <small>Public contact number for customers</small>
                        </div>

                        <div class="form-group">
                            <label for="restaurant_email">Restaurant Email</label>
                            <input type="email" id="restaurant_email" name="restaurant_email" 
                                   value="<?= htmlspecialchars($restaurant_settings['email']) ?>" 
                                   placeholder="info@restaurant.com">
                            <small>Public email for customer inquiries</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="restaurant_location">Restaurant Location *</label>
                        <input type="text" id="restaurant_location" name="restaurant_location" 
                               value="<?= htmlspecialchars($restaurant_settings['location']) ?>" 
                               placeholder="Full address including city and country" required>
                        <small>Complete address that will appear on your about page</small>
                    </div>

                    <div class="form-group">
                        <label for="restaurant_description">Restaurant Description</label>
                        <textarea id="restaurant_description" name="restaurant_description" 
                                  placeholder="Brief description of your restaurant, cuisine, and atmosphere"><?= htmlspecialchars($restaurant_settings['description']) ?></textarea>
                        <small>This description will appear on your about page hero section</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="opening_time">Opening Time</label>
                            <input type="time" id="opening_time" name="opening_time" 
                                   value="<?= htmlspecialchars($restaurant_settings['opening_time']) ?>">
                            <small>Daily opening time</small>
                        </div>

                        <div class="form-group">
                            <label for="closing_time">Closing Time</label>
                            <input type="time" id="closing_time" name="closing_time" 
                                   value="<?= htmlspecialchars($restaurant_settings['closing_time']) ?>">
                            <small>Daily closing time</small>
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update Restaurant Information
                    </button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_payment_accounts">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-credit-card"></i>
                        <span>Payment Accounts</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="cbe_account">CBE Account Number *</label>
                            <input type="text" id="cbe_account" name="cbe_account" 
                                   value="<?= htmlspecialchars($owner_settings['cbe_account'] ?? '1000123456789') ?>" 
                                   placeholder="1000123456789" maxlength="13" required>
                        </div>

                        <div class="form-group">
                            <label for="telebirr_account">TeleBirr Account Number *</label>
                            <input type="tel" id="telebirr_account" name="telebirr_account" 
                                   value="<?= htmlspecialchars($owner_settings['telebirr_account'] ?? '0712272260') ?>" 
                                   placeholder="0912345678 or +251912345678" required>
                        </div>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update Payment Accounts
                    </button>
                </div>
            </form>
        </div>

<script>

function validatePhone(input) {
    const phonePattern = /^(\+?251[79]\d{8}|0[79]\d{8})$/;
    const value = input.value.trim();
    
    if (value === '') {
        input.style.borderColor = '#e1e5e9';
        return false;
    }
    
    if (phonePattern.test(value)) {
        input.style.borderColor = '#28a745';
        return true;
    } else {
        input.style.borderColor = '#dc3545';
        return false;
    }
}

function validateCBEAccount(input) {
    const value = input.value.trim();
    const cbePattern = /^\d{13}$/;
    
    if (value === '') {
        input.style.borderColor = '#e1e5e9';
        return false;
    }
    
    if (cbePattern.test(value)) {
        input.style.borderColor = '#28a745';
        return true;
    } else {
        input.style.borderColor = '#dc3545';
        return false;
    }
}

function validatePasswordConfirmation() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (newPassword.value === '' && confirmPassword.value === '') {
        confirmPassword.style.borderColor = '#e1e5e9';
        return true;
    }
    
    if (newPassword.value === confirmPassword.value) {
        confirmPassword.style.borderColor = '#28a745';
        return true;
    } else {
        confirmPassword.style.borderColor = '#dc3545';
        return false;
    }
}

function validateOwnerForm(form) {
    const phoneInput = form.querySelector('input[name="owner_phone"]');
    const currentPassword = form.querySelector('input[name="current_password"]');
    const newPassword = form.querySelector('input[name="new_password"]');
    const confirmPassword = form.querySelector('input[name="confirm_password"]');
    
    let isValid = true;

    if (phoneInput && !validatePhone(phoneInput)) {
        alert('Please enter a valid Ethiopian phone number.\n\nAccepted formats:\n• 0912345678\n• 0712345678\n• +251912345678\n• +251712345678');
        phoneInput.focus();
        return false;
    }

    if (currentPassword && currentPassword.value.trim() === '') {
        alert('Please enter your current password to confirm changes.');
        currentPassword.focus();
        return false;
    }

    if (newPassword && newPassword.value.trim() !== '') {
        if (newPassword.value.length < 6) {
            alert('New password must be at least 6 characters long.');
            newPassword.focus();
            return false;
        }
        
        if (confirmPassword && newPassword.value !== confirmPassword.value) {
            alert('New passwords do not match. Please check and try again.');
            confirmPassword.focus();
            return false;
        }
    }
    
    return true;
}

function validatePaymentForm(form) {
    const cbeAccount = form.querySelector('input[name="cbe_account"]');
    const telebirrAccount = form.querySelector('input[name="telebirr_account"]');

    if (cbeAccount && !validateCBEAccount(cbeAccount)) {
        alert('CBE account number must be exactly 13 digits.');
        cbeAccount.focus();
        return false;
    }

    if (telebirrAccount && !validatePhone(telebirrAccount)) {
        alert('TeleBirr account must be a valid Ethiopian phone number.');
        telebirrAccount.focus();
        return false;
    }
    
    return true;
}

document.addEventListener('DOMContentLoaded', function() {

    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            validatePhone(this);
        });
    });
 
    const cbeInput = document.getElementById('cbe_account');
    if (cbeInput) {
        cbeInput.addEventListener('input', function() {

            this.value = this.value.replace(/\D/g, '');
            validateCBEAccount(this);
        });
    }

    const confirmPasswordInput = document.getElementById('confirm_password');
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validatePasswordConfirmation);
        
        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', validatePasswordConfirmation);
        }
    }

    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = this.querySelector('input[name="action"]').value;
            
            if (action === 'update_owner_info') {
                if (!validateOwnerForm(this)) {
                    e.preventDefault();
                    return false;
                }
            } else if (action === 'update_payment_accounts') {
                if (!validatePaymentForm(this)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
});
</script>
</body>
</html>