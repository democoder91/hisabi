import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { type ColumnDef } from '@tanstack/react-table';
import { WrenchIcon } from '@phosphor-icons/react';

import Authenticated from '@/Layouts/Authenticated';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';

interface ToolLogUser {
    id: number | null;
    name: string | null;
    email: string | null;
}

interface ToolLogEntry {
    id: string;
    conversationId: string;
    conversationTitle: string | null;
    agent: string;
    content: string;
    toolCalls: Array<Record<string, unknown>>;
    toolResults: Array<Record<string, unknown>>;
    toolNames: string[];
    user: ToolLogUser;
    createdAt: string;
}

interface ToolUsageProps {
    auth: {
        user: {
            is_super?: boolean;
        };
    };
    logs: ToolLogEntry[];
    filters: {
        user: string;
        tool: string;
        conversation_id: string;
        per_page: number;
    };
    pagination: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
        hasMorePages: boolean;
    };
}

const renderJson = (value: unknown): string => JSON.stringify(value, null, 2);

export default function ToolUsage({ auth, logs, filters, pagination }: ToolUsageProps) {
    const { t } = useTranslation();
    const { data, setData, get, processing } = useForm(filters);

    const header = (
        <div>
            <h2>{t('aiToolLogs.title')}</h2>
            <p className="text-sm text-muted-foreground">{t('aiToolLogs.description')}</p>
        </div>
    );

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        get(route('ai.tool-usage'), {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setData({
            user: '',
            tool: '',
            conversation_id: '',
            per_page: filters.per_page,
        });

        router.get(route('ai.tool-usage'), {
            user: '',
            tool: '',
            conversation_id: '',
            per_page: filters.per_page,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const columns = useMemo<ColumnDef<ToolLogEntry>[]>(() => [
        {
            id: 'user',
            header: t('aiToolLogs.user'),
            cell: ({ row }) => (
                <div className="space-y-1 text-sm">
                    <p className="font-medium">{row.original.user.name ?? t('aiToolLogs.systemUser')}</p>
                    <p className="text-muted-foreground">{row.original.user.email ?? '-'}</p>
                </div>
            ),
        },
        {
            id: 'conversation',
            header: t('aiToolLogs.conversation'),
            cell: ({ row }) => (
                <div className="space-y-1 text-sm">
                    <p className="font-medium">{row.original.conversationTitle ?? t('aiToolLogs.untitledConversation')}</p>
                    <p className="break-all text-muted-foreground">{row.original.conversationId}</p>
                </div>
            ),
        },
        {
            id: 'tools',
            header: t('aiToolLogs.tools'),
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-2">
                    {row.original.toolNames.map((toolName) => (
                        <Badge key={`${row.original.id}-${toolName}`} variant="secondary">
                            {toolName}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            id: 'assistantReply',
            header: t('aiToolLogs.assistantReply'),
            cell: ({ row }) => (
                <div className="max-w-md text-sm text-muted-foreground">
                    {row.original.content || t('aiToolLogs.noAssistantReply')}
                </div>
            ),
        },
        {
            id: 'toolCalls',
            header: t('aiToolLogs.toolCalls'),
            cell: ({ row }) => (
                <pre className="max-h-56 max-w-md overflow-auto whitespace-pre-wrap break-all rounded-lg bg-muted/40 p-3 text-xs">
                    {row.original.toolCalls.length > 0 ? renderJson(row.original.toolCalls) : t('aiToolLogs.noToolCalls')}
                </pre>
            ),
        },
        {
            id: 'toolResults',
            header: t('aiToolLogs.toolResults'),
            cell: ({ row }) => (
                <pre className="max-h-56 max-w-md overflow-auto whitespace-pre-wrap break-all rounded-lg bg-muted/40 p-3 text-xs">
                    {row.original.toolResults.length > 0 ? renderJson(row.original.toolResults) : t('aiToolLogs.noToolResults')}
                </pre>
            ),
        },
        {
            id: 'timestamp',
            header: t('aiToolLogs.timestamp'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-sm text-muted-foreground">
                    {new Date(row.original.createdAt).toLocaleString()}
                </span>
            ),
        },
    ], [t]);

    return (
        <Authenticated auth={auth} header={header}>
            <Head title={t('aiToolLogs.title')} />

            <div className="p-4">
                <div className="mx-auto max-w-7xl space-y-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <WrenchIcon size={18} />
                                {t('aiToolLogs.title')}
                            </CardTitle>
                            <CardDescription>
                                {t('aiToolLogs.summary', { count: pagination.total })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <form className="grid gap-3 md:grid-cols-4" onSubmit={submit}>
                                <Input
                                    value={data.user}
                                    placeholder={t('aiToolLogs.userFilter')}
                                    onChange={(event) => setData('user', event.target.value)}
                                />
                                <Input
                                    value={data.tool}
                                    placeholder={t('aiToolLogs.toolFilter')}
                                    onChange={(event) => setData('tool', event.target.value)}
                                />
                                <Input
                                    value={data.conversation_id}
                                    placeholder={t('aiToolLogs.conversationFilter')}
                                    onChange={(event) => setData('conversation_id', event.target.value)}
                                />
                                <div className="flex gap-2">
                                    <Button className="flex-1" disabled={processing} type="submit">
                                        {t('aiToolLogs.applyFilters')}
                                    </Button>
                                    <Button disabled={processing} onClick={clearFilters} type="button" variant="outline">
                                        {t('aiToolLogs.clearFilters')}
                                    </Button>
                                </div>
                            </form>

                            <DataTable
                                columns={columns}
                                data={logs}
                                emptyMessage={t('aiToolLogs.noLogs')}
                                getRowId={(log) => log.id}
                            />

                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <p className="text-sm text-muted-foreground">
                                    {t('aiToolLogs.pageSummary', {
                                        page: pagination.currentPage,
                                        lastPage: pagination.lastPage,
                                    })}
                                </p>

                                <div className="flex gap-2">
                                    <Button
                                        asChild
                                        disabled={pagination.currentPage <= 1}
                                        variant="outline"
                                    >
                                        <Link href={route('ai.tool-usage', {
                                            ...filters,
                                            page: Math.max(1, pagination.currentPage - 1),
                                        })}>
                                            {t('aiToolLogs.previous')}
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        disabled={!pagination.hasMorePages}
                                        variant="outline"
                                    >
                                        <Link href={route('ai.tool-usage', {
                                            ...filters,
                                            page: pagination.currentPage + 1,
                                        })}>
                                            {t('aiToolLogs.next')}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </Authenticated>
    );
}