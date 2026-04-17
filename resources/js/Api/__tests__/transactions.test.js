import '@testing-library/jest-dom'

import { getTransactionFormOptions, getTransactions } from '../transactions'

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

it('includes account filters when fetching transactions', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [], paginatorInfo: { hasMorePages: false } }),
    })

    await getTransactions(1, 'Lunch', {
        accountId: '7',
        categoryId: '3',
        transactionType: 'DEBIT',
    })

    expect(fetchSpy).toHaveBeenCalledWith(
        '/api/v1/transactions?page=1&perPage=100&filter%5Bsearch%5D=Lunch&filter%5Bcategory_id%5D=3&filter%5Baccount_id%5D=7&filter%5Btransaction_type%5D=DEBIT',
        expect.objectContaining({ method: 'GET' })
    )
})