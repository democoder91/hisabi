import '@testing-library/jest-dom'

import { getTransactionFormOptions } from '../transactions'

afterEach(() => {
    jest.restoreAllMocks()
})

it('fetches transaction form options without an account filter by default', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ categories: [] }),
    })

    await getTransactionFormOptions()

    expect(fetchSpy).toHaveBeenCalledWith(
        '/api/v1/transactions/form-options',
        expect.objectContaining({ method: 'GET' })
    )
})

it('fetches transaction form options for a specific account owner when account id is provided', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ categories: [{ id: 1 }] }),
    })

    const response = await getTransactionFormOptions(42)

    expect(fetchSpy).toHaveBeenCalledWith(
        '/api/v1/transactions/form-options?account_id=42',
        expect.objectContaining({ method: 'GET' })
    )
    expect(response.data.categories).toEqual([{ id: 1 }])
})