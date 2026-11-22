export type TaskStatus = 'TODO' | 'IN_PROGRESS' | 'DONE';
export type Priority = 'LOW' | 'MEDIUM' | 'HIGH';
export type TeamRole = 'owner' | 'editor' | 'viewer';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface Project {
    id: number;
    name: string;
    description: string | null;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface Task {
    id: number;
    project_id: number;
    title: string;
    description: string | null;
    status: TaskStatus;
    priority: Priority;
    due_date: string | null;
    assigned_to: number | null;
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface Member {
    id: number;
    name: string;
    email: string;
    role: 'editor' | 'viewer';
}

export interface MemberList {
    owner: Pick<User, 'id' | 'name' | 'email'>;
    members: Member[];
}

export interface Invitation {
    id: number;
    project_id: number;
    email: string;
    role: 'editor' | 'viewer';
    expires_at: string;
    accepted_at: string | null;
    revoked_at: string | null;
}

export interface PageMeta {
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface Envelope<T> {
    data: T;
    message?: string;
    errors?: Record<string, string[]>;
    meta: PageMeta | null;
    links: { first: string; last: string; prev: string | null; next: string | null } | null;
}

export type Paginated<T> = Envelope<T[]> & { meta: PageMeta };
export interface SessionResponse { data: User | null; csrf_token: string }
