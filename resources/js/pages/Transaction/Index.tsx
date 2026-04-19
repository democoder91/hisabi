import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Head } from '@inertiajs/react';
import { debounce } from 'lodash';
import { endOfMonth, startOfMonth } from 'date-fns';
import { DateRange } from 'react-day-picker';
import { type ColumnDef } from '@tanstack/react-table';
import { X } from '@phosphor-icons/react';

import RecordTransactionButton from '@/components/Domain/RecordTransactionButton';
import TransactionStats from '@/components/Domain/TransactionStats';
import LoadMore from '@/components/Global/LoadMore';
import Filters from '@/pages/Transaction/Filters';
import Authenticated from '@/Layouts/Authenticated';
import { useActiveLocale } from '@/hooks/useActiveLocale';
import Edit from './Edit';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DatePickerWithRange } from '@/components/ui/date-picker-with-range';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { getAllAccounts, getTransactions } from '@/Api';
import {
    animateRowItem,
    formatNumber,
    getSharedAccountOwnerLabel,
    isCreditTransaction,
    withLocalizedName,
    withLocalizedNames,
} from '@/Utils';

export default function Index({ auth }: { auth: any }) {
    const { t } = useTranslation();
    const activeLocale = useActiveLocale();
    const urlParams = new URLSearchParams(window.location.search);
    const initialSearch = urlParams.get('search') || '';

    const initialFilters = {
        accountId: urlParams.get('account') || '',
        fromAccountId: urlParams.get('fromAccount') || '',
        toAccountId: urlParams.get('toAccount') || '',
        dateFrom: urlParams.get('dateFrom') || '',
        dateTo: urlParams.get('dateTo') || '',
    };

    const [transactions, setTransactions] = useState<any[]>([]);
    const [allAccounts, setAllAccounts] = useState<any[]>([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [hasMorePages, setHasMorePages] = useState(true);
    const [searchQuery, setSearchQuery] = useState(initialSearch);
    const [loading, setLoading] = useState(false);
    const [editItem, setEditItem] = useState(null);
    const [filters, setFilters] = useState(initialFilters);
    const [dateRange, setDateRange] = useState<DateRange>({
        from: startOfMonth(new Date()),
        to: endOfMonth(new Date()),
    });

    useEffect(() => {
        getAllAccounts()
            .then(({ data }) => {
                setAllAccounts(data.allAccounts);
            })
            .catch(console.error);
    }, []);

    useEffect(() => {
        if (currentPage > 1 && !hasMorePages) {
            return;
        }

        setLoading(true);

        getTransactions(currentPage, searchQuery, filters)
            .then(({ data }) => {
                setTransactions((current) => currentPage === 1
                    ? data.transactions.data
                    : [...current, ...data.transactions.data]);
                setHasMorePages(data.transactions.paginatorInfo.hasMorePages);
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    }, [currentPage, searchQuery, filters, hasMorePages]);

    const onCreate = (createdItems: any) => {
        const normalizedItems = (Array.isArray(createdItems) ? createdItems : [createdItems])
            .filter(Boolean)
            .sort((left, right) => right.id - left.id);

        if (normalizedItems.length === 0) {
            return;
        }

        setTransactions((current) => [...normalizedItems, ...current]);

        normalizedItems.forEach((item, index) => {
            setTimeout(() => animateRowItem(item.id), index * 75);
        });
    };

    const onUpdate = (updatedItem: any) => {
        setTransactions((current) => current.map((transaction) => {
            if (transaction.id === updatedItem.id) {
                return updatedItem;
            }

            return transaction;
        }));
        animateRowItem(updatedItem.id);
    };

    const onDelete = (deletedItem: any) => {
        (animateRowItem as any)(deletedItem.id, 'deleted', () => {
            setTransactions((current) => current.filter((item) => item.id != deletedItem.id));
        });
    };

    const performSearchHandler = (e: any) => {
        const value = e.target.value ?? '';
        const url = new URL(window.location.href);

        if (value) {
            url.searchParams.set('search', value);
        } else {
            url.searchParams.delete('search');
        }

        window.history.pushState({}, '', url);
        setCurrentPage(1);
        setSearchQuery(value);
    };

    const performSearch = useMemo(() => debounce(performSearchHandler, 300), []);
    const localizedAllAccounts = useMemo(() => withLocalizedNames(allAccounts, activeLocale), [allAccounts, activeLocale]);
    const localizedAccountMap = useMemo(
        () => new Map(localizedAllAccounts.map((account: any) => [String(account.id), account])),
        [localizedAllAccounts],
    );

    const localizedTransactions = useMemo(() => {
        const resolveAccount = (account: any) => {
            if (!account) {
                return null;
            }

            return localizedAccountMap.get(String(account.id)) ?? withLocalizedName(account, activeLocale);
        };

        return transactions.map((transaction) => {
            const fallbackFromAccount = transaction.fromAccount
                ?? (transaction.transaction_type === 'DEBIT' ? transaction.account : null);
            const fallbackToAccount = transaction.toAccount
                ?? (transaction.transaction_type === 'CREDIT' ? transaction.account : null);

            return {
                ...transaction,
                account: resolveAccount(transaction.account),
                fromAccount: resolveAccount(fallbackFromAccount),
                toAccount: resolveAccount(fallbackToAccount),
            };
        });
    }, [transactions, localizedAccountMap, activeLocale]);

    const handleFiltersApply = (newFilters: any) => {
        const url = new URL(window.location.href);

        if (newFilters.accountId) {
            url.searchParams.set('account', newFilters.accountId);
        } else {
            url.searchParams.delete('account');
        }

        if (newFilters.fromAccountId) {
            url.searchParams.set('fromAccount', newFilters.fromAccountId);
        } else {
            url.searchParams.delete('fromAccount');
        }

        if (newFilters.toAccountId) {
            url.searchParams.set('toAccount', newFilters.toAccountId);
        } else {
            url.searchParams.delete('toAccount');
        }

        if (newFilters.dateFrom && newFilters.dateTo) {
            url.searchParams.set('dateFrom', newFilters.dateFrom);
            url.searchParams.set('dateTo', newFilters.dateTo);
        } else {
            url.searchParams.delete('dateFrom');
            url.searchParams.delete('dateTo');
        }

        window.history.pushState({}, '', url);
        setCurrentPage(1);
        setFilters(newFilters);
    };

    const clearFilter = (filterKey: string) => {
        const updatedFilters = { ...filters };

        switch (filterKey) {
            case 'account':
                updatedFilters.accountId = '';
                break;
            case 'fromAccount':
                updatedFilters.fromAccountId = '';
                break;
            case 'toAccount':
                updatedFilters.toAccountId = '';
                break;
            case 'date':
                updatedFilters.dateFrom = '';
                updatedFilters.dateTo = '';
                break;
        }

        handleFiltersApply(updatedFilters);
    };

    const handleDateChange = (newDateRange: DateRange | undefined) => {
        if (newDateRange?.from && newDateRange?.to) {
            setDateRange(newDateRange);
        }
    };

    const getAccountById = (accountId: string) => {
        if (!accountId) {
            return null;
        }

        return localizedAllAccounts.find((account: any) => account.id == accountId) ?? null;
    };

    const renderAccountCell = (account: any) => {
        if (!account) {
            return '-';
        }

        const sharedOwnerLabel = getSharedAccountOwnerLabel(account, (ownerName: string) => t('account.sharedBy', { name: ownerName }));

        return (
            <div className="space-y-1">
                <p className="font-medium">{account.name}</p>
                {sharedOwnerLabel && (
                    <p className="text-xs text-muted-foreground">{sharedOwnerLabel}</p>
                )}
                <p className="text-xs text-muted-foreground">{account.currency}</p>
            </div>
        );
    };

    const columns = useMemo<ColumnDef<any>[]>(() => [
        {
            id: 'date',
            header: t('transaction.date'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-sm text-muted-foreground">
                    {row.original.created_at ?? '-'}
                </span>
            ),
        },
        {
            id: 'sourceAccount',
            header: t('transaction.sourceAccount'),
            cell: ({ row }) => renderAccountCell(row.original.fromAccount ?? row.original.account),
        },
        {
            id: 'destinationAccount',
            header: t('transaction.destinationAccount'),
            cell: ({ row }) => renderAccountCell(row.original.toAccount),
        },
        {
            accessorKey: 'note',
            header: t('transaction.note'),
            cell: ({ row }) => row.original.note ? <Badge variant="secondary">{row.original.note}</Badge> : '-',
        },
        {
            accessorKey: 'amount',
            header: t('transaction.amount'),
            cell: ({ row }) => {
                const transaction = row.original;
                const isIncomeTransaction = isCreditTransaction(transaction);

                return (
                    <p className={`${isIncomeTransaction ? 'text-green-500' : 'text-red-500'} whitespace-nowrap text-right`}>
                        {isIncomeTransaction ? '' : '-'}{transaction.currency} {formatNumber(transaction.amount, '')}
                    </p>
                );
            },
        },
        {
            id: 'actions',
            header: () => <div className="text-right">{t('common.actions')}</div>,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <Button variant="outline" size="sm" onClick={() => setEditItem(row.original)}>
                        {t('common.edit')}
                    </Button>
                </div>
            ),
        },
    ], [t]);

    const header = (
        <div className="flex w-full items-center justify-between">
            <h2>{t('transaction.title')}</h2>
            <div className="flex items-center gap-2">
                <DatePickerWithRange
                    onDateChange={handleDateChange}
                    initialDate={dateRange}
                />
                <RecordTransactionButton
                    accounts={localizedAllAccounts}
                    onSuccess={onCreate}
                />
            </div>
        </div>
    );

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('transaction.title')} />

            <Edit
                transaction={editItem}
                accounts={localizedAllAccounts}
                onUpdate={onUpdate}
                onDelete={onDelete}
                onClose={() => setEditItem(null)}
            />

            <div className="p-4">
                <div className="mx-auto grid max-w-7xl gap-4">
                    <TransactionStats dateRange={dateRange} />

                    <div className="flex justify-between gap-2">
                        <Input
                            name="search"
                            placeholder={t('common.search')}
                            className="max-w-56"
                            defaultValue={searchQuery}
                            onChange={performSearch}
                        />
                        <div className="flex gap-2">
                            {filters.accountId && (
                                <Badge
                                    variant="secondary"
                                    className="h-9 cursor-pointer gap-1.5 rounded-full px-3 transition-colors hover:bg-secondary/80"
                                    onClick={() => clearFilter('account')}
                                >
                                    {getAccountById(filters.accountId)?.name}
                                    <X size={14} weight="bold" />
                                </Badge>
                            )}
                            {filters.fromAccountId && (
                                <Badge
                                    variant="secondary"
                                    className="h-9 cursor-pointer gap-1.5 rounded-full px-3 transition-colors hover:bg-secondary/80"
                                    onClick={() => clearFilter('fromAccount')}
                                >
                                    {getAccountById(filters.fromAccountId)?.name}
                                    <X size={14} weight="bold" />
                                </Badge>
                            )}
                            {filters.toAccountId && (
                                <Badge
                                    variant="secondary"
                                    className="h-9 cursor-pointer gap-1.5 rounded-full px-3 transition-colors hover:bg-secondary/80"
                                    onClick={() => clearFilter('toAccount')}
                                >
                                    {getAccountById(filters.toAccountId)?.name}
                                    <X size={14} weight="bold" />
                                </Badge>
                            )}
                            {filters.dateFrom && filters.dateTo && (
                                <Badge
                                    variant="secondary"
                                    className="h-9 cursor-pointer gap-1.5 rounded-full px-3 transition-colors hover:bg-secondary/80"
                                    onClick={() => clearFilter('date')}
                                >
                                    {filters.dateFrom} - {filters.dateTo}
                                    <X size={14} weight="bold" />
                                </Badge>
                            )}
                            <Filters
                                accounts={localizedAllAccounts}
                                onApply={handleFiltersApply}
                                activeFilters={filters}
                            />
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={localizedTransactions}
                        loading={loading}
                        loadingMessage={t('common.loading')}
                        emptyMessage={t('common.noResults')}
                        getRowId={(transaction) => transaction.id}
                    />

                    {transactions.length > 0 && (
                        <LoadMore
                            hasContent={transactions.length > 0}
                            hasMorePages={hasMorePages}
                            loading={loading}
                            onClick={() => setCurrentPage(currentPage + 1)}
                        />
                    )}
                </div>
            </div>
        </Authenticated>
    );
}
