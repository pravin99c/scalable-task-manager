import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { TaskForm } from './TaskForm';

describe('Task editor', () => {
    it('allows creation with task status and priority', async () => {
        const save = vi.fn().mockResolvedValue(undefined);
        render(<TaskForm task={null} members={null} canDelete={false} busy={false}
            onClose={vi.fn()} onSave={save} onDelete={vi.fn()} />);
        fireEvent.change(screen.getByLabelText('Task title'), { target: { value: 'Implement onboarding' } });
        fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'IN_PROGRESS' } });
        fireEvent.change(screen.getByLabelText('Priority'), { target: { value: 'HIGH' } });
        fireEvent.click(screen.getByRole('button', { name: 'Create task' }));
        await waitFor(() => expect(save).toHaveBeenCalledWith(expect.objectContaining({
            title: 'Implement onboarding', status: 'IN_PROGRESS', priority: 'HIGH',
        })));
    });
});
