<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\Comment;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Check if user has required permission.
     */
    private function checkPermission($permission)
    {
        if (!Auth::user()->hasPermission($permission)) {
            abort(403, 'Unauthorized action. Required permission: ' . $permission);
        }
    }

    /**
     * Show the admin login form.
     */
    public function loginForm()
    {
        if (Auth::check() && in_array(Auth::user()->role, ['superadmin', 'admin'])) {
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

        if (Auth::attempt($credentials) && in_array(Auth::user()->role, ['superadmin', 'admin'])) {
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
     * List all users.
     */
    public function userIndex()
    {
        $this->checkPermission('manage_users');
        $users = User::with('roles')
            ->whereDoesntHave('roles', function($q) {
                $q->where('slug', 'superadmin');
            })
            ->latest()
            ->paginate(10);
        return view('admin.users.index', compact('users'));
    }

    public function userCreate()
    {
        $this->checkPermission('manage_users');
        $roles = \App\Models\Role::where('slug', '!=', 'superadmin')->get();
        return view('admin.users.form', ['user' => new User(), 'roles' => $roles]);
    }

    public function userStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'roles' => 'required|array',
        ]);
        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);
        $user->roles()->sync($request->roles);
        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function userEdit(User $user)
    {
        if ($user->hasRole('superadmin')) {
            abort(403, 'Super Admin accounts cannot be modified via UI.');
        }
        $roles = \App\Models\Role::where('slug', '!=', 'superadmin')->get();
        return view('admin.users.form', compact('user', 'roles'));
    }

    public function userUpdate(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'roles' => 'required|array',
        ]);
        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }
        $user->update($data);
        $user->roles()->sync($request->roles);
        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function userDestroy(User $user)
    {
        $this->checkPermission('manage_users');
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    /**
     * List all roles.
     */
    public function roleIndex()
    {
        $this->checkPermission('manage_roles');
        $roles = Role::with('permissions')->where('slug', '!=', 'superadmin')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function roleCreate()
    {
        $this->checkPermission('manage_roles');
        $permissions = Permission::all();
        return view('admin.roles.form', ['role' => new Role(), 'permissions' => $permissions]);
    }

    public function roleStore(Request $request)
    {
        $this->checkPermission('manage_roles');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:roles,slug',
            'description' => 'nullable|string',
            'permissions' => 'required|array'
        ]);
        $role = Role::create($data);
        $role->permissions()->sync($request->permissions);
        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function roleEdit(Role $role)
    {
        $this->checkPermission('manage_roles');
        if ($role->slug === 'superadmin') {
            abort(403, 'The Super Admin role is protected and cannot be edited.');
        }
        $permissions = Permission::all();
        return view('admin.roles.form', compact('role', 'permissions'));
    }

    public function roleUpdate(Request $request, Role $role)
    {
        $this->checkPermission('manage_roles');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:roles,slug,' . $role->id,
            'description' => 'nullable|string',
            'permissions' => 'required|array'
        ]);
        $role->update($data);
        $role->permissions()->sync($request->permissions);
        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function roleDestroy(Role $role)
    {
        $this->checkPermission('manage_roles');
        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }

    /**
     * List all projects.
     */
    public function projectIndex()
    {
        $projects = Project::with('creator')->latest()->paginate(10);
        return view('admin.projects.index', compact('projects'));
    }

    public function projectCreate()
    {
        return view('admin.projects.form', ['project' => new Project()]);
    }

    public function projectStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $data['creator_id'] = auth()->id();
        Project::create($data);
        return redirect()->route('admin.projects.index')->with('success', 'Project created successfully.');
    }

    public function projectEdit(Project $project)
    {
        return view('admin.projects.form', compact('project'));
    }

    public function projectUpdate(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $project->update($data);
        return redirect()->route('admin.projects.index')->with('success', 'Project updated successfully.');
    }

    public function projectDestroy(Project $project)
    {
        $project->delete();
        return redirect()->route('admin.projects.index')->with('success', 'Project deleted.');
    }

    /**
     * List all tasks.
     */
    public function taskIndex()
    {
        $tasks = Task::with(['project', 'creator'])->latest()->paginate(10);
        return view('admin.tasks.index', compact('tasks'));
    }

    public function taskCreate()
    {
        $projects = Project::all();
        $users = User::all();
        return view('admin.tasks.form', [
            'task' => new Task(),
            'projects' => $projects,
            'users' => $users
        ]);
    }

    public function taskStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'required|exists:projects,id',
            'status' => 'required|in:TODO,IN_PROGRESS,DONE',
            'priority' => 'required|in:LOW,MEDIUM,HIGH',
            'due_date' => 'nullable|date',
        ]);
        $data['creator_id'] = auth()->id();
        Task::create($data);
        return redirect()->route('admin.tasks.index')->with('success', 'Task created.');
    }

    public function taskEdit(Task $task)
    {
        $projects = Project::all();
        $users = User::all();
        return view('admin.tasks.form', compact('task', 'projects', 'users'));
    }

    public function taskUpdate(Request $request, Task $task)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|in:TODO,IN_PROGRESS,DONE',
            'priority' => 'required|in:LOW,MEDIUM,HIGH',
            'due_date' => 'nullable|date',
        ]);
        $task->update($data);
        return redirect()->route('admin.tasks.index')->with('success', 'Task updated.');
    }

    public function taskDestroy(Task $task)
    {
        $task->delete();
        return redirect()->route('admin.tasks.index')->with('success', 'Task deleted.');
    }

    /**
     * List all comments.
     */
    public function comments()
    {
        $comments = Comment::with(['task', 'user'])->latest()->paginate(10);
        return view('admin.comments.index', compact('comments'));
    }

    /**
     * List activity logs.
     */
    public function logs()
    {
        $this->checkPermission('view_logs');
        $logs = ActivityLog::with('user')->latest()->paginate(15);
        return view('admin.logs.index', compact('logs'));
    }

    /**
     * System settings.
     */
    public function settings()
    {
        $this->checkPermission('manage_settings');
        $settings = Settings::pluck('value', 'key');
        return view('admin.settings.index', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $this->checkPermission('manage_settings');
        
        $data = $request->except('_token');
        
        // Handle checkbox (if missing, set to 0)
        if (!$request->has('maintenance_mode')) {
            $data['maintenance_mode'] = '0';
        }

        foreach ($data as $key => $value) {
            Settings::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return redirect()->route('admin.settings.index')->with('success', 'Settings updated successfully.');
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
