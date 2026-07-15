import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Bot,
    Coins,
    FileText,
    MessageSquareText,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useT } from '@/lib/i18n';

type Chat = {
    id: number;
    title: string;
    status: string;
    context_tokens: number;
    updated_at: string;
    document: { title: string; source: string };
    latest_message: { content: string } | null;
};
export default function ChatIndex({
    chats,
    credits,
}: {
    chats: { data: Chat[] };
    credits: number;
}) {
    const t = useT();

    return (
        <>
            <Head title={t('chat')} />
            <div className="mx-auto w-full max-w-6xl p-4 md:p-8">
                <div className="mb-7 flex items-end justify-between gap-6">
                    <div>
                        <Badge variant="secondary" className="mb-3">
                            <Bot className="mr-1 size-3" /> Document-grounded
                            assistant
                        </Badge>
                        <h1 className="text-3xl font-semibold">{t('chat')}</h1>
                        <p className="mt-2 text-muted-foreground">
                            Private conversations bound to one regulatory
                            document version.
                        </p>
                    </div>
                    <div className="rounded-2xl border bg-card px-5 py-3 text-right">
                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                            {t('credits')}
                        </p>
                        <p className="text-2xl font-semibold">{credits}</p>
                    </div>
                </div>
                <div className="space-y-3">
                    {chats.data.map((chat) => (
                        <Link key={chat.id} href={`/chats/${chat.id}`}>
                            <Card className="mb-3 rounded-2xl transition hover:border-indigo-400/60 hover:shadow-md">
                                <CardContent className="flex items-center gap-4 p-5">
                                    <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-500/10 text-indigo-600">
                                        <MessageSquareText className="size-5" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <h2 className="truncate font-semibold">
                                                {chat.title ||
                                                    chat.document.title}
                                            </h2>
                                            <Badge
                                                variant={
                                                    chat.status === 'active'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {chat.status ===
                                                'closed_context_full'
                                                    ? 'Context full — start new chat.'
                                                    : chat.status.replaceAll(
                                                          '_',
                                                          ' ',
                                                      )}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                            <FileText className="size-3.5" />{' '}
                                            {chat.document.source.toUpperCase()}{' '}
                                            · Updated{' '}
                                            {new Date(
                                                chat.updated_at,
                                            ).toLocaleString()}
                                        </p>
                                        <p className="mt-1 truncate text-xs text-muted-foreground">
                                            {chat.latest_message?.content ??
                                                'No messages yet'}
                                        </p>
                                    </div>
                                    <ArrowRight className="size-5 text-muted-foreground" />
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                    {!chats.data.length && (
                        <div className="rounded-3xl border border-dashed p-14 text-center">
                            <Coins className="mx-auto mb-4 size-9 text-muted-foreground" />
                            <h2 className="font-semibold">No chats yet</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Open a document in the archive and choose “Ask
                                about this document.”
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
ChatIndex.layout = { breadcrumbs: [{ title: 'AI chat', href: '/chats' }] };
