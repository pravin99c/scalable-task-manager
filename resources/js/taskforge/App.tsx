import { useEffect, useMemo, useState, type DragEvent, type FormEvent } from 'react';
import { Activity, ArrowRight, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, CircleAlert, FolderKanban, FolderPlus, GripVertical, Inbox, Layers3, LayoutDashboard, ListTodo, LoaderCircle, LogOut, Mail, Menu, MoreHorizontal, Plus, RefreshCcw, Search, Settings2, ShieldCheck, Sparkles, Trash2, Users, X } from 'lucide-react';
import { ApiError, api, clearCsrf, firstError, session } from './api';
import { AuthScreen } from './AuthScreen';
import { TaskForm, type TaskDraft } from './TaskForm';
import type { Invitation, MemberList, PageMeta, Priority, Project, SessionResponse, Task, TaskStatus, User } from './types';

type View = 'board' | 'team' | 'account';
const columns: { id: TaskStatus; name: string; subtitle: string }[] = [
    { id: 'TODO', name: 'To do', subtitle: 'Ready to start' },
    { id: 'IN_PROGRESS', name: 'In progress', subtitle: 'Moving forward' },
    { id: 'DONE', name: 'Done', subtitle: 'Completed work' },
];
const dateLabel = (value: string | null) => value ? new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';

function Avatar({ name, small = false }: { name: string; small?: boolean }) {
    return <span className={'avatar '+(small?'avatar-sm':'')} title={name}>{name.split(' ').slice(0,2).map(x=>x[0]?.toUpperCase()??'').join('')}</span>;
}
function Empty({title,detail,icon}: {title:string;detail:string;icon:'projects'|'tasks'}) {
    return <div className="empty-state"><span className="empty-icon">{icon==='projects'?<FolderPlus size={30}/>:<Inbox size={26}/>}</span><h3>{title}</h3><p>{detail}</p></div>;
}

export function App() {
    const [user,setUser] = useState<User|null>(null);
    const [starting,setStarting] = useState(true);
    const [fatal,setFatal] = useState('');
    const [view,setView] = useState<View>('board');
    const [mobile,setMobile] = useState(false);
    const [projects,setProjects] = useState<Project[]>([]);
    const [projectMeta,setProjectMeta] = useState<PageMeta|null>(null);
    const [projectPage,setProjectPage] = useState(1);
    const [projectSearch,setProjectSearch] = useState('');
    const [search,setSearch] = useState('');
    const [projectId,setProjectId] = useState<number|null>(null);
    const [tasks,setTasks] = useState<Task[]>([]);
    const [tasksTotal,setTasksTotal] = useState(0);
    const [members,setMembers] = useState<MemberList|null>(null);
    const [invitations,setInvitations] = useState<Invitation[]>([]);
    const [loadingProjects,setLoadingProjects] = useState(false);
    const [loadingTasks,setLoadingTasks] = useState(false);
    const [loadingTeam,setLoadingTeam] = useState(false);
    const [error,setError] = useState('');
    const [notice,setNotice] = useState('');
    const [busy,setBusy] = useState(false);
    const [reload,setReload] = useState(0);
    const [projectDialog,setProjectDialog] = useState<'new'|'edit'|null>(null);
    const [projectName,setProjectName] = useState('');
    const [projectDescription,setProjectDescription] = useState('');
    const [taskDialog,setTaskDialog] = useState<Task|'new'|null>(null);
    const [inviteEmail,setInviteEmail] = useState('');
    const [inviteRole,setInviteRole] = useState<'editor'|'viewer'>('editor');
    const [inviteToken,setInviteToken] = useState('');
    const [verifyToken,setVerifyToken] = useState('');
    const [changeEmail,setChangeEmail] = useState('');
    const [password,setPassword] = useState('');
    const [dragging,setDragging] = useState<number|null>(null);
    const [dropping,setDropping] = useState<TaskStatus|null>(null);

    const refresh = () => setReload(v=>v+1);
    useEffect(()=>{
        let alive=true;
        session().then(r=>{if(alive)setUser(r.data)}).catch(e=>{if(alive)setFatal(firstError(e))}).finally(()=>{if(alive)setStarting(false)});
        return ()=>{alive=false};
    },[]);
    useEffect(()=>{
        const timer=window.setTimeout(()=>{setSearch(projectSearch);setProjectPage(1)},300);
        return ()=>window.clearTimeout(timer);
    },[projectSearch]);
    useEffect(()=>{
        if(!user)return;
        let alive=true;setLoadingProjects(true);
        api.projects(projectPage,search).then(r=>{
            if(!alive)return;
            setProjects(r.data);setProjectMeta(r.meta);
            setProjectId(previous=>previous && r.data.some(p=>p.id===previous)?previous:r.data[0]?.id??null);
        }).catch(e=>{if(alive)setError(firstError(e))}).finally(()=>{if(alive)setLoadingProjects(false)});
        return ()=>{alive=false};
    },[user?.id,projectPage,search,reload]);
    useEffect(()=>{
        if(!user || projectId===null){setTasks([]);setMembers(null);setInvitations([]);return}
        let alive=true;setLoadingTasks(true);setLoadingTeam(true);
        const loadTasks=async()=>{
            try{
                const collected:Task[]=[];let total=0;
                for(let page=1;page<=20;page++){
                    const result=await api.tasks(projectId,page);
                    if(!alive)return;
                    collected.push(...result.data);total=result.meta.total;
                    if(page>=result.meta.last_page)break;
                }
                if(alive){setTasks(collected);setTasksTotal(total)}
            }catch(e){if(alive)setError(firstError(e))}
            finally{if(alive)setLoadingTasks(false)}
        };
        const loadTeam=async()=>{
            try{
                const result=await api.members(projectId);
                if(!alive)return;
                setMembers(result);
                if(result.owner.id===user.id){
                    const list=await api.invitations(projectId);
                    if(alive)setInvitations(list.data);
                }else if(alive)setInvitations([]);
            }catch(e){if(alive)setError(firstError(e))}
            finally{if(alive)setLoadingTeam(false)}
        };
        void loadTasks();void loadTeam();
        return ()=>{alive=false};
    },[user?.id,projectId,reload]);

    const selected=projects.find(p=>p.id===projectId)??null;
    const isOwner=!!selected && selected.created_by===user?.id;
    const role=isOwner?'owner':members?.members.find(m=>m.id===user?.id)?.role??'viewer';
    const canEdit=isOwner || role==='editor';
    const grouped=useMemo(()=>({
        TODO:tasks.filter(t=>t.status==='TODO'),
        IN_PROGRESS:tasks.filter(t=>t.status==='IN_PROGRESS'),
        DONE:tasks.filter(t=>t.status==='DONE'),
    }),[tasks]);
    const percent=tasks.length?Math.round(grouped.DONE.length*100/tasks.length):0;

    const failure=(e:unknown)=>{
        setNotice('');setError(firstError(e));
        if(e instanceof ApiError && e.status===401){setUser(null);clearCsrf()}
    };
    const success=(message:string)=>{setError('');setNotice(message);refresh()};
    const action=async(work:()=>Promise<void>)=>{
        if(busy)return;
        setBusy(true);setError('');
        try{await work()}catch(e){failure(e)}finally{setBusy(false)}
    };
    const authenticated=(r:SessionResponse)=>{setUser(r.data);setView('board');setError('');setFatal('');refresh()};
    const logout=()=>void action(async()=>{
        await api.logout();clearCsrf();setUser(null);setTasks([]);setProjects([]);setProjectId(null);
    });
    const openProject=(mode:'new'|'edit')=>{
        setProjectName(mode==='edit'?selected?.name??'':'');
        setProjectDescription(mode==='edit'?selected?.description??'':'');
        setProjectDialog(mode);
    };
    const saveProject=(event:FormEvent)=>{event.preventDefault();void action(async()=>{
        if(projectDialog==='new'){
            const r=await api.createProject(projectName.trim(),projectDescription.trim());
            setProjectPage(1);setProjectSearch('');setSearch('');setProjectId(r.data.id);
            success('Project created.');
        }else if(selected){
            await api.updateProject(selected.id,projectName.trim(),projectDescription.trim());success('Project updated.');
        }
        setProjectDialog(null);
    })};
    const deleteProject=()=>{
        if(!selected||!window.confirm('Delete this project and all its tasks? This cannot be undone.'))return;
        void action(async()=>{await api.deleteProject(selected.id);setProjectId(null);setProjectDialog(null);success('Project deleted.')});
    };
    const saveTask=async(draft:TaskDraft)=>{
        if(!selected)return;
        await action(async()=>{
            if(taskDialog && taskDialog!=='new')await api.updateTask(taskDialog.id,draft);
            else await api.createTask({project_id:selected.id,...draft});
            setTaskDialog(null);success('Task saved.');
        });
    };
    const deleteTask=async(task:Task)=>{
        if(!window.confirm('Delete this task permanently?'))return;
        await action(async()=>{await api.deleteTask(task.id);setTaskDialog(null);success('Task deleted.')});
    };
    const moveTask=async(id:number,status:TaskStatus)=>{
        if(!canEdit)return;
        const task=tasks.find(t=>t.id===id);if(!task||task.status===status)return;
        await action(async()=>{
            await api.updateTask(id,{status});
            setTasks(list=>list.map(t=>t.id===id?{...t,status}:t));
            setNotice('Task moved.');
        });
    };
    const onDrop=(event:DragEvent<HTMLElement>,status:TaskStatus)=>{
        event.preventDefault();setDropping(null);
        const id=dragging;setDragging(null);
        if(id!==null)void moveTask(id,status);
    };
    const sendInvitation=(e:FormEvent)=>{e.preventDefault();if(!selected)return;void action(async()=>{
        await api.invite(selected.id,inviteEmail.trim(),inviteRole);setInviteEmail('');
        success('Invitation queued for delivery.');
    })};
    const acceptInvitation=(e:FormEvent)=>{e.preventDefault();void action(async()=>{
        await api.acceptInvite(inviteToken.trim());setInviteToken('');setProjectPage(1);setSearch('');setProjectSearch('');
        success('Invitation accepted. Project added to your workspace.');
    })};
    const verifyEmail=(e:FormEvent)=>{e.preventDefault();void action(async()=>{
        await api.verifyEmail(verifyToken.trim());const r=await session();setUser(r.data);setVerifyToken('');
        success('Email verified successfully.');
    })};
    const updateEmail=(e:FormEvent)=>{e.preventDefault();
        if(!window.confirm('Changing your email signs out your devices and removes existing team memberships. Continue?'))return;
        void action(async()=>{
            await api.changeEmail(changeEmail.trim(),password);setUser(null);setProjectId(null);
            setPassword('');setChangeEmail('');clearCsrf();
        });
    };

    if(starting)return <div className="app-loading"><span className="brand-logo"><Layers3 size={28}/></span><LoaderCircle className="spin" size={26}/>Preparing your workspace…</div>;
    if(fatal)return <div className="app-loading"><CircleAlert size={32}/><h2>Unable to load TaskForge</h2><p>{fatal}</p><button className="button button-primary" onClick={()=>window.location.reload()}>Retry</button></div>;
    if(!user)return <AuthScreen onAuthenticated={authenticated}/>;
    return <div className="dashboard-shell">
        <aside className={'sidebar '+(mobile?'sidebar-open':'')}>
            <div className="sidebar-header"><span className="brand-logo"><Layers3 size={22}/></span><div className="brand-type"><strong>TaskForge</strong><small>TEAM WORKSPACE</small></div><button className="icon-button mobile-only" aria-label="Close navigation" onClick={()=>setMobile(false)}><X size={18}/></button></div>
            <div className="workspace-selector"><span className="workspace-icon"><FolderKanban size={19}/></span><div><strong>My Workspace</strong><small>{projectMeta?.total??0} projects</small></div><MoreHorizontal size={17}/></div>
            <nav className="sidebar-nav" aria-label="Workspace navigation"><span className="nav-heading">WORKSPACE</span>
                <button className={'nav-item '+(view==='board'?'active':'')} onClick={()=>{setView('board');setMobile(false)}}><LayoutDashboard size={18}/> Overview & board</button>
                <button className={'nav-item '+(view==='team'?'active':'')} onClick={()=>{setView('team');setMobile(false)}}><Users size={18}/> Team & invitations</button>
                <button className={'nav-item '+(view==='account'?'active':'')} onClick={()=>{setView('account');setMobile(false)}}><Settings2 size={18}/> Account & security</button>
                <div className="sidebar-divider"/><div className="sidebar-row"><span className="nav-heading">YOUR PROJECTS</span><button className="mini-icon-button" onClick={()=>openProject('new')} aria-label="Add project"><Plus size={17}/></button></div>
                <div className="sidebar-search"><Search size={15}/><input aria-label="Search projects" placeholder="Search projects…" value={projectSearch} onChange={e=>setProjectSearch(e.target.value)}/></div>
                <div className="project-links">
                    {loadingProjects && !projects.length && <span className="muted-small">Loading projects…</span>}
                    {projects.map((p,i)=><button title={p.name} key={p.id} className={'project-nav-item '+(projectId===p.id?'active':'')} onClick={()=>{setProjectId(p.id);setView('board');setMobile(false)}}><span className={'project-color project-color-'+(i%5)}/><span className="truncate">{p.name}</span>{projectId===p.id&&<span className="tiny-dot"/>}</button>)}
                    {!loadingProjects && !projects.length && <span className="muted-small">No projects found.</span>}
                </div>
                {(projectMeta?.last_page??1)>1&&<div className="project-pager"><button disabled={projectPage===1} aria-label="Previous project page" onClick={()=>setProjectPage(p=>p-1)}><ChevronLeft size={16}/></button><span>{projectPage} / {projectMeta?.last_page}</span><button disabled={projectPage>=(projectMeta?.last_page??1)} aria-label="Next project page" onClick={()=>setProjectPage(p=>p+1)}><ChevronRight size={16}/></button></div>}
            </nav>
            <div className="sidebar-footer"><p><Sparkles size={16}/> Your best work starts here</p><small>Focused work. Stronger teams.</small><div className="sidebar-profile"><Avatar name={user.name}/><div className="profile-name"><strong>{user.name}</strong><small>{user.email}</small></div><button aria-label="Sign out" title="Sign out" className="icon-button" disabled={busy} onClick={logout}><LogOut size={17}/></button></div></div>
        </aside>
        {mobile&&<button className="mobile-overlay" aria-label="Close navigation" onClick={()=>setMobile(false)}/>}
        <main className="main-content">
            <header className="topbar"><div className="topbar-left"><button className="icon-button mobile-only" aria-label="Open navigation" onClick={()=>setMobile(true)}><Menu size={21}/></button><span>Workspace</span><ChevronRight size={15}/><strong>{view==='board'?selected?.name??'Overview':view==='team'?'Team & invitations':'Account & security'}</strong></div><div className="topbar-right"><span className="system-label"><span className="online-dot"/> Connected</span><button className="icon-button" aria-label="Refresh workspace" onClick={refresh}><RefreshCcw size={17}/></button><button className="topbar-avatar" aria-label="Account settings" onClick={()=>setView('account')}><Avatar name={user.name}/></button></div></header>
            <div className="page-content">
                {error&&<div className="alert alert-error notification" role="alert"><CircleAlert size={17}/><span>{error}</span><button aria-label="Dismiss error" onClick={()=>setError('')}><X size={16}/></button></div>}
                {notice&&<div className="alert alert-success notification" role="status"><CheckCircle2 size={17}/><span>{notice}</span><button aria-label="Dismiss notice" onClick={()=>setNotice('')}><X size={16}/></button></div>}
                {view==='board'&&<>
                    <div className="page-hero"><div><span className="eyebrow"><span className="eyebrow-dot"/> YOUR WORKSPACE</span><h1>{selected?.name??'Your workspace'}</h1><p>{selected?.description??(selected?'Make progress one task at a time.':'Create a project to start organizing work.')}</p></div><div className="hero-actions">{selected&&isOwner&&<button className="button button-secondary" onClick={()=>openProject('edit')}><Settings2 size={16}/> Edit project</button>}<button className="button button-primary" onClick={()=>openProject('new')}><Plus size={17}/> New project</button></div></div>
                    {!selected?<Empty icon="projects" title="Start your first project" detail="Organize tasks, invite teammates and turn ideas into progress."/>:<>
                        <section className="stats-grid" aria-label="Project statistics">
                            <div className="stat-card"><div className="stat-label">Total tasks <span className="stat-icon purple"><ListTodo size={19}/></span></div><strong>{tasksTotal}</strong><small>Across this project</small></div>
                            <div className="stat-card"><div className="stat-label">In progress <span className="stat-icon orange"><Activity size={19}/></span></div><strong>{grouped.IN_PROGRESS.length}</strong><small>Currently being worked on</small></div>
                            <div className="stat-card"><div className="stat-label">Completed <span className="stat-icon green"><CheckCircle2 size={19}/></span></div><strong>{grouped.DONE.length}</strong><small>Finished tasks</small></div>
                            <div className="stat-card"><div className="stat-label">Progress <span className="stat-icon blue"><ShieldCheck size={19}/></span></div><strong>{percent}%</strong><span className="progress-track"><span style={{width:percent+'%'}}/></span></div>
                        </section>
                        <div className="section-top"><div><span className="eyebrow">PROJECT BOARD</span><h2>Kanban board <span className="count-badge">{tasksTotal}</span></h2><p>Move work across columns as it progresses.</p></div><div className="board-actions"><span className={'role-chip role-'+role}>{role}</span>{canEdit&&<button className="button button-primary" onClick={()=>setTaskDialog('new')}><Plus size={16}/> Add task</button>}</div></div>
                        {loadingTasks?<div className="board-loading"><LoaderCircle className="spin" size={24}/> Loading tasks…</div>:<div className="kanban-grid">{columns.map(col=><section key={col.id} className={'kanban-column '+(dropping===col.id?'drop-over':'')} aria-label={col.name+' tasks'} onDragOver={e=>{if(canEdit){e.preventDefault();setDropping(col.id)}}} onDragLeave={()=>setDropping(null)} onDrop={e=>onDrop(e,col.id)}>
                            <div className="column-header"><span className={'column-dot '+col.id.toLowerCase()}/><div><h3>{col.name}</h3><small>{col.subtitle}</small></div><span className="column-count">{grouped[col.id].length}</span>{canEdit&&<button className="mini-icon-button" aria-label={'Add task to '+col.name} onClick={()=>setTaskDialog('new')}><Plus size={17}/></button>}</div>
                            <div className="column-body">{grouped[col.id].map(task=><article key={task.id} className={'task-card '+(canEdit?'can-drag':'')} draggable={canEdit&&!busy} onDragStart={e=>{setDragging(task.id);e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',String(task.id))}} onDragEnd={()=>{setDragging(null);setDropping(null)}}>
                                <div className="task-card-top"><span className={'priority priority-'+task.priority.toLowerCase()}><span/>{task.priority.toLowerCase()}</span>{canEdit&&<GripVertical size={15} className="grab-icon"/>}</div>
                                <button className="task-title" onClick={()=>setTaskDialog(task)}>{task.title}</button>{task.description&&<p className="task-desc">{task.description}</p>}
                                <div className="task-card-bottom">{task.due_date&&<span className="due-date"><CalendarDays size={13}/>{dateLabel(task.due_date)}</span>}<span className="grow"/>{task.assigned_to&&<Avatar small name={members?.owner.id===task.assigned_to?members.owner.name:members?.members.find(m=>m.id===task.assigned_to)?.name??'Assigned'}/>}</div>
                                {canEdit&&<select className="quick-status" aria-label={'Status of '+task.title} value={task.status} disabled={busy} onChange={e=>void moveTask(task.id,e.target.value as TaskStatus)}>{columns.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</select>}
                            </article>)}{!grouped[col.id].length&&<div className="empty-column"><Inbox size={24}/><span>No tasks yet</span><small>Drop work here when ready.</small></div>}</div>
                        </section>)}</div>}
                        {tasksTotal>tasks.length&&<p className="limit-note"><CircleAlert size={15}/> Showing {tasks.length} of {tasksTotal} tasks. Split very large projects for the best board experience.</p>}
                    </>}
                </>}
                {view==='team'&&<><div className="page-hero"><div><span className="eyebrow"><span className="eyebrow-dot"/> WORK TOGETHER</span><h1>Team & invitations</h1><p>Collaborate with the right people and the right permissions.</p></div></div>
                    {!selected?<Empty icon="projects" title="Select a project" detail="Choose a project to view its team."/>:<>
                    <div className="section-top"><div><h2>{selected.name} team</h2><p>{isOwner?'Manage invitations and access.':'See who works on this project.'}</p></div><span className={'role-chip role-'+role}>{role}</span></div>
                    {loadingTeam?<div className="board-loading"><LoaderCircle className="spin" size={22}/> Loading team…</div>:<div className="team-layout"><div className="panel"><div className="panel-heading"><h3>Project members</h3><span className="count-badge">{(members?.members.length??0)+1}</span></div>{members?.owner&&<div className="member-row"><Avatar name={members.owner.name}/><div className="member-text"><strong>{members.owner.name}</strong><small>{members.owner.email}</small></div><span className="role-chip role-owner">Owner</span></div>}
                        {members?.members.map(m=><div key={m.id} className="member-row"><Avatar name={m.name}/><div className="member-text"><strong>{m.name}</strong><small>{m.email}</small></div><span className={'role-chip role-'+m.role}>{m.role}</span>{isOwner&&<div className="member-tools"><select aria-label={'Role of '+m.name} value={m.role} disabled={busy} onChange={e=>void action(async()=>{await api.saveMember(selected.id,m.id,e.target.value as 'viewer'|'editor');success('Member role updated.')})}><option value="editor">Editor</option><option value="viewer">Viewer</option></select><button className="icon-button" title="Remove member" aria-label={'Remove '+m.name} disabled={busy} onClick={()=>{if(window.confirm('Remove this member?'))void action(async()=>{await api.removeMember(selected.id,m.id);success('Member removed.')})}}><Trash2 size={16}/></button></div>}</div>)}{!members?.members.length&&<p className="panel-empty">No teammates yet. Invite someone to collaborate.</p>}</div>
                        <div className="team-side">{isOwner&&<><div className="panel panel-pad"><div className="side-panel-head"><span className="stat-icon purple"><Mail size={18}/></span><h3>Invite a teammate</h3></div><p>Recipients must verify their email before joining.</p><form className="stack-form" onSubmit={sendInvitation}><label>Email<input type="email" required placeholder="teammate@example.com" value={inviteEmail} onChange={e=>setInviteEmail(e.target.value)}/></label><label>Access<select value={inviteRole} onChange={e=>setInviteRole(e.target.value as 'editor'|'viewer')}><option value="editor">Editor — manage tasks</option><option value="viewer">Viewer — read only</option></select></label><button className="button button-primary button-full" type="submit" disabled={busy||!user.email_verified_at}><Mail size={16}/> Send invitation</button></form>{!user.email_verified_at&&<p className="warning-text">Verify your email to invite teammates.</p>}</div><div className="panel"><div className="panel-heading"><h3>Invitations</h3><span className="count-badge">{invitations.length}</span></div>{invitations.map(i=><div className="invite-row" key={i.id}><div><strong>{i.email}</strong><small>{i.role} · {i.accepted_at?'Accepted':i.revoked_at?'Revoked':new Date(i.expires_at)<new Date()?'Expired':'Pending'}</small></div>{!i.accepted_at&&!i.revoked_at&&new Date(i.expires_at)>new Date()&&<button className="icon-button" title="Revoke invitation" aria-label={'Revoke '+i.email} disabled={busy} onClick={()=>void action(async()=>{await api.revokeInvite(selected.id,i.id);success('Invitation revoked.')})}><X size={16}/></button>}</div>)}{!invitations.length&&<p className="panel-empty">No invitations sent yet.</p>}</div></>}<div className="panel panel-pad"><div className="side-panel-head"><span className="stat-icon blue"><ShieldCheck size={18}/></span><h3>Protected collaboration</h3></div><p>Owners control membership. Editors can update tasks. Viewers can follow progress.</p></div></div></div>}
                    <div className="panel panel-pad accept-panel"><h3>Have an invitation token?</h3><p>Paste the token from your invitation email.</p><form className="inline-form" onSubmit={acceptInvitation}><input aria-label="Invitation token" value={inviteToken} onChange={e=>setInviteToken(e.target.value)} required minLength={40} placeholder="Paste invitation token"/><button className="button button-primary" disabled={busy||!user.email_verified_at} type="submit">Accept invitation <ArrowRight size={16}/></button></form>{!user.email_verified_at&&<p className="warning-text">Verify your email to accept an invitation.</p>}</div>
                    </>}
                </>}
                {view==='account'&&<><div className="page-hero"><div><span className="eyebrow"><span className="eyebrow-dot"/> PERSONAL SETTINGS</span><h1>Account & security</h1><p>Keep your account and team access secure.</p></div></div><div className="account-grid"><div className="panel panel-pad"><div className="account-identity"><Avatar name={user.name}/><div><h3>{user.name}</h3><p>{user.email}</p></div></div><div className={'verify-status '+(user.email_verified_at?'verified':'unverified')}>{user.email_verified_at?<><CheckCircle2 size={17}/> Email verified</>:<><CircleAlert size={17}/> Email not verified</>}</div>{!user.email_verified_at&&<><p className="panel-description">Verify your mailbox to send or accept project invitations.</p><button className="button button-secondary" disabled={busy} onClick={()=>void action(async()=>{await api.resendVerification();setNotice('Verification message queued. Check your email.')})}><Mail size={16}/> Send verification email</button><form className="stack-form top-gap" onSubmit={verifyEmail}><label>Verification token<input value={verifyToken} required minLength={40} onChange={e=>setVerifyToken(e.target.value)} placeholder="Paste your verification token"/></label><button className="button button-primary" type="submit" disabled={busy}>Verify email <ArrowRight size={16}/></button></form></>}</div><div className="panel panel-pad"><div className="side-panel-head"><span className="stat-icon orange"><ShieldCheck size={18}/></span><h3>Change email</h3></div><p className="panel-description">This signs out every device and removes team memberships linked to your old email.</p><form className="stack-form" onSubmit={updateEmail}><label>New email<input type="email" required value={changeEmail} onChange={e=>setChangeEmail(e.target.value)} placeholder="new@example.com"/></label><label>Current password<input type="password" required autoComplete="current-password" value={password} onChange={e=>setPassword(e.target.value)}/></label><button className="button button-secondary" type="submit" disabled={busy}>Update email</button></form></div></div></>}
                <footer className="dashboard-footer"><span>© TaskForge</span><span><ShieldCheck size={14}/> Secure, team-first work</span></footer>
            </div>
        </main>
        {projectDialog&&<div className="modal-backdrop" onMouseDown={e=>{if(e.target===e.currentTarget)setProjectDialog(null)}}><section className="modal-panel" role="dialog" aria-modal="true" aria-labelledby="project-heading"><header className="modal-heading"><div><span className="eyebrow">PROJECT WORKSPACE</span><h2 id="project-heading">{projectDialog==='new'?'Create project':'Edit project'}</h2></div><button className="icon-button" aria-label="Close" onClick={()=>setProjectDialog(null)}><X size={19}/></button></header><form className="stack-form" onSubmit={saveProject}><label>Project name<input autoFocus required maxLength={255} value={projectName} onChange={e=>setProjectName(e.target.value)} placeholder="e.g. Website redesign"/></label><label>Description<textarea rows={3} value={projectDescription} onChange={e=>setProjectDescription(e.target.value)} placeholder="What are you working on?"/></label><div className="modal-actions">{projectDialog==='edit'&&<button className="button button-danger" type="button" onClick={deleteProject} disabled={busy}>Delete project</button>}<span className="grow"/><button className="button button-ghost" type="button" onClick={()=>setProjectDialog(null)}>Cancel</button><button className="button button-primary" type="submit" disabled={busy||!projectName.trim()}>{busy?'Saving…':projectDialog==='new'?'Create project':'Save changes'}</button></div></form></section></div>}
        {taskDialog&&selected&&(canEdit?<TaskForm key={taskDialog==='new'?'new':taskDialog.id} task={taskDialog==='new'?null:taskDialog} members={members} canDelete={isOwner} busy={busy} onClose={()=>setTaskDialog(null)} onSave={saveTask} onDelete={deleteTask}/>:taskDialog!=='new'&&<div className="modal-backdrop" onMouseDown={e=>{if(e.target===e.currentTarget)setTaskDialog(null)}}><section role="dialog" aria-modal="true" className="modal-panel"><header className="modal-heading"><h2>{taskDialog.title}</h2><button className="icon-button" aria-label="Close" onClick={()=>setTaskDialog(null)}><X size={18}/></button></header><p>{taskDialog.description||'No description.'}</p><div className="read-only-task"><span className="priority">{taskDialog.priority}</span><span>{taskDialog.status}</span><span>{dateLabel(taskDialog.due_date)}</span></div><button className="button button-ghost" onClick={()=>setTaskDialog(null)}>Close</button></section></div>)}
    </div>;
}
