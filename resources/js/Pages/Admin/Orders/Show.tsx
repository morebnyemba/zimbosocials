import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Order { id: number; user?: any; service?: any; status: string; charge: string; quantity: number; link: string; external_order_id?: string; push_attempts?: number; pushed_to_upstream?: boolean; upstream_last_error?: string; created_at: string; started_at?: string; completed_at?: string; transaction?: any; }
interface Props { order: Order; }

const sC: Record<string, string> = { pending: 'bg-amber-100 text-amber-800 border-amber-200', processing: 'bg-blue-100 text-blue-800 border-blue-200', in_progress: 'bg-indigo-100 text-indigo-800 border-indigo-200', completed: 'bg-brand-green/10 text-brand-green border-brand-green/20', cancelled: 'bg-red-100 text-red-800 border-red-200', refunded: 'bg-gray-100 text-gray-800 border-gray-200' };

export default function OrderShow({ order }: Props) {
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
                    </div>
                    <div className="flex gap-3 pt-6 border-t border-gray-100">
                        {order.status !== 'refunded' && <button onClick={() => { if (confirm('Refund?')) router.post(route('admin.orders.refund', order.id)); }} className="px-4 py-2 text-sm font-medium rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition-colors shadow-sm">Refund Order</button>}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
