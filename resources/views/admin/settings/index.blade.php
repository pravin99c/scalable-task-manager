@extends('layouts.admin')

@section('title', 'System Settings')
@section('header_title', 'System Settings')
@section('header_subtitle', 'Configure system parameters and preferences')

@section('content')
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">General Configuration</span>
    </div>
    <form method="POST" action="{{ route('admin.settings.update') }}" style="padding: 2rem;">
        @csrf
        
        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1rem; color: #fff; margin-bottom: 0.5rem;">System Name</h3>
            <p style="color: var(--muted); font-size: 0.85rem;">The name of your application as it appears in emails and the UI.</p>
            <input type="text" name="system_name" value="{{ $settings['system_name'] ?? 'TaskForge' }}" style="width: 100%; max-width: 400px; padding: 0.75rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; color: #fff; margin-top: 0.75rem;">
        </div>

        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1rem; color: #fff; margin-bottom: 0.5rem;">Maintenance Mode</h3>
            <p style="color: var(--muted); font-size: 0.85rem;">When enabled, only administrators can access the system.</p>
            <div style="margin-top: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" id="maint" name="maintenance_mode" value="1" {{ ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : '' }} style="width: 18px; height: 18px;">
                <label for="maint" style="font-size: 0.85rem; color: #fff;">Enable Maintenance Mode</label>
            </div>
        </div>

        <button type="submit" class="badge todo" style="border: none; padding: 0.75rem 1.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 600;">SAVE CHANGES</button>
    </form>
</div>
@endsection
