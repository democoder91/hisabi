import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import CreateTransaction from '@/components/Domain/CreateTransaction';

interface RecordTransactionButtonProps {
    categories: any[];
    accounts: any[];
    onSuccess?: (transaction: any) => void;
    className?: string;
}

export default function RecordTransactionButton({ categories, accounts, onSuccess, className }: RecordTransactionButtonProps) {
    const { t } = useTranslation();
    const [showCreate, setShowCreate] = useState(false);
    const editableAccounts = accounts.filter((account) => account.canEditTransactions);

    const handleCreate = (transaction: any) => {
        if (onSuccess) {
            onSuccess(transaction);
        }
        setShowCreate(false);
    };

    return (
        <>
            <Button onClick={() => setShowCreate(true)} className={className} disabled={editableAccounts.length === 0}>
                {t('transaction.recordTransaction')}
            </Button>

            <CreateTransaction
                showCreate={showCreate}
                accounts={accounts}
                categories={categories}
                onCreate={handleCreate}
                onClose={() => setShowCreate(false)}
            />
        </>
    );
}
