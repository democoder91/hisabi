import { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { debounce } from 'lodash';

import { getBudgets } from '@/Api/budgets';
import { getAllCategories } from '@/Api/categories';
import Authenticated from '@/Layouts/Authenticated';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

                    {filteredBudgets.length === 0 ? (
                        <Card>
                            <CardContent className="p-6 text-sm text-muted-foreground">
                                {t('budget.noBudgets')}
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {filteredBudgets.map((budget) => (
                                <Card key={budget.id} id={`item-${budget.id}`} className="py-0">
                                    <CardContent className="grid gap-4 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <button className="text-left font-medium hover:underline" onClick={() => setEditBudget(budget)}>
                                                    {budget.name}
                                                </button>
                                                <p className="text-sm text-muted-foreground">
                                                    AED {formatNumber(budget.amount, null)}
                                                </p>
                                            </div>
                                            <Badge variant="secondary">
                                                {t(`budget.${budget.reoccurrence.toLowerCase()}`)}
                                            </Badge>
                                        </div>

                                        <div className="space-y-2 text-sm text-muted-foreground">
                                            <p>
                                                {budget.saving ? t('budget.saving') : t('budget.spending')}
                                                {' · '}
                                                {budget.categories.map((category) => category.name).join(', ')}
                                            </p>
                                            <p>
                                                {budget.start_at_date} - {budget.end_at_date}
                                            </p>
                                            <p>
                                                {t('budget.spent')}: AED {formatNumber(budget.total_transactions_amount, null)}
                                            </p>
                                            <p>
                                                {t('budget.remaining')}: AED {formatNumber(budget.remaining_to_spend, null)}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </Authenticated>
    );
}