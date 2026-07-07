import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import ConfirmModal from '@/Components/ConfirmModal'
import { PageProps } from '@/types'
import { Head, Link, router } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import {
    FaArrowLeft, FaBan, FaCheckCircle, FaClock, FaExternalLinkAlt,
    FaMoneyBillWave, FaRocket, FaSyncAlt, FaUndoAlt,
} from 'react-icons/fa'

interface Service { name: string; category: string }
interface OrderTransaction {
    id: number; type: string; amount: string | number
    status: string; notes?: string | null; created_at: string
}
interface Order {
    id: number; link: string; quantity: number; charge: string | number
    status: string; created_at: string; updated_at: string; rate_at_order: string | number
    start_count?: number | null; remains?: number | null
    pushed_at?: string | null
    service?: Service
}

interface Props extends PageProps {
    order: Order
    transactions: OrderTransaction[]
    can_cancel: boolean
    can_sync: boolean
    can_refill: boolean
}

const statusMeta: Record<string, { label: string; pill: string; description: string }> = {
    pending: { label: 'Pending', pill: 'bg-amber-100 text-amber-700', description: 'Your order is queued and will be submitted shortly.' },
    processing: { label: 'Processing', pill: 'bg-blue-100 text-blue-700', description: 'Your order has been submitted and delivery is underway.' },
    in_progress: { label: 'In Progress', pill: 'bg-indigo-100 text-indigo-700', description: 'Delivery is actively in progress.' },
    completed: { label: 'Completed', pill: 'bg-emerald-100 text-emerald-700', description: 'Delivery finished — enjoy!' },
    partial: { label: 'Partial', pill: 'bg-orange-100 text-orange-700', description: 'Delivery finished partially; the undelivered portion was refunded to your wallet.' },
    cancelled: { label: 'Cancelled', pill: 'bg-red-100 text-red-700', description: 'This order was cancelled and refunded to your wallet.' },
    refunded: { label: 'Refunded', pill: 'bg-zinc-200 text-zinc-700', description: 'This order was refunded to your wallet.' },
    failed: { label: 'Failed', pill: 'bg-red-100 text-red-700', description: 'This order failed and was refunded to your wallet.' },
}

const ACTIVE_STATUSES = ['pending', 'processing', 'in_progress']

export default function OrderShow({ order, transactions, can_cancel, can_sync, can_refill }: Props) {
    const [showCancelConfirm, setShowCancelConfirm] = useState(false)
    const [syncing, setSyncing] = useState(false)
    const [refilling, setRefilling] = useState(false)
    const autoSynced = useRef(false)

    const meta = statusMeta[order.status] ?? { label: order.status, pill: 'bg-zinc-100 text-zinc-700', description: '' }
    const isActive = ACTIVE_STATUSES.includes(order.status)
    const delivered = order.remains !== null && order.remains !== undefined
        ? Math.max(order.quantity - order.remains, 0)
        : null
    const progress = delivered !== null && order.quantity > 0
        ? Math.min(100, Math.round((delivered / order.quantity) * 100))
        : order.status === 'completed' ? 100 : null

    const refreshStatus = (isAuto = false) => {
        if (!can_sync || syncing) return
        setSyncing(true)
        router.post(route('orders.sync-status', order.id), {}, {
            preserveScroll: true,
            onFinish: () => setSyncing(false),
            ...(isAuto ? { preserveState: true } : {}),
        })
    }

    // Refresh once automatically when opening a live order, so users see the
    // freshest status without waiting for the next scheduled sync pass.
    // The sessionStorage timestamp survives the reload the sync itself causes,
    // so this can never loop; the server additionally gates per order (60s).
    useEffect(() => {
        if (!can_sync || autoSynced.current) return
        autoSynced.current = true

        const key = `order-auto-sync-${order.id}`
        const last = Number(sessionStorage.getItem(key) ?? 0)
        if (Date.now() - last < 90_000) return

        sessionStorage.setItem(key, String(Date.now()))
        refreshStatus(true)
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    // Timeline: which stage the order has reached
    const timeline = (() => {
        const placed = { label: 'Order Placed', done: true, at: order.created_at }
        const submitted = {
            label: 'Submitted to Network',
            done: order.status !== 'pending',
            at: order.pushed_at ?? null,
        }
        if (order.status === 'cancelled' || order.status === 'refunded' || order.status === 'failed') {
            return [placed, submitted, { label: statusMeta[order.status]?.label ?? 'Closed', done: true, at: order.updated_at, terminal: true }]
        }
        if (order.status === 'partial') {
            return [placed, submitted, { label: 'Partially Delivered', done: true, at: order.updated_at, terminal: true }]
        }
        return [
            placed,
            submitted,
            { label: 'Delivering', done: ['in_progress', 'completed'].includes(order.status) || (delivered !== null && delivered > 0), at: null },
            { label: 'Completed', done: order.status === 'completed', at: order.status === 'completed' ? order.updated_at : null },
        ]
    })()

    const refunds = transactions.filter((t) => t.type === 'refund')

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center gap-4">
                <Link href={route('orders.index')} className="text-zinc-400 hover:text-zinc-900 transition-colors">
                    <FaArrowLeft />
                </Link>
                <h2 className="text-xl font-black text-zinc-900 tracking-tight">Order #{order.id}</h2>
                <span className={`rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest ${meta.pill}`}>
                    {meta.label}
                </span>
            </div>
        }>
            <Head title={`Order #${order.id}`} />

            <div className="mx-auto max-w-3xl space-y-6">
                {/* Status card */}
                <div className="rounded-[2rem] border border-zinc-200 bg-white p-7 shadow-sm space-y-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="min-w-0">
                            <h3 className="text-lg font-black text-zinc-900 tracking-tight truncate">
                                {order.service?.name ?? `Order #${order.id}`}
                            </h3>
                            <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mt-1">
                                {order.service?.category ?? '—'}
                            </p>
                            {meta.description && (
                                <p className="text-sm text-zinc-500 font-medium mt-2">{meta.description}</p>
                            )}
                        </div>
                        {can_sync && (
                            <button
                                onClick={() => refreshStatus(false)}
                                disabled={syncing}
                                className="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-zinc-700 transition-colors disabled:opacity-50"
                            >
                                <FaSyncAlt className={syncing ? 'animate-spin' : ''} />
                                {syncing ? 'Checking…' : 'Refresh Status'}
                            </button>
                        )}
                    </div>

                    {/* Progress */}
                    {progress !== null && isActive && (
                        <div>
                            <div className="mb-1.5 flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-zinc-400">
                                <span>Delivery Progress</span>
                                <span className="text-zinc-900">{progress}%</span>
                            </div>
                            <div className="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100">
                                <div
                                    className="h-full rounded-full bg-emerald-500 transition-all duration-700"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                            {delivered !== null && (
                                <p className="mt-1.5 text-xs font-medium text-zinc-500">
                                    {delivered.toLocaleString()} of {order.quantity.toLocaleString()} delivered
                                    {order.remains ? ` — ${order.remains.toLocaleString()} remaining` : ''}
                                </p>
                            )}
                        </div>
                    )}

                    {/* Timeline */}
                    <ol className="relative space-y-4 border-l-2 border-zinc-100 pl-5 ml-1.5">
                        {timeline.map((step: any) => (
                            <li key={step.label} className="relative">
                                <span className={`absolute -left-[27px] top-0.5 flex h-4 w-4 items-center justify-center rounded-full border-2 ${
                                    step.done
                                        ? (step.terminal && ['Cancelled', 'Refunded', 'Failed'].includes(step.label)
                                            ? 'border-red-400 bg-red-400'
                                            : 'border-emerald-500 bg-emerald-500')
                                        : 'border-zinc-200 bg-white'
                                }`}>
                                    {step.done && <FaCheckCircle className="text-[8px] text-white" />}
                                </span>
                                <p className={`text-xs font-black uppercase tracking-widest ${step.done ? 'text-zinc-900' : 'text-zinc-300'}`}>
                                    {step.label}
                                </p>
                                {step.at && (
                                    <p className="text-[11px] font-medium text-zinc-400">{new Date(step.at).toLocaleString()}</p>
                                )}
                            </li>
                        ))}
                    </ol>

                    <p className="text-[11px] font-medium text-zinc-400 flex items-center gap-1.5">
                        <FaClock className="text-[10px]" /> Last updated {new Date(order.updated_at).toLocaleString()}
                        {isActive && ' — status refreshes automatically every few minutes'}
                    </p>
                </div>

                {/* Details card */}
                <div className="rounded-[2rem] border border-zinc-200 bg-white p-7 shadow-sm">
                    <h4 className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-5">Order Details</h4>
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                        {([
                            ['Quantity', order.quantity.toLocaleString()],
                            order.start_count !== null && order.start_count !== undefined
                                ? ['Start Count', order.start_count.toLocaleString()] : null,
                            delivered !== null ? ['Delivered', delivered.toLocaleString()] : null,
                            ['Charge', `$${Number(order.charge).toFixed(2)}`],
                            ['Rate', `$${Number(order.rate_at_order).toFixed(2)}/1k`],
                            ['Placed', new Date(order.created_at).toLocaleDateString()],
                        ] as ([string, string] | null)[])
                            .filter((row): row is [string, string] => row !== null)
                            .map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{label}</dt>
                                    <dd className="mt-1 font-bold text-zinc-900">{value}</dd>
                                </div>
                            ))}
                    </dl>
                    <div className="mt-5 border-t border-zinc-100 pt-4">
                        <dt className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">Target Link</dt>
                        <a
                            href={order.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 break-all text-sm font-bold text-emerald-600 hover:text-emerald-700 transition-colors"
                        >
                            <FaExternalLinkAlt className="shrink-0 text-xs" /> {order.link}
                        </a>
                    </div>
                </div>

                {/* Payments & refunds */}
                {transactions.length > 0 && (
                    <div className="rounded-[2rem] border border-zinc-200 bg-white p-7 shadow-sm">
                        <h4 className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-5">Payments &amp; Refunds</h4>
                        <ul className="space-y-3">
                            {transactions.map((tx) => {
                                const amount = Number(tx.amount)
                                const isRefund = tx.type === 'refund'
                                return (
                                    <li key={tx.id} className="flex items-center justify-between gap-4 rounded-2xl bg-zinc-50 border border-zinc-100 px-4 py-3">
                                        <div className="flex items-center gap-3 min-w-0">
                                            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${isRefund ? 'bg-blue-50 text-blue-500' : 'bg-rose-50 text-rose-500'}`}>
                                                {isRefund ? <FaUndoAlt /> : <FaMoneyBillWave />}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="text-xs font-black text-zinc-900 uppercase tracking-wide">
                                                    {isRefund ? 'Refund to Wallet' : 'Order Charge'}
                                                </p>
                                                <p className="text-[11px] font-medium text-zinc-400 truncate">
                                                    {new Date(tx.created_at).toLocaleString()}
                                                </p>
                                            </div>
                                        </div>
                                        <span className={`shrink-0 text-sm font-black ${amount >= 0 ? 'text-emerald-600' : 'text-zinc-900'}`}>
                                            {amount >= 0 ? '+' : ''}{`$${Math.abs(amount).toFixed(2)}`}
                                        </span>
                                    </li>
                                )
                            })}
                        </ul>
                        {refunds.length > 0 && (
                            <p className="mt-4 text-xs font-medium text-zinc-500">
                                Refunds are credited to your wallet instantly and can be used for new orders right away.
                            </p>
                        )}
                    </div>
                )}

                {/* Refill */}
                {can_refill && (
                    <div className="rounded-[2rem] border border-emerald-100 bg-emerald-50/50 p-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-black text-zinc-900">Numbers dropped?</p>
                            <p className="text-xs font-medium text-zinc-500 mt-0.5">
                                This service is refill-eligible — request a free refill and lost engagement is typically restored within 24–72 hours.
                            </p>
                        </div>
                        <button
                            onClick={() => {
                                if (refilling) return
                                setRefilling(true)
                                router.post(route('orders.refill', order.id), {}, {
                                    preserveScroll: true,
                                    onFinish: () => setRefilling(false),
                                })
                            }}
                            disabled={refilling}
                            className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-600 transition-colors disabled:opacity-50"
                        >
                            <FaSyncAlt className={refilling ? 'animate-spin' : ''} />
                            {refilling ? 'Requesting…' : 'Request Refill'}
                        </button>
                    </div>
                )}

                {/* Actions */}
                {can_cancel && (
                    <div className="rounded-[2rem] border border-red-100 bg-red-50/50 p-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-black text-zinc-900">Changed your mind?</p>
                            <p className="text-xs font-medium text-zinc-500 mt-0.5">
                                This order hasn't been submitted yet — cancel now for a full, instant refund.
                            </p>
                        </div>
                        <button
                            onClick={() => setShowCancelConfirm(true)}
                            className="inline-flex items-center gap-2 rounded-xl border-2 border-red-200 bg-white px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-red-600 hover:bg-red-50 transition-colors"
                        >
                            <FaBan /> Cancel &amp; Refund
                        </button>
                    </div>
                )}

                {isActive && !can_cancel && (
                    <p className="text-center text-xs font-medium text-zinc-400 flex items-center justify-center gap-2">
                        <FaRocket className="text-[10px]" />
                        This order is already with the delivery network and can no longer be cancelled.
                    </p>
                )}
            </div>

            <ConfirmModal
                open={showCancelConfirm}
                title="Cancel Order"
                message="Are you sure you want to cancel this order? A refund will be issued to your wallet balance."
                confirmLabel="Cancel Order"
                danger
                onConfirm={() => { setShowCancelConfirm(false); router.post(route('orders.cancel', order.id)) }}
                onCancel={() => setShowCancelConfirm(false)}
            />
        </AuthenticatedLayout>
    )
}
