import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Clock3Icon, MenuIcon, MessageSquareIcon, PlusIcon, SparklesIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { chat } from '@/Api/ai';
import AIFinancialWidget from '@/components/Global/AIFinancialWidget';
import AIChartRenderer from '@/components/Global/AIChartRenderer';
import VoiceRecorder from '@/components/Global/VoiceRecorder';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Loader } from '@/components/ui/shadcn-io/ai/loader';
import { PromptInput, PromptInputSubmit, PromptInputTextarea, PromptInputToolbar } from '@/components/ui/shadcn-io/ai/prompt-input';
import { Response } from '@/components/ui/shadcn-io/ai/response';
import { cn } from '@/lib/utils';
import Authenticated from '@/Layouts/Authenticated';

interface ConversationSummary {
    id: string;
    title: string;
    updatedAt: string;
}

interface StoredMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    createdAt: string;
}

interface ActiveConversation {
    id: string;
    title: string;
    updatedAt: string;
    messages: StoredMessage[];
}

interface ChatMessage {
    id: string;
    content: string;
    role: 'user' | 'assistant';
    createdAt?: string;
    charts?: unknown[];
    components?: unknown[];
    suggestions?: string[];
}

interface ChatPageProps {
    auth: {
        user: {
            name: string;
            available_credits?: number;
            is_super?: boolean;
        };
    };
    conversations: ConversationSummary[];
    activeConversation: ActiveConversation | null;
}

const conversationTitleLimit = 52;

const buildConversationTitle = (prompt: string, fallbackTitle: string): string => {
    const normalizedPrompt = prompt.trim().replace(/\s+/g, ' ');

    if (normalizedPrompt === '') {
        return fallbackTitle;
    }

    return normalizedPrompt.length > conversationTitleLimit
        ? `${normalizedPrompt.slice(0, conversationTitleLimit).trimEnd()}...`
        : normalizedPrompt;
};

const createLocalMessageId = (prefix: string): string => `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

export default function AiIndex({ auth, conversations, activeConversation }: ChatPageProps) {
    const { t } = useTranslation();
    const { direction = 'ltr', locale = 'en' } = usePage<{ direction?: string; locale?: string }>().props as {
        direction?: string;
        locale?: string;
    };
    const isSuperUser = auth.user.is_super === true;
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [isHistoryOpen, setIsHistoryOpen] = useState(false);
    const [selectedConversationId, setSelectedConversationId] = useState<string | null>(activeConversation?.id ?? null);
    const [conversationItems, setConversationItems] = useState<ConversationSummary[]>(conversations);
    const [availableCredits, setAvailableCredits] = useState(auth.user.available_credits ?? 0);
    const [needsCredits, setNeedsCredits] = useState(!isSuperUser && (auth.user.available_credits ?? 0) < 1);
    const [chatHistory, setChatHistory] = useState<ChatMessage[]>(() => {
        const emptyAssistantReply = t('ai.emptyAssistantReply');

        return activeConversation?.messages.map((storedMessage) => ({
            id: storedMessage.id,
            role: storedMessage.role,
            content: storedMessage.content || (storedMessage.role === 'assistant' ? emptyAssistantReply : ''),
            createdAt: storedMessage.createdAt,
            charts: [],
            components: [],
            suggestions: [],
        })) ?? [];
    });

    useEffect(() => {
        const emptyAssistantReply = t('ai.emptyAssistantReply');

        setConversationItems(conversations);
        setSelectedConversationId(activeConversation?.id ?? null);
        setChatHistory(
            activeConversation?.messages.map((storedMessage) => ({
                id: storedMessage.id,
                role: storedMessage.role,
                content: storedMessage.content || (storedMessage.role === 'assistant' ? emptyAssistantReply : ''),
                createdAt: storedMessage.createdAt,
                charts: [],
                components: [],
                suggestions: [],
            })) ?? [],
        );
        setLoading(false);
        setNeedsCredits(false);
    }, [activeConversation, conversations, t]);

    useEffect(() => {
        setAvailableCredits(auth.user.available_credits ?? 0);
    }, [auth.user.available_credits]);

    const currentSuggestions = useMemo(() => {
        const lastAssistantMessage = [...chatHistory].reverse().find((entry) => entry.role === 'assistant');

        if (lastAssistantMessage?.suggestions && lastAssistantMessage.suggestions.length > 0) {
            return lastAssistantMessage.suggestions.slice(0, 3);
        }

        return [
            t('ai.defaultSuggestions.summary'),
            t('ai.defaultSuggestions.expenses'),
            t('ai.defaultSuggestions.savings'),
        ];
    }, [chatHistory, t]);

    const activeConversationTitle = selectedConversationId
        ? conversationItems.find((conversation) => conversation.id === selectedConversationId)?.title
        ?? activeConversation?.title
        ?? t('ai.fallbackConversationTitle')
        : t('ai.newChat');

    const desktopLayoutClass = direction === 'rtl' ? 'lg:flex-row-reverse' : 'lg:flex-row';
    const mobileSheetSide = direction === 'rtl' ? 'right' : 'left';
    const chatDisabled = loading || (!isSuperUser && availableCredits < 1);

    const updateConversationList = (conversationId: string, prompt: string) => {
        setConversationItems((currentConversations) => {
            const existingConversation = currentConversations.find((conversation) => conversation.id === conversationId);
            const nextConversation: ConversationSummary = {
                id: conversationId,
                title: existingConversation?.title ?? buildConversationTitle(prompt, t('ai.fallbackConversationTitle')),
                updatedAt: new Date().toISOString(),
            };

            return [
                nextConversation,
                ...currentConversations.filter((conversation) => conversation.id !== conversationId),
            ];
        });
    };

    const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const prompt = message.trim();

        if (prompt === '' || loading) {
            return;
        }

        const userMessage: ChatMessage = {
            id: createLocalMessageId('user'),
            role: 'user',
            content: prompt,
            createdAt: new Date().toISOString(),
            charts: [],
            components: [],
            suggestions: [],
        };

        const nextHistory = [...chatHistory, userMessage];

        setChatHistory(nextHistory);
        setMessage('');
        setNeedsCredits(false);
        setLoading(true);

        try {
            const aiResponse = await chat(
                nextHistory.map((entry) => ({
                    role: entry.role,
                    content: entry.content,
                })),
                selectedConversationId,
            );

            if (!isSuperUser && typeof aiResponse.available_credits === 'number') {
                setAvailableCredits(aiResponse.available_credits);
            }

            const nextConversationId = aiResponse.conversation_id ?? selectedConversationId;

            if (nextConversationId) {
                setSelectedConversationId(nextConversationId);
                updateConversationList(nextConversationId, prompt);
                window.history.replaceState(window.history.state, '', route('ai.chat', {
                    conversation_id: nextConversationId,
                }));
            }

            const assistantMessage: ChatMessage = {
                id: createLocalMessageId('assistant'),
                role: 'assistant',
                content: aiResponse.content,
                createdAt: new Date().toISOString(),
                charts: aiResponse.charts || [],
                components: aiResponse.components || [],
                suggestions: aiResponse.suggestions || [],
            };

            setChatHistory([...nextHistory, assistantMessage]);
        } catch (error) {
            const chatError = error as {
                status?: number;
                payload?: {
                    available_credits?: number;
                    message?: string;
                };
            };

            if (!isSuperUser && chatError.status === 402) {
                const remainingCredits = chatError.payload?.available_credits ?? 0;

                setAvailableCredits(remainingCredits);
                setNeedsCredits(true);
                setChatHistory([
                    ...nextHistory,
                    {
                        id: createLocalMessageId('assistant'),
                        role: 'assistant',
                        content: chatError.payload?.message || t('ai.creditGateTitle'),
                        createdAt: new Date().toISOString(),
                        charts: [],
                        components: [],
                        suggestions: [],
                    },
                ]);
            } else {
                console.error('AI Chat Error:', error);
                setChatHistory([
                    ...nextHistory,
                    {
                        id: createLocalMessageId('assistant'),
                        role: 'assistant',
                        content: t('ai.errorMessage'),
                        createdAt: new Date().toISOString(),
                        charts: [],
                        components: [],
                        suggestions: [],
                    },
                ]);
            }
        } finally {
            setLoading(false);
        }
    };

    const header = (
        <div className="flex w-full items-center justify-between gap-3">
            <div>
                <h2 className="font-semibold tracking-tight">{t('ai.title')}</h2>
                <p className="text-sm text-muted-foreground">{t('ai.description')}</p>
            </div>
            <div className="flex items-center gap-2">
                <Button className="lg:hidden" onClick={() => setIsHistoryOpen(true)} size="sm" type="button" variant="outline">
                    <MenuIcon className="size-4" />
                    <span>{t('ai.history')}</span>
                </Button>
                <div className="hidden items-center gap-2 rounded-full border bg-background/90 px-3 py-1 text-xs text-muted-foreground sm:flex">
                    <span className="size-2 rounded-full bg-emerald-500" />
                    <span>{t('ai.onlineStatus')}</span>
                </div>
            </div>
        </div>
    );

    const historySidebar = (
        <div className="flex h-full flex-col">
            <div className="border-b border-border/70 p-4">
                <Button asChild className="w-full justify-start rounded-2xl" size="lg">
                    <Link href={route('ai.chat')} onClick={() => setIsHistoryOpen(false)}>
                        <PlusIcon className="size-4" />
                        <span>{t('ai.newChat')}</span>
                    </Link>
                </Button>
                <div className="mt-4 space-y-1">
                    <p className="text-sm font-medium text-foreground">{t('ai.history')}</p>
                    <p className="text-xs text-muted-foreground">{t('ai.historyDescription')}</p>
                </div>
            </div>

            <ScrollArea className="flex-1">
                <div className="space-y-2 p-3">
                    {conversationItems.length > 0 ? conversationItems.map((conversation) => {
                        const isActive = conversation.id === selectedConversationId;

                        return (
                            <Link
                                key={conversation.id}
                                href={route('ai.chat', { conversation_id: conversation.id })}
                                onClick={() => setIsHistoryOpen(false)}
                                className={cn(
                                    'group flex items-start gap-3 rounded-2xl border px-3 py-3 transition-colors',
                                    isActive
                                        ? 'border-primary/30 bg-primary/10 text-foreground shadow-sm'
                                        : 'border-transparent bg-background/70 hover:border-border/70 hover:bg-accent/60',
                                )}
                            >
                                <div className={cn(
                                    'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl border',
                                    isActive ? 'border-primary/30 bg-primary/15 text-primary' : 'border-border/70 bg-muted/60 text-muted-foreground',
                                )}>
                                    <MessageSquareIcon className="size-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{conversation.title || t('ai.fallbackConversationTitle')}</p>
                                    <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                        <Clock3Icon className="size-3.5" />
                                        <span>{new Date(conversation.updatedAt).toLocaleDateString(locale, {
                                            month: 'short',
                                            day: 'numeric',
                                        })}</span>
                                    </div>
                                </div>
                            </Link>
                        );
                    }) : (
                        <div className="rounded-2xl border border-dashed border-border/80 bg-background/70 p-4 text-sm text-muted-foreground">
                            {t('ai.noConversations')}
                        </div>
                    )}
                </div>
            </ScrollArea>
        </div>
    );

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('ai.title')} />

            <Sheet open={isHistoryOpen} onOpenChange={setIsHistoryOpen}>
                <SheetContent className="w-full max-w-sm p-0" side={mobileSheetSide}>
                    <SheetHeader className="sr-only">
                        <SheetTitle>{t('ai.history')}</SheetTitle>
                        <SheetDescription>{t('ai.historyDescription')}</SheetDescription>
                    </SheetHeader>
                    {historySidebar}
                </SheetContent>
            </Sheet>

            <div className="relative h-[calc(100svh-4rem)] overflow-hidden p-4">
                <div className="pointer-events-none absolute inset-x-0 top-0 flex justify-center">
                    <div className="h-72 w-[36rem] rounded-full bg-primary/10 blur-3xl" />
                </div>

                <div className={cn('relative flex h-full gap-4', desktopLayoutClass)}>
                    <aside className="hidden h-full w-80 shrink-0 overflow-hidden rounded-[28px] border border-border/70 bg-card/85 shadow-sm backdrop-blur lg:block">
                        {historySidebar}
                    </aside>

                    <section className="flex h-full min-w-0 flex-1 flex-col overflow-hidden rounded-[30px] border border-border/70 bg-background/92 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur">
                        <div className="border-b border-border/70 px-4 py-4 md:px-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-[0.24em] text-muted-foreground">{t('ai.title')}</p>
                                    <h1 className="mt-2 text-xl font-semibold tracking-tight text-foreground md:text-2xl">{activeConversationTitle}</h1>
                                </div>
                                <div className="flex flex-wrap items-center justify-end gap-2">
                                    <Badge className="rounded-full px-3 py-1 font-medium" variant="secondary">
                                        {isSuperUser ? t('ai.unlimitedAccess') : t('ai.creditsRemaining', { count: availableCredits })}
                                    </Badge>
                                    <Badge className="rounded-full border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-emerald-700 dark:text-emerald-300" variant="outline">
                                        <span className="mr-1 size-2 rounded-full bg-emerald-500" />
                                        {t('ai.onlineStatus')}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <div className="relative flex min-h-0 flex-1 flex-col bg-[linear-gradient(180deg,rgba(255,255,255,0.03),transparent)]">
                            <ScrollArea className="min-h-0 flex-1">
                                {chatHistory.length === 0 ? (
                                    <div className="mx-auto flex h-full w-full max-w-4xl flex-col items-center justify-center px-6 py-12 text-center">
                                        <div className="flex size-[4.5rem] items-center justify-center rounded-[28px] bg-primary/10 text-primary shadow-[0_20px_60px_-30px_rgba(16,185,129,0.55)]">
                                            <SparklesIcon className="size-8" />
                                        </div>
                                        <p className="mt-6 text-sm font-medium uppercase tracking-[0.26em] text-muted-foreground">{t('ai.workspaceLabel')}</p>
                                        <h2 className="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-foreground md:text-4xl">{t('ai.emptyStateTitle')}</h2>
                                        <p className="mt-4 max-w-2xl text-sm leading-7 text-muted-foreground md:text-base">{t('ai.emptyStateDescription')}</p>
                                        <div className="mt-8 grid w-full gap-3 sm:grid-cols-3">
                                            {currentSuggestions.map((suggestion) => (
                                                <button
                                                    key={suggestion}
                                                    className="rounded-2xl border border-border/80 bg-background/80 px-4 py-4 text-start text-sm text-foreground transition-colors hover:border-primary/30 hover:bg-primary/5"
                                                    onClick={() => setMessage(suggestion)}
                                                    type="button"
                                                >
                                                    {suggestion}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="mx-auto flex w-full max-w-4xl flex-col px-4 py-6 md:px-8 md:py-8">
                                        {chatHistory.map((entry) => {
                                            const isUserMessage = entry.role === 'user';

                                            return (
                                                <div key={entry.id} className={cn('py-5', isUserMessage ? '' : 'border-b border-border/50')}>
                                                    <div className={cn('flex gap-4', isUserMessage ? 'justify-end' : 'items-start')}>
                                                        {!isUserMessage && (
                                                            <Avatar className="mt-1 size-10 border border-primary/20 bg-primary/10 text-primary">
                                                                <AvatarFallback className="bg-primary/10 text-primary">
                                                                    AI
                                                                </AvatarFallback>
                                                            </Avatar>
                                                        )}

                                                        <div className={cn('space-y-4', isUserMessage ? 'max-w-2xl' : 'min-w-0 flex-1')}>
                                                            <div className={cn(
                                                                isUserMessage
                                                                    ? 'rounded-[28px] bg-primary px-5 py-4 text-sm leading-7 text-primary-foreground shadow-lg shadow-primary/15'
                                                                    : 'text-sm leading-7 text-foreground',
                                                            )}>
                                                                {isUserMessage ? (
                                                                    <div className="whitespace-pre-wrap">{entry.content}</div>
                                                                ) : (
                                                                    <Response className="[&>*:first-child]:mt-0 [&>*:last-child]:mb-0">{entry.content}</Response>
                                                                )}
                                                            </div>

                                                            {!isUserMessage && entry.charts && entry.charts.length > 0 && (
                                                                <div className="space-y-4">
                                                                    {entry.charts.map((chart, index) => (
                                                                        <AIChartRenderer key={`${entry.id}-chart-${index}`} chart={chart} />
                                                                    ))}
                                                                </div>
                                                            )}

                                                            {!isUserMessage && entry.components && entry.components.length > 0 && (
                                                                <div className="space-y-4">
                                                                    {entry.components.map((component, index) => (
                                                                        <AIFinancialWidget key={`${entry.id}-component-${index}`} widget={component} />
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </div>

                                                        {isUserMessage && (
                                                            <Avatar className="mt-1 size-10 border border-border/70 bg-background text-foreground">
                                                                <AvatarFallback>{auth.user.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                                                            </Avatar>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}

                                        {loading && (
                                            <div className="flex items-center gap-3 py-6 text-sm text-muted-foreground">
                                                <Loader size={18} />
                                                <span>{t('ai.loading')}</span>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </ScrollArea>

                            <div className="border-t border-border/70 bg-background/95 px-4 py-4 md:px-6">
                                <div className="mx-auto w-full max-w-4xl space-y-3">
                                    {!isSuperUser && (needsCredits || availableCredits < 1) && (
                                        <div className="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                            <p className="font-medium">{t('ai.creditGateTitle')}</p>
                                            <p className="mt-1 text-xs opacity-80">{t('ai.creditGateDescription')}</p>
                                            <Button asChild className="mt-3 rounded-full" variant="outline">
                                                <Link href={route('billing.index')}>{t('ai.openBilling')}</Link>
                                            </Button>
                                        </div>
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        {currentSuggestions.map((suggestion) => (
                                            <button
                                                key={suggestion}
                                                className="rounded-full border border-border/80 bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:border-primary/30 hover:text-foreground"
                                                onClick={() => setMessage(suggestion)}
                                                type="button"
                                            >
                                                {suggestion}
                                            </button>
                                        ))}
                                    </div>

                                    <PromptInput className="overflow-hidden rounded-[28px] border-border/80 bg-background shadow-lg shadow-black/5" onSubmit={handleSubmit}>
                                        <PromptInputTextarea
                                            disabled={chatDisabled}
                                            onChange={(event) => setMessage(event.target.value)}
                                            placeholder={t('ai.placeholder')}
                                            value={message}
                                        />
                                        <PromptInputToolbar className="px-2 py-2">
                                            <div className="flex items-center gap-2">
                                                <VoiceRecorder
                                                    disabled={chatDisabled}
                                                    onTranscript={(text) => setMessage(text)}
                                                />
                                                <span className="hidden text-xs text-muted-foreground sm:inline">{t('ai.inputHelper')}</span>
                                            </div>
                                            <PromptInputSubmit
                                                disabled={loading || message.trim() === '' || (!isSuperUser && availableCredits < 1)}
                                                status={loading ? 'streaming' : 'idle'}
                                            />
                                        </PromptInputToolbar>
                                    </PromptInput>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </Authenticated>
    );
}