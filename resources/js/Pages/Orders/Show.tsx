import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import ConfirmModal from '@/Components/ConfirmModal'
import { PageProps } from '@/types'
import { Head, router } from '@inertiajs/react'
import { useState } from 'react'

interface Service { name: string; category: string }
interface Transaction { id: number; amount: number; type: string; description: string }
interface Order {
    id: number; link: string; quantity: number; charge: number
    status: string; created_at: string; rate_at_order: number
    start_count?: number | null; remains?: number | null
    service?: Service; transaction?: Transaction
}

interface Props extends PageProps {
    order: Order
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    failed: 'bg-red-100 text-red-700',
}

export default function OrderShow({ order }: Props) {
    const [showCancelConfirm, setShowCancelConfirm] = useState(false)

    function cancelOrder() {
        setShowCancelConfirm(true)
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-bold text-slate-900">Order #{order.id}</h2>}>
            <Head title={`Order #${order.id}`} />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-slate-900">{order.service?.name ?? `Order #${order.id}`}</h3>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusColors[order.status] ?? 'bg-slate-100 text-slate-700'}`}>
                            {order.status}
                        </span>
                    </div>

                    <dl className="grid grid-cols-2 gap-3 text-sm">
                        {([
                            ['Category', order.service?.category ?? '—'],
                            ['Quantity', order.quantity.toLocaleString()],
                            order.start_count !== null && order.start_count !== undefined
                                ? ['Start Count', order.start_count.toLocaleString()] : null,
                            order.remains !== null && order.remains !== undefined
                                ? ['Delivered So Far', Math.max(order.quantity - order.remains, 0).toLocaleString()] : null,
                            order.remains !== null && order.remains !== undefined
                                ? ['Remaining', order.remains.toLocaleString()] : null,
                            ['Charge', `$${Number(order.charge).toFixed(4)}`],
                            ['Rate', `$${Number(order.rate_at_order).toFixed(4)}/1k`],
                            ['Date', new Date(order.created_at).toLocaleString()],
                        ] as ([string, string] | null)[]).filter((row): row is [string, string] => row !== null).map(([label, value]) => (
                            <div key={label}>
                                <dt className="font-medium text-slate-500">{label}</dt>
                                <dd className="mt-0.5 text-slate-900">{value}</dd>
                            </div>
                        ))}
                        <div className="col-span-2">
                            <dt className="font-medium text-slate-500">Link</dt>
                            <dd className="mt-0.5 break-all text-slate-900">{order.link}</dd>
                        </div>
                    </dl>

                    {order.remains !== null && order.remains !== undefined && order.quantity > 0 && (
                        <div className="pt-2">
                            <div className="mb-1 flex items-center justify-between text-xs font-medium text-slate-500">
                                <span>Delivery progress</span>
                                <span>{Math.round((Math.max(order.quantity - order.remains, 0) / order.quantity) * 100)}%</span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-full rounded-full bg-emerald-500 transition-all"
                                    style={{ width: `${Math.min(100, Math.round((Math.max(order.quantity - order.remains, 0) / order.quantity) * 100))}%` }}
                                />
                            </div>
                        </div>
                    )}

                    {order.status === 'pending' && (
                        <button
                            onClick={cancelOrder}
                            className="mt-2 rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                        >
                            Cancel & Refund
                        </button>
                    )}
                </div>
            </div>
            
            <ConfirmModal
                open={showCancelConfirm}
                title="Cancel Order"
                message="Are you sure you want to cancel this order? A refund will be issued to your wallet balance."
                confirmLabel="Cancel Order"
                danger
                onConfirm={() => { setShowCancelConfirm(false); router.post(route('orders.cancel', order.id)); }}
                onCancel={() => setShowCancelConfirm(false)}
            />
        </AuthenticatedLayout>
    )
}
