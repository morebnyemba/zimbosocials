import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FaWhatsapp, FaArrowLeft, FaPaperPlane, FaHeadset, FaRedo, FaRobot } from 'react-icons/fa';

interface Message {
    id: number;
    direction: 'in' | 'out';
    body: string | null;
    msg_type: string;
    handled_by: string | null;
    intent: string | null;
    ai_used: boolean;
    delivery_status: string | null;
    created_at: string | null;
}

/**
 * WhatsApp-style receipts for messages WE sent, driven by Meta's status
 * webhooks: ✓ sent, ✓✓ delivered, blue ✓✓ read, ⚠ failed. No status yet means
 * it's still in flight (or the number never reported back).
 */
function DeliveryTicks({ status }: { status: string | null }) {
    const map: Record<string, { mark: string; className: string; label: string }> = {
        sent: { mark: '✓', className: 'text-emerald-100', label: 'Sent' },
        delivered: { mark: '✓✓', className: 'text-emerald-100', label: 'Delivered' },
        read: { mark: '✓✓', className: 'text-sky-300 font-bold', label: 'Read by contact' },
        failed: { mark: '⚠', className: 'text-red-200 font-bold', label: 'Failed to deliver' },
    };

    const s = status ? map[status] : undefined;
    if (!s) {
        return <span className="opacity-60" title="Sending…">🕘</span>;
    }

    return <span className={s.className} title={s.label}>{s.mark}</span>;
}

interface Account {
    id: number;
    wa_phone: string;
    display_name: string | null;
    link_status: string;
    opted_in: boolean;
    in_handoff: boolean;
    handoff_until: string | null;
    user: { id: number; name: string; email: string } | null;
}

interface Props {
    account: Account;
    messages: Message[];
    session: { flow: string | null; state: string | null; status: string } | null;
}

const POLL_MS = 5000;

export default function Conversation({ account, messages, session }: Props) {
    const { data, setData, post, processing, reset } = useForm({ message: '' });
    const [live, setLive] = useState(true);
    const liveRef = useRef(live);
    liveRef.current = live;
    const bottomRef = useRef<HTMLDivElement>(null);
    const lastCountRef = useRef(messages.length);

    // Near-real-time transcript: reload just the messages on an interval. A
    // partial reload with preserveState keeps the reply box (and everything the
    // agent typed) intact, so this never interrupts them.
    useEffect(() => {
        const id = setInterval(() => {
            if (!liveRef.current) return;
            router.reload({ only: ['messages', 'session', 'account'] });
        }, POLL_MS);
        return () => clearInterval(id);
    }, []);

    // Auto-scroll to the newest message when the count grows.
    useEffect(() => {
        if (messages.length !== lastCountRef.current) {
            bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
            lastCountRef.current = messages.length;
        }
    }, [messages.length]);

    const sendReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.message.trim()) return;
        post(route('admin.whatsapp.conversation.reply', account.id), {
            preserveScroll: true,
            onSuccess: () => reset('message'),
        });
    };

    const toggleHandoff = () => router.post(route('admin.whatsapp.conversation.handoff', account.id), {}, { preserveScroll: true });
    const resetSession = () => {
        if (confirm('Reset this conversation\'s state? The user will start fresh from the menu.')) {
            router.post(route('admin.whatsapp.conversation.reset', account.id), {}, { preserveScroll: true });
        }
    };

    const time = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : '');

    return (
        <AdminLayout>
            <Head title={`Chat · ${account.display_name || account.wa_phone}`} />
            <div className="p-6 md:p-8 max-w-5xl mx-auto space-y-6">
                <Link href={route('admin.whatsapp.conversations')} className="inline-flex items-center gap-2 text-sm font-bold text-zinc-500 hover:text-zinc-900">
                    <FaArrowLeft /> Back to conversations
                </Link>

                {/* Header */}
                <div className="bg-white rounded-2xl border border-zinc-200 p-6 flex flex-wrap items-center gap-4">
                    <div className="h-12 w-12 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-2xl">
                        <FaWhatsapp />
                    </div>
                    <div className="min-w-0">
                        <h1 className="text-xl font-black text-zinc-900">{account.display_name || 'Unknown'}</h1>
                        <p className="text-xs text-zinc-400 font-mono">+{account.wa_phone}</p>
                    </div>
                    <div className="flex flex-wrap gap-2 items-center">
                        {account.user
                            ? <span className="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase">{account.user.name}</span>
                            : <span className="px-2.5 py-1 rounded-md bg-zinc-100 text-zinc-500 text-[10px] font-black uppercase">Guest</span>}
                        {account.in_handoff && <span className="px-2.5 py-1 rounded-md bg-amber-100 text-amber-700 text-[10px] font-black uppercase">In handoff</span>}
                        {session?.flow && <span className="px-2.5 py-1 rounded-md bg-indigo-100 text-indigo-700 text-[10px] font-black uppercase">Flow: {session.flow}</span>}
                    </div>
                    <div className="ml-auto flex gap-2">
                        <button onClick={toggleHandoff} className={`inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition ${account.in_handoff ? 'bg-emerald-500 text-white hover:bg-emerald-600' : 'bg-amber-500 text-white hover:bg-amber-600'}`}>
                            <FaHeadset /> {account.in_handoff ? 'Resume bot' : 'Take over'}
                        </button>
                        <button onClick={resetSession} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold bg-zinc-100 text-zinc-600 hover:bg-zinc-200 transition">
                            <FaRedo /> Reset
                        </button>
                    </div>
                </div>

                {/* Live toggle */}
                <div className="flex items-center justify-end -mb-2">
                    <button
                        type="button"
                        onClick={() => setLive((v) => !v)}
                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 ${live ? 'bg-emerald-500 text-white' : 'bg-zinc-100 text-zinc-500'}`}
                        title="Auto-refresh this chat"
                    >
                        <span className={`h-2 w-2 rounded-full ${live ? 'bg-white animate-pulse' : 'bg-zinc-400'}`} />
                        {live ? 'Live' : 'Paused'}
                    </button>
                </div>

                {/* Transcript */}
                <div className="bg-zinc-50 rounded-2xl border border-zinc-200 p-4 md:p-6 space-y-3 max-h-[60vh] overflow-y-auto">
                    {messages.length === 0 && <p className="text-center text-zinc-400 py-12 font-medium">No messages yet.</p>}
                    {messages.map((m) => (
                        <div key={m.id} className={`flex ${m.direction === 'out' ? 'justify-end' : 'justify-start'}`}>
                            <div className={`max-w-[75%] rounded-2xl px-4 py-2.5 ${m.direction === 'out' ? 'bg-emerald-500 text-white' : 'bg-white border border-zinc-200 text-zinc-800'}`}>
                                <p className="whitespace-pre-wrap text-sm leading-relaxed">{m.body || <em className="opacity-60">[{m.msg_type}]</em>}</p>
                                <div className={`mt-1 flex items-center gap-2 text-[10px] ${m.direction === 'out' ? 'text-emerald-100' : 'text-zinc-400'}`}>
                                    <span>{time(m.created_at)}</span>
                                    {m.handled_by && <span className="uppercase font-bold">· {m.handled_by}</span>}
                                    {m.ai_used && <FaRobot title="AI" />}
                                    {m.direction === 'out' && <DeliveryTicks status={m.delivery_status} />}
                                </div>
                            </div>
                        </div>
                    ))}
                    <div ref={bottomRef} />
                </div>

                {/* Agent reply */}
                <form onSubmit={sendReply} className="bg-white rounded-2xl border border-zinc-200 p-4 flex gap-3 items-end">
                    <textarea
                        value={data.message}
                        onChange={(e) => setData('message', e.target.value)}
                        placeholder="Reply as a human agent… (this pauses the bot for 2 hours)"
                        rows={2}
                        className="flex-1 resize-none bg-zinc-50 border-2 border-zinc-100 rounded-xl px-4 py-3 text-sm font-medium text-zinc-900 focus:outline-none focus:border-emerald-500 transition"
                    />
                    <button type="submit" disabled={processing || !data.message.trim()} className="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-zinc-900 text-white text-sm font-bold hover:bg-zinc-800 disabled:opacity-40 transition">
                        <FaPaperPlane /> Send
                    </button>
                </form>
            </div>
        </AdminLayout>
    );
}
