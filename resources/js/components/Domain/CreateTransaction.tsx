import { useEffect, useMemo, useState } from "react";
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createTransaction } from "@/Api";
import Combobox from "@/components/Global/Combobox";
import { Button } from "@/components/ui/button";
import {
    getAppCurrency,
    getTransactionTypeForCategoryType,
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
    const [isReady, setIsReady] = useState(false);
    const [loading, setLoading] = useState(false);

    const selectedCurrency = account?.currency || getAppCurrency();

    const editableAccounts = useMemo(() => {
        return accounts.filter((item) => item.canEditTransactions);
    }, [accounts]);

    const filteredCategories = useMemo(() => {
        return categories.filter((item) => isCategoryCompatibleWithTransactionType(item, transactionType));
    }, [categories, transactionType]);

    useEffect(() => {
        setIsReady(amount != 0 && createdAt != '' && account !== null && category !== null ? true : false);
    }, [amount, createdAt, account, category]);

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
        if (category && !isCategoryCompatibleWithTransactionType(category, transactionType)) {
            setCategory(null);
        }
    }, [category, transactionType]);

    const handleCategoryChange = (item) => {
        setCategory(item);

        if (item?.type) {
            setTransactionType(getTransactionTypeForCategoryType(item.type));
        }
    };

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
        })
            .then(({ data }) => {
                onCreate(data.transaction);
                // Reset form
                setCategory(null);
                setAccount(editableAccounts[0] ?? null);
                setAmount(0);
                setCreatedAt('');
                setNote('');
                setTransactionType(TRANSACTION_TYPES.DEBIT);
                setLoading(false);
                onClose();
            })
            .catch(console.error);
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
                            displayInputValue={(item) => item ? item.name : ''}
                            displayOptionValue={(item) => item ? item.name : ''}
                        />
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
                            displayInputValue={(item) => item ? item.name : ''}
                            displayOptionValue={(item) => item ? item.name : ''}
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
