import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Bot,
    CircleStop,
    Coins,
    Download,
    FileText,
    Send,
    Sparkles,
    UserRound,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

type Message = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
};
type Chat = {
    id: number;
    title: string;
    status: string;
    context_tokens: number;
    document: {
        id: number;
        title: string;
        source: string;
        document_type: string;
    };
    version: { version: number };
    messages: Message[];
};
export default function ChatShow({
    chat,
    credits,
    contextLimit,
    creditsResetAt,
    topupUrl,
}: {
    chat: Chat;
    credits: number;
    contextLimit: number;
    creditsResetAt: string | null;
    topupUrl: string | null;
}) {
    const [messages, setMessages] = useState(chat.messages);
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const bottom = useRef<HTMLDivElement>(null);
    const active = chat.status === 'active' && credits > 0;
    // Inertia may reuse this page component when navigating between conversations.
    // Synchronizing here ensures a newly selected chat never displays the prior chat's messages.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    useEffect(() => setMessages(chat.messages), [chat.id, chat.messages]);
    useEffect(
        () => bottom.current?.scrollIntoView({ behavior: 'smooth' }),
        [messages],
    );
    const send = async (e: FormEvent) => {
        e.preventDefault();
        const content = draft.trim();

        if (!content || busy) {
            return;
        }

        const requestId = crypto.randomUUID();
        const createdAt = new Date().toISOString();
        const assistantId = -Date.now();
        setMessages((current) => [
            ...current,
            {
                id: assistantId - 1,
                role: 'user',
                content,
                created_at: createdAt,
            },
            {
                id: assistantId,
                role: 'assistant',
                content: '',
                created_at: createdAt,
            },
        ]);
        setDraft('');
        setBusy(true);
        setError(null);

        try {
            const response = await fetch(`/chats/${chat.id}/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
                body: JSON.stringify({
                    message: content,
                    request_id: requestId,
                }),
            });

            if (!response.ok || !response.body) {
                throw new Error(
                    response.status === 422
                        ? 'The message could not be sent. Check your credits and context limit.'
                        : 'The assistant stream could not be started.',
                );
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                const frames = buffer.split('\n\n');
                buffer = frames.pop() ?? '';

                for (const frame of frames) {
                    const data = frame
                        .split('\n')
                        .find((line) => line.startsWith('data: '))
                        ?.slice(6);

                    if (!data || data === '[DONE]') {
                        continue;
                    }

                    const event = JSON.parse(data) as {
                        type?: string;
                        delta?: string;
                    };

                    if (event.type === 'text_delta' && event.delta) {
                        setMessages((current) =>
                            current.map((message) =>
                                message.id === assistantId
                                    ? {
                                          ...message,
                                          content:
                                              message.content + event.delta,
                                      }
                                    : message,
                            ),
                        );
                    }
                }
            }

            router.reload({ only: ['chat', 'credits'] });
        } catch (reason) {
            setMessages((current) =>
                current.filter((message) => message.id !== assistantId),
            );
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'The assistant request failed.',
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={chat.title} />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col p-4 md:p-8">
                <Link
                    href="/chats"
                    className="mb-4 inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="mr-1 size-4" /> All chats
                </Link>
                <Card className="flex min-h-[75vh] flex-1 flex-col overflow-hidden rounded-3xl border-border/60 shadow-lg">
                    <header className="flex flex-wrap items-center justify-between gap-4 border-b bg-muted/30 p-5">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-600 text-white">
                                <Bot className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <Link
                                    href={`/archive/${chat.document.id}`}
                                    className="truncate font-semibold hover:text-indigo-600 hover:underline"
                                >
                                    {chat.document.title}
                                </Link>
                                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                    <FileText className="size-3" />{' '}
                                    {chat.document.source.toUpperCase()} ·
                                    Version {chat.version.version} ·
                                    one-document scope
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">
                                <Coins className="mr-1 size-3" /> {credits}{' '}
                                credits
                            </Badge>
                            <div className="group relative">
                                <Button variant="outline" size="sm">
                                    <Download className="mr-1 size-4" /> Export
                                </Button>
                                <div className="invisible absolute right-0 z-10 mt-1 flex rounded-lg border bg-popover p-1 opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100">
                                    {['json', 'md', 'pdf'].map((f) => (
                                        <a
                                            key={f}
                                            href={`/chats/${chat.id}/export/${f}`}
                                            className="rounded px-3 py-1.5 text-xs uppercase hover:bg-muted"
                                        >
                                            {f}
                                        </a>
                                    ))}
                                </div>
                            </div>
                            {chat.status === 'active' && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.patch(`/chats/${chat.id}/close`)
                                    }
                                >
                                    <CircleStop className="mr-1 size-4" /> Close
                                </Button>
                            )}
                        </div>
                    </header>
                    <div className="flex-1 space-y-5 overflow-y-auto p-5 md:p-7">
                        {messages.length === 0 && (
                            <div className="mx-auto mt-16 max-w-md text-center">
                                <Sparkles className="mx-auto mb-4 size-9 text-indigo-500" />
                                <h2 className="text-lg font-semibold">
                                    Ask a precise regulatory question
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    Answers use only this document version. Try
                                    “What actions are required?” or “List every
                                    deadline.”
                                </p>
                            </div>
                        )}
                        {messages.map((message) => (
                            <div
                                key={message.id}
                                className={`flex gap-3 ${message.role === 'user' ? 'justify-end' : ''}`}
                            >
                                {message.role === 'assistant' && (
                                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-600 text-white">
                                        <Bot className="size-4" />
                                    </span>
                                )}
                                <div
                                    className={`max-w-[82%] rounded-2xl px-4 py-3 text-sm leading-6 ${message.role === 'user' ? 'bg-indigo-600 text-white' : 'border bg-card'}`}
                                >
                                    <p className="whitespace-pre-wrap">
                                        {message.content}
                                    </p>
                                    <p
                                        className={`mt-2 text-[10px] ${message.role === 'user' ? 'text-indigo-200' : 'text-muted-foreground'}`}
                                    >
                                        {new Date(
                                            message.created_at,
                                        ).toLocaleTimeString()}
                                    </p>
                                </div>
                                {message.role === 'user' && (
                                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-muted">
                                        <UserRound className="size-4" />
                                    </span>
                                )}
                            </div>
                        ))}
                        <div ref={bottom} />
                    </div>
                    <footer className="border-t bg-background p-4">
                        {chat.status !== 'active' ? (
                            <div className="rounded-xl bg-muted p-4 text-center text-sm">
                                <p className="font-medium">
                                    This chat is{' '}
                                    {chat.status.replaceAll('_', ' ')}.
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    Its history and exports remain available.
                                    Start a new chat from the document to
                                    continue with a clean context.
                                </p>
                                {chat.status === 'closed_context_full' && (
                                    <Button
                                        className="mt-3"
                                        onClick={() =>
                                            router.post(
                                                `/documents/${chat.document.id}/chats`,
                                            )
                                        }
                                    >
                                        Start new chat
                                    </Button>
                                )}
                            </div>
                        ) : credits === 0 ? (
                            <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-center text-sm dark:bg-amber-950/20">
                                <p>
                                    You've used all your credits for this cycle.
                                    Your allowance resets on {creditsResetAt}.
                                </p>
                                {topupUrl && (
                                    <a
                                        href={topupUrl}
                                        className="mt-2 inline-block font-semibold text-indigo-700 underline dark:text-indigo-300"
                                    >
                                        Need more credits now? Buy a top-up.
                                    </a>
                                )}
                            </div>
                        ) : (
                            <form
                                onSubmit={send}
                                className="flex items-end gap-3"
                            >
                                <div className="flex-1">
                                    <Textarea
                                        rows={2}
                                        value={draft}
                                        onChange={(e) =>
                                            setDraft(e.target.value)
                                        }
                                        placeholder="Ask about this document…"
                                        disabled={!active || busy}
                                    />
                                    <div className="mt-1 flex justify-between text-[10px] text-muted-foreground">
                                        <span>1 credit per message</span>
                                        <span>
                                            {Math.round(
                                                (chat.context_tokens /
                                                    contextLimit) *
                                                    100,
                                            )}
                                            % context used
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    size="icon"
                                    className="size-11 rounded-xl"
                                    disabled={!active || busy || !draft.trim()}
                                >
                                    <Send className="size-4" />
                                </Button>
                            </form>
                        )}
                        {error && (
                            <p className="mt-2 text-center text-xs text-red-600">
                                {error}
                            </p>
                        )}
                    </footer>
                </Card>
            </div>
        </>
    );
}
ChatShow.layout = {
    breadcrumbs: [
        { title: 'AI chat', href: '/chats' },
        { title: 'Conversation', href: '#' },
    ],
};
