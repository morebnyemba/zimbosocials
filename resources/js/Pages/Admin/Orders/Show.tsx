import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { RefreshCcw, LoaderCircle } from 'lucide-react';

interface Order { id: number; user?: any; service?: any; status: string; charge: string; quantity: number; link: string; external_order_id?: string; push_attempts?: number; pushed_to_upstream?: boolean; upstream_last_error?: string; start_count?: number | null; remains?: number | null; created_at: string; started_at?: string; completed_at?: string; transaction?: any; }
interface Props { order: Order; }

const sC: Record<string, string> = { pending: 'bg-amber-100 text-amber-800 border-amber-200', processing: 'bg-blue-100 text-blue-800 border-blue-200', in_progress: 'bg-indigo-100 text-indigo-800 border-indigo-200', completed: 'bg-brand-green/10 text-brand-green border-brand-green/20', partial: 'bg-orange-100 text-orange-800 border-orange-200', cancelled: 'bg-red-100 text-red-800 border-red-200', refunded: 'bg-gray-100 text-gray-800 border-gray-200' };

const STATUS_OPTIONS = ['pending', 'processing', 'in_progress', 'completed', 'partial', 'cancelled', 'refunded'];

export default function OrderShow({ order }: Props) {
    const [showRefundConfirm, setShowRefundConfirm] = useState(false);
    const [statusChoice, setStatusChoice] = useState(order.status);
    const [statusNotes, setStatusNotes] = useState('');
    const [savingStatus, setSavingStatus] = useState(false);
    const [syncing, setSyncing] = useState(false);

    const canSync = Boolean(order.pushed_to_upstream && order.external_order_id);

    const submitStatus = (e: React.FormEvent) => {
        e.preventDefault();
        if (statusChoice === order.status) return;
        setSavingStatus(true);
        router.post(route('admin.orders.status', order.id), { status: statusChoice, notes: statusNotes }, {
            preserveScroll: true,
            onFinish: () => setSavingStatus(false),
            onSuccess: () => setStatusNotes(''),
        });
    };

    const forceSync = () => {
        setSyncing(true);
        router.post(route('admin.orders.sync', order.id), {}, {
            preserveScroll: true,
            onFinish: () => setSyncing(false),
        });
    };
    return (
        <AdminLayout>
            <Head title={`Order #${order.id}`} />
            <div className="max-w-4xl mx-auto space-y-6">
                <Link href={route('admin.orders.index')} className="text-sm font-medium text-brand-green hover:text-brand-green/80 transition-colors">
                    ← Back to Orders
                </Link>
                <div className="rounded-xl border border-gray-200 bg-white shadow-sm p-6 space-y-6">
                    <div className="flex items-center justify-between pb-4 border-b border-gray-100">
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Order #{order.id}</h1>
                        <span className={`px-3 py-1 text-xs font-medium rounded-full border uppercase tracking-wider ${sC[order.status] || 'bg-gray-100 text-gray-800 border-gray-200'}`}>{order.status}</span>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div className="flex flex-col space-y-1">
                            <span className="text-gray-500 font-medium">User</span>
                            <Link href={route('admin.users.show', order.user?.id)} className="text-brand-green font-medium hover:underline">{order.user?.name}</Link>
                        </div>
                        <div className="flex flex-col space-y-1">
                            <span className="text-gray-500 font-medium">Service</span>
                            <span className="text-gray-900 font-medium">{order.service?.name}</span>
                        </div>
                        <div className="flex flex-col space-y-1">
                            <span className="text-gray-500 font-medium">Quantity</span>
                            <span className="text-gray-900 font-mono">{order.quantity?.toLocaleString()}</span>
                        </div>
                        <div className="flex flex-col space-y-1">
                            <span className="text-gray-500 font-medium">Charge</span>
                            <span className="text-gray-900 font-mono font-bold">${Number(order.charge).toFixed(2)}</span>
                        </div>
                        {order.start_count !== null && order.start_count !== undefined && (
                            <div className="flex flex-col space-y-1">
                                <span className="text-gray-500 font-medium">Start Count</span>
                                <span className="text-gray-900 font-mono">{order.start_count.toLocaleString()}</span>
                            </div>
                        )}
                        {order.remains !== null && order.remains !== undefined && (
                            <>
                                <div className="flex flex-col space-y-1">
                                    <span className="text-gray-500 font-medium">Delivered So Far</span>
                                    <span className="text-gray-900 font-mono">{Math.max(order.quantity - order.remains, 0).toLocaleString()}</span>
                                </div>
                                <div className="flex flex-col space-y-1">
                                    <span className="text-gray-500 font-medium">Remaining</span>
                                    <span className="text-gray-900 font-mono">{order.remains.toLocaleString()}</span>
                                </div>
                            </>
                        )}
                        <div className="flex flex-col space-y-1 md:col-span-2">
                            <span className="text-gray-500 font-medium">Link</span>
                            <a href={order.link} target="_blank" rel="noopener" className="text-brand-green hover:underline truncate block">{order.link}</a>
                        </div>
                        <div className="flex flex-col space-y-1">
                            <span className="text-gray-500 font-medium">External ID</span>
                            <span className="text-gray-900 font-mono">{order.external_order_id || '—'}</span>
                        </div>
                        <div className="flex flex-col space-y-1">
                            <span className="text-gray-500 font-medium">Created</span>
                            <span className="text-gray-900">{new Date(order.created_at).toLocaleString()}</span>
                        </div>
                        {order.started_at && (
                            <div className="flex flex-col space-y-1">
                                <span className="text-gray-500 font-medium">Started</span>
                                <span className="text-gray-900">{new Date(order.started_at).toLocaleString()}</span>
                            </div>
                        )}
                        {order.completed_at && (
                            <div className="flex flex-col space-y-1">
                                <span className="text-gray-500 font-medium">Completed</span>
                                <span className="text-gray-900">{new Date(order.completed_at).toLocaleString()}</span>
                            </div>
                        )}
                    </div>
                    {/* Upstream info */}
                    <div className="border-t border-gray-100 pt-6">
                        <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Upstream Details</h3>
                        <div className="grid grid-cols-2 gap-4 text-sm bg-gray-50 p-4 rounded-lg border border-gray-100">
                            <div><span className="text-gray-500 font-medium mr-2">Pushed:</span> <span className={`font-semibold ${order.pushed_to_upstream ? 'text-brand-green' : 'text-red-600'}`}>{order.pushed_to_upstream ? 'Yes' : 'No'}</span></div>
                            <div><span className="text-gray-500 font-medium mr-2">Attempts:</span> <span className="text-gray-900 font-mono">{order.push_attempts || 0}</span></div>
                            {order.upstream_last_error && <div className="col-span-2"><span className="text-gray-500 font-medium mr-2">Error:</span> <span className="text-red-600 font-mono text-xs">{order.upstream_last_error}</span></div>}
                        </div>
                        {canSync && (
                            <button
                                onClick={forceSync}
                                disabled={syncing}
                                className="mt-3 inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm disabled:opacity-50"
                            >
                                {syncing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
                                {syncing ? 'Checking upstream…' : 'Force Sync Now'}
                            </button>
                        )}
                    </div>

                    {/* Manual status override — orders normally auto-update via the
                        scheduled upstream sync; this is for edge cases the sync can't
                        resolve (e.g. provider stopped reporting, manual orders). */}
                    <div className="border-t border-gray-100 pt-6">
                        <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Manage Status</h3>
                        <form onSubmit={submitStatus} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
                                <select
                                    value={statusChoice}
                                    onChange={e => setStatusChoice(e.target.value)}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none transition-all shadow-sm"
                                >
                                    {STATUS_OPTIONS.map(s => <option key={s} value={s}>{s.replace('_', ' ')}</option>)}
                                </select>
                            </div>
                            <div className="flex-[2]">
                                <label className="block text-xs font-medium text-gray-500 mb-1">Notes (optional)</label>
                                <input
                                    type="text"
                                    value={statusNotes}
                                    onChange={e => setStatusNotes(e.target.value)}
                                    placeholder="Reason for the manual change…"
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none transition-all shadow-sm"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={savingStatus || statusChoice === order.status}
                                className="rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-green/90 disabled:opacity-50 shadow-sm"
                            >
                                {savingStatus ? 'Saving…' : 'Update Status'}
                            </button>
                        </form>
                        <p className="mt-2 text-xs text-gray-400">
                            Orders with an upstream provider update automatically every 5 minutes. Use this only to override.
                        </p>
                    </div>

                    <div className="flex gap-3 pt-6 border-t border-gray-100">
                        {order.status !== 'refunded' && <button onClick={() => setShowRefundConfirm(true)} className="px-4 py-2 text-sm font-medium rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition-colors shadow-sm">Refund Order</button>}
                    </div>
                </div>
            </div>
            
            <ConfirmModal
                open={showRefundConfirm}
                title="Refund Order"
                message={`Refund order #${order.id}? The charge amount will be returned to the user's wallet.`}
                confirmLabel="Refund"
                danger
                onConfirm={() => { setShowRefundConfirm(false); router.post(route('admin.orders.refund', order.id)); }}
                onCancel={() => setShowRefundConfirm(false)}
            />
        </AdminLayout>
    );
}
