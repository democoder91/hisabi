import { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { debounce } from 'lodash';
import { type ColumnDef } from '@tanstack/react-table';

import { getBudgets } from '@/Api/budgets';
import { getAllCategories } from '@/Api/categories';
import Authenticated from '@/Layouts/Authenticated';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { animateRowItem, formatNumber } from '@/Utils';

import Create from './Create';
import Edit from './Edit';
import { BudgetCategory, BudgetRecord } from './types';

export default function Index({ auth }: { auth: any }) {
    const { t } = useTranslation();
    const [budgets, setBudgets] = useState<BudgetRecord[]>([]);
    const [categories, setCategories] = useState<BudgetCategory[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [editBudget, setEditBudget] = useState<BudgetRecord | null>(null);

    useEffect(() => {
        Promise.all([getBudgets(), getAllCategories()])
            .then(([{ data: budgetData }, { data: categoryData }]) => {
                setBudgets(budgetData.budgets);
                setCategories(categoryData.allCategories);
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

    const filteredBudgets = useMemo(() => {
        if (!searchQuery) {
            return budgets;
        }

        const normalizedQuery = searchQuery.toLowerCase();

        return budgets.filter((budget) => {
            const englishName = budget.name_translations?.en?.toLowerCase() ?? '';
            const arabicName = budget.name_translations?.ar?.toLowerCase() ?? '';

            return budget.name.toLowerCase().includes(normalizedQuery)
                || englishName.includes(normalizedQuery)
                || arabicName.includes(normalizedQuery);
        });
    }, [budgets, searchQuery]);

    const columns = useMemo<ColumnDef<BudgetRecord>[]>(() => [
        {
            accessorKey: 'name',
            header: t('budget.name'),
            cell: ({ row }) => <p className="font-medium">{row.original.name}</p>,
        },
        {
            accessorKey: 'amount',
            header: t('budget.amount'),
            cell: ({ row }) => <span className="whitespace-nowrap">{row.original.currency} {formatNumber(row.original.amount, null)}</span>,
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
            id: 'categories',
            header: t('budget.categories'),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.categories.map((category) => category.name).join(', ') || ' - '}
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
            cell: ({ row }) => <span className="whitespace-nowrap">{row.original.currency} {formatNumber(row.original.total_transactions_amount, null)}</span>,
        },
        {
            accessorKey: 'remaining_to_spend',
            header: t('budget.remaining'),
            cell: ({ row }) => <span className="whitespace-nowrap">{row.original.currency} {formatNumber(row.original.remaining_to_spend, null)}</span>,
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
                categories={categories}
                onClose={() => setShowCreate(false)}
                onCreate={onCreate}
            />

            <Edit
                budget={editBudget}
                categories={categories}
                onClose={() => setEditBudget(null)}
                onDelete={onDelete}
                onUpdate={onUpdate}
            />

            <div className="p-4">
                <div className="max-w-7xl mx-auto grid gap-4">
                    {(budgets.length > 0 || searchQuery) && (
                        <Input
                            name="search"
                            placeholder={t('budget.searchBudgets')}
                            className="max-w-56"
                            onChange={performSearch}
                        />
                    )}

                    <DataTable
                        columns={columns}
                        data={filteredBudgets}
                        emptyMessage={searchQuery ? t('common.noResults') : t('budget.noBudgets')}
                        getRowId={(budget) => budget.id}
                    />
                </div>
            </div>
        </Authenticated>
    );
}