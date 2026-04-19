import * as React from 'react';
import { FormEvent, useEffect, useState } from 'react';
import { CheckIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface InteractiveQuestionOption {
    label: string;
    value: string;
    meta?: Record<string, string>;
}

export interface InteractiveQuestion {
    id: string;
    label: string;
    type: 'text' | 'select' | 'multiselect';
    options?: InteractiveQuestionOption[];
}

export interface PendingInteraction {
    status: 'pending';
    tool_name: string;
    tool_call_id: string;
    questions: InteractiveQuestion[];
}

interface InteractiveChatFormProps {
    interaction: PendingInteraction;
    disabled?: boolean;
    errorMessage?: string | null;
    onSubmit: (answers: Record<string, string | string[]>) => Promise<void> | void;
}

type FormValues = Record<string, string | string[]>;
type FieldErrors = Record<string, string>;

const buildInitialValues = (questions: InteractiveQuestion[]): FormValues => questions.reduce((carry, question) => {
    carry[question.id] = question.type === 'multiselect' ? [] : '';

    return carry;
}, {} as FormValues);

const resolveExcludedAccountValue = (questionId: string, values: FormValues): string | null => {
    const counterpartKey = questionId === 'from_account_id'
        ? 'to_account_id'
        : questionId === 'to_account_id'
            ? 'from_account_id'
            : null;

    if (counterpartKey === null) {
        return null;
    }

    const counterpartValue = values[counterpartKey];

    return typeof counterpartValue === 'string' && counterpartValue.trim() !== ''
        ? counterpartValue.trim()
        : null;
};

const resolveVisibleOptions = (question: InteractiveQuestion, values: FormValues): InteractiveQuestionOption[] => {
    const options = question.options ?? [];

    const excludedAccountValue = resolveExcludedAccountValue(question.id, values);

    if (excludedAccountValue === null) {
        return options;
    }

    return options.filter((option) => option.value !== excludedAccountValue);
};

export default function InteractiveChatForm({ interaction, disabled = false, errorMessage = null, onSubmit }: InteractiveChatFormProps) {
    const { t } = useTranslation();
    const [values, setValues] = useState<FormValues>(() => buildInitialValues(interaction.questions));
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

    useEffect(() => {
        setValues(buildInitialValues(interaction.questions));
        setFieldErrors({});
    }, [interaction]);

    useEffect(() => {
        for (const questionId of ['from_account_id', 'to_account_id']) {
            const accountQuestion = interaction.questions.find((question) => question.id === questionId);

            if (! accountQuestion) {
                continue;
            }

            const allowedValues = new Set(resolveVisibleOptions(accountQuestion, values).map((option) => option.value));
            const selectedAccountValue = values[questionId];

            if (typeof selectedAccountValue !== 'string' || selectedAccountValue === '' || allowedValues.has(selectedAccountValue)) {
                continue;
            }

            setValues((currentValues) => ({
                ...currentValues,
                [questionId]: '',
            }));

            return;
        }
    }, [interaction, values.from_account_id, values.to_account_id]);

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const nextErrors: FieldErrors = {};
        const answers: Record<string, string | string[]> = {};

        interaction.questions.forEach((question) => {
            const value = values[question.id];

            if (question.type === 'multiselect') {
                const selectedValues = Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string' && item.trim() !== '') : [];

                if (selectedValues.length === 0) {
                    nextErrors[question.id] = t('ai.interactiveFieldRequired');
                    return;
                }

                answers[question.id] = selectedValues;

                return;
            }

            const normalizedValue = typeof value === 'string' ? value.trim() : '';

            if (normalizedValue === '') {
                nextErrors[question.id] = t('ai.interactiveFieldRequired');
                return;
            }

            answers[question.id] = normalizedValue;
        });

        if (Object.keys(nextErrors).length > 0) {
            setFieldErrors(nextErrors);
            return;
        }

        setFieldErrors({});

        await onSubmit(answers);
    };

    const setTextValue = (questionId: string, value: string) => {
        setValues((currentValues) => ({
            ...currentValues,
            [questionId]: value,
        }));
    };

    const toggleMultiSelectValue = (questionId: string, value: string) => {
        setValues((currentValues) => {
            const currentSelection = Array.isArray(currentValues[questionId]) ? currentValues[questionId] as string[] : [];
            const nextSelection = currentSelection.includes(value)
                ? currentSelection.filter((selectedValue) => selectedValue !== value)
                : [...currentSelection, value];

            return {
                ...currentValues,
                [questionId]: nextSelection,
            };
        });
    };

    return (
        <form className="rounded-[28px] border border-primary/15 bg-primary/5 p-5" onSubmit={handleSubmit}>
            <div className="space-y-1">
                <p className="text-xs font-semibold uppercase tracking-[0.24em] text-primary/80">{t('ai.interactiveHeading')}</p>
                <p className="text-sm text-muted-foreground">{t('ai.interactiveDescription')}</p>
            </div>

            <div className="mt-5 space-y-4">
                {interaction.questions.map((question) => {
                    const questionError = fieldErrors[question.id];
                    const questionOptions = resolveVisibleOptions(question, values);
                    const selectedValues = Array.isArray(values[question.id]) ? values[question.id] as string[] : [];

                    return (
                        <div key={question.id} className="space-y-2">
                            <Label className="text-sm font-medium text-foreground" htmlFor={`interactive-question-${question.id}`}>
                                {question.label}
                            </Label>

                            {question.type === 'text' && (
                                <Input
                                    id={`interactive-question-${question.id}`}
                                    disabled={disabled}
                                    onChange={(event) => setTextValue(question.id, event.target.value)}
                                    placeholder={t('ai.interactiveTextPlaceholder')}
                                    value={typeof values[question.id] === 'string' ? values[question.id] as string : ''}
                                />
                            )}

                            {question.type === 'select' && (
                                <select
                                    id={`interactive-question-${question.id}`}
                                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-10 w-full rounded-md border bg-background px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                                    disabled={disabled}
                                    onChange={(event) => setTextValue(question.id, event.target.value)}
                                    value={typeof values[question.id] === 'string' ? values[question.id] as string : ''}
                                >
                                    <option value="">{t('ai.interactiveSelectPlaceholder')}</option>
                                    {questionOptions.map((option) => (
                                        <option key={`${question.id}-${option.value}`} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            )}

                            {question.type === 'multiselect' && (
                                <div className="space-y-3">
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {questionOptions.map((option) => {
                                            const isSelected = selectedValues.includes(option.value);

                                            return (
                                                <button
                                                    key={`${question.id}-${option.value}`}
                                                    type="button"
                                                    className={cn(
                                                        'flex items-center justify-between rounded-2xl border px-3 py-2 text-left text-sm transition-colors',
                                                        isSelected
                                                            ? 'border-primary/40 bg-primary/10 text-foreground'
                                                            : 'border-border/80 bg-background text-muted-foreground hover:border-primary/30 hover:text-foreground',
                                                    )}
                                                    disabled={disabled}
                                                    onClick={() => toggleMultiSelectValue(question.id, option.value)}
                                                >
                                                    <span>{option.label}</span>
                                                    <CheckIcon className={cn('size-4', isSelected ? 'opacity-100 text-primary' : 'opacity-0')} />
                                                </button>
                                            );
                                        })}
                                    </div>

                                    {selectedValues.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {selectedValues.map((selectedValue) => {
                                                const optionLabel = questionOptions.find((option) => option.value === selectedValue)?.label
                                                    ?? (question.options ?? []).find((option) => option.value === selectedValue)?.label
                                                    ?? selectedValue;

                                                return (
                                                    <Badge key={`${question.id}-selected-${selectedValue}`} variant="secondary">
                                                        {optionLabel}
                                                    </Badge>
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            )}

                            {questionError && <p className="text-sm text-destructive">{questionError}</p>}
                        </div>
                    );
                })}
            </div>

            {errorMessage && <p className="mt-4 text-sm text-destructive">{errorMessage}</p>}

            <div className="mt-5 flex flex-col gap-3 border-t border-border/60 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-muted-foreground">{t('ai.interactiveStructuredHint')}</p>
                <Button className="rounded-full" disabled={disabled} type="submit">
                    {t('ai.interactiveSubmit')}
                </Button>
            </div>
        </form>
    );
}