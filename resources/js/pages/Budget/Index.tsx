import { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { debounce } from 'lodash';
import { type ColumnDef } from '@tanstack/react-table';

import { getAllAccounts } from '@/Api/accounts';
import { getBudgets } from '@/Api/budgets';
import { getCurrencySettings } from '@/Api/settings';
import Authenticated from '@/Layouts/Authenticated';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { useActiveLocale } from '@/hooks/useActiveLocale';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { animateRowItem, formatNumber, withLocalizedName, withLocalizedNames } from '@/Utils';
import { filterBudgets } from '@/Utils/listFilters';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import Create from './Create';
import Edit from './Edit';
import { BudgetAccount, BudgetRecord } from './types';

export default function Index({ auth }: { auth: any }) {
    const { t } = useTranslation();
    const activeLocale = useActiveLocale();
    const [budgets, setBudgets] = useState<BudgetRecord[]>([]);
    const [accounts, setAccounts] = useState<BudgetAccount[]>([]);
    const [currencies, setCurrencies] = useState<{ value: string; label: string }[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [budgetTypeFilter, setBudgetTypeFilter] = useState('');
    const [recurrenceFilter, setRecurrenceFilter] = useState('');
    const [currencyFilter, setCurrencyFilter] = useState('');
    const [accountFilter, setAccountFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [editBudget, setEditBudget] = useState<BudgetRecord | null>(null);

    useEffect(() => {
        Promise.all([getBudgets(), getAllAccounts(), getCurrencySettings()])
            .then(([{ data: budgetData }, { data: accountData }, currencyPayload]) => {
                setBudgets(budgetData.budgets);
                setAccounts(accountData.allAccounts);
                setCurrencies(currencyPayload.options.currencies);
            })
            .catch(console.error);
    }, []);

    const onCreate = (budget: BudgetRecord) => {
        setShowCreate(false);
        setBudgets((current) => [budget, ...current]);
        animateRowItem(budget.id);
    };

    const onUpdate = (budget: BudgetRecord) => {
        setBudgets((current) => current.map((item) => item.id === budget.id ? budget : item));
        animateRowItem(budget.id);
    };

    const onDelete = (budget: BudgetRecord) => {
        animateRowItem(budget.id, 'deleted', () => {
            setBudgets((current) => current.filter((item) => item.id !== budget.id));
        });
    };

    const performSearch = useMemo(() => debounce((event: React.ChangeEvent<HTMLInputElement>) => {
        setSearchQuery(event.target.value ?? '');
    }, 300), []);

    const localizedAccounts = useMemo(() => withLocalizedNames(accounts, activeLocale), [accounts, activeLocale]);

    const localizedBudgets = useMemo(() => budgets.map((budget) => ({
        ...withLocalizedName(budget, activeLocale),
        accounts: withLocalizedNames(budget.accounts ?? [], activeLocale),
    })), [budgets, activeLocale]);

    const filteredBudgets = useMemo(() => filterBudgets(localizedBudgets, {
        searchQuery,
        budgetType: budgetTypeFilter,
        recurrence: recurrenceFilter,
        currency: currencyFilter,
        accountId: accountFilter,
    }), [localizedBudgets, searchQuery, budgetTypeFilter, recurrenceFilter, currencyFilter, accountFilter]);

    const hasActiveFilters = Boolean(searchQuery || budgetTypeFilter || recurrenceFilter || currencyFilter || accountFilter);

    const columns = useMemo<ColumnDef<BudgetRecord>[]>(() => [
        {
            accessorKey: 'name',
            header: t('budget.name'),
            cell: ({ row }) => <p className="font-medium">{row.original.name}</p>,
        },
        {
            accessorKey: 'amount',
            header: t('budget.amount'),
            cell: ({ row }) => <span className="whitespace-nowrap">{row.original.currency} {formatNumber(row.original.amount, '')}</span>,
        },
        {
            accessorKey: 'reoccurrence',
            header: t('budget.reoccurrence'),
            cell: ({ row }) => (
                <Badge variant="secondary">
                    {t(`budget.${row.original.reoccurrence.toLowerCase()}`)}
                </Badge>
            ),
        },
        {
            id: 'budgetType',
            header: t('budget.budgetType'),
            cell: ({ row }) => (
                <span>{row.original.saving ? t('budget.saving') : t('budget.spending')}</span>
            ),
        },
        {
            id: 'accounts',
            header: t('budget.accounts'),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.accounts.map((account) => account.name).join(', ') || ' - '}
                </span>
            ),
        },
        {
            id: 'period',
            header: t('budget.period'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-sm text-muted-foreground">
                    {row.original.start_at_date} - {row.original.end_at_date}
                </span>
            ),
        },
        {
            accessorKey: 'total_transactions_amount',
            header: t('budget.spent'),
            cell: ({ row }) => <span className="whitespace-nowrap">{row.original.currency} {formatNumber(row.original.total_transactions_amount, '')}</span>,
        },
        {
            accessorKey: 'remaining_to_spend',
            header: t('budget.remaining'),
            cell: ({ row }) => <span className="whitespace-nowrap">{row.original.currency} {formatNumber(row.original.remaining_to_spend, '')}</span>,
        },
        {
            id: 'actions',
            header: () => <div className="text-right">{t('common.actions')}</div>,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <Button variant="outline" size="sm" onClick={() => setEditBudget(row.original)}>
                        {t('common.edit')}
                    </Button>
                </div>
            ),
        },
    ], [t]);

    const header = (
        <div className="flex items-center justify-between w-full">
            <h2>{t('budget.title')}</h2>
            <Button onClick={() => setShowCreate(true)}>{t('budget.createBudget')}</Button>
        </div>
    );

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('budget.title')} />

            <Create
                open={showCreate}
                accounts={localizedAccounts}
                onClose={() => setShowCreate(false)}
                onCreate={onCreate}
            />

            <Edit
                budget={editBudget}
                accounts={localizedAccounts}
                onClose={() => setEditBudget(null)}
                onDelete={onDelete}
                onUpdate={onUpdate}
            />

            <div className="p-4">
                <div className="max-w-7xl mx-auto grid gap-4">
                    {(budgets.length > 0 || hasActiveFilters) && (
                        <div className="flex flex-wrap gap-2">
                            <Input
                                name="search"
                                placeholder={t('budget.searchBudgets')}
                                className="max-w-56"
                                onChange={performSearch}
                            />
                            <Select value={budgetTypeFilter || 'ALL'} onValueChange={(value) => setBudgetTypeFilter(value === 'ALL' ? '' : value)}>
                                <SelectTrigger className="w-full sm:w-[180px]">
                                    <SelectValue placeholder={t('budget.budgetType')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ALL">{t('budget.allBudgetTypes')}</SelectItem>
                                    <SelectItem value="spending">{t('budget.spending')}</SelectItem>
                                    <SelectItem value="saving">{t('budget.saving')}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={recurrenceFilter || 'ALL'} onValueChange={(value) => setRecurrenceFilter(value === 'ALL' ? '' : value)}>
                                <SelectTrigger className="w-full sm:w-[180px]">
                                    <SelectValue placeholder={t('budget.reoccurrence')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ALL">{t('budget.allRecurrences')}</SelectItem>
                                    <SelectItem value="CUSTOM">{t('budget.custom')}</SelectItem>
                                    <SelectItem value="DAILY">{t('budget.daily')}</SelectItem>
                                    <SelectItem value="WEEKLY">{t('budget.weekly')}</SelectItem>
                                    <SelectItem value="MONTHLY">{t('budget.monthly')}</SelectItem>
                                    <SelectItem value="YEARLY">{t('budget.yearly')}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={currencyFilter || 'ALL'} onValueChange={(value) => setCurrencyFilter(value === 'ALL' ? '' : value)}>
                                <SelectTrigger className="w-full sm:w-[180px]">
                                    <SelectValue placeholder={t('settings.nav.currencies')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ALL">{t('budget.allCurrencies')}</SelectItem>
                                    {currencies.map((currency) => (
                                        <SelectItem key={currency.value} value={currency.value}>{currency.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={accountFilter || 'ALL'} onValueChange={(value) => setAccountFilter(value === 'ALL' ? '' : value)}>
                                <SelectTrigger className="w-full sm:w-[220px]">
                                    <SelectValue placeholder={t('budget.accounts')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ALL">{t('budget.allAccounts')}</SelectItem>
                                    {localizedAccounts.map((account) => (
                                        <SelectItem key={account.id} value={String(account.id)}>{account.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <DataTable
                        columns={columns}
                        data={filteredBudgets}
                        emptyMessage={hasActiveFilters ? t('common.noResults') : t('budget.noBudgets')}
                        getRowId={(budget) => budget.id}
                    />
                </div>
            </div>
        </Authenticated>
    );
}