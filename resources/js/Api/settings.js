import { getCsrfToken } from './common.js';

const getJsonError = async (response) => {
    const errorData = await response.json().catch(() => ({}));

    return new Error(errorData.message || `HTTP error! status: ${response.status}`);
};

export const getSettings = async () => {
    const response = await fetch('/api/v1/settings', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw await getJsonError(response);
    }

    return response.json();
};

export const getCurrencySettings = async () => {
    const response = await fetch('/api/v1/settings/currencies', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw await getJsonError(response);
    }

    return response.json();
};

export const updateSettings = async ({ default_currency } = {}) => {
    const body = {};

    if (default_currency !== undefined) {
        body.default_currency = default_currency;
    }

    const response = await fetch('/api/v1/settings', {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw await getJsonError(response);
    }

    return response.json();
};

export const updateCurrencySettings = async ({ default_currency } = {}) => {
    const body = {};

    if (default_currency !== undefined) {
        body.default_currency = default_currency;
    }

    const response = await fetch('/api/v1/settings/currencies', {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw await getJsonError(response);
    }

    return response.json();
};

export const updateCurrencyRates = async ({ rates } = {}) => {
    const response = await fetch('/api/v1/settings/currencies/rates', {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ rates }),
    });

    if (!response.ok) {
        throw await getJsonError(response);
    }

    return response.json();
};

export const refreshCurrencyRates = async () => {
    const response = await fetch('/api/v1/settings/currencies/refresh', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw await getJsonError(response);
    }

    return response.json();
};