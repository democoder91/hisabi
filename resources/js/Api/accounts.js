import { apiFetch } from './common.js';

export const getAllAccounts = async () => {
    const response = await apiFetch('/api/v1/accounts/all', {
        method: 'GET',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            allAccounts: result.data,
        },
    };
};

export const getAccounts = async (page, searchQuery = '', filters = {}) => {
    const params = new URLSearchParams({
        page: page.toString(),
        perPage: '50',
    });

    if (searchQuery) {
        params.append('filter[search]', searchQuery);
    }

    if (filters.currency) {
        params.append('filter[currency]', filters.currency);
    }

    if (filters.access) {
        params.append('filter[access]', filters.access);
    }

    const response = await apiFetch(`/api/v1/accounts?${params.toString()}`, {
        method: 'GET',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            accounts: result,
        },
    };
};

export const getAccountAudits = async (accountId, page = 1) => {
    const params = new URLSearchParams({
        page: page.toString(),
        perPage: '25',
    });

    const response = await apiFetch(`/api/v1/accounts/${accountId}/audits?${params.toString()}`, {
        method: 'GET',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            account: result.account,
            audits: result.data,
            paginatorInfo: result.paginatorInfo,
        },
    };
};

export const createAccount = async ({ name, balance, currency }) => {
    const response = await apiFetch('/api/v1/accounts', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name, balance, currency }),
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            createAccount: result.account,
        },
    };
};

export const updateAccount = async ({ id, name, balance, currency }) => {
    const response = await apiFetch(`/api/v1/accounts/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name, balance, currency }),
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            updateAccount: result.account,
        },
    };
};

export const searchAccountShareableUsers = async ({ id, query }) => {
    const params = new URLSearchParams();

    if (query) {
        params.append('search', query);
    }

    const response = await apiFetch(`/api/v1/accounts/${id}/shareable-users?${params.toString()}`, {
        method: 'GET',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            users: result.users,
        },
    };
};

export const inviteAccountShare = async ({ id, email, permissionLevel }) => {
    const response = await apiFetch(`/api/v1/accounts/${id}/shares`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email,
            permission_level: permissionLevel,
        }),
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            account: result.account,
        },
    };
};

export const updateAccountSharePermission = async ({ id, shareUserId, permissionLevel }) => {
    const response = await apiFetch(`/api/v1/accounts/${id}/shares/${shareUserId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            permission_level: permissionLevel,
        }),
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            account: result.account,
        },
    };
};

export const revokeAccountShare = async ({ id, shareUserId }) => {
    const response = await apiFetch(`/api/v1/accounts/${id}/shares/${shareUserId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            account: result.account,
        },
    };
};

export const deleteAccount = async (id) => {
    const response = await apiFetch(`/api/v1/accounts/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            deleteAccount: result.account,
        },
    };
};