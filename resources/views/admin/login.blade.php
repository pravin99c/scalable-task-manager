<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskForge Admin — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #0a0e1a;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 20%, rgba(99, 102, 241, 0.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 80%, rgba(16, 185, 129, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 60% at 60% 10%, rgba(236, 72, 153, 0.08) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* Floating grid */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(99,102,241,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 1rem;
            animation: fadeUp 0.6s ease-out;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 2.5rem;
            backdrop-filter: blur(20px);
            box-shadow:
                0 32px 64px rgba(0,0,0,0.4),
                0 0 0 1px rgba(99,102,241,0.1) inset;
        }

        .logo-area {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(99,102,241,0.35);
        }

        .logo-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .logo-subtitle {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.35);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 4px;
        }

        h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            margin-bottom: 0.25rem;
        }

        p.sub {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.35);
            margin-bottom: 1.75rem;
        }

        .field {
            margin-bottom: 1.1rem;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(255,255,255,0.55);
            margin-bottom: 0.4rem;
            letter-spacing: 0.3px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: rgba(99,102,241,0.6);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
            background: rgba(99,102,241,0.06);
        }

        input::placeholder { color: rgba(255,255,255,0.2); }

        .error-msg {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 0.85rem 1rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 16px rgba(99,102,241,0.35);
            letter-spacing: 0.3px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99,102,241,0.5);
        }

        .btn:active { transform: translateY(0); }

        .footer-note {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.2);
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.3);
            color: #6ee7b7;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 6px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="card">
            <div class="logo-area">
                <div class="logo-icon">⚡</div>
                <div class="logo-title">TaskForge</div>
                <div class="logo-subtitle">Admin Panel</div>
            </div>

            <h2>Welcome back <span class="badge">Admin</span></h2>
            <p class="sub">Sign in to access the control panel</p>

            @if($errors->any())
                <div class="error-msg">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="admin@taskforge.app"
                           value="{{ old('email') }}" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn">Sign In to Dashboard</button>
            </form>

            <div class="footer-note">TaskForge © {{ date('Y') }} · Admin Access Only</div>
        </div>
    </div>
</body>
</html>
