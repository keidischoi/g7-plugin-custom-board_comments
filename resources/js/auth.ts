const TOKEN_KEYS = ['auth_token', 'access_token'] as const;

type G7Window = Window & {
    G7Core?: {
        api?: { getToken?: () => string | null };
        AuthManager?: { getInstance?: () => { getAccessToken?: () => string | null } };
    };
};

function usableToken(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }
    const token = value.trim();
    if (token === '' || token === 'undefined' || token === 'null') {
        return null;
    }
    return token;
}

function fromStorage(storage: Storage | undefined): string | null {
    if (!storage) {
        return null;
    }
    for (const key of TOKEN_KEYS) {
        try {
            const token = usableToken(storage.getItem(key));
            if (token) {
                return token;
            }
        } catch {
            // private mode
        }
    }
    return null;
}

export function readAuthToken(win: G7Window = window): string | null {
    try {
        const fromClient = usableToken(win.G7Core?.api?.getToken?.());
        if (fromClient) {
            return fromClient;
        }
    } catch {
        // G7Core may be absent in tests
    }
    try {
        const fromAuth = usableToken(win.G7Core?.AuthManager?.getInstance?.()?.getAccessToken?.());
        if (fromAuth) {
            return fromAuth;
        }
    } catch {
        // AuthManager may be absent
    }
    try {
        const fromLocal = fromStorage(win.localStorage);
        if (fromLocal) {
            return fromLocal;
        }
    } catch {
        // private mode
    }
    try {
        return fromStorage(win.sessionStorage);
    } catch {
        return null;
    }
}

export function pluginRequestHeaders(extra: Record<string, string> = {}, win: G7Window = window): Record<string, string> {
    const csrf = win.document?.querySelector?.('meta[name="csrf-token"]')?.getAttribute('content');
    const token = readAuthToken(win);
    const locale = (() => {
        try {
            return win.localStorage?.getItem('g7_locale');
        } catch {
            return null;
        }
    })();
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(locale ? { 'Accept-Language': locale } : {}),
        ...extra,
    };
}
