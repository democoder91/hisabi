import { useEffect, useState } from "react";
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { LongPressButton } from '@/components/ui/long-press-button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { updateCategory, deleteCategory } from "../../Api";
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { getCategoryIcon } from '@/Utils/categoryIcons';
import { IconColorSelector } from '@/components/ui/icon-color-selector';
import { PencilSimple } from '@phosphor-icons/react';

export default function Edit({ category, onUpdate, onDelete, onClose }) {
    const { t } = useTranslation();
    const [nameEn, setNameEn] = useState('');
    const [nameAr, setNameAr] = useState('');
    const [nameLang, setNameLang] = useState('en');
    const [type, setType] = useState('');
    const [color, setColor] = useState('gray');
    const [icon, setIcon] = useState('wallet');
    const [showIconColorSelector, setShowIconColorSelector] = useState(false);

    useEffect(() => {
        if (!category) return;

        const translations = category.name_translations ?? {};
        setNameEn(translations.en ?? category.name ?? '');
        setNameAr(translations.ar ?? '');
        setType(category.type);
        setColor(category.color);
        setIcon(category.icon || 'wallet');
    }, [category]);

    const handleUpdate = () => {
        if (!category) return;

        const categoryId = category.id;
        updateCategory({
            id: categoryId,
            name: { en: nameEn, ar: nameAr },
            type,
            color,
            icon
        })
            .then(({ data }) => {
                onUpdate(data.updateCategory);
                onClose();
            })
            .catch(console.error);
    };

    const handleDelete = () => {
        if (!category) return;

        const categoryToDelete = category;
        deleteCategory(categoryToDelete.id)
            .then(() => {
                onDelete(categoryToDelete);
                onClose();
            })
            .catch(console.error);
    };

    return (
        <>
            <Dialog open={!!category} onOpenChange={(open) => !open && onClose()}>
                <DialogContent>
                    <DialogTitle className="sr-only">{t('category.editTitle')}</DialogTitle>
                    {category && (
                        <div className="space-y-4">
                            <div className="flex justify-center">
                                <div
                                    className={`relative group size-14 rounded-full flex items-center justify-center badge badge-${color} cursor-pointer transition-all hover:ring-2 hover:ring-primary/50`}
                                    onClick={() => setShowIconColorSelector(true)}
                                >
                                    {(() => {
                                        const IconComponent = getCategoryIcon(icon);
                                        return <IconComponent size={32} weight="regular" className="text-current" />;
                                    })()}
                                    <div className="absolute -top-1 -right-1 opacity-0 group-hover:opacity-100 transition-opacity bg-primary text-primary-foreground rounded-full p-1">
                                        <PencilSimple size={10} weight="bold" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <Label>{t('category.name')}</Label>
                                <Tabs value={nameLang} onValueChange={setNameLang} className="mt-1">
                                    <TabsList className="grid w-full grid-cols-2">
                                        <TabsTrigger value="en">{t('category.lang_en')}</TabsTrigger>
                                        <TabsTrigger value="ar">{t('category.lang_ar')}</TabsTrigger>
                                    </TabsList>
                                    {nameLang === 'en' ? (
                                        <Input
                                            type="text"
                                            value={nameEn}
                                            className="mt-2"
                                            placeholder={t('category.namePlaceholder_en')}
                                            onChange={(e) => setNameEn(e.target.value)}
                                            dir="ltr"
                                        />
                                    ) : (
                                        <Input
                                            type="text"
                                            value={nameAr}
                                            className="mt-2"
                                            placeholder={t('category.namePlaceholder_ar')}
                                            onChange={(e) => setNameAr(e.target.value)}
                                            dir="rtl"
                                        />
                                    )}
                                </Tabs>
                            </div>

                            <div>
                                <Label>{t('category.type')}</Label>
                                <Tabs value={type} onValueChange={setType} className="mt-1">
                                    <TabsList className="grid w-full grid-cols-4">
                                        <TabsTrigger value="EXPENSES">{t('category.expenses')}</TabsTrigger>
                                        <TabsTrigger value="INCOME">{t('category.income')}</TabsTrigger>
                                        <TabsTrigger value="SAVINGS">{t('category.savings')}</TabsTrigger>
                                        <TabsTrigger value="INVESTMENT">{t('category.investment')}</TabsTrigger>
                                    </TabsList>
                                </Tabs>
                            </div>

                            <div className="flex items-center justify-end pt-2 gap-2">
                                <LongPressButton onLongPress={handleDelete}>
                                    {t('common.holdToDelete')}
                                </LongPressButton>
                                <Button onClick={handleUpdate}>
                                    {t('common.update')}
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <IconColorSelector
                open={showIconColorSelector}
                onOpenChange={setShowIconColorSelector}
                selectedIcon={icon}
                selectedColor={color}
                onIconChange={setIcon}
                onColorChange={setColor}
                onSave={() => { }}
            />
        </>
    );
}
