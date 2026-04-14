import '@testing-library/jest-dom'

import { apiFetch, getCsrfToken } from '../common'

afterEach(() => {
    jest.restoreAllMocks()
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
    document.head.innerHTML = ''
})

it('uses the current XSRF cookie for mutating requests', async () => {
    document.cookie = 'XSRF-TOKEN=cookie-token; path=/'

    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        status: 200,
    })

    await apiFetch('/api/v1/accounts/24/shares', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: 'admin@hisabi.com' }),
    })

    expect(getCsrfToken()).toBe('cookie-token')
    expect(fetchSpy).toHaveBeenCalledTimes(1)

    const [, options] = fetchSpy.mock.calls[0]

    expect(options.credentials).toBe('same-origin')
    expect(options.headers.get('X-XSRF-TOKEN')).toBe('cookie-token')
    expect(options.headers.get('X-Requested-With')).toBe('XMLHttpRequest')
    expect(options.headers.get('X-CSRF-TOKEN')).toBeNull()
})

it('bootstraps the csrf cookie before the first mutating request when needed', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockImplementation(async (url) => {
        if (url === '/sanctum/csrf-cookie') {
            document.cookie = 'XSRF-TOKEN=bootstrapped-token; path=/'

            return {
                ok: true,
                status: 204,
            }
        }

        return {
            ok: true,
            status: 200,
        }
    })

    await apiFetch('/api/v1/accounts/24/shares', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: 'admin@hisabi.com' }),
    })

    expect(fetchSpy).toHaveBeenCalledTimes(2)
    expect(fetchSpy.mock.calls[0][0]).toBe('/sanctum/csrf-cookie')
    expect(fetchSpy.mock.calls[1][1].headers.get('X-XSRF-TOKEN')).toBe('bootstrapped-token')
})

it('refreshes the csrf cookie and retries once after a 419 response', async () => {
    document.cookie = 'XSRF-TOKEN=stale-token; path=/'

    const fetchSpy = jest.spyOn(global, 'fetch').mockImplementation(async (url, options) => {
        if (url === '/sanctum/csrf-cookie') {
            document.cookie = 'XSRF-TOKEN=refreshed-token; path=/'

            return {
                ok: true,
                status: 204,
            }
        }

        if (fetchSpy.mock.calls.length === 1) {
            return {
                ok: false,
                status: 419,
            }
        }

        return {
            ok: true,
            status: 200,
            headers: options?.headers,
        }
    })

    const response = await apiFetch('/api/v1/accounts/24/shares', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: 'admin@hisabi.com' }),
    })

    expect(response.ok).toBe(true)
    expect(fetchSpy).toHaveBeenCalledTimes(3)
    expect(fetchSpy.mock.calls[1][0]).toBe('/sanctum/csrf-cookie')
    expect(fetchSpy.mock.calls[2][1].headers.get('X-XSRF-TOKEN')).toBe('refreshed-token')
})