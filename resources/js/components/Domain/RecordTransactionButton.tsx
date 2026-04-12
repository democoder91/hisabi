import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import CreateTransaction from '@/components/Domain/CreateTransaction';

interface RecordTransactionButtonProps {
    brands: any[];
    onSuccess?: (transaction: any) => void;
    className?: string;
}

export default function RecordTransactionButton({ brands, onSuccess, className }: RecordTransactionButtonProps) {
    const { t } = useTranslation();
    const [showCreate, setShowCreate] = useState(false);

    const handleCreate = (transaction: any) => {
        if (onSuccess) {
            onSuccess(transaction);
        }
        setShowCreate(false);
    };

    return (
        <>
            <Button onClick={() => setShowCreate(true)} className={className}>
                {t('transaction.recordTransaction')}
            </Button>

            <CreateTransaction
                showCreate={showCreate}
                brands={brands}
                onCreate={handleCreate}
                onClose={() => setShowCreate(false)}
            />
        </>
    );
}
