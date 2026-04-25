<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskForge Admin — Dashboard</title>
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
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.18s;
            text-decoration: none;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); }

        /* ---- CONTENT ---- */
        .content { padding: 2rem; flex: 1; }

        /* ---- STAT CARDS ---- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.4rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
        }

        .stat-card.purple::before { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
        .stat-card.green::before  { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-card.amber::before  { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-card.red::before    { background: linear-gradient(90deg, #ef4444, #f87171); }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 1rem;
        }

        .stat-card.purple .stat-icon { background: rgba(99,102,241,0.15); }
        .stat-card.green .stat-icon  { background: rgba(16,185,129,0.15); }
        .stat-card.amber .stat-icon  { background: rgba(245,158,11,0.15); }
        .stat-card.red .stat-icon    { background: rgba(239,68,68,0.15); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            line-height: 1;
            margin-bottom: 0.4rem;
        }

        .stat-label { font-size: 0.78rem; color: var(--muted); font-weight: 500; }

        /* ---- GRID ---- */
        .dash-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .panel-header {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-title { font-size: 0.9rem; font-weight: 600; color: #fff; }
        .panel-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
            background: rgba(99,102,241,0.15);
            color: #a5b4fc;
        }

        /* ---- STATUS BARS ---- */
        .status-list { padding: 1.2rem 1.4rem; }

        .status-item { margin-bottom: 1.1rem; }
        .status-item:last-child { margin-bottom: 0; }

        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.45rem;
        }

        .status-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.82rem;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
        }

        .dot { width: 8px; height: 8px; border-radius: 50%; }
        .dot.todo { background: #6366f1; }
        .dot.in_progress { background: #f59e0b; }
        .dot.done { background: #10b981; }
        .dot.low { background: #6b7280; }
        .dot.medium { background: #3b82f6; }
        .dot.high { background: #ef4444; }

        .status-count { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.6); }

        .bar-track {
            height: 6px;
            background: rgba(255,255,255,0.06);
            border-radius: 99px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            border-radius: 99px;
            transition: width 0.8s ease;
        }

        .bar-fill.todo { background: linear-gradient(90deg, #6366f1, #818cf8); }
        .bar-fill.in_progress { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .bar-fill.done { background: linear-gradient(90deg, #10b981, #34d399); }
        .bar-fill.low { background: linear-gradient(90deg, #6b7280, #9ca3af); }
        .bar-fill.medium { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .bar-fill.high { background: linear-gradient(90deg, #ef4444, #f87171); }

        /* ---- ACTIVITY LOG ---- */
        .log-list { padding: 0; }

        .log-item {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            padding: 0.85rem 1.4rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            transition: background 0.15s;
        }

        .log-item:last-child { border-bottom: none; }
        .log-item:hover { background: rgba(255,255,255,0.02); }

        .log-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #10b981);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: #fff;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .log-body { flex: 1; }
        .log-action { font-size: 0.8rem; color: rgba(255,255,255,0.75); line-height: 1.4; }
        .log-action strong { color: #fff; font-weight: 600; }
        .log-time { font-size: 0.7rem; color: var(--muted); margin-top: 3px; }

        .action-chip {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 99px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .action-chip.created { background: rgba(16,185,129,0.15); color: #6ee7b7; }
        .action-chip.updated { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .action-chip.deleted { background: rgba(239,68,68,0.15); color: #fca5a5; }

        /* ---- PROJECTS TABLE ---- */
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

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
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
        <a href="{{ route('admin.dashboard') }}" class="nav-link active">
            <span class="icon">📊</span> Dashboard
        </a>

        <div class="nav-section-label">Management</div>
        <a href="#" class="nav-link"><span class="icon">👥</span> Users</a>
        <a href="#" class="nav-link"><span class="icon">📁</span> Projects</a>
        <a href="#" class="nav-link"><span class="icon">✅</span> Tasks</a>
        <a href="#" class="nav-link"><span class="icon">💬</span> Comments</a>

        <div class="nav-section-label">System</div>
        <a href="#" class="nav-link"><span class="icon">📋</span> Activity Logs</a>
        <a href="#" class="nav-link"><span class="icon">⚙️</span> Settings</a>
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

<!-- MAIN -->
<main class="main">
    <!-- TOPBAR -->
    <header class="topbar">
        <div class="page-title">
            <h1>Dashboard</h1>
            <p>{{ now()->format('l, F j, Y') }}</p>
        </div>
        <div class="topbar-actions">
            <form method="POST" action="{{ route('admin.logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="logout-btn">↩ Logout</button>
            </form>
        </div>
    </header>

    <!-- CONTENT -->
    <div class="content">

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon">👥</div>
                <div class="stat-value">{{ $stats['total_users'] }}</div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">📁</div>
                <div class="stat-value">{{ $stats['total_projects'] }}</div>
                <div class="stat-label">Total Projects</div>
            </div>
            <div class="stat-card amber">
                <div class="stat-icon">✅</div>
                <div class="stat-value">{{ $stats['total_tasks'] }}</div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon">📋</div>
                <div class="stat-value">{{ $stats['total_logs'] }}</div>
                <div class="stat-label">Activity Logs</div>
            </div>
        </div>

        <!-- MIDDLE ROW -->
        <div class="dash-grid">

            <!-- Task Status Breakdown -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">Tasks by Status</span>
                    <span class="panel-badge">{{ $stats['total_tasks'] }} total</span>
                </div>
                <div class="status-list">
                    @php $total = max($stats['total_tasks'], 1); @endphp
                    @foreach(['TODO' => 'todo', 'IN_PROGRESS' => 'in_progress', 'DONE' => 'done'] as $label => $cls)
                        @php $count = $tasksByStatus[$label] ?? 0; @endphp
                        <div class="status-item">
                            <div class="status-row">
                                <span class="status-name">
                                    <span class="dot {{ $cls }}"></span>
                                    {{ str_replace('_', ' ', $label) }}
                                </span>
                                <span class="status-count">{{ $count }}</span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill {{ $cls }}" style="width: {{ round(($count/$total)*100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Task Priority Breakdown -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">Tasks by Priority</span>
                </div>
                <div class="status-list">
                    @foreach(['LOW' => 'low', 'MEDIUM' => 'medium', 'HIGH' => 'high'] as $label => $cls)
                        @php $count = $tasksByPriority[$label] ?? 0; @endphp
                        <div class="status-item">
                            <div class="status-row">
                                <span class="status-name">
                                    <span class="dot {{ $cls }}"></span> {{ $label }}
                                </span>
                                <span class="status-count">{{ $count }}</span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill {{ $cls }}" style="width: {{ round(($count/$total)*100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- BOTTOM ROW -->
        <div class="dash-grid">

            <!-- Recent Activity -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">Recent Activity</span>
                    <span class="panel-badge">Last 10</span>
                </div>
                <div class="log-list">
                    @forelse($recentLogs as $log)
                        <div class="log-item">
                            <div class="log-avatar">
                                {{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}
                            </div>
                            <div class="log-body">
                                <div class="log-action">
                                    <strong>{{ $log->user?->name ?? 'System' }}</strong>
                                    <span class="action-chip {{ strtolower($log->action) }}">{{ $log->action }}</span>
                                    a task
                                </div>
                                <div class="log-time">{{ $log->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No activity recorded yet.</div>
                    @endforelse
                </div>
            </div>

            <!-- Recent Projects -->
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">Recent Projects</span>
                    <span class="panel-badge">Last 5</span>
                </div>
                @if($recentProjects->isNotEmpty())
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Created By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentProjects as $project)
                        <tr>
                            <td><span class="proj-name">{{ $project->name }}</span></td>
                            <td>{{ $project->creator?->name ?? '—' }}</td>
                            <td>{{ $project->created_at->format('M j, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="empty-state">No projects yet.</div>
                @endif
            </div>

        </div>
    </div><!-- /content -->
</main>

</body>
</html>
