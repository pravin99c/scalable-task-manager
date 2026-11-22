import { useEffect, useState, type FormEvent } from 'react';
import { X } from 'lucide-react';
import { useDialogAccessibility } from './useDialogAccessibility';
import type { MemberList, Priority, Task, TaskStatus } from './types';

export interface TaskDraft {
    title: string;
    description: string;
    status: TaskStatus;
    priority: Priority;
    due_date: string | null;
    assigned_to: number | null;
}

interface Props {
    task: Task | null;
    members: MemberList | null;
    canDelete: boolean;
    busy: boolean;
    onClose: () => void;
    onSave: (draft: TaskDraft) => Promise<void>;
    onDelete: (task: Task) => Promise<void>;
}

export function TaskForm({ task, members, canDelete, busy, onClose, onSave, onDelete }: Props) {
    const dialogRef = useDialogAccessibility<HTMLElement>(true, onClose, busy);
    const [title, setTitle] = useState(task?.title ?? '');
    const [description, setDescription] = useState(task?.description ?? '');
    const [status, setStatus] = useState<TaskStatus>(task?.status ?? 'TODO');
    const [priority, setPriority] = useState<Priority>(task?.priority ?? 'MEDIUM');
    const [due, setDue] = useState(task?.due_date?.slice(0, 10) ?? '');
    const [assignee, setAssignee] = useState(task?.assigned_to?.toString() ?? '');

    useEffect(() => { setTitle(task?.title ?? ''); setDescription(task?.description ?? ''); setStatus(task?.status ?? 'TODO'); setPriority(task?.priority ?? 'MEDIUM'); setDue(task?.due_date?.slice(0,10) ?? ''); setAssignee(task?.assigned_to?.toString() ?? ''); }, [task]);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        await onSave({
            title: title.trim(),
            description: description.trim(),
            status,
            priority,
            due_date: due || null,
            assigned_to: assignee ? Number(assignee) : null,
        });
    };

    const editors = members?.members.filter(m => m.role === 'editor') ?? [];
    return (
        <div className="modal-backdrop" role="presentation" onMouseDown={e=>{ if (e.target===e.currentTarget) onClose(); }}>
            <section ref={dialogRef} tabIndex={-1} className="modal-panel" role="dialog" aria-modal="true" aria-labelledby="task-modal-heading">
                <header className="modal-heading">
                    <div><span className="eyebrow">TASK DETAILS</span><h2 id="task-modal-heading">{task ? 'Edit task' : 'Create a task'}</h2></div>
                    <button className="icon-button" onClick={onClose} aria-label="Close" type="button"><X size={19}/></button>
                </header>
                <form className="stack-form" onSubmit={submit}>
                    <label>Task title<input autoFocus value={title} onChange={e=>setTitle(e.target.value)} required maxLength={255} placeholder="What needs to be done?"/></label>
                    <label>Description<textarea value={description} onChange={e=>setDescription(e.target.value)} rows={4} placeholder="Add context, notes or a brief…"/></label>
                    <div className="form-grid">
                        <label>Status<select value={status} onChange={e=>setStatus(e.target.value as TaskStatus)}><option value="TODO">To do</option><option value="IN_PROGRESS">In progress</option><option value="DONE">Done</option></select></label>
                        <label>Priority<select value={priority} onChange={e=>setPriority(e.target.value as Priority)}><option value="LOW">Low</option><option value="MEDIUM">Medium</option><option value="HIGH">High</option></select></label>
                        <label>Due date<input type="date" value={due} onChange={e=>setDue(e.target.value)}/></label>
                        <label>Assignee<select value={assignee} onChange={e=>setAssignee(e.target.value)}><option value="">Unassigned</option>{members?.owner && <option value={members.owner.id}>Owner: {members.owner.name}</option>}{editors.map(m=><option key={m.id} value={m.id}>{m.name}</option>)}</select></label>
                    </div>
                    <div className="modal-actions">
                        {task && canDelete && <button className="button button-danger" type="button" disabled={busy} onClick={()=>onDelete(task)}>Delete task</button>}
                        <span className="grow"/>
                        <button className="button button-ghost" type="button" onClick={onClose} disabled={busy}>Cancel</button>
                        <button className="button button-primary" type="submit" disabled={busy || !title.trim()}>{busy ? 'Saving…' : task ? 'Save changes' : 'Create task'}</button>
                    </div>
                </form>
            </section>
        </div>
    );
}
