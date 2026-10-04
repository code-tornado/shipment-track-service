const TOKEN_KEY = 'sts.token';

export class ApiError extends Error {
    status: number;
    errors: Record<string, string[]>;

    constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }

    /** All validation messages as one flat list. */
    get messages(): string[] {
        const list = Object.values(this.errors).flat();
        return list.length ? list : [this.message];
    }
}

export const token = {
    get: (): string | null => {
        try {
            return localStorage.getItem(TOKEN_KEY);
        } catch {
            return null;
        }
    },
    set: (value: string | null) => {
        try {
            value ? localStorage.setItem(TOKEN_KEY, value) : localStorage.removeItem(TOKEN_KEY);
        } catch {
            /* storage unavailable: session only */
        }
    },
};

interface Options {
    method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
    body?: unknown;
    formData?: FormData;
    query?: Record<string, string | number | boolean | null | undefined>;
}

export async function api<T>(path: string, options: Options = {}): Promise<T> {
    const url = new URL('/api/v1' + path, window.location.origin);
    for (const [key, value] of Object.entries(options.query ?? {})) {
        if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value));
    }

    const headers: Record<string, string> = { Accept: 'application/json' };
    const current = token.get();
    if (current) headers.Authorization = `Bearer ${current}`;
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';

    const response = await fetch(url, {
        method: options.method ?? 'GET',
        headers,
        body: options.formData ?? (options.body !== undefined ? JSON.stringify(options.body) : undefined),
    });

    if (response.status === 204) return undefined as T;

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 401) {
            token.set(null);
            window.dispatchEvent(new Event('sts:unauthenticated'));
        }
        throw new ApiError(response.status, payload.message ?? `Request failed (${response.status})`, payload.errors ?? {});
    }

    return payload as T;
}

export const fmtDate = (value: string | null | undefined): string => (value ? value.slice(0, 10) : '–');

export const fmtDateTime = (value: string | null | undefined): string => {
    if (!value) return '–';
    const d = new Date(value);
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
};
