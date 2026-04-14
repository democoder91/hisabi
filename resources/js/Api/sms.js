import { apiFetch } from './common.js';

export const getSms = async (page) => {
    const response = await apiFetch(`/api/v1/sms?page=${page}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    return { data: { sms: result } };
}

export const createSms = async ({sms, createdAt}) => {
    const response = await apiFetch(`/api/v1/sms`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            body: sms,
            created_at: createdAt || null
        })
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    return { data: { createSms: result.data } };
}

export const updateSms = async ({id, body}) => {
    const response = await apiFetch(`/api/v1/sms/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ body })
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    return { data: { updateSms: result.sms } };
}

export const deleteSms = async (id) => {
    const response = await apiFetch(`/api/v1/sms/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    return { data: { deleteSms: result.sms } };
}
