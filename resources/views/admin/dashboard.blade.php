@extends('layouts.admin')

@section('title', 'Dashboard')
@section('header_title', 'Dashboard')

@section('extra_css')
<style>
    /* STAT CARDS */
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

    /* DASH GRID */
    .dash-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .panel-badge {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 99px;
        background: rgba(99,102,241,0.15);
        color: #a5b4fc;
    }

    /* STATUS BARS */
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

    /* LOGS */
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
</style>
@section('content')
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
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Recent Activity</span>
            </div>
            <div class="log-list">
                @forelse($recentLogs as $log)
                    <div class="log-item">
                        <div class="log-avatar">{{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}</div>
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

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Recent Projects</span>
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
@endsection
