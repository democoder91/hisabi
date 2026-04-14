const csrfSafeMethods = new Set(['GET', 'HEAD', 'OPTIONS']);

let csrfCookieRequest = null;

const getMetaCsrfToken = () => {
    const token = document.querySelector('meta[name="csrf-token"]');

    return token ? token.getAttribute('content') : '';
};

const getCookieValue = (name) => {
    if (typeof document === 'undefined' || typeof document.cookie !== 'string') {
        return '';
    }

    const cookiePrefix = `${name}=`;
    const cookie = document.cookie
        .split(';')
        .map((value) => value.trim())
        .find((value) => value.startsWith(cookiePrefix));

    return cookie ? decodeURIComponent(cookie.slice(cookiePrefix.length)) : '';
};

const needsCsrfProtection = (method) => !csrfSafeMethods.has(method.toUpperCase());

const buildHeaders = (headers = {}) => {
    const requestHeaders = new Headers(headers);

    if (!requestHeaders.has('X-Requested-With')) {
        requestHeaders.set('X-Requested-With', 'XMLHttpRequest');
    }

    return requestHeaders;
};

const applyCsrfHeader = async (headers, forceRefresh = false) => {
    if (forceRefresh || getCookieValue('XSRF-TOKEN') === '') {
        await refreshCsrfCookie();
    }

    const xsrfToken = getCookieValue('XSRF-TOKEN');

    if (xsrfToken !== '') {
        headers.set('X-XSRF-TOKEN', xsrfToken);
        headers.delete('X-CSRF-TOKEN');

        return headers;
    }

    const csrfToken = getMetaCsrfToken();

    if (csrfToken !== '') {
        headers.set('X-CSRF-TOKEN', csrfToken);
    }

    return headers;
};

export const getCsrfToken = () => getCookieValue('XSRF-TOKEN') || getMetaCsrfToken();

export const refreshCsrfCookie = async () => {
    if (csrfCookieRequest) {
        return csrfCookieRequest;
    }

    csrfCookieRequest = fetch('/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).finally(() => {
        csrfCookieRequest = null;
    });

    return csrfCookieRequest;
};

export const apiFetch = async (url, options = {}) => {
    const method = (options.method ?? 'GET').toUpperCase();

    const executeRequest = async (forceRefresh = false) => {
        const headers = buildHeaders(options.headers);

        if (needsCsrfProtection(method)) {
            await applyCsrfHeader(headers, forceRefresh);
        }

        return fetch(url, {
            ...options,
            method,
            credentials: options.credentials ?? 'same-origin',
            headers,
        });
    };

    const response = await executeRequest();

    if (!needsCsrfProtection(method) || response.status !== 419) {
        return response;
    }

    return executeRequest(true);
};
