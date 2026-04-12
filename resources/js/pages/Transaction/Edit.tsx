import { useEffect, useMemo, useState } from "react";
import { useTranslation } from 'react-i18next';

import { updateTransaction, deleteTransaction } from "../../Api";
import { Input } from '@/components/ui/input';
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { LongPressButton } from '@/components/ui/long-press-button';
import Combobox from "@/components/Global/Combobox";
import {
    getAppCurrency,
    getTransactionTypeForCategoryType,
    isBrandCompatibleWithTransactionType,
    TRANSACTION_TYPES,
} from '@/Utils';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';

export default function Edit({ transaction, accounts, brands, onUpdate, onDelete, onClose }) {
    const { t } = useTranslation();
    const [amount, setAmount] = useState(0);
    const [account, setAccount] = useState(null);
    const [createdAt, setCreatedAt] = useState('');
    const [brand, setBrand] = useState(null);
    const [note, setNote] = useState('');
    const [transactionType, setTransactionType] = useState(TRANSACTION_TYPES.DEBIT);

    const filteredBrands = useMemo(() => {
        return brands.filter((item) => isBrandCompatibleWithTransactionType(item, transactionType));
    }, [brands, transactionType]);

    const editableAccounts = useMemo(() => {
        return accounts.filter((item) => item.canEditTransactions);
    }, [accounts]);

    const canEdit = transaction?.canEdit ?? false;

    useEffect(() => {
        if (!transaction) return;

        setAmount(transaction.amount);
        setAccount(transaction.account);
        setBrand(transaction.brand);
        setCreatedAt(transaction.created_at);
        setNote(transaction.note ?? '');
        setTransactionType(
            transaction.transaction_type
            ?? getTransactionTypeForCategoryType(transaction.brand?.category?.type)
        );
    }, [transaction]);

    useEffect(() => {
        if (brand && !isBrandCompatibleWithTransactionType(brand, transactionType)) {
            setBrand(null);
        }
    }, [brand, transactionType]);

    const handleBrandChange = (item) => {
        setBrand(item);

        if (item?.category?.type) {
            setTransactionType(getTransactionTypeForCategoryType(item.category.type));
        }
    };

    const handleUpdate = () => {
        if (!transaction) return;

        const transactionId = transaction.id;
        updateTransaction({
            id: transactionId,
            amount,
            accountId: account?.id,
            brandId: brand?.id,
            createdAt,
            note,
            transactionType,
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
                            <Label htmlFor="transaction_type">{t('transaction.type')}</Label>
                            <Tabs value={transactionType} onValueChange={setTransactionType} className="mt-2">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger value={TRANSACTION_TYPES.DEBIT} disabled={!canEdit}>{t('transaction.debit')}</TabsTrigger>
                                    <TabsTrigger value={TRANSACTION_TYPES.CREDIT} disabled={!canEdit}>{t('transaction.credit')}</TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>

                        <div>
                            <Label htmlFor="amount">
                                {`${t('transaction.amount')} (${getAppCurrency()})`}
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
                                label={t('transaction.account')}
                                items={editableAccounts}
                                initialSelectedItem={account}
                                onChange={(item) => canEdit && setAccount(item)}
                                displayInputValue={(item) => item ? item.name : ''}
                                displayOptionValue={(item) => item ? item.name : ''}
                            />
                        </div>

                        <div>
                            <Combobox
                                label={t('transaction.brand')}
                                items={filteredBrands}
                                initialSelectedItem={brand}
                                onChange={(item) => canEdit && handleBrandChange(item)}
                                displayInputValue={(item) => item ? `${item.name} (${item.category?.name ?? 'N/A'})` : ''}
                                displayOptionValue={(item) => item ? `${item.name} (${item.category?.name ?? 'N/A'})` : ''}
                            />
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
                                <Button onClick={handleUpdate}>
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

