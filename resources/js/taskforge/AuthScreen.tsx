import { useState, type FormEvent } from 'react';
import { ArrowRight, CheckCircle2, Eye, EyeOff, Layers3, LockKeyhole, ShieldCheck, Sparkles } from 'lucide-react';
import { api, firstError } from './api';
import type { SessionResponse } from './types';

interface Props {
    onAuthenticated: (session: SessionResponse) => void;
}

export function AuthScreen({ onAuthenticated }: Props) {
    const [mode, setMode] = useState<'login' | 'register'>('login');
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [visible, setVisible] = useState(false);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (pending) return;
        if (mode === 'register' && password !== confirmation) {
            setError('Passwords do not match.');
            return;
        }
        setPending(true);
        setError('');
        try {
            const result = mode === 'login'
                ? await api.login(email.trim(), password)
                : await api.register(name.trim(), email.trim(), password, confirmation);
            onAuthenticated(result);
        } catch (reason) {
            setError(firstError(reason));
        } finally {
            setPending(false);
        }
    };

    return (
        <main className="auth-layout">
            <section className="auth-story">
                <div className="auth-brand"><span className="brand-logo"><Layers3 size={22}/></span><strong>TaskForge</strong></div>
                <div className="auth-story-content">
                    <span className="eyebrow"><Sparkles size={14}/> WORK BETTER, TOGETHER</span>
                    <h1>Great work happens when everything comes together.</h1>
                    <p>Plan your next project, organize every task, and keep your team moving—all in one beautifully focused workspace.</p>
                    <div className="auth-features">
                        <span><CheckCircle2 size={17}/> Visual Kanban workflows</span>
                        <span><CheckCircle2 size={17}/> Teams and project invitations</span>
                        <span><CheckCircle2 size={17}/> Permission-aware collaboration</span>
                    </div>
                    <div className="auth-preview" aria-hidden="true">
                        <div className="preview-toolbar"><span/><span/><span/> <b>Website redesign</b></div>
                        <div className="preview-columns">
                            <div><em>TO DO</em><strong>Research user needs</strong><strong>Review wireframes</strong></div>
                            <div><em>IN PROGRESS</em><strong>Design system</strong><strong>Build components</strong></div>
                            <div><em>DONE</em><strong>Kickoff meeting ✓</strong><strong>Project scope ✓</strong></div>
                        </div>
                    </div>
                </div>
                <div className="auth-foot"><ShieldCheck size={16}/> A secure, focused workspace for your team</div>
            </section>
            <section className="auth-form-side">
                <div className="auth-card">
                    <span className="mobile-brand"><Layers3 size={19}/> TaskForge</span>
                    <div className="auth-welcome">
                        <span className="eyebrow">WELCOME TO TASKFORGE</span>
                        <h2>{mode === 'login' ? 'Welcome back' : 'Create your workspace'}</h2>
                        <p>{mode === 'login' ? 'Sign in to continue where you left off.' : 'Start planning and collaborating with your team.'}</p>
                    </div>
                    <form onSubmit={submit} className="stack-form">
                        {mode === 'register' && <label>Full name<input name="name" autoComplete="name" value={name} onChange={e=>setName(e.target.value)} required maxLength={255} placeholder="Your name"/></label>}
                        <label>Email address<input type="email" name="email" autoComplete="email" value={email} onChange={e=>setEmail(e.target.value)} required placeholder="you@example.com"/></label>
                        <label>Password
                            <div className="input-icon-wrap">
                                <input type={visible ? 'text' : 'password'} name="password" autoComplete={mode === 'login' ? 'current-password' : 'new-password'} value={password} onChange={e=>setPassword(e.target.value)} minLength={mode === 'register' ? 12 : undefined} required placeholder={mode === 'register' ? 'At least 12 characters' : 'Enter your password'}/>
                                <button className="input-icon-button" type="button" aria-label={visible ? 'Hide password' : 'Show password'} onClick={()=>setVisible(!visible)}>{visible ? <EyeOff size={17}/> : <Eye size={17}/>}</button>
                            </div>
                        </label>
                        {mode === 'register' && <label>Confirm password<input type="password" name="confirmation" autoComplete="new-password" value={confirmation} onChange={e=>setConfirmation(e.target.value)} required minLength={12} placeholder="Repeat your password"/></label>}
                        {error && <div className="alert alert-error" role="alert">{error}</div>}
                        <button className="button button-primary auth-submit" type="submit" disabled={pending}>{pending ? 'Please wait…' : mode === 'login' ? 'Sign in' : 'Create account'}<ArrowRight size={17}/></button>
                    </form>
                    <p className="auth-switch">{mode === 'login' ? 'New to TaskForge?' : 'Already have an account?'} <button type="button" onClick={()=>{setMode(mode === 'login' ? 'register' : 'login'); setError('');}}>{mode === 'login' ? 'Create an account' : 'Sign in'}</button></p>
                    <p className="auth-note"><LockKeyhole size={14}/> Sessions are secured with HTTP-only cookies and CSRF protection.</p>
                </div>
            </section>
        </main>
    );
}
