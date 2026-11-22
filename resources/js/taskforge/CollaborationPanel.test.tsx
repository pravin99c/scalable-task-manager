import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from './api';
import { CollaborationPanel } from './CollaborationPanel';

const pager = {
    current_page: 1, per_page: 20, last_page: 1, total: 1, from: 1, to: 1,
};
const links = { first: '', last: '', prev: null, next: null };
const activity = {
    id: 11,
    project_id: 7,
    task_id: 21,
    action: 'updated' as const,
    title: 'Test task',
    actor: { id: 2, name: 'Teammate' },
    created_at: '2026-11-22T10:00:00Z',
};

describe('project collaboration UI', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('shows authorized project activity and notifications', async () => {
        vi.spyOn(api, 'activity').mockResolvedValue({
            data: [activity], meta: pager, links,
        });
        vi.spyOn(api, 'notifications').mockResolvedValue({
            data: [{
                id: 80,
                project_id: 7,
                read_at: null,
                activity: {
                    id: 11, task_id: 21, action: 'updated',
                    title: 'Test task', actor: { id: 2, name: 'Teammate' },
                    created_at: '2026-11-22T10:00:00Z',
                },
            }],
            meta: pager, links, unread_count: 1,
        });
        const onUnread = vi.fn();
        render(<CollaborationPanel projectId={7} refreshKey={0} onSelectProject={vi.fn()} onUnread={onUnread}/>);
        await waitFor(() => expect(screen.getByRole('heading', { name: /Activity & alerts/ })).toBeInTheDocument());
        await waitFor(() => expect(screen.getAllByText('Test task').length).toBe(2));
        expect(onUnread).toHaveBeenCalledWith(1);
    });

    it('can mark an accessible notification read', async () => {
        let read = false;
        vi.spyOn(api, 'activity').mockResolvedValue({ data: [], meta: {...pager,total:0}, links });
        vi.spyOn(api, 'notifications').mockImplementation(async () => ({
            data: [{
                id: 80, project_id: 7,
                read_at: read ? '2026-11-22T10:10:00Z' : null,
                activity: { id: 11, task_id: 21, action: 'updated', title: 'Test task', actor: null, created_at: '2026-11-22T10:00:00Z' },
            }],
            meta: pager, links, unread_count: read ? 0 : 1,
        }));
        const mark = vi.spyOn(api, 'markNotificationRead').mockImplementation(async () => {
            read = true;
            return { data: null, meta: null, links: null };
        });
        render(<CollaborationPanel projectId={7} refreshKey={0} onSelectProject={vi.fn()} onUnread={vi.fn()}/>);
        const button = await screen.findByRole('button', { name: 'Mark read' });
        fireEvent.click(button);
        await waitFor(() => expect(mark).toHaveBeenCalledWith(80));
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Mark read' })).not.toBeInTheDocument());
    });
});
