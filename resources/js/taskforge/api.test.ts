import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError, clearCsrf, request, session } from './api';

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
});

describe('TaskForge same-origin API client', () => {
    beforeEach(() => {
        clearCsrf();
        vi.unstubAllGlobals();
    });

    it('uses same-origin cookies and obtains an in-memory CSRF token before mutation', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce(json({ data: null, csrf_token: 'token-from-session' }))
            .mockResolvedValueOnce(json({ data: { id: 1, name: 'My project' }, meta: null, links: null }, 201));
        vi.stubGlobal('fetch', fetchMock);
        const project = await api.createProject('My project', '');
        expect(project.data.name).toBe('My project');
        expect(fetchMock).toHaveBeenCalledTimes(2);
        const mutation = fetchMock.mock.calls[1] as [string, RequestInit];
        expect(mutation[0]).toBe('/api/v2/projects');
        expect(mutation[1].credentials).toBe('same-origin');
        expect(new Headers(mutation[1].headers).get('X-CSRF-TOKEN')).toBe('token-from-session');
        expect(new Headers(mutation[1].headers).get('Authorization')).toBeNull();
    });

    it('supports v2 paginated responses and sends bounded project requests', async () => {
        const fetchMock = vi.fn().mockResolvedValue(json({
            data: [{ id: 3, name: 'Example' }],
            meta: { current_page: 2, per_page: 15, last_page: 3, total: 32, from: 16, to: 30 },
            links: { first: '', last: '', prev: '', next: '' },
        }));
        vi.stubGlobal('fetch', fetchMock);
        const result = await api.projects(2, 'example');
        expect(result.meta.total).toBe(32);
        expect(fetchMock.mock.calls[0][0]).toContain('page=2');
        expect(fetchMock.mock.calls[0][0]).toContain('search=example');
    });

    it('surfaces Laravel 422 validation errors', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({
            message: 'Validation failed.',
            errors: { email: ['This email address is already in use.'] },
        }, 422)));
        await expect(request('/api/v2/projects')).rejects.toMatchObject({
            status: 422,
            errors: { email: ['This email address is already in use.'] },
        } satisfies Partial<ApiError>);
    });

    it('reads and caches a server-issued CSRF token', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ data: null, csrf_token: 'test-only' })));
        expect((await session()).csrf_token).toBe('test-only');
    });

    it('recovers once when the browser CSRF token expires', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce(json({ data: null, csrf_token: 'stale' }))
            .mockResolvedValueOnce(json({ message: 'CSRF expired' }, 419))
            .mockResolvedValueOnce(json({ data: null, csrf_token: 'fresh' }))
            .mockResolvedValueOnce(json({ data: { id: 7, name: 'Retried' }, meta: null, links: null }, 201));
        vi.stubGlobal('fetch', fetchMock);
        expect((await api.createProject('Retried', '')).data.id).toBe(7);
        expect(fetchMock).toHaveBeenCalledTimes(4);
        expect(new Headers(fetchMock.mock.calls[1][1].headers).get('X-CSRF-TOKEN')).toBe('stale');
        expect(new Headers(fetchMock.mock.calls[3][1].headers).get('X-CSRF-TOKEN')).toBe('fresh');
    });

    it('does not retry a permanently invalid CSRF token forever', async () => {
        const fetchMock = vi.fn()
            .mockResolvedValueOnce(json({ data: null, csrf_token: 'old' }))
            .mockResolvedValueOnce(json({ message: 'CSRF expired' }, 419))
            .mockResolvedValueOnce(json({ data: null, csrf_token: 'also-old' }))
            .mockResolvedValueOnce(json({ message: 'CSRF expired again' }, 419));
        vi.stubGlobal('fetch', fetchMock);
        await expect(api.createProject('No', '')).rejects.toMatchObject({ status: 419 });
        expect(fetchMock).toHaveBeenCalledTimes(4);
    });

});
