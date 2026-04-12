import { useEffect, useState } from "react";
import { useTranslation } from 'react-i18next';

import { updateBrand, deleteBrand } from "../../Api";
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { LongPressButton } from '@/components/ui/long-press-button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import Combobox from "@/components/Global/Combobox";
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';

export default function Edit({ categories, brand, onUpdate, onDelete, onClose }) {
    const { t } = useTranslation();
    const [nameEn, setNameEn] = useState('');
    const [nameAr, setNameAr] = useState('');
    const [nameLang, setNameLang] = useState('en');
    const [category, setCategory] = useState(null);

    useEffect(() => {
        if (!brand) return;

        const translations = brand.name_translations ?? {};
        setNameEn(translations.en ?? brand.name ?? '');
        setNameAr(translations.ar ?? '');
        if (brand.category) {
            setCategory(brand.category);
        }
    }, [brand]);

    const handleUpdate = () => {
        if (!brand || !category) return;

        const brandId = brand.id;
        updateBrand({
            id: brandId,
            name: { en: nameEn, ar: nameAr },
            categoryId: category.id
        })
        .then(({ data }) => {
            onUpdate(data.updateBrand);
            onClose();
        })
        .catch(console.error);
    };

    const handleDelete = () => {
        if (!brand) return;

        const brandToDelete = brand;
        deleteBrand(brandToDelete.id)
            .then(() => {
                onDelete(brandToDelete);
                onClose();
            })
            .catch(console.error);
    };

    const isReady = nameEn !== '' && category !== null;

    return (
        <Dialog open={!!brand} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle className="sr-only">{t('brand.editTitle')}</DialogTitle>
                {brand && (
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

                        <div className="flex items-center justify-end pt-2 gap-2">
                            <LongPressButton onLongPress={handleDelete}>
                                {t('common.holdToDelete')}
                            </LongPressButton>
                            <Button disabled={!isReady} onClick={handleUpdate}>
                                {t('common.update')}
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
