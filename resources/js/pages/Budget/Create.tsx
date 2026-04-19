import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { createBudget } from '@/Api/budgets';
import { getCurrencySettings } from '@/Api/settings';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

import CategoryMultiSelect from './CategoryMultiSelect';
import { BudgetAccount, BudgetRecord, budgetRecurrenceOptions } from './types';

type CreateBudgetProps = {
    open: boolean;
    accounts: BudgetAccount[];
    onClose: () => void;
    onCreate: (budget: BudgetRecord) => void;
};

type CurrencyOption = {
    value: string;
    label: string;
};

const getToday = () => new Date().toISOString().slice(0, 10);

export default function Create({ open, accounts, onClose, onCreate }: CreateBudgetProps) {
    const { t } = useTranslation();
    const [nameEn, setNameEn] = useState('');
    const [nameAr, setNameAr] = useState('');
    const [nameLang, setNameLang] = useState('en');
    const [amount, setAmount] = useState('0');
    const [currency, setCurrency] = useState('');
    const [currencies, setCurrencies] = useState<CurrencyOption[]>([]);
    const [startAt, setStartAt] = useState(getToday());
    const [endAt, setEndAt] = useState(getToday());
    const [period, setPeriod] = useState('1');
    const [reoccurrence, setReoccurrence] = useState<BudgetRecord['reoccurrence']>('MONTHLY');
    const [budgetType, setBudgetType] = useState<'spending' | 'saving'>('spending');
    const [selectedAccountIds, setSelectedAccountIds] = useState<number[]>([]);
    const [loading, setLoading] = useState(false);
    const selectedCurrencyLabel = currencies.find((item) => item.value === currency)?.label ?? currency;

    useEffect(() => {
        if (open) {
            getCurrencySettings()
                .then((payload) => {
                    setCurrencies(payload.options.currencies);
                    setCurrency(payload.settings.effective_currency);
                })
                .catch(console.error);
        }

        if (!open) {
            setNameEn('');
            setNameAr('');
            setNameLang('en');
            setAmount('0');
            setCurrency('');
            setStartAt(getToday());
            setEndAt(getToday());
            setPeriod('1');
            setReoccurrence('MONTHLY');
            setBudgetType('spending');
            setSelectedAccountIds([]);
            setLoading(false);
        }
    }, [open]);

    const isReady = useMemo(() => {
        if (!nameEn.trim()) {
            return false;
        }

        if (!currency || Number(amount) <= 0 || selectedAccountIds.length === 0) {
            return false;
        }

        if (reoccurrence === 'CUSTOM' && !endAt) {
            return false;
        }

        return true;
    }, [amount, currency, endAt, nameEn, reoccurrence, selectedAccountIds]);

    const handleCreate = () => {
        if (!isReady || loading) {
            return;
        }

        setLoading(true);

        createBudget({
            name: {
                en: nameEn.trim(),
                ar: nameAr.trim(),
            },
            amount: Number(amount),
            currency,
            start_at: startAt,
            end_at: reoccurrence === 'CUSTOM' ? endAt : null,
            saving: budgetType === 'saving',
            period: Number(period || 1),
            reoccurrence,
            account_ids: selectedAccountIds,
        })
            .then(({ data }) => {
                onCreate(data.createBudget);
                onClose();
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    };

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">{t('budget.createTitle')}</DialogTitle>
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
                            <Label htmlFor="budget-amount">{t('budget.amount')}</Label>
                            <Input
                                id="budget-amount"
                                className="mt-1"
                                type="number"
                                min="0"
                                step="0.01"
                                value={amount}
                                onChange={(event) => setAmount(event.target.value)}
                            />
                        </div>
                        <div>
                            <Label>{t('settings.nav.currencies')}</Label>
                            <Select value={currency} onValueChange={setCurrency}>
                                <SelectTrigger className="mt-1 h-10">
                                    <SelectValue placeholder={t('settings.preferences.currencyPlaceholder')}>
                                        {selectedCurrencyLabel}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {currencies.map((item) => (
                                        <SelectItem key={item.value} value={item.value}>
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>{t('budget.budgetType')}</Label>
                            <Select value={budgetType} onValueChange={(value) => setBudgetType(value as 'spending' | 'saving')}>
                                <SelectTrigger className="mt-1 h-10">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="spending">{t('budget.spending')}</SelectItem>
                                    <SelectItem value="saving">{t('budget.saving')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div>
                        <Label>{t('budget.reoccurrence')}</Label>
                        <Select value={reoccurrence} onValueChange={(value) => setReoccurrence(value as BudgetRecord['reoccurrence'])}>
                            <SelectTrigger className="mt-1 h-10">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {budgetRecurrenceOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {t(`budget.${option.toLowerCase()}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="budget-start-at">{t('budget.startDate')}</Label>
                            <Input
                                id="budget-start-at"
                                className="mt-1"
                                type="date"
                                value={startAt}
                                onChange={(event) => setStartAt(event.target.value)}
                            />
                        </div>
                        <div>
                            <Label htmlFor="budget-end-at">{t('budget.endDate')}</Label>
                            <Input
                                id="budget-end-at"
                                className="mt-1"
                                type="date"
                                value={endAt}
                                disabled={reoccurrence !== 'CUSTOM'}
                                onChange={(event) => setEndAt(event.target.value)}
                            />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="budget-period">{t('budget.period')}</Label>
                        <Input
                            id="budget-period"
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
                        <Label>{t('budget.accounts')}</Label>
                        <CategoryMultiSelect
                            accounts={accounts}
                            selectedAccountIds={selectedAccountIds}
                            onChange={setSelectedAccountIds}
                        />
                    </div>

                    <div className="flex justify-end">
                        <Button onClick={handleCreate} disabled={!isReady || loading}>
                            {t('common.create')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}