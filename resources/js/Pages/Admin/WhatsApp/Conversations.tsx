import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FaWhatsapp, FaSearch, FaRobot, FaUserCheck, FaComments, FaHeadset } from 'react-icons/fa';

interface Conversation {
    id: number;
    wa_phone: string;
    display_name: string | null;
    link_status: string;
    user: { id: number; name: string; email: string } | null;
    opted_in: boolean;
    in_handoff: boolean;
    last_seen_at: string | null;
    last_message: string | null;
    last_direction: string | null;
    last_delivery_status: string | null;
}

/** Compact receipt for the last outbound message in the list. */
const receipt = (status: string | null) => {
    switch (status) {
        case 'read': return <span className="text-sky-500 font-bold" title="Read by contact">✓✓</span>;
        case 'delivered': return <span className="text-zinc-400" title="Delivered">✓✓</span>;
        case 'sent': return <span className="text-zinc-300" title="Sent">✓</span>;
        case 'failed': return <span className="text-red-500 font-bold" title="Failed to deliver">⚠</span>;
        default: return null;
    }
};

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props {
    conversations: Paginator<Conversation>;
    stats: Record<string, number>;
    filters: { search: string };
}

const tiles: { key: string; label: string; icon: any }[] = [
    { key: 'conversations', label: 'Conversations', icon: FaComments },
    { key: 'linked', label: 'Linked accounts', icon: FaUserCheck },
    { key: 'messages_today', label: 'Messages today', icon: FaWhatsapp },
    { key: 'ai_today', label: 'AI replies today', icon: FaRobot },
    { key: 'active_flows', label: 'Active flows', icon: FaComments },
    { key: 'in_handoff', label: 'In handoff', icon: FaHeadset },
];

const POLL_MS = 12000;

export default function Conversations({ conversations, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [live, setLive] = useState(true);
    const liveRef = useRef(live);
    liveRef.current = live;

    // Near-real-time: quietly refresh the list + stats on an interval without
    // disrupting scroll, typing, or the current page.
    useEffect(() => {
        const id = setInterval(() => {
            if (!liveRef.current) return;
            router.reload({ only: ['conversations', 'stats'] });
        }, POLL_MS);
        return () => clearInterval(id);
    }, []);

    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('admin.whatsapp.conversations'), { search }, { preserveState: true, replace: true });
    };

    const timeAgo = (iso: string | null) => {
        if (!iso) return '—';
        const diff = (Date.now() - new Date(iso).getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    };

    return (
        <AdminLayout>
            <Head title="WhatsApp Assistant" />
            <div className="p-6 md:p-8 space-y-8 max-w-7xl mx-auto">
                <div className="flex items-center gap-3">
                    <div className="h-12 w-12 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-2xl">
                        <FaWhatsapp />
                    </div>
                    <div>
                        <h1 className="text-2xl font-black text-zinc-900 tracking-tight">WhatsApp Assistant</h1>
                        <p className="text-sm text-zinc-500 font-medium">Conversations & live agent takeover</p>
                    </div>
                    <Link
                        href={route('admin.whatsapp.kb')}
                        className="ml-auto px-5 py-2.5 rounded-xl bg-zinc-900 text-white text-sm font-bold hover:bg-zinc-800 transition"
                    >
                        Knowledge Base
                    </Link>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    {tiles.map((t) => (
                        <div key={t.key} className="bg-white rounded-2xl border border-zinc-200 p-4">
                            <div className="flex items-center gap-2 text-zinc-400 mb-2">
                                <t.icon className="text-sm" />
                                <span className="text-[10px] font-black uppercase tracking-widest">{t.label}</span>
                            </div>
                            <p className="text-3xl font-black text-zinc-900">{stats[t.key] ?? 0}</p>
                        </div>
                    ))}
                </div>

                {/* Search + live toggle */}
                <div className="flex flex-wrap items-center gap-3">
                    <form onSubmit={submitSearch} className="relative flex-1 min-w-[240px] max-w-md">
                        <FaSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search phone, name, or email…"
                            className="w-full bg-white border-2 border-zinc-200 rounded-xl pl-11 pr-4 py-3 font-medium text-zinc-900 focus:outline-none focus:border-emerald-500 transition"
                        />
                    </form>
                    <button
                        type="button"
                        onClick={() => setLive((v) => !v)}
                        className={`px-4 py-2.5 rounded-xl text-sm font-bold transition flex items-center gap-2 ${live ? 'bg-emerald-500 text-white' : 'bg-zinc-100 text-zinc-500'}`}
                        title="Auto-refresh the list"
                    >
                        <span className={`h-2 w-2 rounded-full ${live ? 'bg-white animate-pulse' : 'bg-zinc-400'}`} />
                        {live ? 'Live' : 'Paused'}
                    </button>
                    <span className="text-xs font-semibold text-zinc-400">{conversations.total} total</span>
                </div>

                {/* Table */}
                <div className="bg-white rounded-2xl border border-zinc-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] font-black uppercase tracking-widest text-zinc-400 border-b border-zinc-100">
                                    <th className="px-5 py-4">Contact</th>
                                    <th className="px-5 py-4">Linked user</th>
                                    <th className="px-5 py-4">Last message</th>
                                    <th className="px-5 py-4">Status</th>
                                    <th className="px-5 py-4">Last seen</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-50">
                                {conversations.data.length === 0 && (
                                    <tr><td colSpan={5} className="px-5 py-16 text-center text-zinc-400 font-medium">No conversations yet.</td></tr>
                                )}
                                {conversations.data.map((c) => (
                                    <tr
                                        key={c.id}
                                        onClick={() => router.visit(route('admin.whatsapp.conversation', c.id))}
                                        className="hover:bg-zinc-50 cursor-pointer transition"
                                    >
                                        <td className="px-5 py-4">
                                            <div className="font-bold text-zinc-900">{c.display_name || 'Unknown'}</div>
                                            <div className="text-xs text-zinc-400 font-mono">+{c.wa_phone}</div>
                                        </td>
                                        <td className="px-5 py-4">
                                            {c.user ? (
                                                <span className="text-zinc-700">{c.user.name}</span>
                                            ) : (
                                                <span className="text-xs font-bold text-zinc-400 uppercase">Guest</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 max-w-xs">
                                            <span className="text-zinc-500 line-clamp-1">
                                                {c.last_direction === 'out' && <span className="text-emerald-500">↩ </span>}
                                                {c.last_message || '—'}
                                                {c.last_direction === 'out' && <span className="ml-1.5">{receipt(c.last_delivery_status)}</span>}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-1.5">
                                                {c.in_handoff && <span className="px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[10px] font-black uppercase">Handoff</span>}
                                                {c.link_status === 'linked'
                                                    ? <span className="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase">Linked</span>
                                                    : <span className="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-500 text-[10px] font-black uppercase">{c.link_status}</span>}
                                                {!c.opted_in && <span className="px-2 py-0.5 rounded-md bg-red-100 text-red-600 text-[10px] font-black uppercase">Opted out</span>}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-zinc-400">{timeAgo(c.last_seen_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {conversations.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-zinc-100">
                            <span className="text-xs font-semibold text-zinc-400">
                                {conversations.from}–{conversations.to} of {conversations.total}
                            </span>
                            <div className="flex flex-wrap gap-1">
                                {conversations.links.map((l, i) => (
                                    <button
                                        key={i}
                                        disabled={!l.url}
                                        onClick={() => l.url && router.visit(l.url, { preserveScroll: true, preserveState: true })}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition ${
                                            l.active ? 'bg-emerald-500 text-white' : l.url ? 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' : 'bg-transparent text-zinc-300 cursor-default'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: l.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
