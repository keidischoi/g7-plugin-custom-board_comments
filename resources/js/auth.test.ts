import { afterEach, describe, expect, it } from 'vitest';
import { pluginFetch, pluginRequestHeaders, readAuthToken } from './auth';

afterEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    document.head.innerHTML = '';
    delete (window as Window & { G7Core?: unknown }).G7Core;
});

describe('auth headers', () => {
    it('sends the G7 Sanctum token from localStorage', () => {
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-test">';
        localStorage.setItem('auth_token', 'user-token-1');
        expect(readAuthToken()).toBe('user-token-1');
        expect(pluginRequestHeaders()).toMatchObject({
            'X-CSRF-TOKEN': 'csrf-test',
            Authorization: 'Bearer user-token-1',
        });
        expect(pluginRequestHeaders()['Content-Type']).toBeUndefined();
    });

    it('prefers G7Core.api.getToken over localStorage', () => {
        localStorage.setItem('auth_token', 'stored');
        window.G7Core = { api: { getToken: () => 'live-token' } };
        expect(readAuthToken()).toBe('live-token');
        expect(pluginRequestHeaders().Authorization).toBe('Bearer live-token');
    });

    it('reads access_token when auth_token is missing', () => {
        localStorage.setItem('access_token', 'alt-token');
        expect(readAuthToken()).toBe('alt-token');
    });

    it('ignores a stored undefined token string', () => {
        localStorage.setItem('auth_token', 'undefined');
        expect(readAuthToken()).toBeNull();
        expect(pluginRequestHeaders().Authorization).toBeUndefined();
    });

    it('retries once after AuthManager refreshes a 401', async () => {
        localStorage.setItem('auth_token', 'old-token');
        window.G7Core = {
            AuthManager: {
                getInstance: () => ({
                    refreshToken: async () => {
                        localStorage.setItem('auth_token', 'new-token');
                        return true;
                    },
                }),
            },
        };
        const calls: Array<string | undefined> = [];
        const fetchImpl: typeof fetch = async (_url, init) => {
            const headers = new Headers(init?.headers);
            calls.push(headers.get('Authorization') ?? undefined);
            if (calls.length === 1) {
                return new Response(JSON.stringify({ message: 'unauthenticated' }), { status: 401 });
            }
            return new Response(JSON.stringify({ ok: true }), { status: 200 });
        };
        const response = await pluginFetch('/api/plugins/custom-board_comments/media', { method: 'POST' }, window, fetchImpl);
        expect(response.status).toBe(200);
        expect(calls).toEqual(['Bearer old-token', 'Bearer new-token']);
    });
});
