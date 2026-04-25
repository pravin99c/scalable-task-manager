<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function loginForm()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    /**
     * Handle admin login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials) && Auth::user()->role === 'admin') {
            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }

        Auth::logout();
        return back()->withErrors(['email' => 'Invalid credentials or insufficient permissions.']);
    }

    /**
     * Show the admin dashboard.
     */
    public function dashboard()
    {
        $stats = [
            'total_users'    => User::count(),
            'total_projects' => Project::count(),
            'total_tasks'    => Task::count(),
            'total_logs'     => ActivityLog::count(),
        ];

        $tasksByStatus = Task::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $tasksByPriority = Task::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $recentLogs = ActivityLog::with('user')
            ->latest()
            ->take(10)
            ->get();

        $recentProjects = Project::with('creator')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'tasksByStatus', 'tasksByPriority', 'recentLogs', 'recentProjects'
        ));
    }

    /**
     * Logout admin.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
