import { apiFetch } from './common.js';
import { getCategoryStats as getCategoryStatsMetric } from './metrics.js';

const normalizeCategory = (category = {}) => {
    const nameTranslations = category.name_translations
        ?? (typeof category.name === 'object' && category.name !== null ? category.name : {});

    return {
        ...category,
        name: typeof category.name === 'string'
            ? category.name
            : nameTranslations.en ?? nameTranslations.ar ?? '',
        name_translations: nameTranslations,
        transactionsCount: category.transactionsCount ?? category.transactions_count ?? 0,
    };
};

export const getAllCategories = async () => {
    const response = await apiFetch('/api/v1/categories/all', {
        method: 'GET',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            allCategories: result.data.map(normalizeCategory)
        }
    };
}

export const createCategory = async ({name, type, color, icon}) => {
    const response = await apiFetch('/api/v1/categories', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name, type, color, icon })
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            createCategory: normalizeCategory(result.category)
        }
    };
}

export const updateCategory = async ({id, name, type, color, icon}) => {
    const response = await apiFetch(`/api/v1/categories/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ name, type, color, icon })
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            updateCategory: normalizeCategory(result.category)
        }
    };
}

export const deleteCategory = async (id) => {
    const response = await apiFetch(`/api/v1/categories/${id}`, {
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
            deleteCategory: normalizeCategory(result.category)
        }
    };
}

export const getCategoryStats = async (dateRange) => {
    const response = await getCategoryStatsMetric(dateRange);
    return {
        data: response.data
    };
}
