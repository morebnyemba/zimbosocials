import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Tx { id: number; user?: { id: number; name: string; email: string }; type: string; amount: string; status: string; method?: string; reference?: string; reference_code?: string; notes?: string; created_at: string; }
interface Props { transactions: { data: Tx[]; links: any[]; total: number }; filters: Record<string, string>; pending_deposits: number; pending_withdrawals: number; }

const sC: Record<string, string> = { pending: 'bg-amber-100 text-amber-800 border-amber-200', completed: 'bg-brand-green/10 text-brand-green border-brand-green/20', failed: 'bg-red-100 text-red-800 border-red-200', rejected: 'bg-red-100 text-red-800 border-red-200' };

export default function TransactionsIndex({ transactions, filters, pending_deposits, pending_withdrawals }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [pendingRejectId, setPendingRejectId] = useState<number | null>(null);
    const [pendingRejectWithdrawalId, setPendingRejectWithdrawalId] = useState<number | null>(null);
    const applySearch = () => router.get(route('admin.transactions.index'), { ...filters, search }, { preserveState: true });
    const setFilter = (k: string, v: string) => router.get(route('admin.transactions.index'), { ...filters, [k]: v || undefined }, { preserveState: true });

    const approve = (id: number) => router.post(route('admin.transactions.approve', id), {}, { preserveScroll: true });
    const reject = (id: number) => { setPendingRejectId(id); };
    const processW = (id: number) => router.post(route('admin.transactions.process-withdrawal', id), {}, { preserveScroll: true });
    const rejectW = (id: number) => { setPendingRejectWithdrawalId(id); };

    return (
        <AdminLayout>
            <Head title="Transactions" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Transactions</h1>
                        <p className="text-gray-500 text-sm mt-1">
                            {pending_deposits > 0 && <span className="text-amber-600 font-medium mr-3">⚠ {pending_deposits} pending deposits</span>}
                            {pending_withdrawals > 0 && <span className="text-brand-orange font-medium">⚠ {pending_withdrawals} pending withdrawals</span>}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('admin.revenue')} className="px-4 py-2 text-sm font-medium rounded-lg bg-brand-green/10 text-brand-green hover:bg-brand-green/20 transition-colors">Revenue →</Link>
                    </div>
                </div>

                {/* Quick Filters */}
                <div className="flex gap-2 flex-wrap">
                    {['', 'deposit', 'order_charge', 'withdrawal', 'refund', 'adjustment', 'contract_earning', 'contract_payout'].map(t => (
                        <button key={t} onClick={() => setFilter('type', t)}
                            className={`px-4 py-1.5 text-xs font-medium uppercase tracking-wider rounded-full border transition-all ${(filters.type === t || (!filters.type && !t)) ? 'bg-brand-green/10 text-brand-green border-brand-green/30' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 shadow-sm'}`}>
                            {t ? t.replace('_', ' ') : 'All'}
                        </button>
                    ))}
                </div>
                <div className="flex gap-2 flex-wrap">
                    {['', 'pending', 'completed', 'failed'].map(s => (
                        <button key={s} onClick={() => setFilter('status', s)}
                            className={`px-4 py-1.5 text-xs font-medium uppercase tracking-wider rounded-full border transition-all ${(filters.status === s || (!filters.status && !s)) ? 'bg-brand-orange/10 text-brand-orange border-brand-orange/30' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 shadow-sm'}`}>
                            {s || 'All Statuses'}
                        </button>
                    ))}
                </div>

                <div className="flex gap-3">
                    <input type="text" value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && applySearch()} placeholder="Search..." className="flex-1 rounded-xl bg-white border border-gray-200 text-gray-900 text-sm px-4 py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 placeholder:text-gray-400 shadow-sm" />
                    <button onClick={applySearch} className="px-5 py-2.5 rounded-xl bg-brand-green text-white text-sm font-medium hover:bg-brand-green/90 shadow-sm transition-colors">Search</button>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-gray-500 font-medium text-xs uppercase tracking-wider">
                                    <th className="py-3 px-5">#</th><th className="py-3 px-5">User</th><th className="py-3 px-5">Type</th>
                                    <th className="text-right py-3 px-5">Amount</th><th className="text-center py-3 px-5">Status</th><th className="text-left py-3 px-5">Method</th><th className="text-right py-3 px-5">Date</th><th className="text-right py-3 px-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {transactions.data.map(tx => (
                                    <tr key={tx.id} className="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                        <td className="py-3 px-5">
                                            <div className="font-medium text-gray-500">#{tx.id}</div>
                                            {tx.reference_code && (
                                                <div className="text-[10px] font-mono text-gray-300 select-all">{tx.reference_code}</div>
                                            )}
                                        </td>
                                        <td className="py-3 px-5">{tx.user ? <Link href={route('admin.users.show', tx.user.id)} className="text-gray-900 hover:text-brand-green font-medium">{tx.user.name}</Link> : '—'}</td>
                                        <td className="py-3 px-5"><span className="px-2.5 py-1 text-xs font-medium rounded-md bg-gray-100 text-gray-600 capitalize tracking-wide border border-gray-200">{tx.type.replace('_', ' ')}</span></td>
                                        <td className={`py-3 px-5 text-right font-mono font-bold ${Number(tx.amount) >= 0 ? 'text-brand-green' : 'text-red-600'}`}>{Number(tx.amount) >= 0 ? '+' : ''}${Number(tx.amount).toFixed(2)}</td>
                                        <td className="py-3 px-5 text-center"><span className={`px-2.5 py-0.5 text-xs font-medium uppercase tracking-wider rounded-full border ${sC[tx.status] || 'bg-gray-100 text-gray-800 border-gray-200'}`}>{tx.status}</span></td>
                                        <td className="py-3 px-5 text-gray-500 text-xs">{tx.method || '—'}</td>
                                        <td className="py-3 px-5 text-right text-gray-500 text-xs">{new Date(tx.created_at).toLocaleDateString()}</td>
                                        <td className="py-3 px-5 text-right space-x-2">
                                            {tx.type === 'deposit' && tx.status === 'pending' && (<>
                                                <button onClick={() => approve(tx.id)} className="px-3 py-1 text-xs font-medium rounded-lg bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">Approve</button>
                                                <button onClick={() => reject(tx.id)} className="px-3 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100">Reject</button>
                                            </>)}
                                            {tx.type === 'withdrawal' && tx.status === 'pending' && (<>
                                                <button onClick={() => processW(tx.id)} className="px-3 py-1 text-xs font-medium rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100">Process</button>
                                                <button onClick={() => rejectW(tx.id)} className="px-3 py-1 text-xs font-medium rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100">Reject</button>
                                            </>)}
                                        </td>
                                    </tr>
                                ))}
                                {transactions.data.length === 0 && <tr><td colSpan={8} className="px-5 py-8 text-center text-gray-500">No transactions found.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    {transactions.links && (<div className="flex justify-center gap-1 py-4 border-t border-gray-100 bg-gray-50">{transactions.links.map((l: any, i: number) => <Link key={i} href={l.url || '#'} className={`px-3 py-1.5 text-xs font-medium rounded-lg ${l.active ? 'bg-brand-green text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200'}`} dangerouslySetInnerHTML={{ __html: l.label }} />)}</div>)}
                </div>
            </div>
            
            {pendingRejectId !== null && (
                <ConfirmModal
                    open
                    title="Reject Transaction"
                    message="Reject this transaction? The user will be notified."
                    confirmLabel="Reject"
                    danger
                    onConfirm={() => { router.post(route('admin.transactions.reject', pendingRejectId)); setPendingRejectId(null); }}
                    onCancel={() => setPendingRejectId(null)}
                />
            )}

            {pendingRejectWithdrawalId !== null && (
                <ConfirmModal
                    open
                    title="Reject Withdrawal"
                    message="Reject this withdrawal? The reserved funds will be returned to the user's wallet balance immediately."
                    confirmLabel="Reject & Refund"
                    danger
                    onConfirm={() => { router.post(route('admin.transactions.reject-withdrawal', pendingRejectWithdrawalId)); setPendingRejectWithdrawalId(null); }}
                    onCancel={() => setPendingRejectWithdrawalId(null)}
                />
            )}
        </AdminLayout>
    );
}
