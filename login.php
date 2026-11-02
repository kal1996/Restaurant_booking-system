<?php
session_start();
require_once "connection.php";

// Owner credentials - load from settings file
$config_file = 'config/owner_settings.json';
$default_owner = [
    'owner_name' => 'Abebe Kebede',
    'owner_phone' => '0712272260',
    'owner_email' => 'abebekeb@gmail.com',
    'owner_password' => 'owner123'
];

// Load owner settings
function loadOwnerSettings($config_file, $default_owner) {
    if (file_exists($config_file)) {
        $settings = json_decode(file_get_contents($config_file), true);
        return array_merge($default_owner, $settings);
    }
    return $default_owner;
}

$owner_settings = loadOwnerSettings($config_file, $default_owner);

$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validation
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if it's the owner login first
        if ($email === $owner_settings['owner_email'] && $password === $owner_settings['owner_password']) {
            // Owner login
            $_SESSION['owner_logged_in'] = true;
            $_SESSION['owner_name'] = $owner_settings['owner_name'];
            $_SESSION['owner_email'] = $owner_settings['owner_email'];
            $_SESSION['owner_phone'] = $owner_settings['owner_phone'];
            
            $success = "Owner login successful! Redirecting to owner dashboard...";
            header("refresh:1;url=owner/dashboard.php");
            exit;
        } else {
            // Check database for regular users (user, admin, chef)
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['role'] = $user['role'];

                    $role_name = ucfirst($user['role']);
                    $success = "$role_name login successful! Redirecting to dashboard...";
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header("refresh:1;url=admin/dashboard.php");
                        exit;
                    } elseif ($user['role'] === 'chef') {
                        // Ensure chef dashboard exists before redirecting
                        if (file_exists('chef/dashboard.php')) {
                            header("refresh:1;url=chef/dashboard.php");
                            exit;
                        } else {
                            $error = "Chef dashboard not found. Please contact administrator.";
                        }
                    } else {
                        // Regular user
                        header("refresh:1;url=user/dashboard.php");
                        exit;
                    }
                } else {
                    $error = "Invalid email or password";
                }
            } catch (Exception $e) {
                $error = "Login failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Restaurant Login - All Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

/* Navigation Bar */
.nav-bar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(10px);
    padding: 15px 30px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
    z-index: 1000;
}

.nav-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-logo {
    font-size: 1.5rem;
    font-weight: bold;
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-decoration: none;
}

.nav-links {
    display: flex;
    gap: 20px;
    align-items: center;
}

.nav-link {
    color: #333;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 20px;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-link:hover {
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
    color: white;
    transform: translateY(-2px);
}

.nav-link.home-btn {
    background: rgba(78,205,196,0.1);
    border: 1px solid rgba(78,205,196,0.3);
}

body { 
    font-family: Arial; 
    background: url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80') center/cover fixed,
                linear-gradient(135deg, rgba(0,0,0,0.7), rgba(0,0,0,0.5));
    min-height: 100vh; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    padding-top: 80px; /* Account for fixed nav */
}

.container { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(10px); 
    padding: 40px; 
    border-radius: 20px; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
    width: 100%; 
    max-width: 400px; 
    animation: slideUp 0.6s ease; 
}

@keyframes slideUp { 
    from { opacity: 0; transform: translateY(30px); } 
    to { opacity: 1; transform: translateY(0); } 
}

.logo { 
    text-align: center; 
    margin-bottom: 30px; 
}

.logo h1 { 
    font-size: 2.5rem; 
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    margin-bottom: 5px; 
}

.logo p { 
    color: #666; 
    font-size: 1rem;
}

.form-group { 
    margin-bottom: 20px; 
}

.form-input { 
    width: 100%; 
    padding: 15px; 
    border: 2px solid #eee; 
    border-radius: 10px; 
    font-size: 16px; 
    transition: 0.3s; 
    background: white; 
}

.form-input:focus { 
    outline: none; 
    border-color: #4ecdc4; 
    box-shadow: 0 0 10px rgba(78,205,196,0.3); 
}

.form-input.error { 
    border-color: #ff6b6b; 
}

.btn { 
    width: 100%; 
    padding: 15px; 
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4); 
    color: white; 
    border: none; 
    border-radius: 10px; 
    font-size: 16px; 
    font-weight: bold; 
    cursor: pointer; 
    transition: 0.3s; 
    margin-bottom: 20px; 
}

.btn:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 10px 20px rgba(0,0,0,0.2); 
}

.btn:disabled { 
    opacity: 0.7; 
    cursor: not-allowed; 
}

.message { 
    padding: 12px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    text-align: center; 
    font-weight: 500; 
}

.error { 
    background: rgba(255,107,107,0.1); 
    color: #ff6b6b; 
    border: 1px solid rgba(255,107,107,0.3); 
}

.success { 
    background: rgba(78,205,196,0.1); 
    color: #4ecdc4; 
    border: 1px solid rgba(78,205,196,0.3); 
}

.register { 
    text-align: center; 
    padding-top: 20px; 
    border-top: 1px solid #eee; 
}

.register a { 
    color: #4ecdc4; 
    text-decoration: none; 
    font-weight: 500; 
}

.register a:hover { 
    text-decoration: underline; 
}

.password-toggle { 
    position: absolute; 
    right: 15px; 
    top: 50%; 
    transform: translateY(-50%); 
    cursor: pointer; 
    color: #999; 
    user-select: none; 
}

.password-field { 
    position: relative; 
}

@media (max-width: 768px) { 
    .container { 
        margin: 20px; 
        padding: 30px; 
    } 
    
    .logo h1 { 
        font-size: 2rem; 
    }
    
    .nav-bar { 
        padding: 10px 20px; 
    }
    
    .nav-links { 
        gap: 10px; 
    }
    
    .nav-link { 
        padding: 6px 12px; 
        font-size: 0.9rem; 
    }
}
</style>
</head>
<body>
<!-- Navigation Bar -->
<nav class="nav-bar">
    <div class="nav-content">
        <a href="about.php" class="nav-logo">
            🍽️ Adabina Restaurant
        </a>
        <div class="nav-links">
            <a href="about.php" class="nav-link home-btn">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="register.php" class="nav-link">
                <i class="fas fa-user-plus"></i> Register
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="logo">
        <h1>🍽️ Restaurant</h1>
        <p>Welcome back! Please sign in to your account</p>
    </div>

    <?php if ($error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
        <div class="form-group">
            <input type="email" name="email" id="email" class="form-input" placeholder="Email Address" 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <div class="password-field">
                <input type="password" name="password" id="password" class="form-input" placeholder="Password" required>
                <span class="password-toggle" onclick="togglePassword()">👁️</span>
            </div>
        </div>

        <button type="submit" class="btn" id="submitBtn">
            <span id="btnText"><i class="fas fa-sign-in-alt"></i> Sign In</span>
            <span id="loading" style="display:none;">Signing in...</span>
        </button>
    </form>

    <div class="register">
        <p>Don't have an account? <a href="register.php"><i class="fas fa-user-plus"></i> Create one here</a></p>
    </div>
</div>

<script>
function togglePassword() {
    const pwd = document.getElementById('password');
    const toggle = document.querySelector('.password-toggle');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        toggle.textContent = '🙈';
    } else {
        pwd.type = 'password';
        toggle.textContent = '👁️';
    }
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const loading = document.getElementById('loading');
    
    // Reset previous errors
    email.classList.remove('error');
    password.classList.remove('error');
    
    let hasError = false;
    
    // Validate email
    if (!email.value.trim()) {
        email.classList.add('error');
        email.focus();
        hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        email.classList.add('error');
        email.focus();
        hasError = true;
    }
    
    // Validate password
    if (!password.value.trim()) {
        password.classList.add('error');
        if (!hasError) password.focus();
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        return false;
    }
    
    // Show loading
    btnText.style.display = 'none';
    loading.style.display = 'inline';
    btn.disabled = true;
});

// Real-time validation
document.getElementById('email').addEventListener('input', function() {
    this.classList.remove('error');
});

document.getElementById('password').addEventListener('input', function() {
    this.classList.remove('error');
});
</script>
</body>
</html>
