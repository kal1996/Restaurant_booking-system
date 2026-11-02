<?php
session_start();

if (!isset($_SESSION['owner_logged_in']) || $_SESSION['owner_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

require_once "../connection.php";

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? 'add_staff';

    if ($action === 'add_staff') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $role = $_POST['role'] ?? '';

        if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($role)) {
            $error = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (!in_array($role, ['admin', 'chef'])) {
            $error = "Please select a valid role.";
        } elseif (!preg_match('/^(\+?251[79]\d{8}|0[79]\d{8})$/', $phone)) {
            $error = "Please enter a valid Ethiopian phone number (09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx).";
        } else {
            try {

                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Email address already exists. Please use a different email.";
                } else {
                 
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    
                    if ($stmt->execute([$name, $email, $phone, $hashed_password, $role])) {
                        $role_name = ucfirst($role);
                        $success = "$role_name added successfully! Login credentials have been created for $email.";
                        
                     
                        $name = $email = $phone = $password = $confirm_password = $role = '';
                    } else {
                        $error = "Failed to add staff member. Please try again.";
                    }
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    if ($action === 'update_staff') {
        $staff_id = intval($_POST['staff_id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $new_password = trim($_POST['new_password']);
        
        if ($staff_id > 0) {
            if (!preg_match('/^(\+?251[79]\d{8}|0[79]\d{8})$/', $phone)) {
                $error = "Please enter a valid Ethiopian phone number (09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx).";
            } elseif (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, password=? WHERE id=? AND role IN ('admin', 'chef')");
                    if ($stmt->execute([$name, $email, $phone, $hashed, $staff_id])) {
                        $success = "Staff member updated successfully!";
                    } else {
                        $error = "Failed to update staff member.";
                    }
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=? AND role IN ('admin', 'chef')");
                if ($stmt->execute([$name, $email, $phone, $staff_id])) {
                    $success = "Staff member updated successfully!";
                } else {
                    $error = "Failed to update staff member.";
                }
            }
        }
    }

    if ($action === 'delete_staff') {
        $staff_id = intval($_POST['staff_id']);
        
        if ($staff_id > 0) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role IN ('admin', 'chef')");
            if ($stmt->execute([$staff_id])) {
                $success = "Staff member deleted successfully!";
            } else {
                $error = "Failed to delete staff member.";
            }
        }
    }
}

try {
    $stmt = $conn->prepare("
        SELECT id, name, email, phone, role, created_at 
        FROM users 
        WHERE role IN ('admin', 'chef') 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staff_members = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff - Owner Dashboard</title>
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
            max-width: 1200px;
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            color: #333;
            font-size: 1.3rem;
        }

        .section-header i {
            color: #3f2a1d;
            font-size: 1.4rem;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3f2a1d;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 10px;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-label {
            display: block;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .role-option input[type="radio"]:checked + .role-label {
            border-color: #3f2a1d;
            background: rgba(102, 126, 234, 0.1);
            color: #3f2a1d;
        }

        .role-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: block;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: #6d6764ff;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .staff-list {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .staff-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #3f2a1d;
            position: relative;
        }

        .staff-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .staff-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .staff-details {
            color: #666;
            font-size: 0.9rem;
        }

        .staff-role {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .role-admin {
            background: #dc3545;
            color: white;
        }

        .role-chef {
            background: #fd7e14;
            color: white;
        }

        .staff-dropdown {
            position: relative;
            display: inline-block;
        }

        .staff-menu-btn {
            background: transparent;
            color: #3f2a1d;
            border: none;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .staff-menu-btn:hover {
            color: #3f2a1d;
            transform: scale(1.2);
        }

        .staff-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            bottom: 45px;
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(20px);
            min-width: 200px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            border-radius: 15px;
            z-index: 1000;
            border: 1px solid rgba(102,126,234,0.2);
            overflow: hidden;
        }

        .staff-dropdown-content.show {
            display: block;
            animation: dropdownFadeIn 0.3s ease;
        }

        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .dropdown-header {
            padding: 15px 20px;
            background: #6d6764ff;
            color: white;
            font-weight: 600;
            text-align: center;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .staff-dropdown-item {
            display: block;
            color: #333;
            padding: 15px 20px;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .staff-dropdown-item:hover {
            background: rgba(102,126,234,0.1);
            color: #6d6764ff;
            transform: translateX(-5px);
        }

        .staff-dropdown-item.edit-item:hover {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
        }

        .staff-dropdown-item.view-item:hover {
            background: linear-gradient(45deg, #3b82f6, #60a5fa);
            color: white;
        }

        .staff-dropdown-item.delete-item:hover {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
        }

        .staff-dropdown-item:last-child {
            border-bottom: none;
        }

        .staff-dropdown-item i {
            width: 18px;
            text-align: center;
            font-size: 1.1rem;
        }

        .dropdown-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(102,126,234,0.3), transparent);
            margin: 8px 0;
        }

        .edit-form {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 15px;
            padding: 20px;
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            border: 2px solid rgba(102,126,234,0.2);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #6d6764ff;
            color: white;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        }

        .btn-warning {
            background: linear-gradient(45deg, #ff9800, #f57c00);
            color: white;
            box-shadow: 0 4px 15px rgba(255,152,0,0.3);
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        .no-staff {
            text-align: center;
            padding: 40px;
            color: #666;
        }

       

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .role-selector {
                grid-template-columns: 1fr;
            }

            .staff-item {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                position: relative;
            }

            .staff-actions {
                justify-content: center;
                width: 100%;
            }

            .staff-menu-btn {
                width: 30px;
                height: 30px;
                font-size: 1rem;
            }
            
            .staff-dropdown-content {
                min-width: 160px;
                left: 50%;
                transform: translateX(-50%);
                right: auto;
            }

            .edit-form {
                position: relative;
                top: auto;
                left: auto;
                right: auto;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-user-plus"></i> Add Staff</h1>
            </div>
            <div class="header-right">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="content-grid">

            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-user-plus"></i>
                    <h2>Add New Staff Member</h2>
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

                <form method="POST" action="" onsubmit="return validateForm(this)">
                    <input type="hidden" name="action" value="add_staff">
                    
                    <div class="form-group">
                        <label for="name">
                            <i class="fas fa-user"></i> Full Name
                        </label>
                        <input type="text" id="name" name="name" required 
                               placeholder="Enter full name"
                               value="<?= htmlspecialchars($name ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="email" name="email" required 
                               placeholder="Enter email address"
                               value="<?= htmlspecialchars($email ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone" required 
                               placeholder="Example: 0912345678 or +251912345678"
                               value="<?= htmlspecialchars($phone ?? '') ?>"
                               oninput="validatePhone(this)">
                        
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter password (min 6 characters)">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm password">
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-user-tag"></i> Staff Role
                        </label>
                        <div class="role-selector">
                            <div class="role-option">
                                <input type="radio" id="role_admin" name="role" value="admin" 
                                       <?= (isset($role) && $role === 'admin') ? 'checked' : '' ?>>
                                <label for="role_admin" class="role-label">
                                    <i class="fas fa-user-shield role-icon"></i>
                                    <strong>Administrator</strong>
                                    <div style="font-size: 0.8rem; margin-top: 5px;">
                                        Full system access
                                    </div>
                                </label>
                            </div>
                            <div class="role-option">
                                <input type="radio" id="role_chef" name="role" value="chef"
                                       <?= (isset($role) && $role === 'chef') ? 'checked' : '' ?>>
                                <label for="role_chef" class="role-label">
                                    <i class="fas fa-utensils role-icon"></i>
                                    <strong>Chef</strong>
                                    <div style="font-size: 0.8rem; margin-top: 5px;">
                                        Kitchen management
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-user-plus"></i> Add Staff Member
                    </button>
                </form>

            
            </div>

            <div class="staff-list">
                <div class="section-header">
                    <i class="fas fa-users"></i>
                    <h2>Current Staff Members</h2>
                </div>

                <?php if (empty($staff_members)): ?>
                    <div class="no-staff">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px; color: #ddd;"></i>
                        <h3>No Staff Members</h3>
                        <p>No administrators or chefs have been added yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($staff_members as $staff): ?>
                        <div class="staff-item">
                            <div class="staff-info">
                                <h4><?= htmlspecialchars($staff['name']) ?></h4>
                                <div class="staff-details">
                                    <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($staff['email']) ?></div>
                                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($staff['phone']) ?></div>
                                    <div><i class="fas fa-calendar"></i> Added: <?= date('M j, Y', strtotime($staff['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="staff-actions">
                                <div class="staff-role role-<?= $staff['role'] ?>">
                                    <?= ucfirst($staff['role']) ?>
                                </div>
                                <div class="staff-dropdown">
                                    <button class="staff-menu-btn" onclick="toggleStaffMenu(<?= $staff['id'] ?>)" title="Staff Options">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="staff-dropdown-content" id="staffDropdown<?= $staff['id'] ?>">
                                        <div class="dropdown-header">
                                            <i class="fas fa-cog"></i> Staff Options
                                        </div>
                                        <button class="staff-dropdown-item view-item" onclick="viewStaffDetails(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['name']) ?>', '<?= htmlspecialchars($staff['email']) ?>', '<?= htmlspecialchars($staff['phone']) ?>', '<?= ucfirst($staff['role']) ?>', '<?= date('M d, Y', strtotime($staff['created_at'])) ?>')">
                                            <i class="fas fa-eye"></i>
                                            View Details
                                        </button>
                                        <button class="staff-dropdown-item edit-item" onclick="toggleEditForm(<?= $staff['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                            Edit Staff
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        <button class="staff-dropdown-item delete-item" onclick="confirmStaffDelete(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['name']) ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                            Delete Staff
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="edit-form" id="editForm<?= $staff['id'] ?>">
                                <form method="POST" onsubmit="return validateForm(this)">
                                    <input type="hidden" name="action" value="update_staff">
                                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($staff['name']) ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($staff['email']) ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($staff['phone']) ?>" placeholder="Example: 0912345678 or +251912345678" required oninput="validatePhone(this)">
                                        <small style="color: #666; font-size: 0.9rem;">Ethiopian format: 09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">New Password (leave blank to keep current)</label>
                                        <input type="password" name="new_password" class="form-input" placeholder="Enter new password (min 6 characters)" minlength="6">
                                    </div>
                                    
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="toggleEditForm(<?= $staff['id'] ?>)">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <form id="deleteStaffForm" method="POST" style="display: none;">
                        <input type="hidden" name="action" value="delete_staff">
                        <input type="hidden" name="staff_id" id="deleteStaffId">
                    </form>
                    
                    <div style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> 
                        Total Staff: <?= count($staff_members) ?> 
                        (<?= count(array_filter($staff_members, fn($s) => $s['role'] === 'admin')) ?> Admins, 
                        <?= count(array_filter($staff_members, fn($s) => $s['role'] === 'chef')) ?> Chefs)
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

<script>

function toggleStaffMenu(staffId) {
    const dropdown = document.getElementById('staffDropdown' + staffId);
    const allDropdowns = document.querySelectorAll('.staff-dropdown-content');

    allDropdowns.forEach(dd => {
        if (dd.id !== 'staffDropdown' + staffId) {
            dd.classList.remove('show');
        }
    });

    dropdown.classList.toggle('show');
}

function viewStaffDetails(id, name, email, phone, role, joinDate) {
    const details = `
👤 STAFF DETAILS

📋 Staff Information:
• Staff ID: #${id}
• Name: ${name}
• Role: ${role}
• Email: ${email}
• Phone: ${phone}
• Joined: ${joinDate}

This staff member has ${role.toLowerCase()} privileges and access to the system.
    `;
    
    alert(details);

    const dropdown = document.getElementById('staffDropdown' + id);
    dropdown.classList.remove('show');
}

function confirmStaffDelete(staffId, staffName) {
    const confirmed = confirm(`⚠️ WARNING!\n\nAre you absolutely sure you want to delete "${staffName}"?\n\nThis action cannot be undone and will permanently remove:\n• The staff member from the system\n• All their access privileges\n• Their login credentials\n\nClick OK to proceed with deletion, or Cancel to keep the staff member.`);
    
    if (confirmed) {
        document.getElementById('deleteStaffId').value = staffId;
        document.getElementById('deleteStaffForm').submit();
    }

    const dropdown = document.getElementById('staffDropdown' + staffId);
    dropdown.classList.remove('show');
}

function toggleEditForm(staffId) {
    const form = document.getElementById('editForm' + staffId);
    const allForms = document.querySelectorAll('.edit-form');

    allForms.forEach(f => {
        if (f.id !== 'editForm' + staffId) {
            f.style.display = 'none';
        }
    });

    form.style.display = form.style.display === 'none' || form.style.display === '' ? 'block' : 'none';

    const dropdown = document.getElementById('staffDropdown' + staffId);
    dropdown.classList.remove('show');
}

window.addEventListener('click', function(event) {
    if (!event.target.matches('.staff-menu-btn') && !event.target.closest('.staff-menu-btn')) {
        const dropdowns = document.querySelectorAll('.staff-dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.form-section, .staff-list');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = `opacity 0.6s ease ${index * 0.2}s, transform 0.6s ease ${index * 0.2}s`;
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 200));
    });
});

function validatePhone(input) {
    const phonePattern = /^(\+?251[79]\d{8}|0[79]\d{8})$/;
    let phoneHelp = input.parentNode.querySelector('small');

    if (!phoneHelp) {
        phoneHelp = document.createElement('small');
        phoneHelp.style.fontSize = '0.9rem';
        phoneHelp.style.display = 'block';
        phoneHelp.style.marginTop = '5px';
        input.parentNode.appendChild(phoneHelp);
    }
    
    const value = input.value.trim();
    
    if (value === '') {
        phoneHelp.style.color = '#666';
        phoneHelp.textContent = 'Ethiopian format: 09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx';
        input.style.borderColor = '#e1e5e9';
        return false;
    }
    
    if (phonePattern.test(value)) {
        phoneHelp.style.color = '#28a745';
        phoneHelp.textContent = '✓ Valid Ethiopian phone number';
        input.style.borderColor = '#28a745';
        return true;
    } else {
        phoneHelp.style.color = '#dc3545';
        phoneHelp.textContent = '✗ Invalid format. Examples: 0912345678, 0712345678, +251912345678';
        input.style.borderColor = '#dc3545';
        return false;
    }
}

function isValidEthiopianPhone(phone) {

    const cleaned = phone.replace(/[\s\-\(\)]/g, '');

    const patterns = [
        /^0[79]\d{8}$/,     
        /^\+251[79]\d{8}$/,      
        /^251[79]\d{8}$/,         
        /^[79]\d{8}$/             
    ];
    
    return patterns.some(pattern => pattern.test(cleaned));
}

function formatPhoneInput(input) {
    let value = input.value.replace(/\D/g, ''); 

    if (value.startsWith('251')) {

        if (value.length <= 12) {
            value = '+' + value;
        }
    } else if (value.startsWith('09') || value.startsWith('07')) {

        if (value.length > 10) {
            value = value.substring(0, 10);
        }
    }
    
    input.value = value;
    validatePhone(input);
}

document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            validatePhone(this);
        });

        input.addEventListener('paste', function(e) {
            setTimeout(() => {
                validatePhone(this);
            }, 10);
        });
    });
});

function validateForm(form) {
    const phoneInput = form.querySelector('input[name="phone"]');
    const passwordInput = form.querySelector('input[name="password"]');
    const confirmPasswordInput = form.querySelector('input[name="confirm_password"]');

    if (phoneInput && !isValidEthiopianPhone(phoneInput.value)) {
        alert('Please enter a valid Ethiopian phone number.\n\nAccepted formats:\n• 0912345678\n• 0712345678\n• +251912345678\n• +251712345678');
        phoneInput.focus();
        return false;
    }

    if (passwordInput && confirmPasswordInput) {
        if (passwordInput.value !== confirmPasswordInput.value) {
            alert('Passwords do not match. Please check and try again.');
            confirmPasswordInput.focus();
            return false;
        }
        
        if (passwordInput.value.length < 6) {
            alert('Password must be at least 6 characters long.');
            passwordInput.focus();
            return false;
        }
    }
    
    return true;
}
</script>
</html>