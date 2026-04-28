import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Ticket { id: number; subject: string; status: string; priority?: string; user?: { id: number; name: string; email: string }; replies_count: number; last_reply_at?: string; created_at: string; }
interface Props { tickets: { data: Ticket[]; links: any[]; total: number }; filters: Record<string, string>; status_counts: Record<string, number>; }

const sC: Record<string, string> = { open: 'bg-brand-green/10 text-brand-green border-brand-green/20', pending: 'bg-amber-100 text-amber-800 border-amber-200', closed: 'bg-gray-100 text-gray-800 border-gray-200' };

export default function TicketsIndex({ tickets, filters, status_counts }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const applySearch = () => router.get(route('admin.tickets.index'), { ...filters, search }, { preserveState: true });
    const setFilter = (k: string, v: string) => router.get(route('admin.tickets.index'), { ...filters, [k]: v || undefined }, { preserveState: true });

    return (
        <AdminLayout>
            <Head title="Ticket Management" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Tickets</h1>
                        <p className="text-gray-500 text-sm mt-1">{tickets.total} total tickets</p>
                    </div>
                </div>

                <div className="flex gap-2 flex-wrap">
                    {Object.entries(status_counts).map(([s, c]) => (
                        <button key={s} onClick={() => setFilter('status', s === 'all' ? '' : s)}
                            className={`px-4 py-1.5 text-sm font-medium rounded-full border transition-all ${(filters.status === s || (!filters.status && s === 'all')) ? 'bg-brand-green/10 text-brand-green border-brand-green/30' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 shadow-sm capitalize'}`}>{s} ({c})</button>
                    ))}
                </div>

                <div className="flex gap-3">
                    <input type="text" value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && applySearch()} placeholder="Search..." className="flex-1 rounded-xl bg-white border border-gray-200 text-gray-900 text-sm px-4 py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 placeholder:text-gray-400 shadow-sm" />
                    <button onClick={applySearch} className="px-5 py-2.5 rounded-xl bg-brand-green text-white text-sm font-medium hover:bg-brand-green/90 shadow-sm transition-colors">Search</button>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div className="divide-y divide-gray-100">
                        {tickets.data.map(t => (
                            <Link key={t.id} href={route('admin.tickets.show', t.id)} className="flex items-center gap-4 p-4 hover:bg-gray-50 transition-colors">
                                <div className="flex-1 min-w-0">
                                    <p className="text-gray-900 font-medium truncate">{t.subject}</p>
                                    <p className="text-xs text-gray-500 mt-0.5"><span className="font-medium text-gray-700">{t.user?.name}</span> · {t.replies_count} replies</p>
                                </div>
                                <span className={`px-2.5 py-0.5 text-xs font-medium uppercase tracking-wider rounded-full border ${sC[t.status] || 'bg-gray-100 text-gray-800 border-gray-200'}`}>{t.status}</span>
                                <span className="text-xs text-gray-500 w-24 text-right">{new Date(t.created_at).toLocaleDateString()}</span>
                            </Link>
                        ))}
                        {tickets.data.length === 0 && <div className="p-8 text-center text-gray-500">No tickets found</div>}
                    </div>
                    {tickets.links && (<div className="flex justify-center gap-1 py-4 border-t border-gray-100 bg-gray-50">{tickets.links.map((l: any, i: number) => <Link key={i} href={l.url || '#'} className={`px-3 py-1.5 text-xs font-medium rounded-lg ${l.active ? 'bg-brand-green text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200'}`} dangerouslySetInnerHTML={{ __html: l.label }} />)}</div>)}
                </div>
            </div>
        </AdminLayout>
    );
}
