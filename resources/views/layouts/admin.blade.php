<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard') — TaskForge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0a0e1a;
            --surface: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
            --text: rgba(255,255,255,0.9);
            --muted: rgba(255,255,255,0.4);
            --accent: #6366f1;
            --accent-2: #10b981;
            --accent-3: #f59e0b;
            --accent-4: #ef4444;
            --sidebar-w: 260px;
        }

        html { font-size: 15px; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* ---- SIDEBAR ---- */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: rgba(255,255,255,0.025);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            backdrop-filter: blur(24px);
        }

        .sidebar-brand {
            padding: 1.6rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(99,102,241,0.4);
        }

        .brand-name { font-size: 1.1rem; font-weight: 700; color: #fff; letter-spacing: -0.3px; }
        .brand-role { font-size: 0.7rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1.5px; }

        .sidebar-nav { flex: 1; padding: 1.25rem 0.75rem; }

        .nav-section-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: rgba(255,255,255,0.2);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0.5rem 0.75rem;
            margin-top: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.18s;
            margin-bottom: 2px;
        }

        .nav-link:hover { background: rgba(255,255,255,0.06); color: #fff; }
        .nav-link.active { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .nav-link .icon { font-size: 1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--border);
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem;
            border-radius: 10px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
        }

        .avatar {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, #6366f1, #10b981);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700; color: #fff;
            flex-shrink: 0;
        }

        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-size: 0.8rem; font-weight: 600; color: #fff; truncate: clip; }
        .user-role { font-size: 0.7rem; color: var(--muted); }

        /* ---- MAIN ---- */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ---- TOPBAR ---- */
        .topbar {
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(10,14,26,0.8);
            backdrop-filter: blur(20px);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .page-title h1 { font-size: 1.2rem; font-weight: 700; color: #fff; }
        .page-title p  { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

        .topbar-actions { display: flex; align-items: center; gap: 0.75rem; }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            padding: 0.65rem 1.4rem;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            line-height: 1;
        }
        .logout-btn .icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); transform: translateY(-1px); }

        /* ---- CONTENT ---- */
        .content { padding: 2rem; flex: 1; }

        /* Shared Styles for Other Pages */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .panel-header {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-title { font-size: 0.9rem; font-weight: 600; color: #fff; }

        .table { width: 100%; border-collapse: collapse; }
        .table th {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 0.75rem 1.4rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .table td {
            padding: 0.8rem 1.4rem;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.75);
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: rgba(255,255,255,0.02); }

        .proj-name { font-weight: 600; color: #fff; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge.todo { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .badge.in_progress { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .badge.done { background: rgba(16,185,129,0.15); color: #6ee7b7; }
        .badge.low { background: rgba(107,114,128,0.15); color: #9ca3af; }
        .badge.medium { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .badge.high { background: rgba(239,68,68,0.15); color: #fca5a5; }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
            font-size: 0.85rem;
        }

        /* Pagination Styling */
        .pagination {
            display: flex;
            list-style: none;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: center;
        }
        .pagination li a, .pagination li span {
            padding: 0.5rem 0.8rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: var(--muted);
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        .pagination li.active span {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .pagination li.disabled span {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Alerts */
        .alert {
            padding: 1.25rem 1.75rem;
            border-radius: 14px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #10b981; border: 1px solid #34d399; color: #fff; }
        .alert-danger  { background: #ef4444; border: 1px solid #f87171; color: #fff; }

        @yield('extra_css')
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">⚡</div>
        <div>
            <div class="brand-name">TaskForge</div>
            <div class="brand-role">Admin Control</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="icon">📊</span> Dashboard
        </a>

        <div class="nav-section-label">Management</div>
        @if(auth()->user()->role === 'superadmin')
        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <span class="icon">👥</span> Users
        </a>
        <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <span class="icon">🛡️</span> Roles
        </a>
        @endif
        <a href="{{ route('admin.projects.index') }}" class="nav-link {{ request()->routeIs('admin.projects.*') ? 'active' : '' }}">
            <span class="icon">📁</span> Projects
        </a>
        <a href="{{ route('admin.tasks.index') }}" class="nav-link {{ request()->routeIs('admin.tasks.*') ? 'active' : '' }}">
            <span class="icon">✅</span> Tasks
        </a>
        <a href="{{ route('admin.comments.index') }}" class="nav-link {{ request()->routeIs('admin.comments.*') ? 'active' : '' }}">
            <span class="icon">💬</span> Comments
        </a>

        @if(auth()->user()->role === 'superadmin')
        <div class="nav-section-label">System</div>
        <a href="{{ route('admin.logs.index') }}" class="nav-link {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">
            <span class="icon">📋</span> Activity Logs
        </a>
        <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <span class="icon">⚙️</span> Settings
        </a>
        @endif
    </nav>

    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <div class="user-info">
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="page-title">
            <h1>@yield('header_title')</h1>
            <p>@yield('header_subtitle', now()->format('l, F j, Y'))</p>
        </div>
        <div class="topbar-actions">
            <form method="POST" action="{{ route('admin.logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="logout-btn">
                    <span class="icon-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </span>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </header>

    <div class="content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin-left: 1rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </div>
</main>

@yield('extra_js')
</body>
</html>
