import { useEffect, useRef, useState } from 'react';
import { session } from './api';

export type LiveStatus = 'polling' | 'connecting' | 'connected';

export function useProjectRealtime(
    projectId: number | null,
    onSignal: () => void,
): LiveStatus {
    const callback = useRef(onSignal);
    callback.current = onSignal;
    const [status, setStatus] = useState<LiveStatus>('polling');

    useEffect(() => {
        setStatus('polling');
        if (projectId === null) return;

        // Always retain a bounded fallback; a disconnected socket must not
        // leave the board permanently stale. Don't poll hidden tabs.
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') callback.current();
        }, 45_000);

        let active = true;
        let socket: { disconnect: () => void; leave: (name: string) => void } | null = null;
        const key = import.meta.env.VITE_REVERB_APP_KEY;
        const host = import.meta.env.VITE_REVERB_HOST;

        if (key && host) {
            setStatus('connecting');
            void (async () => {
                try {
                    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
                        import('laravel-echo'), import('pusher-js'),
                    ]);
                    const details = await session();
                    if (!active) return;
                    (window as Window & { Pusher?: typeof Pusher }).Pusher = Pusher;
                    const echo = new Echo({
                        broadcaster: 'reverb',
                        key,
                        wsHost: host,
                        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
                        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
                        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
                        enabledTransports: ['ws', 'wss'],
                        authEndpoint: '/broadcasting/auth',
                        auth: { headers: { 'X-CSRF-TOKEN': details.csrf_token } },
                    });
                    socket = echo;
                    echo.private('project.' + projectId)
                        .listen('.project.activity.changed', (payload: { project_id: number; activity_id: number }) => {
                            // Refetch only after an authorized project signal.
                            if (active && payload.project_id === projectId && payload.activity_id > 0) {
                                callback.current();
                            }
                        });
                    setStatus('connected');
                } catch {
                    if (active) setStatus('polling');
                }
            })();
        }

        return () => {
            active = false;
            window.clearInterval(interval);
            socket?.leave('project.' + projectId);
            socket?.disconnect();
        };
    }, [projectId]);

    return status;
}
