import { useEffect, useMemo, useState } from "react";
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createTransaction } from "@/Api";
import Combobox from "@/components/Global/Combobox";
import { Button } from "@/components/ui/button";
import {
    getAccountOptionLabel,
    getAppCurrency,
    getCategoryOptionLabel,
    getSharedAccountOwnerLabel,
    getTransactionTypeForCategoryType,
    isCategoryAvailableForAccount,
    isCategoryCompatibleWithTransactionType,
    TRANSACTION_TYPES,
} from '@/Utils';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';

export default function Create({ accounts, categories, showCreate, onClose, onCreate }) {
    const { t } = useTranslation();
    const [amount, setAmount] = useState(0);
    const [account, setAccount] = useState(null);
    const [category, setCategory] = useState(null);
    const [createdAt, setCreatedAt] = useState('');
    const [note, setNote] = useState('');
    const [transactionType, setTransactionType] = useState(TRANSACTION_TYPES.DEBIT);
    const [createReverseTransaction, setCreateReverseTransaction] = useState(false);
    const [reverseAccount, setReverseAccount] = useState(null);
    const [isReady, setIsReady] = useState(false);
    const [loading, setLoading] = useState(false);
    const formatSharedBy = (ownerName) => t('account.sharedBy', { name: ownerName });

    const selectedCurrency = account?.currency || getAppCurrency();

    const editableAccounts = useMemo(() => {
        return accounts.filter((item) => item.canEditTransactions);
    }, [accounts]);

    const reverseAccountOptions = useMemo(() => {
        return editableAccounts.filter((item) => item.id !== account?.id);
    }, [editableAccounts, account]);

    const canCreateReverseTransaction = transactionType === TRANSACTION_TYPES.CREDIT;

    const filteredCategories = useMemo(() => {
        return categories.filter((item) => {
            return isCategoryAvailableForAccount(item, account)
                && isCategoryCompatibleWithTransactionType(item, transactionType);
        });
    }, [account, categories, transactionType]);

    useEffect(() => {
        setIsReady(
            amount != 0
            && createdAt != ''
            && account !== null
            && category !== null
            && (!createReverseTransaction || reverseAccount !== null)
        );
    }, [amount, createdAt, account, category, createReverseTransaction, reverseAccount]);

    useEffect(() => {
        if (!account && editableAccounts.length > 0) {
            setAccount(editableAccounts[0]);
        }
    }, [editableAccounts, account]);

    useEffect(() => {
        if (account && !editableAccounts.some((item) => item.id === account.id)) {
            setAccount(editableAccounts[0] ?? null);
        }
    }, [editableAccounts, account]);

    useEffect(() => {
        if (category && (!isCategoryCompatibleWithTransactionType(category, transactionType)
            || !isCategoryAvailableForAccount(category, account))) {
            setCategory(null);
        }
    }, [account, category, transactionType]);

    useEffect(() => {
        if (!canCreateReverseTransaction) {
            setCreateReverseTransaction(false);
            setReverseAccount(null);

            return;
        }

        if (reverseAccountOptions.length === 0) {
            setCreateReverseTransaction(false);
            setReverseAccount(null);

            return;
        }

        if (reverseAccount && !reverseAccountOptions.some((item) => item.id === reverseAccount.id)) {
            setReverseAccount(null);
        }
    }, [canCreateReverseTransaction, reverseAccount, reverseAccountOptions]);

    const handleCategoryChange = (item) => {
        setCategory(item);

        if (item?.type) {
            setTransactionType(getTransactionTypeForCategoryType(item.type));
        }
    };

    const getCategoryLabel = (item) => getCategoryOptionLabel(item, filteredCategories);

    const handleCreate = () => {
        if (loading || !isReady) return;

        setLoading(true);

        createTransaction({
            amount,
            accountId: account?.id,
            categoryId: category?.id,
            createdAt,
            note,
            transactionType,
            createReverseTransaction,
            reverseAccountId: reverseAccount?.id,
        })
            .then(({ data }) => {
                onCreate(data.transactions ?? [data.transaction]);
                // Reset form
                setCategory(null);
                setAccount(editableAccounts[0] ?? null);
                setAmount(0);
                setCreatedAt('');
                setNote('');
                setTransactionType(TRANSACTION_TYPES.DEBIT);
                setCreateReverseTransaction(false);
                setReverseAccount(null);
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
                        <Label htmlFor="transaction_type">{t('transaction.type')}</Label>
                        <Tabs value={transactionType} onValueChange={setTransactionType} className="mt-2">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value={TRANSACTION_TYPES.DEBIT}>{t('transaction.debit')}</TabsTrigger>
                                <TabsTrigger value={TRANSACTION_TYPES.CREDIT}>{t('transaction.credit')}</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>

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
                            label={t('transaction.account')}
                            items={editableAccounts}
                            initialSelectedItem={account}
                            onChange={setAccount}
                            displayInputValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                            displayOptionValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                        />
                        {account && getSharedAccountOwnerLabel(account, formatSharedBy) && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {getSharedAccountOwnerLabel(account, formatSharedBy)}
                            </p>
                        )}
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/30 p-3 text-sm text-muted-foreground">
                        {t('settings.preferences.effectiveCurrency')}: <span className="font-medium text-foreground">{selectedCurrency}</span>
                    </div>

                    <div>
                        <Combobox
                            label={t('transaction.category')}
                            items={filteredCategories}
                            initialSelectedItem={category}
                            onChange={handleCategoryChange}
                            displayInputValue={(item) => item ? getCategoryLabel(item) : ''}
                            displayOptionValue={(item) => item ? getCategoryLabel(item) : ''}
                            getItemValue={(item) => item ? `${getCategoryLabel(item)} ${item.id}` : ''}
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
                            className="mt-1"
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </div>

                    {canCreateReverseTransaction && (
                        <div className="space-y-3 rounded-lg border border-border/60 bg-muted/30 p-3">
                            <label className="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    checked={createReverseTransaction}
                                    disabled={reverseAccountOptions.length === 0}
                                    className="mt-1 size-4 rounded border border-input"
                                    onChange={(event) => {
                                        setCreateReverseTransaction(event.target.checked);

                                        if (!event.target.checked) {
                                            setReverseAccount(null);
                                        }
                                    }}
                                />
                                <div className="space-y-1">
                                    <span className="text-sm font-medium text-foreground">
                                        {t('transaction.createReverseTransaction')}
                                    </span>
                                    <p className="text-xs text-muted-foreground">
                                        {reverseAccountOptions.length > 0
                                            ? t('transaction.createReverseTransactionHint')
                                            : t('transaction.noReverseAccountOptions')}
                                    </p>
                                </div>
                            </label>

                            {createReverseTransaction && reverseAccountOptions.length > 0 && (
                                <div>
                                    <Combobox
                                        label={t('transaction.reverseAccount')}
                                        items={reverseAccountOptions}
                                        initialSelectedItem={reverseAccount}
                                        onChange={setReverseAccount}
                                        displayInputValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                                        displayOptionValue={(item) => item ? getAccountOptionLabel(item, formatSharedBy) : ''}
                                    />
                                    {reverseAccount && getSharedAccountOwnerLabel(reverseAccount, formatSharedBy) && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {getSharedAccountOwnerLabel(reverseAccount, formatSharedBy)}
                                        </p>
                                    )}
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {t('transaction.reverseTransactionCategoryHint')}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

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
