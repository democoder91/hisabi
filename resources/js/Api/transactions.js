import { apiFetch } from './common.js';
import { getTotalIncome, getTotalExpenses, getTransactionsCount } from './metrics.js';

export const getTransactions = async (page, searchQuery, filters = {}) => {
    const params = new URLSearchParams({
        page: page.toString(),
        perPage: '100'
    });

    if (searchQuery) {
        params.append('filter[search]', searchQuery);
    }

    if (filters.accountId) {
        params.append('filter[account_id]', filters.accountId);
    }
    if (filters.fromAccountId) {
        params.append('filter[from_account_id]', filters.fromAccountId);
    }
    if (filters.toAccountId) {
        params.append('filter[to_account_id]', filters.toAccountId);
    }
    if (filters.dateFrom) {
        params.append('filter[date_from]', filters.dateFrom);
    }
    if (filters.dateTo) {
        params.append('filter[date_to]', filters.dateTo);
    }

    const response = await apiFetch(`/api/v1/transactions?${params.toString()}`, {
        method: 'GET',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    return {
        data: {
            transactions: data
        }
    };
}

export const createTransaction = async ({ amount, fromAccountId, toAccountId, createdAt, note }) => {
    const response = await apiFetch('/api/v1/transactions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            amount,
            from_account_id: fromAccountId,
            to_account_id: toAccountId,
            created_at: createdAt,
            note,
        })
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    return {
        data: data
    };
}

export const updateTransaction = async ({ id, amount, fromAccountId, toAccountId, createdAt, note }) => {
    const response = await apiFetch(`/api/v1/transactions/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            amount,
            from_account_id: fromAccountId,
            to_account_id: toAccountId,
            created_at: createdAt,
            note
        })
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    return {
        data: data
    };
}

export const deleteTransaction = async (id) => {
    const response = await apiFetch(`/api/v1/transactions/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },

    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    return {
        data: data
    };
}

export const getTransactionStats = async (dateRange) => {
    const [incomeRes, expensesRes, countRes] = await Promise.all([
        getTotalIncome(dateRange),
        getTotalExpenses(dateRange),
        getTransactionsCount(dateRange)
    ]);

    return {
        data: {
            totalIncome: incomeRes.data,
            totalExpenses: expensesRes.data,
            numberOfTransactions: countRes.data
        }
    };
}
