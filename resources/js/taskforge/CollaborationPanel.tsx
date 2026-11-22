import { useCallback, useEffect, useState } from 'react';
import { Activity, Bell, CheckCheck, CircleAlert, LoaderCircle, RefreshCcw } from 'lucide-react';
import { api, firstError } from './api';
import type { ProjectActivity, ProjectNotification } from './types';

interface Props {
    projectId: number | null;
    onSelectProject: (id: number) => void;
    onUnread: (total: number) => void;
    refreshKey: number;
}

export function CollaborationPanel({ projectId, onSelectProject, onUnread, refreshKey }: Props) {
    const [activities, setActivities] = useState<ProjectActivity[]>([]);
    const [notifications, setNotifications] = useState<ProjectNotification[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [unread, setUnread] = useState(0);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        setError('');
        try {
            const [feed, alerts] = await Promise.all([
                projectId ? api.activity(projectId) : Promise.resolve(null),
                api.notifications(),
            ]);
            setActivities(feed?.data ?? []);
            setNotifications(alerts.data);
            setUnread(alerts.unread_count);
            onUnread(alerts.unread_count);
        } catch (reason) {
            setError(firstError(reason));
        } finally {
            setLoading(false);
        }
    }, [projectId, onUnread]);

    useEffect(() => { void load(); }, [load, refreshKey]);

    const markRead = async (id: number) => {
        if (busy) return;
        setBusy(true);
        try {
            await api.markNotificationRead(id);
            await load();
        } catch (reason) {
            setError(firstError(reason));
        } finally {
            setBusy(false);
        }
    };

    const markAll = async () => {
        if (busy || unread === 0) return;
        setBusy(true);
        try {
            await api.markAllNotificationsRead();
            await load();
        } catch (reason) {
            setError(firstError(reason));
        } finally {
            setBusy(false);
        }
    };

    return <section className="collaboration-page" aria-label="Activity and alerts">
        <div className="page-hero"><div><span className="eyebrow">LIVE COLLABORATION</span><h1>Activity & alerts</h1><p>Track work across your projects. Activity is visible only to authorized project members.</p></div>
            <button className="button button-secondary" type="button" onClick={() => void load()}><RefreshCcw size={16}/> Refresh</button>
        </div>
        {error && <div className="alert alert-error" role="alert"><CircleAlert size={16}/>{error}</div>}
        <div className="activity-layout">
            <div className="panel"><div className="panel-heading"><h3><Activity size={17}/> Project activity</h3></div>
                {!projectId && <p className="panel-empty">Select a project to see its activity history.</p>}
                {loading ? <p className="panel-empty"><LoaderCircle size={16} className="spin"/> Loading activity…</p> :
                    activities.length ? activities.map(activity=><div className="activity-row" key={activity.id}>
                        <span className="activity-dot"/><div><strong>{activity.actor?.name ?? 'Former teammate'} {activity.action} a task</strong>
                            <p>{activity.title}</p><small>{new Date(activity.created_at).toLocaleString()}</small></div>
                    </div>) : projectId && <p className="panel-empty">No task activity in this project yet.</p>}
            </div>
            <div className="panel"><div className="panel-heading"><h3><Bell size={17}/> Notifications <span className="count-badge">{unread} unread</span></h3>
                <button className="button button-ghost" type="button" onClick={() => void markAll()} disabled={busy || !unread}><CheckCheck size={15}/> Mark all read</button>
            </div>
                {loading ? <p className="panel-empty"><LoaderCircle size={16} className="spin"/> Loading notifications…</p> :
                    notifications.length ? notifications.map(note=><div className={'activity-row '+(!note.read_at ? 'unread' : '')} key={note.id}>
                        <span className="activity-dot"/><div><strong>{note.activity?.actor?.name ?? 'Former teammate'} {note.activity?.action ?? 'updated'} a task</strong>
                            <p>{note.activity?.title ?? 'Task activity'}</p>
                            <small>{note.activity?.created_at ? new Date(note.activity.created_at).toLocaleString() : ''}</small>
                            <div className="activity-actions">
                                <button type="button" className="button button-ghost" onClick={()=>onSelectProject(note.project_id)}>Open project</button>
                                {!note.read_at && <button type="button" className="button button-secondary" onClick={()=>void markRead(note.id)} disabled={busy}>Mark read</button>}
                            </div></div>
                    </div>) : <p className="panel-empty">You're all caught up. No new notifications.</p>}
            </div>
        </div>
    </section>;
}
