/**
 * Session-backed JSON requests (web middleware + CSRF).
 * Shared by vault UI and setup so POST/PUT behavior stays consistent.
 */

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function syncCsrfToken(token) {
    if (!token) return;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }
}

export function requireCsrfToken() {
    const token = csrfToken();
    if (!token) {
        throw new Error('Security token missing. Hard refresh this page, sign in, and unlock again.');
    }
    return token;
}

/** PHP only parses multipart/urlencoded bodies on POST; spoof PUT/PATCH/DELETE when using forms. */
export function formBackedFetchMethod(method) {
    const upper = (method || 'GET').toUpperCase();
    if (upper === 'PUT' || upper === 'PATCH' || upper === 'DELETE') {
        return { fetchMethod: 'POST', spoofMethod: upper };
    }
    return { fetchMethod: upper, spoofMethod: null };
}

/** Laravel form CSRF (most reliable for mutating web routes). */
export function buildFormBody(fields, { spoofMethod = null } = {}) {
    const form = new FormData();
    form.append('_token', requireCsrfToken());
    if (spoofMethod) {
        form.append('_method', spoofMethod);
    }
    Object.entries(fields).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            return;
        }
        form.append(key, typeof value === 'string' ? value : String(value));
    });
    return form;
}

export function withJsonCsrf(body) {
    if (typeof body !== 'string' || body === '') {
        return body;
    }
    try {
        const data = JSON.parse(body);
        delete data._token;
        data._token = requireCsrfToken();
        return JSON.stringify(data);
    } catch {
        return body;
    }
}

/** Some hosts return 405 for PUT/PATCH/DELETE; Laravel accepts POST + `_method` in JSON. */
export function prepareMutatingApiRequest(method, body) {
    const upper = (method || 'GET').toUpperCase();
    if (!['PUT', 'PATCH', 'DELETE'].includes(upper)) {
        return { method: upper, body };
    }

    let payload = {};
    if (body !== undefined && body !== null && body !== '') {
        try {
            payload = typeof body === 'string' ? JSON.parse(body) : { ...body };
        } catch {
            payload = {};
        }
    }
    delete payload._method;
    payload._method = upper;

    return {
        method: 'POST',
        body: withJsonCsrf(JSON.stringify(payload)),
    };
}

export function buildApiHeaders(extra = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...extra,
    };
    const metaToken = csrfToken();
    if (metaToken) {
        headers['X-CSRF-TOKEN'] = metaToken;
    }
    return headers;
}
