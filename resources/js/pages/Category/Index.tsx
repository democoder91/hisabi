import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Head, Link, usePage } from '@inertiajs/react';
import { debounce } from 'lodash';
import { startOfMonth, endOfMonth } from 'date-fns';
import { DateRange } from 'react-day-picker';
import { type ColumnDef } from '@tanstack/react-table';

import Authenticated from '@/Layouts/Authenticated';
import Edit from './Edit';
import Create from './Create';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { getAllCategories } from '@/Api';
import { useActiveLocale } from '@/hooks/useActiveLocale';
import { animateRowItem } from '@/Utils';
import { withLocalizedNames } from '@/Utils';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import CategoryStats from '@/components/Domain/CategoryStats';
import { getCategoryIcon } from '@/Utils/categoryIcons';
import { DatePickerWithRange } from '@/components/ui/date-picker-with-range';

interface Category {
    id: number;
    name: string;
    type: string;
    color: string;
    icon: string;
    transactionsCount: number;
}

interface GroupedCategories {
    INCOME: Category[];
    EXPENSES: Category[];
    SAVINGS: Category[];
    INVESTMENT: Category[];
}

export default function Index({ auth }: { auth: any }) {
    const { t } = useTranslation();
    const { direction = 'ltr' } = usePage().props as { direction?: 'ltr' | 'rtl' };
    const activeLocale = useActiveLocale();
    const [categories, setCategories] = useState<Category[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [editCategory, setEditCategory] = useState<Category | null>(null);
    const [showCreate, setShowCreate] = useState(false);
    const [dateRange, setDateRange] = useState<DateRange>({
        from: startOfMonth(new Date()),
        to: endOfMonth(new Date()),
    });

    useEffect(() => {
        getAllCategories()
            .then(({ data }) => {
                setCategories(data.allCategories);
            })
            .catch(console.error);
    }, []);

    const onCreate = (createdItem: Category) => {
        setShowCreate(false);
        setCategories([createdItem, ...categories]);

        animateRowItem(createdItem.id);
    };

    const onUpdate = (updatedItem: Category) => {
        setCategories(categories.map((category) => {
            if (category.id === updatedItem.id) {
                return updatedItem;
            }

            return category;
        }));
        animateRowItem(updatedItem.id);
    };

    const onDelete = (deletedItem: Category) => {
        // @ts-ignore
        animateRowItem(deletedItem.id, 'deleted', () => {
            setCategories(categories.filter((item) => item.id != deletedItem.id));
        });
    };

    const performSearchHandler = (e: any) => {
        setSearchQuery(e.target.value ?? '');
    };

    const performSearch = useMemo(
        () => debounce(performSearchHandler, 300),
        []
    );

    const localizedCategories = useMemo(
        () => withLocalizedNames(categories, activeLocale),
        [categories, activeLocale]
    );

    const filteredCategories = useMemo(() => {
        if (!searchQuery) {
            return localizedCategories;
        }

        return localizedCategories.filter((category) =>
            category.name.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }, [localizedCategories, searchQuery]);

    const groupedCategories = useMemo<GroupedCategories>(() => {
        const grouped: GroupedCategories = {
            INCOME: [],
            EXPENSES: [],
            SAVINGS: [],
            INVESTMENT: [],
        };

        filteredCategories.forEach((category) => {
            if (grouped[category.type as keyof GroupedCategories]) {
                grouped[category.type as keyof GroupedCategories].push(category);
            }
        });

        return grouped;
    }, [filteredCategories]);

    const handleDateChange = (newDateRange: DateRange | undefined) => {
        if (newDateRange?.from && newDateRange?.to) {
            setDateRange(newDateRange);
        }
    };

    const columns = useMemo<ColumnDef<Category>[]>(() => [
        {
            accessorKey: 'name',
            header: t('category.name'),
            cell: ({ row }) => {
                const category = row.original;
                const CategoryIcon = category.icon ? getCategoryIcon(category.icon) : null;

                return (
                    <div className="flex items-center gap-3">
                        {CategoryIcon ? (
                            <div className={`badge badge-${category.color} flex size-10 items-center justify-center rounded-full`}>
                                <CategoryIcon className="h-5 w-5" />
                            </div>
                        ) : (
                            <Badge
                                className={`badge badge-${category.color} h-3 w-3 rounded-full p-0`}
                                variant="outline"
                            />
                        )}
                        <p className="font-medium">{category.name}</p>
                    </div>
                );
            },
        },
        {
            accessorKey: 'type',
            header: t('category.type'),
            cell: ({ row }) => (
                <Badge variant="secondary">{t(`category.${row.original.type.toLowerCase()}`)}</Badge>
            ),
        },
        {
            accessorKey: 'transactionsCount',
            header: t('common.transactions'),
            cell: ({ row }) => row.original.transactionsCount,
        },
        {
            id: 'actions',
            header: () => <div className="text-end">{t('common.actions')}</div>,
            cell: ({ row }) => (
                <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/transactions?category=${row.original.id}`}>{t('common.view')}</Link>
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => setEditCategory(row.original)}>
                        {t('common.edit')}
                    </Button>
                </div>
            ),
        },
    ], [t]);

    const categoryTabs = useMemo(() => [
        {
            value: 'all',
            label: t('category.all'),
            items: filteredCategories,
        },
        {
            value: 'INCOME',
            label: t('category.income'),
            items: groupedCategories.INCOME,
        },
        {
            value: 'EXPENSES',
            label: t('category.expenses'),
            items: groupedCategories.EXPENSES,
        },
        {
            value: 'SAVINGS',
            label: t('category.savings'),
            items: groupedCategories.SAVINGS,
        },
        {
            value: 'INVESTMENT',
            label: t('category.investment'),
            items: groupedCategories.INVESTMENT,
        },
    ], [filteredCategories, groupedCategories, t]);

    const header = (
        <div className="flex w-full items-center justify-between">
            <h2>{t('category.title')}</h2>
            <div className="flex items-center gap-2">
                <DatePickerWithRange
                    onDateChange={handleDateChange}
                    initialDate={dateRange}
                />
                <Button onClick={() => setShowCreate(true)}>{t('category.createCategory')}</Button>
            </div>
        </div>
    );

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('category.title')} />

            <Create
                showCreate={showCreate}
                onCreate={onCreate}
                onClose={() => setShowCreate(false)}
            />

            <Edit
                category={editCategory}
                onUpdate={onUpdate}
                onDelete={onDelete}
                onClose={() => setEditCategory(null)}
            />

            <div className="p-4">
                <div className="mx-auto grid max-w-7xl gap-4">
                    <CategoryStats dateRange={dateRange} />

                    {categories.length > 0 ? (
                        <Tabs defaultValue="all" className="w-full">
                            <div className="mb-2 flex items-center justify-between">
                                <Input
                                    name="search"
                                    placeholder={t('common.search')}
                                    className="max-w-56"
                                    onChange={performSearch}
                                />
                                <TabsList className="h-auto flex-wrap justify-start">
                                    {categoryTabs
                                        .filter((tab) => tab.value === 'all' || tab.items.length > 0)
                                        .map((tab) => (
                                            <TabsTrigger key={tab.value} value={tab.value}>
                                                {tab.label} ({tab.items.length})
                                            </TabsTrigger>
                                        ))}
                                </TabsList>
                            </div>

                            {categoryTabs
                                .filter((tab) => tab.value === 'all' || tab.items.length > 0)
                                .map((tab) => (
                                    <TabsContent key={tab.value} value={tab.value}>
                                        <DataTable
                                            columns={columns}
                                            data={tab.items}
                                            dir={direction}
                                            emptyMessage={t('common.noResults')}
                                            getRowId={(category) => category.id}
                                        />
                                    </TabsContent>
                                ))}
                        </Tabs>
                    ) : (
                        <DataTable
                            columns={columns}
                            data={[]}
                            dir={direction}
                            emptyMessage={t('common.noResults')}
                            getRowId={(category) => category.id}
                        />
                    )}
                </div>
            </div>
        </Authenticated>
    );
}
