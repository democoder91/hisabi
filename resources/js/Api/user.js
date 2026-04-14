import { apiFetch } from './common.js';

export const updateUserProfile = async ({ name, email, currentPassword, password, locale } = {}) => {
    const body = {};

    if (name !== undefined) body.name = name;
    if (email !== undefined) body.email = email;
    if (locale !== undefined) body.locale = locale;

    // Only include password fields if changing password
    if (password) {
        body.currentPassword = currentPassword;
        body.password = password;
    }

    const response = await apiFetch('/api/v1/user/profile', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body)
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    return {
        data: {
            user: result.user
        }
    };
}
