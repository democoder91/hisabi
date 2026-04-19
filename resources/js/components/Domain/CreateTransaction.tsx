import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { createTransaction } from '@/Api';
import Combobox from '@/components/Global/Combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    getAccountOptionLabel,
    getAppCurrency,
    getSharedAccountOwnerLabel,
} from '@/Utils';

export default function Create({ accounts, showCreate, onClose, onCreate }) {
    const { t } = useTranslation();
    const [amount, setAmount] = useState(0);
    const [fromAccount, setFromAccount] = useState(null);
    const [toAccount, setToAccount] = useState(null);
    const [createdAt, setCreatedAt] = useState('');
    const [note, setNote] = useState('');
    const [loading, setLoading] = useState(false);
    const formatSharedBy = (ownerName) => t('account.sharedBy', { name: ownerName });

    const editableAccounts = useMemo(() => {
        return accounts.filter((item) => item.canEditTransactions);
    }, [accounts]);

    const destinationAccountOptions = useMemo(() => {
        return editableAccounts.filter((item) => item.id !== fromAccount?.id);
    }, [editableAccounts, fromAccount]);

    const defaultFromAccount = useMemo(() => {
        return editableAccounts.find((item) => item.type !== 'income') ?? editableAccounts[0] ?? null;
    }, [editableAccounts]);

    const defaultToAccount = useMemo(() => {
        if (!defaultFromAccount) {
            return editableAccounts[0] ?? null;
        }

        return editableAccounts.find((item) => item.id !== defaultFromAccount.id) ?? null;
    }, [defaultFromAccount, editableAccounts]);

    const selectedCurrency = fromAccount?.currency || toAccount?.currency || getAppCurrency();
    const isReady = Number(amount) !== 0
        && createdAt !== ''
        && fromAccount !== null
        && toAccount !== null
        && fromAccount.id !== toAccount.id;

    useEffect(() => {
        if (!showCreate) {
            setAmount(0);
            setFromAccount(null);
            setToAccount(null);
            setCreatedAt('');
            setNote('');
            setLoading(false);

            return;
        }

        if (!fromAccount && defaultFromAccount) {
            setFromAccount(defaultFromAccount);
        }

        if (!toAccount && defaultToAccount) {
            setToAccount(defaultToAccount);
        }
    }, [defaultFromAccount, defaultToAccount, fromAccount, showCreate, toAccount]);

    useEffect(() => {
        if (fromAccount && !editableAccounts.some((item) => item.id === fromAccount.id)) {
            setFromAccount(defaultFromAccount);
        }
    }, [defaultFromAccount, editableAccounts, fromAccount]);

    useEffect(() => {
        if (!toAccount) {
            return;
        }

        if (toAccount.id === fromAccount?.id || !destinationAccountOptions.some((item) => item.id === toAccount.id)) {
            setToAccount(destinationAccountOptions[0] ?? null);
        }
    }, [destinationAccountOptions, fromAccount, toAccount]);

    const handleCreate = () => {
        if (loading || !isReady) {
            return;
        }

        setLoading(true);

        createTransaction({
            amount: Number(amount),
            fromAccountId: fromAccount?.id,
            toAccountId: toAccount?.id,
            createdAt,
            note,
        })
            .then(({ data }) => {
                onCreate(data.transactions ?? [data.transaction]);
                onClose();
            })
            .catch(console.error)
            .finally(() => setLoading(false));
    };

    return (
        <Dialog open={showCreate} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">Create Transaction</DialogTitle>
                <div className="space-y-4">
                    <div>
                        <Label htmlFor="amount">
                            {`${t('transaction.amount')} (${selectedCurrency})`}
                        </Label>
                        <Input
                            type="number"
                            name="amount"
                            value={amount}
                            className="mt-1"
                            onChange={(e) => setAmount(e.target.value > 0 ? e.target.value : 0)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="date">
                            {t('transaction.date')}
                        </Label>
                        <Input
                            type="date"
                            name="date"
                            value={createdAt}
                            className="mt-1"
                            onChange={(e) => setCreatedAt(e.target.value)}
                        />
                    </div>

                    <div>
                        <Combobox
                            label={t('transaction.sourceAccount')}
                            items={editableAccounts}
                            initialSelectedItem={fromAccount}
                            onChange={setFromAccount}
                            displayInputValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                            displayOptionValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                        />
                        {fromAccount && getSharedAccountOwnerLabel(fromAccount, formatSharedBy) && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {getSharedAccountOwnerLabel(fromAccount, formatSharedBy)}
                            </p>
                        )}
                    </div>

                    <div>
                        <Combobox
                            label={t('transaction.destinationAccount')}
                            items={destinationAccountOptions}
                            initialSelectedItem={toAccount}
                            onChange={setToAccount}
                            disabled={!fromAccount}
                            displayInputValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                            displayOptionValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                        />
                        {toAccount && getSharedAccountOwnerLabel(toAccount, formatSharedBy) && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {getSharedAccountOwnerLabel(toAccount, formatSharedBy)}
                            </p>
                        )}
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-sm text-muted-foreground">
                        {t('settings.preferences.effectiveCurrency')}: <span className="font-medium text-foreground">{selectedCurrency}</span>
                    </div>

                    <div>
                        <Label htmlFor="note">
                            {`${t('transaction.note')} (optional)`}
                        </Label>
                        <Input
                            type="text"
                            name="note"
                            value={note}
                            className="mt-1"
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </div>

                    <div className="flex items-center justify-end pt-2">
                        <Button
                            disabled={!isReady || loading}
                            onClick={handleCreate}
                        >
                            Create
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
