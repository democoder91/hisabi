import { apiFetch } from './common.js';

export const getBudgets = async () => {
    const response = await apiFetch('/api/v1/budgets', {
        method: 'GET',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            budgets: result.data
        }
    };
};

export const createBudget = async ({ name, amount, currency, start_at, end_at, saving, period, reoccurrence, account_ids }) => {
    const response = await apiFetch('/api/v1/budgets', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name, amount, currency, start_at, end_at, saving, period, reoccurrence, account_ids }),
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        throw new Error(errorData?.message || `HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            createBudget: result.budget,
        },
    };
};

export const updateBudget = async ({ id, name, amount, currency, start_at, end_at, saving, period, reoccurrence, account_ids }) => {
    const response = await apiFetch(`/api/v1/budgets/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name, amount, currency, start_at, end_at, saving, period, reoccurrence, account_ids }),
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        throw new Error(errorData?.message || `HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            updateBudget: result.budget,
        },
    };
};

export const deleteBudget = async (id) => {
    const response = await apiFetch(`/api/v1/budgets/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        throw new Error(errorData?.message || `HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            deleteBudget: result.budget,
        },
    };
};
