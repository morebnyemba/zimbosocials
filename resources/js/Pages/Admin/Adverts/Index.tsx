import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Megaphone, Search, Loader2, Send, RotateCcw, CheckCircle2, PlayCircle } from 'lucide-react';

interface Booking {
    id: number;
    user: { id: number; name: string; email: string } | null;
    wa_phone: string | null;
    package: string;
    days: number | null;
    weeks: number | null;
    weekly_price: string | null;
    total: string;
    promoting: string;
    target_link: string | null;
    target_audience: string | null;
    status: string;
    notes: string | null;
    created_at: string;
}

interface Props {
    bookings: { data: Booking[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null; to: number | null; last_page: number };
    filters: { status?: string; search?: string };
    statuses: string[];
    stats: { pending_setup: number; active: number; completed: number; cancelled: number; revenue: number };
}

const STATUS_STYLE: Record<string, string> = {
    pending_setup: 'bg-amber-100 text-amber-700 border-amber-200',
    active: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    completed: 'bg-zinc-100 text-zinc-600 border-zinc-200',
    cancelled: 'bg-red-100 text-red-600 border-red-200',
};

const label = (s: string) => s.replace('_', ' ');

export default function AdvertsIndex({ bookings, filters, statuses, stats }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [busy, setBusy] = useState<number | null>(null);
    const [messaging, setMessaging] = useState<number | null>(null);
    const [draft, setDraft] = useState('');

    const go = (params: Record<string, string>) =>
        router.get(route('admin.adverts.index'), { ...filters, ...params }, { preserveState: true, replace: true });

    const setStatus = (b: Booking, status: string) => {
        setBusy(b.id);
        router.post(route('admin.adverts.status', b.id), { status }, {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    const refund = (b: Booking) => {
        if (!confirm(`Cancel advert #${b.id} and refund $${b.total} to ${b.user?.name ?? 'the customer'}?`)) return;
        setBusy(b.id);
        router.post(route('admin.adverts.refund', b.id), {}, {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    const sendMessage = (b: Booking) => {
        if (!draft.trim()) return;
        setBusy(b.id);
        router.post(route('admin.adverts.message', b.id), { message: draft }, {
            preserveScroll: true,
            onFinish: () => { setBusy(null); setMessaging(null); setDraft(''); },
        });
    };

    const tiles = [
        { label: 'Awaiting setup', value: stats.pending_setup, tone: 'text-amber-600' },
        { label: 'Live', value: stats.active, tone: 'text-emerald-600' },
        { label: 'Completed', value: stats.completed, tone: 'text-zinc-900' },
        { label: 'Cancelled', value: stats.cancelled, tone: 'text-red-600' },
        { label: 'Revenue', value: `$${Number(stats.revenue).toFixed(2)}`, tone: 'text-zinc-900' },
    ];

    return (
        <AdminLayout>
            <Head title="Advert bookings" />
            <div className="p-6 md:p-8 space-y-8 max-w-7xl mx-auto">
                <div className="flex items-center gap-3">
                    <div className="h-12 w-12 rounded-2xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center">
                        <Megaphone size={22} />
                    </div>
                    <div>
                        <h1 className="text-2xl font-black text-zinc-900 tracking-tight">Advert bookings</h1>
                        <p className="text-sm text-zinc-500 font-medium">Paid sponsored campaigns — set them up, then mark them live</p>
                    </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                    {tiles.map(t => (
                        <div key={t.label} className="bg-white rounded-2xl border border-zinc-200 p-4">
                            <div className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-2">{t.label}</div>
                            <p className={`text-2xl font-black ${t.tone}`}>{t.value}</p>
                        </div>
                    ))}
                </div>

                {/* Filters */}
                <div className="flex flex-col md:flex-row gap-3 p-4 bg-white rounded-3xl border border-zinc-200">
                    <div className="relative flex-1">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 w-4 h-4" />
                        <input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && go({ search })}
                            placeholder="Search customer, phone, what's promoted, or #id…"
                            className="w-full pl-10 pr-4 py-3 rounded-2xl bg-zinc-50 border-none font-medium text-zinc-900 focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <select
                        value={filters.status ?? ''}
                        onChange={e => go({ status: e.target.value })}
                        className="px-4 py-3 rounded-2xl bg-zinc-50 border-none font-bold text-zinc-900 focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                    >
                        <option value="">All statuses</option>
                        {statuses.map(s => <option key={s} value={s}>{label(s)}</option>)}
                    </select>
                </div>

                {/* List */}
                <div className="bg-white rounded-2xl border border-zinc-200 overflow-hidden">
                    {bookings.data.length === 0 && (
                        <p className="px-6 py-16 text-center text-zinc-400 font-medium">No advert bookings yet.</p>
                    )}

                    <div className="divide-y divide-zinc-100">
                        {bookings.data.map(b => (
                            <div key={b.id} className="p-5 space-y-3">
                                <div className="flex flex-wrap items-start gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="font-black text-zinc-900">#{b.id}</span>
                                            <span className={`px-2 py-0.5 rounded-md border text-[10px] font-black uppercase ${STATUS_STYLE[b.status] ?? ''}`}>
                                                {label(b.status)}
                                            </span>
                                            <span className="text-xs font-bold text-zinc-500">
                                                {b.days ? (b.days % 30 === 0 ? `${b.days / 30} month${b.days > 30 ? 's' : ''}` : b.days % 7 === 0 ? `${b.days / 7} week${b.days > 7 ? 's' : ''}` : `${b.days} day${b.days > 1 ? 's' : ''}`) : b.package}
                                            </span>
                                            <span className="text-xs text-zinc-400">
                                                <strong className="text-zinc-700">${Number(b.total).toFixed(2)}</strong>
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-zinc-800 font-medium">{b.promoting}</p>
                                        {b.target_audience && (
                                            <p className="text-xs text-indigo-600 font-semibold">🎯 {b.target_audience}</p>
                                        )}
                                        <p className="text-xs text-zinc-500">
                                            {b.user?.name ?? 'Unknown'} · <span className="font-mono">{b.wa_phone ?? '—'}</span>
                                            {b.target_link
                                                ? <> · <a href={b.target_link} target="_blank" rel="noreferrer" className="text-indigo-600 hover:underline break-all">{b.target_link}</a></>
                                                : <> · <em className="text-amber-600">no link yet — ask them</em></>}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        {b.status === 'pending_setup' && (
                                            <button onClick={() => setStatus(b, 'active')} disabled={busy === b.id}
                                                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-500 text-white text-xs font-bold hover:bg-emerald-600 disabled:opacity-50">
                                                {busy === b.id ? <Loader2 size={14} className="animate-spin" /> : <PlayCircle size={14} />} Mark live
                                            </button>
                                        )}
                                        {b.status === 'active' && (
                                            <button onClick={() => setStatus(b, 'completed')} disabled={busy === b.id}
                                                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-900 text-white text-xs font-bold hover:bg-zinc-800 disabled:opacity-50">
                                                {busy === b.id ? <Loader2 size={14} className="animate-spin" /> : <CheckCircle2 size={14} />} Complete
                                            </button>
                                        )}
                                        <button onClick={() => setMessaging(messaging === b.id ? null : b.id)}
                                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-zinc-100 text-zinc-600 text-xs font-bold hover:bg-zinc-200">
                                            <Send size={14} /> Message
                                        </button>
                                        {b.status !== 'cancelled' && b.status !== 'completed' && (
                                            <button onClick={() => refund(b)} disabled={busy === b.id}
                                                className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-bold hover:bg-red-100 disabled:opacity-50">
                                                <RotateCcw size={14} /> Refund
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {messaging === b.id && (
                                    <div className="flex gap-2 items-end pt-1">
                                        <textarea
                                            value={draft}
                                            onChange={e => setDraft(e.target.value)}
                                            rows={2}
                                            placeholder="Message the customer on WhatsApp…"
                                            className="flex-1 resize-none bg-zinc-50 border-2 border-zinc-100 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-indigo-500"
                                        />
                                        <button onClick={() => sendMessage(b)} disabled={busy === b.id || !draft.trim()}
                                            className="px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 disabled:opacity-40">
                                            Send
                                        </button>
                                    </div>
                                )}

                                {b.notes && <p className="text-xs text-zinc-500 italic">Note: {b.notes}</p>}
                            </div>
                        ))}
                    </div>

                    {bookings.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-zinc-100">
                            <span className="text-xs font-semibold text-zinc-400">{bookings.from}–{bookings.to} of {bookings.total}</span>
                            <div className="flex flex-wrap gap-1">
                                {bookings.links.map((l, i) => (
                                    <button key={i} disabled={!l.url}
                                        onClick={() => l.url && router.visit(l.url, { preserveScroll: true, preserveState: true })}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold ${l.active ? 'bg-indigo-600 text-white' : l.url ? 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' : 'bg-transparent text-zinc-300'}`}
                                        dangerouslySetInnerHTML={{ __html: l.label }} />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
