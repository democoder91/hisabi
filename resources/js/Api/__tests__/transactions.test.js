import '@testing-library/jest-dom'

import { createTransaction, getTransactions, updateTransaction } from '../transactions'

afterEach(() => {
    jest.restoreAllMocks()
})

it('includes account-side filters when fetching transactions', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: [], paginatorInfo: { hasMorePages: false } }),
    })

    await getTransactions(1, 'Lunch', {
        accountId: '7',
        fromAccountId: '11',
        toAccountId: '13',
        dateFrom: '2026-04-01',
        dateTo: '2026-04-30',
    })

    expect(fetchSpy).toHaveBeenCalledWith(
        '/api/v1/transactions?page=1&perPage=100&filter%5Bsearch%5D=Lunch&filter%5Baccount_id%5D=7&filter%5Bfrom_account_id%5D=11&filter%5Bto_account_id%5D=13&filter%5Bdate_from%5D=2026-04-01&filter%5Bdate_to%5D=2026-04-30',
        expect.objectContaining({ method: 'GET' })
    )
})

it('posts account-first transaction payloads', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        status: 201,
        json: async () => ({ transaction: { id: 99 } }),
    })

    await createTransaction({
        amount: 42,
        fromAccountId: 7,
        toAccountId: 9,
        createdAt: '2026-04-19',
        note: 'Lunch',
    })

    expect(fetchSpy).toHaveBeenCalledWith(
        '/api/v1/transactions',
        expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({
                amount: 42,
                from_account_id: 7,
                to_account_id: 9,
                created_at: '2026-04-19',
                note: 'Lunch',
            }),
        })
    )
})

it('puts account-first transaction payloads', async () => {
    const fetchSpy = jest.spyOn(global, 'fetch').mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ transaction: { id: 12 } }),
    })

    await updateTransaction({
        id: 12,
        amount: 75,
        fromAccountId: 3,
        toAccountId: 4,
        createdAt: '2026-04-20',
        note: 'Moved',
    })

    expect(fetchSpy).toHaveBeenCalledWith(
        '/api/v1/transactions/12',
        expect.objectContaining({
            method: 'PUT',
            body: JSON.stringify({
                amount: 75,
                from_account_id: 3,
                to_account_id: 4,
                created_at: '2026-04-20',
                note: 'Moved',
            }),
        })
    )
})