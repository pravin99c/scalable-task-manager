import type { Envelope, Invitation, MemberList, Paginated, Project, ProjectActivity, NotificationPage, SessionResponse, Task, User } from './types';

/**
 * Same-origin, cookie-based Laravel Sanctum client.
 * The CSRF token is held in memory, NOT in persistent browser storage.
 * All JSON mutation requests carry the Laravel web middleware CSRF token.
 */
let csrfToken: string | null = null;

export class ApiError extends Error {
    constructor(public status: number, message: string, public errors: Record<string, string[]> = {}) {
        super(message);
        this.name = 'ApiError';
    }
}

export function firstError(error: unknown): string {
    if (!(error instanceof ApiError)) return error instanceof Error ? error.message : 'Unexpected error. Please try again.';
    return Object.values(error.errors).flat()[0] || error.message;
}

export async function request<T>(path: string, options: RequestInit = {}, retryOnCsrf = true): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase();
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        if (!csrfToken) await session();
        if (csrfToken) headers.set('X-CSRF-TOKEN', csrfToken);
    }

    let response: Response;
    try {
        response = await fetch(path, {
            ...options,
            headers,
            credentials: 'same-origin',
            redirect: 'follow',
            cache: 'no-store',
        });
    } catch {
        throw new ApiError(0, 'Cannot reach the server. Check your connection.');
    }
    const text = await response.text();
    let payload: unknown;
    try { payload = text ? JSON.parse(text) as unknown : null; } catch { payload = null; }

    if (response.status === 419 && retryOnCsrf && path !== '/app/api/session') {
        // Laravel rejected the mutation before it reached a controller.
        clearCsrf();
        await session();
        return request<T>(path, options, false);
    }
    if (!response.ok) {
        const json = payload && typeof payload === 'object' ? payload as Record<string, unknown> : {};
        const errors = json.errors && typeof json.errors === 'object' && !Array.isArray(json.errors)
            ? json.errors as Record<string, string[]> : {};
        const message = typeof json.message === 'string' ? json.message :
            response.status === 401 ? 'Please sign in to continue.' :
            response.status === 403 ? 'You do not have permission for this action.' :
            response.status === 419 ? 'Your session expired. Refresh the page and try again.' :
            response.status === 429 ? 'Too many requests. Please try again shortly.' :
            'Request failed. Please try again.';
        throw new ApiError(response.status, message, errors);
    }

    if (!payload || typeof payload !== 'object') {
        throw new ApiError(response.status, 'Unexpected server response.');
    }
    // Laravel regenerates its CSRF token after login, registration and
    // logout. Replace the old token before the next protected request.
    if (path.startsWith('/app/api/') && 'csrf_token' in payload) {
        const token = (payload as { csrf_token?: unknown }).csrf_token;
        if (typeof token === 'string') csrfToken = token;
    }
    return payload as T;
}

export async function session(): Promise<SessionResponse> {
    const data = await request<SessionResponse>('/app/api/session');
    csrfToken = data.csrf_token;
    return data;
}

export function clearCsrf(): void {
    csrfToken = null;
}

export const api = {
    login: (email: string, password: string) =>
        request<SessionResponse>('/app/api/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
    register: (name: string, email: string, password: string, confirmation: string) =>
        request<SessionResponse>('/app/api/register', {
            method: 'POST', body: JSON.stringify({ name, email, password, password_confirmation: confirmation }),
        }),
    logout: () => request<SessionResponse>('/app/api/logout', { method: 'POST' }),
    me: () => request<User>('/api/v1/user'),
    projects: (page = 1, search = '') =>
        request<Paginated<Project>>('/api/v2/projects?' + new URLSearchParams({
            page: String(page), per_page: '15', ...(search.trim() ? { search: search.trim() } : {}),
        }).toString()),
    createProject: (name: string, description: string) =>
        request<Envelope<Project>>('/api/v2/projects', {
            method: 'POST', body: JSON.stringify({ name, description }),
        }),
    updateProject: (id: number, name: string, description: string) =>
        request<Envelope<Project>>('/api/v2/projects/' + id, {
            method: 'PATCH', body: JSON.stringify({ name, description }),
        }),
    deleteProject: (id: number) =>
        request<Envelope<null>>('/api/v2/projects/' + id, { method: 'DELETE' }),
    tasks: (projectId: number, page = 1) =>
        request<Paginated<Task>>('/api/v2/tasks?' + new URLSearchParams({
            project_id: String(projectId), page: String(page), per_page: '50',
        }).toString()),
    createTask: (task: { project_id: number; title: string; description?: string; priority?: string; status?: string; assigned_to?: number | null; due_date?: string | null }) =>
        request<Envelope<Task>>('/api/v2/tasks', { method: 'POST', body: JSON.stringify(task) }),
    updateTask: (id: number, task: Partial<Pick<Task, 'title' | 'description' | 'status' | 'priority' | 'due_date' | 'assigned_to'>>) =>
        request<Envelope<Task>>('/api/v2/tasks/' + id, { method: 'PATCH', body: JSON.stringify(task) }),
    deleteTask: (id: number) => request<Envelope<null>>('/api/v2/tasks/' + id, { method: 'DELETE' }),
    members: (projectId: number) => request<MemberList>('/api/v1/projects/' + projectId + '/members'),
    saveMember: (projectId: number, userId: number, role: 'editor' | 'viewer') =>
        request<{ message: string }>('/api/v1/projects/' + projectId + '/members', {
            method: 'POST', body: JSON.stringify({ user_id: userId, role }),
        }),
    removeMember: (projectId: number, userId: number) =>
        request<{ message: string }>('/api/v1/projects/' + projectId + '/members/' + userId, { method: 'DELETE' }),
    invitations: (projectId: number, page = 1) =>
        request<Paginated<Invitation>>('/api/v2/projects/' + projectId + '/invitations?page=' + page),
    invite: (projectId: number, email: string, role: 'editor' | 'viewer') =>
        request<Envelope<Invitation>>('/api/v2/projects/' + projectId + '/invitations', {
            method: 'POST', body: JSON.stringify({ email, role }),
        }),
    revokeInvite: (projectId: number, invitationId: number) =>
        request<Envelope<null>>('/api/v2/projects/' + projectId + '/invitations/' + invitationId, { method: 'DELETE' }),
    acceptInvite: (token: string) =>
        request<Envelope<{ project_id: number; role: string }>>('/api/v2/invitations/accept', {
            method: 'POST', body: JSON.stringify({ token }),
        }),
    resendVerification: () =>
        request<Envelope<null>>('/api/v2/auth/email/verification/send', { method: 'POST' }),
    verifyEmail: (token: string) =>
        request<Envelope<{ email_verified: boolean }>>('/api/v2/auth/email/verify', {
            method: 'POST', body: JSON.stringify({ token }),
        }),
    changeEmail: (email: string, password: string) =>
        request<Envelope<null>>('/api/v2/auth/email', {
            method: 'PATCH', body: JSON.stringify({ email, current_password: password }),
        }),
    activity: (projectId: number, page = 1) =>
        request<Paginated<ProjectActivity>>('/api/v2/projects/' + projectId + '/activity?page=' + page),
    notifications: (page = 1) => request<NotificationPage>('/api/v2/notifications?page=' + page),
    markNotificationRead: (id: number) =>
        request<Envelope<null>>('/api/v2/notifications/' + id + '/read', { method: 'PATCH' }),
    markAllNotificationsRead: () =>
        request<Envelope<null>>('/api/v2/notifications/read-all', { method: 'PATCH' }),
};
