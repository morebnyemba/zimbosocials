<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Zimbo Socials - cPanel Web Installer
 *
 * Instructions:
 * 1. Upload your extracted Laravel files to `/home/username/my-app`.
 * 2. Upload the contents of the `public` folder (including this file) to `/home/username/public_html`.
 * 3. Visit `https://yourdomain.com/cpanel-installer.php` in your browser.
 */
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Default path assuming Laravel is one level above public_html in a folder named 'my-app'
$defaultAppPath = realpath(__DIR__.'/../my-app');
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

$existingEnv = [];
$envExists = false;
$envPath = $defaultAppPath.'/.env';
if (file_exists($envPath)) {
    $envExists = true;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $existingEnv[trim($parts[0])] = trim($parts[1], '"\'');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['install'])) {
        $appPath = rtrim($_POST['app_path'], '/');
        $appUrl = rtrim($_POST['app_url'], '/');

        $dbHost = $_POST['db_host'];
        $dbPort = $_POST['db_port'];
        $dbName = $_POST['db_name'];
        $dbUser = $_POST['db_user'];
        $dbPass = $_POST['db_pass'];

        $adminName = $_POST['admin_name'];
        $adminEmail = $_POST['admin_email'];
        $adminPass = $_POST['admin_pass'];
        $adminPhone = $_POST['admin_phone'];

        try {
            // 1. Verify Application Path
            if (! file_exists($appPath.'/artisan')) {
                throw new Exception("Laravel application not found at: {$appPath}. Ensure you uploaded the files to the correct folder.");
            }

            // 2. Test Database Connection
            $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // 3. Write .env file
            $envPath = $appPath.'/.env';
            if (! file_exists($envPath)) {
                if (file_exists($appPath.'/.env.example')) {
                    copy($appPath.'/.env.example', $envPath);
                } else {
                    throw new Exception("Neither .env nor .env.example found in {$appPath}.");
                }
            }

            $envContent = file_get_contents($envPath);

            // Helper to replace env variables
            $setEnv = function ($key, $value) use (&$envContent) {
                $value = preg_match('/\s/', $value) ? '"'.$value.'"' : $value;
                if (preg_match("/^{$key}=/m", $envContent)) {
                    $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
                } else {
                    $envContent .= "\n{$key}={$value}";
                }
            };

            $setEnv('APP_ENV', 'production');
            $setEnv('APP_DEBUG', 'false');
            $setEnv('APP_URL', $appUrl);
            $setEnv('DB_CONNECTION', 'mysql');
            $setEnv('DB_HOST', $dbHost);
            $setEnv('DB_PORT', $dbPort);
            $setEnv('DB_DATABASE', $dbName);
            $setEnv('DB_USERNAME', $dbUser);
            $setEnv('DB_PASSWORD', $dbPass);

            file_put_contents($envPath, $envContent);

            // 4. Boot Laravel to run Artisan commands
            require $appPath.'/vendor/autoload.php';
            $app = require_once $appPath.'/bootstrap/app.php';
            $kernel = $app->make(Kernel::class);

            // Run key:generate
            $kernel->call('key:generate', ['--force' => true]);

            // Run migrations
            $kernel->call('migrate', ['--force' => true]);

            // Create custom storage symlink for cPanel
            $target = $appPath.'/storage/app/public';
            $link = __DIR__.'/storage';
            if (! file_exists($link)) {
                symlink($target, $link);
            }

            // 5. Create Admin User
            $hashedPassword = password_hash($adminPass, PASSWORD_BCRYPT);

            // Check if admin exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$adminEmail]);
            if (! $stmt->fetch()) {
                $insert = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, whatsapp_number, email_verified_at, created_at, updated_at) 
                    VALUES (?, ?, ?, 'admin', ?, NOW(), NOW(), NOW())
                ");
                $insert->execute([$adminName, $adminEmail, $hashedPassword, $adminPhone]);
            } else {
                // Update existing user to admin
                $update = $pdo->prepare("UPDATE users SET role = 'admin', password = ? WHERE email = ?");
                $update->execute([$hashedPassword, $adminEmail]);
            }

            // 6. Optimize
            $kernel->call('optimize:clear');
            $kernel->call('view:cache');

            // Success!
            $success = 'Installation completed successfully!';
            $step = 2;

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if (isset($_POST['cleanup'])) {
        // Self-destruct for security
        unlink(__FILE__);
        header('Location: /');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zimbo Socials - Web Installer</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f4f4f5; color: #18181b; line-height: 1.5; margin: 0; padding: 40px 20px; }
        .container { max-w-2xl mx-auto; background: white; padding: 40px; border-radius: 16px; border: 1px solid #e4e4e7; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; font-size: 24px; font-weight: 900; }
        h2 { font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #71717a; border-bottom: 2px solid #f4f4f5; padding-bottom: 8px; margin-top: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #52525b; text-transform: uppercase; }
        input { width: 100%; padding: 10px 15px; border: 2px solid #e4e4e7; border-radius: 8px; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #10b981; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { display: inline-block; width: 100%; padding: 15px; background: #18181b; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: bold; text-transform: uppercase; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #27272a; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: bold; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }
        .note { font-size: 12px; color: #71717a; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Zimbo Socials Web Installer</h1>
    
    <?php if ($error) { ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php } ?>

    <?php if ($step == 1) { ?>
        <?php if ($envExists) { ?>
            <div class="alert" style="background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1;">
                ℹ️ <strong>Existing installation detected!</strong><br> The fields below have been pre-filled with your current configuration. Running the installer again will update your configuration, run any new database migrations, and update/create your admin account.
            </div>
        <?php } else { ?>
            <p style="color: #71717a; font-size: 14px;">Fill out the details below. This tool will automatically connect to your database, generate the environment file, run migrations, and create your admin account.</p>
        <?php } ?>
        
        <form method="POST">
            <h2>System Paths</h2>
            <div class="form-group">
                <label>App URL</label>
                <input type="url" name="app_url" value="<?= htmlspecialchars($existingEnv['APP_URL'] ?? 'https://'.$_SERVER['HTTP_HOST']) ?>" required>
            </div>
            <div class="form-group">
                <label>Laravel Core Path (Absolute path)</label>
                <input type="text" name="app_path" value="<?= htmlspecialchars($defaultAppPath ?: '/home/username/my-app') ?>" required>
                <div class="note">The folder where you uploaded the main application files (outside public_html).</div>
            </div>

            <h2>Database Credentials</h2>
            <div class="grid">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($existingEnv['DB_HOST'] ?? '127.0.0.1') ?>" required>
                </div>
                <div class="form-group">
                    <label>Database Port</label>
                    <input type="text" name="db_port" value="<?= htmlspecialchars($existingEnv['DB_PORT'] ?? '3306') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" value="<?= htmlspecialchars($existingEnv['DB_DATABASE'] ?? '') ?>" required placeholder="e.g. user_zimbo">
            </div>
            <div class="grid">
                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($existingEnv['DB_USERNAME'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="text" name="db_pass" value="<?= htmlspecialchars($existingEnv['DB_PASSWORD'] ?? '') ?>" required>
                </div>
            </div>

            <h2>Admin Account</h2>
            <div class="grid">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="admin_name" required placeholder="System Admin">
                </div>
                <div class="form-group">
                    <label>WhatsApp Number</label>
                    <input type="text" name="admin_phone" required placeholder="263771234567">
                </div>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="admin_email" required placeholder="admin@zimbosocials.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="admin_pass" required>
            </div>

            <button type="submit" name="install" class="btn btn-success" style="margin-top: 20px;">Run Installation</button>
        </form>

    <?php } elseif ($step == 2) { ?>
        <div class="alert alert-success"><?= $success ?></div>
        
        <p>The application has been successfully connected to the database, migrations have run, the storage link is created, and the admin account is ready.</p>

        <h3 style="color: #ef4444;">⚠️ CRITICAL SECURITY STEP</h3>
        <p>You must delete this installation script immediately to prevent anyone else from resetting your database.</p>
        
        <form method="POST">
            <button type="submit" name="cleanup" class="btn btn-danger">Delete Installer & Go to Website</button>
        </form>
    <?php } ?>
</div>

</body>
</html>
