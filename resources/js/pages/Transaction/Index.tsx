import { useEffect, useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Head, router } from '@inertiajs/react';
import { debounce } from 'lodash';
import { startOfMonth, endOfMonth } from 'date-fns';
import { DateRange } from 'react-day-picker';

import Authenticated from '@/Layouts/Authenticated';
import Edit from './Edit';
import RecordTransactionButton from '@/components/Domain/RecordTransactionButton';
import Filters from './Filters';
import LoadMore from '@/components/Global/LoadMore';
import { Button } from '@/components/ui/button';
import { getTransactions, getAllAccounts, getTransactionFormOptions } from '@/Api';
import { animateRowItem, formatNumber, getAppCurrency, isCreditTransaction } from '@/Utils';
import { Card, CardContent } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { ArrowElbowDownRightIcon, X } from '@phosphor-icons/react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import TransactionStats from '@/components/Domain/TransactionStats';
import { getCategoryIcon } from '@/Utils/categoryIcons';
import { DatePickerWithRange } from '@/components/ui/date-picker-with-range';


export default function Index({ auth }: { auth: any }) {
    const { t } = useTranslation();
    const urlParams = new URLSearchParams(window.location.search);
    const initialSearch = urlParams.get('search') || '';

    // Initialize filters from URL
    const initialFilters = {
        categoryId: urlParams.get('category') || '',
        transactionType: urlParams.get('type') || '',
        dateFrom: urlParams.get('dateFrom') || '',
        dateTo: urlParams.get('dateTo') || '',
    };

    const [transactions, setTransactions] = useState<any[]>([]);
    const [allAccounts, setAllAccounts] = useState<any[]>([]);
    const [allCategories, setAllCategories] = useState<any[]>([]);
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
                setAllAccounts(data.allAccounts)
            })
            .catch(console.error);

        getTransactionFormOptions()
            .then(({ data }) => {
                setAllCategories(data.categories)
            })
            .catch(console.error);
    }, []);

    useEffect(() => {
        if (currentPage > 1 && !hasMorePages) return;

        setLoading(true);

        getTransactions(currentPage, searchQuery, filters)
            .then(({ data }) => {
                const newTransactions = currentPage === 1
                    ? data.transactions.data
                    : [...transactions, ...data.transactions.data];

                setTransactions(newTransactions)
                setHasMorePages(data.transactions.paginatorInfo.hasMorePages)
                setLoading(false);
            })
            .catch(console.error);
    }, [currentPage, searchQuery, filters]);

    const onCreate = (createdItem: any) => {
        setTransactions([createdItem, ...transactions]);
        animateRowItem(createdItem.id);
    };

    const onUpdate = (updatedItem: any) => {
        setTransactions(transactions.map(transaction => {
            if (transaction.id === updatedItem.id) {
                return updatedItem;
            }
            return transaction;
        }));
        animateRowItem(updatedItem.id);
    };

    const onDelete = (deletedItem: any) => {
        (animateRowItem as any)(deletedItem.id, 'deleted', () => {
            setTransactions(transactions.filter(item => item.id != deletedItem.id));
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
    }

    const performSearch = useMemo(
        () => debounce(performSearchHandler, 300)
        , []);

    const handleFiltersApply = (newFilters: any) => {
        const url = new URL(window.location.href);

        // Update URL params for filters
        if (newFilters.categoryId) {
            url.searchParams.set('category', newFilters.categoryId);
        } else {
            url.searchParams.delete('category');
        }

        if (newFilters.transactionType) {
            url.searchParams.set('type', newFilters.transactionType);
        } else {
            url.searchParams.delete('type');
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
            case 'category':
                updatedFilters.categoryId = '';
                break;
            case 'type':
                updatedFilters.transactionType = '';
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

    const header = (
        <div className="flex items-center justify-between w-full">
            <h2>{t('transaction.title')}</h2>
            <div className="flex items-center gap-2">
                <DatePickerWithRange
                    onDateChange={handleDateChange}
                    initialDate={dateRange}
                />
                <RecordTransactionButton
                    accounts={allAccounts}
                    categories={allCategories}
                    onSuccess={onCreate}
                />
            </div>
        </div>
    )

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('transaction.title')} />

            <Edit
                transaction={editItem}
                accounts={allAccounts}
                categories={allCategories}
                onUpdate={onUpdate}
                onDelete={onDelete}
                onClose={() => setEditItem(null)}
            />

            <div className="p-4">
                <div className="max-w-7xl mx-auto grid gap-4">

                    <TransactionStats dateRange={dateRange} />

                    <div className="flex justify-between gap-2">
                        <Input
                            name="search"
                            placeholder={t('common.search')}
                            className='max-w-56'
                            defaultValue={searchQuery}
                            onChange={performSearch}
                        />
                        <div className="flex gap-2">
                            {/* Active filter badges */}
                            {filters.categoryId && (
                                <Badge
                                    variant="secondary"
                                    className="h-9 gap-1.5 cursor-pointer hover:bg-secondary/80 transition-colors rounded-full px-3"
                                    onClick={() => clearFilter('category')}
                                >
                                    {allCategories.find((c: any) => c.id == filters.categoryId)?.name}
                                    <X size={14} weight="bold" />
                                </Badge>
                            )}
                            {filters.transactionType && (
                                <Badge
                                    variant="secondary"
                                    className="h-9 gap-1.5 cursor-pointer hover:bg-secondary/80 transition-colors rounded-full px-3"
                                    onClick={() => clearFilter('type')}
                                >
                                    {filters.transactionType === 'CREDIT' ? t('transaction.credit') : t('transaction.debit')}
                                    <X size={14} weight="bold" />
                                </Badge>
                            )}
                            {filters.dateFrom && filters.dateTo && (
                                <Badge
                                    variant="secondary"
                                    className="h-9 gap-1.5 cursor-pointer hover:bg-secondary/80 transition-colors rounded-full px-3"
                                    onClick={() => clearFilter('date')}
                                >
                                    {filters.dateFrom} - {filters.dateTo}
                                    <X size={14} weight="bold" />
                                </Badge>
                            )}
                            <Filters
                                categories={allCategories}
                                onApply={handleFiltersApply}
                                activeFilters={filters}
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        {transactions.length > 0 && transactions.map((transaction) => {
                            const hasCategory = transaction.category !== null;
                            const CategoryIcon = transaction.category?.icon
                                ? getCategoryIcon(transaction.category.icon)
                                : null;
                            const isIncomeTransaction = isCreditTransaction(transaction);
                            const transactionTypeLabel = transaction.transaction_type
                                ? t(`transaction.${transaction.transaction_type.toLowerCase()}`)
                                : null;

                            return (
                                <Card key={transaction.id} className="py-0" id={'item-' + transaction.id}>
                                    <CardContent className='flex justify-between items-center px-4 py-3'>
                                        <div className='flex gap-2 items-center'>
                                            {CategoryIcon && hasCategory ? (
                                                <div className={`size-10 rounded-full flex items-center justify-center badge badge-${transaction.category.color}`}>
                                                    <CategoryIcon size={24} weight="regular" className="text-current" />
                                                </div>
                                            ) : (
                                                <Avatar className='size-10'>
                                                    <AvatarFallback>{hasCategory ? transaction.category.name.charAt(0) : '?'}</AvatarFallback>
                                                </Avatar>
                                            )}
                                            <div>
                                                <button onClick={() => setEditItem(transaction)} className='font-medium hover:underline'>{transaction.category?.name ?? '-'}</button>
                                                <div className='flex gap-1 text-muted-foreground items-center'>
                                                    <ArrowElbowDownRightIcon size={10} weight="bold" />
                                                    <p className=' text-xs'>
                                                        {transaction.account ? <span>{transaction.account.name} - </span> : ''}
                                                        {transaction.created_at}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div className='flex gap-2 items-center'>
                                            {transactionTypeLabel && (
                                                <Badge variant="outline" className={isIncomeTransaction ? 'border-green-200 text-green-600' : 'border-red-200 text-red-600'}>
                                                    {transactionTypeLabel}
                                                </Badge>
                                            )}
                                            {transaction.note && <Badge variant="secondary">{transaction.note}</Badge>
                                            }
                                            <p className={`${isIncomeTransaction ? 'text-green-500' : 'text-red-500'} min-w-26 text-right`}> {isIncomeTransaction ? '' : '-'}{getAppCurrency()} {formatNumber(transaction.amount, null)}</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}

                        <LoadMore hasContent={transactions.length > 0} hasMorePages={hasMorePages} loading={loading} onClick={() => setCurrentPage(currentPage + 1)} />
                    </div>
                </div>
            </div>
        </Authenticated>
    );
}
