import { getCsrfToken } from './common.js';

export const getBudgets = async () => {
    const response = await fetch('/api/v1/budgets', {
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

export const createBudget = async ({ name, amount, currency, start_at, end_at, saving, period, reoccurrence, category_ids }) => {
    const response = await fetch('/api/v1/budgets', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ name, amount, currency, start_at, end_at, saving, period, reoccurrence, category_ids }),
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

export const updateBudget = async ({ id, name, amount, currency, start_at, end_at, saving, period, reoccurrence, category_ids }) => {
    const response = await fetch(`/api/v1/budgets/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ name, amount, currency, start_at, end_at, saving, period, reoccurrence, category_ids }),
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
    const response = await fetch(`/api/v1/budgets/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
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
