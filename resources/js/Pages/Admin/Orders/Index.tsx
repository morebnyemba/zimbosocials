import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Order { id: number; user?: { id: number; name: string; email: string }; service?: { id: number; name: string; category: string }; status: string; charge: string; quantity: number; link: string; external_order_id?: string; created_at: string; }
interface Props { orders: { data: Order[]; links: any[]; total: number }; filters: Record<string, string>; status_counts: Record<string, number>; }

const sC: Record<string, string> = { pending: 'bg-amber-100 text-amber-800 border-amber-200', processing: 'bg-blue-100 text-blue-800 border-blue-200', in_progress: 'bg-indigo-100 text-indigo-800 border-indigo-200', completed: 'bg-brand-green/10 text-brand-green border-brand-green/20', partial: 'bg-brand-orange/10 text-brand-orange border-brand-orange/20', cancelled: 'bg-red-100 text-red-800 border-red-200', refunded: 'bg-gray-100 text-gray-800 border-gray-200' };

export default function OrdersIndex({ orders, filters, status_counts }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusModal, setStatusModal] = useState<Order | null>(null);
    const [newStatus, setNewStatus] = useState('');
    const [pendingRefund, setPendingRefund] = useState<Order | null>(null);

    const applySearch = () => router.get(route('admin.orders.index'), { ...filters, search }, { preserveState: true });
    const setFilter = (k: string, v: string) => router.get(route('admin.orders.index'), { ...filters, [k]: v || undefined }, { preserveState: true });

    const changeStatus = () => { if (!statusModal) return; router.post(route('admin.orders.status', statusModal.id), { status: newStatus }, { preserveScroll: true, onSuccess: () => setStatusModal(null) }); };
    const refund = (order: Order) => { setPendingRefund(order); };

    return (
        <AdminLayout>
            <Head title="Order Management" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Orders</h1>
                        <p className="text-gray-500 text-sm mt-1">{orders.total} total orders</p>
                    </div>
                </div>

                {/* Status Tabs */}
                <div className="flex gap-2 flex-wrap">
                    {Object.entries(status_counts).map(([s, c]) => (
                        <button key={s} onClick={() => setFilter('status', s === 'all' ? '' : s)}
                            className={`px-4 py-1.5 text-sm font-medium rounded-full border transition-all ${(filters.status === s || (!filters.status && s === 'all')) ? 'bg-brand-green/10 text-brand-green border-brand-green/30' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 shadow-sm'}`}>
                            {s} ({c})
                        </button>
                    ))}
                </div>

                <div className="flex gap-3">
                    <input type="text" value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && applySearch()} placeholder="Search by ID, link, or user..." className="flex-1 rounded-xl bg-white border border-gray-200 text-gray-900 text-sm px-4 py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 placeholder:text-gray-400 shadow-sm" />
                    <button onClick={applySearch} className="px-5 py-2.5 rounded-xl bg-brand-green text-white text-sm font-medium hover:bg-brand-green/90 shadow-sm transition-colors">Search</button>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-gray-500 font-medium text-xs uppercase tracking-wider">
                                    <th className="py-3 px-5">#</th><th className="py-3 px-5">User</th><th className="py-3 px-5">Service</th>
                                    <th className="text-center py-3 px-5">Qty</th><th className="text-right py-3 px-5">Charge</th><th className="text-center py-3 px-5">Status</th><th className="text-right py-3 px-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {orders.data.map(o => (
                                    <tr key={o.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="py-3 px-5 font-medium text-brand-green">#{o.id}</td>
                                        <td className="py-3 px-5"><Link href={route('admin.users.show', o.user?.id || 0)} className="text-gray-900 hover:text-brand-green font-medium">{o.user?.name}</Link></td>
                                        <td className="py-3 px-5 text-gray-500 truncate max-w-[200px]">{o.service?.name}</td>
                                        <td className="py-3 px-5 text-center text-gray-600">{o.quantity?.toLocaleString()}</td>
                                        <td className="py-3 px-5 text-right font-mono font-bold text-gray-900">${Number(o.charge).toFixed(2)}</td>
                                        <td className="py-3 px-5 text-center">
                                            <button onClick={() => { setStatusModal(o); setNewStatus(o.status); }} className={`px-2.5 py-0.5 text-xs font-medium rounded-full border cursor-pointer hover:opacity-80 uppercase tracking-wider ${sC[o.status] || 'bg-gray-100 text-gray-800 border-gray-200'}`}>{o.status}</button>
                                        </td>
                                        <td className="py-3 px-5 text-right space-x-3">
                                            <Link href={route('admin.orders.show', o.id)} className="text-brand-green font-medium text-xs hover:text-brand-green/80">View</Link>
                                            {o.status !== 'refunded' && <button onClick={() => refund(o)} className="text-red-600 font-medium text-xs hover:text-red-500">Refund</button>}
                                        </td>
                                    </tr>
                                ))}
                                {orders.data.length === 0 && <tr><td colSpan={7} className="px-5 py-8 text-center text-gray-500">No orders found.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    {orders.links && (<div className="flex justify-center gap-1 py-4 border-t border-gray-100 bg-gray-50">{orders.links.map((l: any, i: number) => <Link key={i} href={l.url || '#'} className={`px-3 py-1.5 text-xs font-medium rounded-lg ${l.active ? 'bg-brand-green text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200'}`} dangerouslySetInnerHTML={{ __html: l.label }} />)}</div>)}
                </div>

                {statusModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={() => setStatusModal(null)}>
                        <div className="bg-white border border-gray-200 rounded-2xl p-6 w-full max-w-md shadow-xl" onClick={e => e.stopPropagation()}>
                            <h3 className="text-lg font-bold text-gray-900 mb-4">Change Status — Order #{statusModal.id}</h3>
                            <select value={newStatus} onChange={e => setNewStatus(e.target.value)} className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-2.5 mb-4 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20">
                                {['pending','processing','in_progress','completed','partial','cancelled','refunded'].map(s => <option key={s} value={s}>{s}</option>)}
                            </select>
                            <div className="flex gap-3 justify-end">
                                <button onClick={() => setStatusModal(null)} className="px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100">Cancel</button>
                                <button onClick={changeStatus} className="px-4 py-2 text-sm font-medium rounded-xl bg-brand-green text-white hover:bg-brand-green/90 shadow-sm">Update</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
            
            {pendingRefund && (
                <ConfirmModal
                    open
                    title="Refund Order"
                    message={`Refund order #${pendingRefund.id} ($${Number(pendingRefund.charge).toFixed(2)})? The amount will be returned to the user's wallet.`}
                    confirmLabel="Refund"
                    danger
                    onConfirm={() => { router.post(route('admin.orders.refund', pendingRefund.id), {}, { preserveScroll: true }); setPendingRefund(null); }}
                    onCancel={() => setPendingRefund(null)}
                />
            )}
        </AdminLayout>
    );
}
