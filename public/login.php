<?php
/**
 * Login Page
 */

require_once '../includes/bootstrap.php';

// Redirect if already logged in
if (Session::isLoggedIn()) {
    if (Session::isAdmin()) {
        redirect(BASE_URL . '/modules/admin/work_orders.php');
    } else {
        redirect(BASE_URL . '/modules/mechanic/work_orders.php');
    }
}

$error = '';
$username = '';

if (isPost()) {
    $username = sanitize(post('username'));
    $password = post('password');
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        $userModel = new User();
        $user = $userModel->authenticate($username, $password);
        
        if ($user) {
            Session::setUserData($user);
            Session::regenerate();
            
            // Redirect based on role
            if ($user['role_id'] == ROLE_ADMIN) {
                redirect(BASE_URL . '/modules/admin/work_orders.php');
            } else {
                redirect(BASE_URL . '/modules/mechanic/work_orders.php');
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/css/style.css')); ?>">
    <style>
        :root {
            --login-bg-start: #eef3fb;
            --login-bg-end: #dfe9f9;
            --login-accent: #214f97;
            --login-accent-dark: #173a70;
            --login-text: #1f2937;
            --login-muted: #64748b;
            --login-border: #d9e2ef;
            --login-card: #ffffff;
            --login-shadow: 0 20px 40px rgba(16, 34, 67, 0.15);
        }

        body.login-page {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            color: var(--login-text);
            background: linear-gradient(145deg, var(--login-bg-start) 0%, var(--login-bg-end) 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-shell {
            width: min(960px, 100%);
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            background: var(--login-card);
            border: 1px solid var(--login-border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--login-shadow);
        }

        .login-brand {
            background: linear-gradient(160deg, #1f4a8f 0%, #143463 100%);
            color: #fff;
            padding: 46px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-pill {
            display: inline-block;
            width: fit-content;
            padding: 6px 10px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            font-size: 12px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 18px;
            background: rgba(255, 255, 255, 0.12);
        }

        .login-brand h1 {
            margin: 0 0 10px;
            font-size: 34px;
            line-height: 1.15;
            font-weight: 700;
        }

        .login-brand p {
            margin: 0;
            font-size: 15px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.88);
        }

        .login-container {
            max-width: none;
            margin: 0;
            border: 0;
            box-shadow: none;
            background: transparent;
        }

        .login-body {
            padding: 40px 36px;
        }

        .login-heading h2 {
            margin: 0;
            font-size: 27px;
            font-weight: 700;
            color: #0f274a;
        }

        .login-heading p {
            margin: 8px 0 26px;
            color: var(--login-muted);
            font-size: 14px;
        }

        .login-body .form-group {
            margin-bottom: 18px;
        }

        .login-body label {
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            display: block;
        }

        .login-body input[type="text"],
        .login-body input[type="password"] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #c9d5e6;
            border-radius: 10px;
            background: #f8fbff;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .login-body input[type="text"]:focus,
        .login-body input[type="password"]:focus {
            outline: none;
            border-color: var(--login-accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(33, 79, 151, 0.15);
        }

        .login-body .alert {
            margin-bottom: 18px;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13px;
        }

        .btn.btn-login {
            width: 100%;
            margin-right: 0;
            margin-top: 6px;
            padding: 11px 16px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            color: #fff;
            border: 1px solid var(--login-accent-dark);
            background: linear-gradient(180deg, var(--login-accent) 0%, var(--login-accent-dark) 100%);
        }

        .btn.btn-login:hover {
            background: linear-gradient(180deg, #2a5baa 0%, #1b427f 100%);
        }

        .login-footer-note {
            margin-top: 14px;
            color: #7a8798;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 860px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .login-brand {
                padding: 28px 30px;
            }

            .login-brand h1 {
                font-size: 28px;
            }

            .login-body {
                padding: 28px 24px;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-shell">
        <div class="login-brand">
            <span class="brand-pill">AutoShop Portal</span>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Sign in to manage work orders, update service progress, and keep your shop operations running smoothly.</p>
        </div>
        <div class="login-container">
            <div class="login-body">
                <div class="login-heading">
                    <h2>Welcome Back</h2>
                    <p>Please enter your credentials to continue.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php csrfField(); ?>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?php echo e($username); ?>"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-login">Sign In</button>
                    </div>
                </form>

                <p class="login-footer-note">Secure staff access only.</p>
            </div>
        </div>
    </div>
</body>
</html>
