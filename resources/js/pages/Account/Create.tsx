import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createAccount } from '@/Api/accounts';
import { getCurrencySettings } from '@/Api/settings';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

type CurrencyOption = {
    value: string;
    label: string;
};

type AccountTranslations = {
    en?: string;
    ar?: string;
};

type AccountRecord = {
    id: number;
    name: string;
    name_translations?: AccountTranslations;
    balance: number;
    currency: string;
};

type CreateAccountProps = {
    open: boolean;
    onClose: () => void;
    onCreate: (account: AccountRecord) => void;
};

export default function Create({ open, onClose, onCreate }: CreateAccountProps) {
    const { t } = useTranslation();
    const [nameEn, setNameEn] = useState('');
    const [nameAr, setNameAr] = useState('');
    const [nameLang, setNameLang] = useState<'en' | 'ar'>('en');
    const [balance, setBalance] = useState('0');
    const [currency, setCurrency] = useState('');
    const [currencies, setCurrencies] = useState<CurrencyOption[]>([]);
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
            setBalance('0');
            setCurrency('');
            setLoading(false);
        }
    }, [open]);

    const handleCreate = () => {
        if (loading || !nameEn.trim()) {
            return;
        }

        setLoading(true);

        createAccount({
            name: {
                en: nameEn.trim(),
                ar: nameAr.trim(),
            },
            balance: Number(balance || 0),
            currency,
        })
            .then(({ data }) => {
                onCreate(data.createAccount);
                onClose();
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    };

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">{t('account.createTitle')}</DialogTitle>
                <div className="space-y-4">
                    <div>
                        <Label>{t('account.name')}</Label>
                        <Tabs value={nameLang} onValueChange={(value) => setNameLang(value as 'en' | 'ar')} className="mt-1">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="en">{t('account.lang_en')}</TabsTrigger>
                                <TabsTrigger value="ar">{t('account.lang_ar')}</TabsTrigger>
                            </TabsList>
                            {nameLang === 'en' ? (
                                <Input
                                    id="account-name-en"
                                    value={nameEn}
                                    className="mt-2"
                                    placeholder={t('account.namePlaceholder_en')}
                                    onChange={(event) => setNameEn(event.target.value)}
                                    dir="ltr"
                                />
                            ) : (
                                <Input
                                    id="account-name-ar"
                                    value={nameAr}
                                    className="mt-2"
                                    placeholder={t('account.namePlaceholder_ar')}
                                    onChange={(event) => setNameAr(event.target.value)}
                                    dir="rtl"
                                />
                            )}
                        </Tabs>
                    </div>
                    <div>
                        <Label htmlFor="account-balance">{t('account.balance')}</Label>
                        <Input
                            id="account-balance"
                            type="number"
                            step="0.01"
                            value={balance}
                            className="mt-1"
                            onChange={(event) => setBalance(event.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="account-currency">{t('settings.nav.currencies')}</Label>
                        <Select value={currency} onValueChange={setCurrency}>
                            <SelectTrigger id="account-currency" className="mt-1">
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
                    <div className="flex justify-end">
                        <Button onClick={handleCreate} disabled={loading || !nameEn.trim() || !currency}>
                            {t('common.create')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}