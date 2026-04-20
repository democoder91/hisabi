import React, { useEffect, useMemo, useState } from "react";
import { useTranslation } from 'react-i18next';

import { updateTransaction, deleteTransaction } from "../../Api";
import { Input } from '@/components/ui/input';
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { LongPressButton } from '@/components/ui/long-press-button';
import Combobox from "@/components/Global/Combobox";
import {
    getAccountOptionLabel,
    getAppCurrency,
    getSharedAccountOwnerLabel,
} from '@/Utils';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';

const mergeCurrentAccount = (accounts, currentAccount) => {
    if (!currentAccount) {
        return accounts;
    }

    if (accounts.some((item) => item.id === currentAccount.id)) {
        return accounts;
    }

    return [currentAccount, ...accounts];
};

export default function Edit({ transaction, accounts, onUpdate, onDelete, onClose }) {
    const { t } = useTranslation();
    const [amount, setAmount] = useState(0);
    const [fromAccount, setFromAccount] = useState(null);
    const [toAccount, setToAccount] = useState(null);
    const [createdAt, setCreatedAt] = useState('');
    const [note, setNote] = useState('');
    const formatSharedBy = (ownerName) => t('account.sharedBy', { name: ownerName });

    const editableAccounts = useMemo(() => {
        return accounts.filter((item) => item.canEditTransactions);
    }, [accounts]);

    const sourceAccountOptions = useMemo(() => {
        return mergeCurrentAccount(editableAccounts, fromAccount);
    }, [editableAccounts, fromAccount]);

    const destinationAccountOptions = useMemo(() => {
        return mergeCurrentAccount(
            editableAccounts.filter((item) => item.id !== fromAccount?.id),
            toAccount && toAccount.id !== fromAccount?.id ? toAccount : null,
        );
    }, [editableAccounts, fromAccount, toAccount]);

    const selectedCurrency = fromAccount?.currency || toAccount?.currency || transaction?.currency || getAppCurrency();

    const canEdit = transaction?.canEdit ?? false;

    useEffect(() => {
        if (!transaction) return;

        setAmount(transaction.amount);
        setFromAccount(transaction.fromAccount ?? transaction.account ?? null);
        setToAccount(transaction.toAccount ?? null);
        setCreatedAt(transaction.created_at);
        setNote(transaction.note ?? '');
    }, [transaction]);

    useEffect(() => {
        if (!toAccount) {
            return;
        }

        if (toAccount.id === fromAccount?.id || !destinationAccountOptions.some((item) => item.id === toAccount.id)) {
            setToAccount(destinationAccountOptions[0] ?? null);
        }
    }, [destinationAccountOptions, fromAccount, toAccount]);

    const handleUpdate = () => {
        if (!transaction) return;

        const transactionId = transaction.id;
        updateTransaction({
            id: transactionId,
            amount,
            fromAccountId: fromAccount?.id,
            toAccountId: toAccount?.id,
            createdAt,
            note,
        })
            .then(({ data }) => {
                onUpdate(data.transaction);
                onClose();
            })
            .catch(console.error);
    };

    const handleDelete = () => {
        if (!transaction) return;

        const transactionToDelete = transaction;
        deleteTransaction(transactionToDelete.id)
            .then(() => {
                onDelete(transactionToDelete);
                onClose();
            })
            .catch(console.error);
    };

    return (
        <Dialog open={!!transaction} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">Edit Transaction</DialogTitle>
                {transaction && (
                    <div className="space-y-4">
                        {!canEdit && (
                            <div className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground">
                                {t('account.viewOnlyTransactionAccess')}
                            </div>
                        )}

                        <div>
                            <Label htmlFor="amount">
                                {`${t('transaction.amount')} (${selectedCurrency})`}
                            </Label>
                            <Input
                                type="number"
                                name="amount"
                                value={amount}
                                disabled={!canEdit}
                                className="mt-1"
                                onChange={(e) => setAmount(e.target.value)}
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
                                disabled={!canEdit}
                                className="mt-1"
                                onChange={(e) => setCreatedAt(e.target.value)}
                            />
                        </div>

                        <div>
                            <Combobox
                                label={t('transaction.sourceAccount')}
                                items={sourceAccountOptions}
                                initialSelectedItem={fromAccount}
                                disabled={!canEdit}
                                onChange={(item) => canEdit && setFromAccount(item)}
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
                                disabled={!canEdit || !fromAccount}
                                onChange={(item) => canEdit && setToAccount(item)}
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
                                disabled={!canEdit}
                                className="mt-1"
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>

                        {canEdit && (
                            <div className="flex items-center justify-end pt-2 gap-2">
                                <LongPressButton onLongPress={handleDelete}>
                                    Hold to Delete
                                </LongPressButton>
                                <Button disabled={!fromAccount || !toAccount || fromAccount.id === toAccount.id || !createdAt || Number(amount) === 0} onClick={handleUpdate}>
                                    Update
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

