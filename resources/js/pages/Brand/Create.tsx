import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import Combobox from "@/components/Global/Combobox";
import { createBrand } from '../../Api';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';

export default function Create({ categories, showCreate, onClose, onCreate }) {
    const { t } = useTranslation();
    const [nameEn, setNameEn] = useState('');
    const [nameAr, setNameAr] = useState('');
    const [nameLang, setNameLang] = useState('en');
    const [category, setCategory] = useState(null);
    const [isReady, setIsReady] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setIsReady(nameEn !== '' && category !== null);
    }, [nameEn, category]);

    const handleCreate = () => {
        if (loading || !isReady || !category) return;

        setLoading(true);

        createBrand({
            name: { en: nameEn, ar: nameAr },
            categoryId: category.id
        })
        .then(({ data }) => {
            onCreate(data.createBrand);
            setCategory(null);
            setNameEn('');
            setNameAr('');
            setLoading(false);
            onClose();
        })
        .catch(console.error);
    };

    return (
        <Dialog open={showCreate} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">{t('brand.createTitle')}</DialogTitle>
                <div className="space-y-4">
                    <div>
                        <Label>{t('brand.name')}</Label>
                        <Tabs value={nameLang} onValueChange={setNameLang} className="mt-1">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="en">{t('brand.lang_en')}</TabsTrigger>
                                <TabsTrigger value="ar">{t('brand.lang_ar')}</TabsTrigger>
                            </TabsList>
                            {nameLang === 'en' ? (
                                <Input
                                    type="text"
                                    value={nameEn}
                                    className="mt-2"
                                    placeholder={t('brand.namePlaceholder_en')}
                                    onChange={(e) => setNameEn(e.target.value)}
                                    dir="ltr"
                                />
                            ) : (
                                <Input
                                    type="text"
                                    value={nameAr}
                                    className="mt-2"
                                    placeholder={t('brand.namePlaceholder_ar')}
                                    onChange={(e) => setNameAr(e.target.value)}
                                    dir="rtl"
                                />
                            )}
                        </Tabs>
                    </div>

                    <div>
                        <Combobox
                            label={t('brand.category')}
                            items={categories}
                            initialSelectedItem={category}
                            onChange={(item) => setCategory(item)}
                            displayInputValue={(item) => item?.name ?? ''}
                        />
                    </div>

                    <div className="flex items-center justify-end pt-2">
                        <Button
                            disabled={!isReady || loading}
                            onClick={handleCreate}
                        >
                            {t('common.create')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
