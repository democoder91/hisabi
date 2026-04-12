import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { deleteBudget, updateBudget } from '@/Api/budgets';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LongPressButton } from '@/components/ui/long-press-button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

import { BudgetCategory, BudgetRecord, budgetRecurrenceOptions } from './types';

type EditBudgetProps = {
    budget: BudgetRecord | null;
    categories: BudgetCategory[];
    onClose: () => void;
    onDelete: (budget: BudgetRecord) => void;
    onUpdate: (budget: BudgetRecord) => void;
};

const getToday = () => new Date().toISOString().slice(0, 10);

export default function Edit({ budget, categories, onClose, onDelete, onUpdate }: EditBudgetProps) {
    const { t } = useTranslation();
    const [nameEn, setNameEn] = useState('');
    const [nameAr, setNameAr] = useState('');
    const [nameLang, setNameLang] = useState('en');
    const [amount, setAmount] = useState('0');
    const [startAt, setStartAt] = useState(getToday());
    const [endAt, setEndAt] = useState(getToday());
    const [period, setPeriod] = useState('1');
    const [reoccurrence, setReoccurrence] = useState<BudgetRecord['reoccurrence']>('MONTHLY');
    const [budgetType, setBudgetType] = useState<'spending' | 'saving'>('spending');
    const [selectedCategoryIds, setSelectedCategoryIds] = useState<number[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!budget) {
            return;
        }

        setNameEn(budget.name_translations?.en ?? budget.name ?? '');
        setNameAr(budget.name_translations?.ar ?? '');
        setNameLang('en');
        setAmount(String(budget.amount ?? 0));
        setStartAt(budget.start_at ?? getToday());
        setEndAt(budget.end_at ?? getToday());
        setPeriod(String(budget.period ?? 1));
        setReoccurrence(budget.reoccurrence);
        setBudgetType(budget.saving ? 'saving' : 'spending');
        setSelectedCategoryIds(budget.categories.map((category) => category.id));
        setLoading(false);
    }, [budget]);

    const isReady = useMemo(() => {
        if (!budget || !nameEn.trim()) {
            return false;
        }

        if (Number(amount) <= 0 || selectedCategoryIds.length === 0) {
            return false;
        }

        if (reoccurrence === 'CUSTOM' && !endAt) {
            return false;
        }

        return true;
    }, [amount, budget, endAt, nameEn, reoccurrence, selectedCategoryIds]);

    const toggleCategory = (categoryId: number) => {
        setSelectedCategoryIds((current) => current.includes(categoryId)
            ? current.filter((id) => id !== categoryId)
            : [...current, categoryId]);
    };

    const handleUpdate = () => {
        if (!budget || !isReady || loading) {
            return;
        }

        setLoading(true);

        updateBudget({
            id: budget.id,
            name: {
                en: nameEn.trim(),
                ar: nameAr.trim(),
            },
            amount: Number(amount),
            start_at: startAt,
            end_at: reoccurrence === 'CUSTOM' ? endAt : null,
            saving: budgetType === 'saving',
            period: Number(period || 1),
            reoccurrence,
            category_ids: selectedCategoryIds,
        })
            .then(({ data }) => {
                onUpdate(data.updateBudget);
                onClose();
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    };

    const handleDelete = () => {
        if (!budget) {
            return;
        }

        deleteBudget(budget.id)
            .then(({ data }) => {
                onDelete(data.deleteBudget);
                onClose();
            })
            .catch(console.error);
    };

    return (
        <Dialog open={!!budget} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">{t('budget.editTitle')}</DialogTitle>
                {budget && (
                    <div className="space-y-4">
                        <div>
                            <Label>{t('budget.name')}</Label>
                            <Tabs value={nameLang} onValueChange={(value) => setNameLang(value as 'en' | 'ar')} className="mt-1">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger value="en">{t('budget.lang_en')}</TabsTrigger>
                                    <TabsTrigger value="ar">{t('budget.lang_ar')}</TabsTrigger>
                                </TabsList>
                                {nameLang === 'en' ? (
                                    <Input
                                        className="mt-2"
                                        value={nameEn}
                                        placeholder={t('budget.namePlaceholder_en')}
                                        onChange={(event) => setNameEn(event.target.value)}
                                        dir="ltr"
                                    />
                                ) : (
                                    <Input
                                        className="mt-2"
                                        value={nameAr}
                                        placeholder={t('budget.namePlaceholder_ar')}
                                        onChange={(event) => setNameAr(event.target.value)}
                                        dir="rtl"
                                    />
                                )}
                            </Tabs>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="budget-edit-amount">{t('budget.amount')}</Label>
                                <Input
                                    id="budget-edit-amount"
                                    className="mt-1"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={amount}
                                    onChange={(event) => setAmount(event.target.value)}
                                />
                            </div>
                            <div>
                                <Label>{t('budget.budgetType')}</Label>
                                <Tabs value={budgetType} onValueChange={(value) => setBudgetType(value as 'spending' | 'saving')} className="mt-1">
                                    <TabsList className="grid w-full grid-cols-2">
                                        <TabsTrigger value="spending">{t('budget.spending')}</TabsTrigger>
                                        <TabsTrigger value="saving">{t('budget.saving')}</TabsTrigger>
                                    </TabsList>
                                </Tabs>
                            </div>
                        </div>

                        <div>
                            <Label>{t('budget.reoccurrence')}</Label>
                            <Tabs value={reoccurrence} onValueChange={(value) => setReoccurrence(value as BudgetRecord['reoccurrence'])} className="mt-1">
                                <TabsList className="grid w-full grid-cols-5">
                                    {budgetRecurrenceOptions.map((option) => (
                                        <TabsTrigger key={option} value={option}>
                                            {t(`budget.${option.toLowerCase()}`)}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </Tabs>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="budget-edit-start-at">{t('budget.startDate')}</Label>
                                <Input
                                    id="budget-edit-start-at"
                                    className="mt-1"
                                    type="date"
                                    value={startAt}
                                    onChange={(event) => setStartAt(event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="budget-edit-end-at">{t('budget.endDate')}</Label>
                                <Input
                                    id="budget-edit-end-at"
                                    className="mt-1"
                                    type="date"
                                    value={endAt}
                                    disabled={reoccurrence !== 'CUSTOM'}
                                    onChange={(event) => setEndAt(event.target.value)}
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="budget-edit-period">{t('budget.period')}</Label>
                            <Input
                                id="budget-edit-period"
                                className="mt-1"
                                type="number"
                                min="1"
                                step="1"
                                value={period}
                                disabled={reoccurrence === 'CUSTOM'}
                                onChange={(event) => setPeriod(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label>{t('budget.categories')}</Label>
                            <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {categories.map((category) => {
                                    const isSelected = selectedCategoryIds.includes(category.id);

                                    return (
                                        <button
                                            key={category.id}
                                            type="button"
                                            onClick={() => toggleCategory(category.id)}
                                            className={cn(
                                                'rounded-lg border px-3 py-2 text-sm text-left transition-colors',
                                                isSelected
                                                    ? 'border-primary bg-primary/10 text-foreground'
                                                    : 'border-border text-muted-foreground hover:border-primary/40 hover:text-foreground'
                                            )}
                                        >
                                            {category.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="flex justify-end gap-2">
                            <LongPressButton onLongPress={handleDelete}>
                                {t('common.holdToDelete')}
                            </LongPressButton>
                            <Button onClick={handleUpdate} disabled={!isReady || loading}>
                                {t('common.update')}
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}