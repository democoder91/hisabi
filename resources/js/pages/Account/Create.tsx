import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createAccount } from '@/Api/accounts';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AccountTranslations = {
    en?: string;
    ar?: string;
};

type AccountRecord = {
    id: number;
    name: string;
    name_translations?: AccountTranslations;
    balance: number;
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
    const [balance, setBalance] = useState('0');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) {
            setNameEn('');
            setNameAr('');
            setBalance('0');
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
                        <div className="mt-2 grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="account-name-en" className="text-xs text-muted-foreground">
                                    {t('account.lang_en')}
                                </Label>
                                <Input
                                    id="account-name-en"
                                    value={nameEn}
                                    className="mt-1"
                                    placeholder={t('account.namePlaceholder_en')}
                                    onChange={(event) => setNameEn(event.target.value)}
                                    dir="ltr"
                                />
                            </div>
                            <div>
                                <Label htmlFor="account-name-ar" className="text-xs text-muted-foreground">
                                    {t('account.lang_ar')}
                                </Label>
                                <Input
                                    id="account-name-ar"
                                    value={nameAr}
                                    className="mt-1"
                                    placeholder={t('account.namePlaceholder_ar')}
                                    onChange={(event) => setNameAr(event.target.value)}
                                    dir="rtl"
                                />
                            </div>
                        </div>
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
                    <div className="flex justify-end">
                        <Button onClick={handleCreate} disabled={loading || !nameEn.trim()}>
                            {t('common.create')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}