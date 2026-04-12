import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { createAccount } from '@/Api/accounts';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Create({ open, onClose, onCreate }) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const [balance, setBalance] = useState('0');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) {
            setName('');
            setBalance('0');
            setLoading(false);
        }
    }, [open]);

    const handleCreate = () => {
        if (loading || !name.trim()) {
            return;
        }

        setLoading(true);

        createAccount({
            name: name.trim(),
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
                        <Label htmlFor="account-name">{t('account.name')}</Label>
                        <Input id="account-name" value={name} className="mt-1" onChange={(event) => setName(event.target.value)} />
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
                        <Button onClick={handleCreate} disabled={loading || !name.trim()}>
                            {t('common.create')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}