import { apiFetch } from './common.js';

const tryParseJson = (rawBody) => {
    if (typeof rawBody !== 'string') {
        return null;
    }

    const trimmed = rawBody.trim();

    if (trimmed === '') {
        return null;
    }

    try {
        return JSON.parse(trimmed);
    } catch {
        const objectStart = trimmed.indexOf('{');
        const objectEnd = trimmed.lastIndexOf('}');

        if (objectStart === -1 || objectEnd === -1 || objectEnd <= objectStart) {
            return null;
        }

        try {
            return JSON.parse(trimmed.slice(objectStart, objectEnd + 1));
        } catch {
            return null;
        }
    }
};

const parseJsonResponse = async (response) => {
    if (typeof response.text !== 'function') {
        if (typeof response.json === 'function') {
            return await response.json();
        }

        const error = new Error('Response does not expose a readable body.');
        throw error;
    }

    const rawBody = await response.text();
    const payload = tryParseJson(rawBody);

    if (payload !== null) {
        return payload;
    }

    const error = new Error('Failed to parse JSON response body.');
    error.rawBody = rawBody;

    throw error;
};

export const chat = async (messages, conversationId = null) => {
    const response = await apiFetch('/api/v1/ai/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            messages,
            conversation_id: conversationId,
        })
    });

    if (!response.ok) {
        const payload = await parseJsonResponse(response).catch(() => ({}));
        const error = new Error(`HTTP error! status: ${response.status}`);
        error.status = response.status;
        error.payload = payload;

        throw error;
    }

    return await parseJsonResponse(response);
}

export const getTranscriptionToken = async () => {
    const response = await apiFetch('/api/v1/ai/transcribe/token', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
}

export const transcribeAudio = async (audioBlob, filename = 'recording.webm') => {
    const formData = new FormData();
    formData.append('audio', audioBlob, filename);

    const response = await apiFetch('/api/v1/ai/transcribe', {
        method: 'POST',
        headers: {
        },
        body: formData,
    });

    if (!response.ok) {
        const payload = await parseJsonResponse(response).catch(() => ({}));
        const error = new Error(`HTTP error! status: ${response.status}`);
        error.status = response.status;
        error.payload = payload;

        throw error;
    }

    return await parseJsonResponse(response);
}
