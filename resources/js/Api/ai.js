import { getCsrfToken } from './common.js';

export const chat = async (messages, conversationId = null) => {
    const response = await fetch('/api/v1/ai/chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            messages,
            conversation_id: conversationId,
        })
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
}

export const getTranscriptionToken = async () => {
    const response = await fetch('/api/v1/ai/transcribe/token', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
}
