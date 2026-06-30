import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PageProps } from '@/types'
import { useTranslation } from '@/lib/i18n'
import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'
import { FaPlus, FaSearch, FaInbox, FaChevronLeft, FaChevronRight } from 'react-icons/fa'

interface Service { name: string; category: string }
interface Order {
    id: number; link: string; quantity: number; charge: number
    status: string; created_at: string; service?: Service
}
interface Paginated<T> { data: T[]; current_page: number; last_page: number; next_page_url: string | null; prev_page_url: string | null }

interface Props extends PageProps {
    orders: Paginated<Order>
    filters: { status?: string; search?: string }
}

const STATUS_OPTIONS = ['', 'pending', 'processing', 'completed', 'cancelled', 'failed']
const statusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-zinc-200 text-zinc-700',
    failed: 'bg-red-100 text-red-700',
}

export default function OrdersIndex({ orders, filters }: Props) {
    const { t } = useTranslation()
    const [search, setSearch] = useState(filters.search ?? '')
    const [status, setStatus] = useState(filters.status ?? '')

    const hasFilters = Boolean(filters.search || filters.status)

    function applyFilters(nextStatus = status) {
        router.get(
            route('orders.index'),
            { search: search || undefined, status: nextStatus || undefined },
            { preserveState: true, preserveScroll: true },
        )
    }

    const statusLabel = (s: string) => t(`status_${s}`) === `status_${s}` ? s : t(`status_${s}`)
    const fmtDate = (d: string) =>
        new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-black text-zinc-900">{t('orders')}</h2>}>
            <Head title={t('orders')} />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* Filter bar */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative flex-1 min-w-[200px]">
                        <FaSearch className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 text-sm" />
                        <input
                            type="text"
                            placeholder={t('search_orders')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 py-3 pl-11 pr-4 text-sm font-medium text-zinc-900 transition-all focus:border-brand-green focus:bg-white focus:outline-none"
                        />
                    </div>
                    <select
                        value={status}
                        onChange={(e) => { setStatus(e.target.value); applyFilters(e.target.value) }}
                        className="rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-3 text-sm font-bold text-zinc-900 transition-all focus:border-brand-green focus:bg-white focus:outline-none"
                    >
                        {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s}>{s ? statusLabel(s) : t('all_statuses')}</option>
                        ))}
                    </select>
                    <Link
                        href={route('orders.create')}
                        className="ms-auto inline-flex items-center gap-2 rounded-2xl bg-brand-green px-5 py-3 text-sm font-bold text-white transition-all hover:bg-brand-green/90 active:scale-[0.98]"
                    >
                        <FaPlus className="text-xs" /> {t('new_order')}
                    </Link>
                </div>

                {/* Empty state */}
                {orders.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-zinc-200 bg-white px-6 py-20 text-center">
                        <div className="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-400">
                            <FaInbox className="text-2xl" />
                        </div>
                        <p className="text-base font-bold text-zinc-900">
                            {hasFilters ? t('no_orders_match') : t('no_orders')}
                        </p>
                        {!hasFilters && (
                            <Link
                                href={route('orders.create')}
                                className="mt-6 inline-flex items-center gap-2 rounded-2xl bg-brand-green px-6 py-3 text-sm font-bold text-white transition-all hover:bg-brand-green/90 active:scale-[0.98]"
                            >
                                <FaPlus className="text-xs" /> {t('new_order')}
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Desktop table */}
                        <div className="hidden overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm md:block">
                            <table className="min-w-full divide-y divide-zinc-100 text-sm">
                                <thead className="bg-zinc-50">
                                    <tr>
                                        {[t('order_id'), t('service'), t('link'), t('quantity'), t('charge'), t('status'), t('date'), ''].map((h, i) => (
                                            <th key={i} className="px-5 py-3.5 text-left text-[11px] font-black uppercase tracking-wider text-zinc-400">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-50">
                                    {orders.data.map((o) => (
                                        <tr key={o.id} className="transition-colors hover:bg-zinc-50">
                                            <td className="px-5 py-4 font-mono text-xs text-zinc-500">#{o.id}</td>
                                            <td className="px-5 py-4 font-bold text-zinc-800">{o.service?.name ?? '—'}</td>
                                            <td className="max-w-[180px] truncate px-5 py-4 text-zinc-600">
                                                <a href={o.link} target="_blank" rel="noopener noreferrer" className="hover:text-brand-green hover:underline">{o.link}</a>
                                            </td>
                                            <td className="px-5 py-4 font-medium text-zinc-700">{Number(o.quantity).toLocaleString()}</td>
                                            <td className="px-5 py-4 font-bold text-zinc-900">${Number(o.charge).toFixed(2)}</td>
                                            <td className="px-5 py-4">
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${statusColors[o.status] ?? 'bg-zinc-100 text-zinc-700'}`}>{statusLabel(o.status)}</span>
                                            </td>
                                            <td className="px-5 py-4 text-xs text-zinc-500">{fmtDate(o.created_at)}</td>
                                            <td className="px-5 py-4 text-right">
                                                <Link href={route('orders.show', o.id)} className="text-xs font-bold text-brand-green hover:underline">{t('view')}</Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile cards */}
                        <div className="space-y-3 md:hidden">
                            {orders.data.map((o) => (
                                <Link
                                    key={o.id}
                                    href={route('orders.show', o.id)}
                                    className="block rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition-all active:scale-[0.99]"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-bold text-zinc-900">{o.service?.name ?? '—'}</p>
                                            <p className="mt-0.5 truncate text-xs text-zinc-500">{o.link}</p>
                                        </div>
                                        <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ${statusColors[o.status] ?? 'bg-zinc-100 text-zinc-700'}`}>{statusLabel(o.status)}</span>
                                    </div>
                                    <div className="mt-3 flex items-center justify-between border-t border-zinc-50 pt-3 text-xs">
                                        <span className="font-mono text-zinc-400">#{o.id}</span>
                                        <span className="text-zinc-500">{Number(o.quantity).toLocaleString()} · {fmtDate(o.created_at)}</span>
                                        <span className="font-black text-zinc-900">${Number(o.charge).toFixed(2)}</span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </>
                )}

                {/* Pagination */}
                {orders.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        {orders.prev_page_url ? (
                            <Link href={orders.prev_page_url} preserveScroll className="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm font-bold text-zinc-700 transition-colors hover:bg-zinc-50">
                                <FaChevronLeft className="text-[10px]" /> {t('previous')}
                            </Link>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 rounded-xl border border-zinc-100 px-4 py-2 text-sm font-bold text-zinc-300">
                                <FaChevronLeft className="text-[10px]" /> {t('previous')}
                            </span>
                        )}
                        <span className="px-3 text-sm font-medium text-zinc-500">
                            {t('page_of', { current: orders.current_page, last: orders.last_page })}
                        </span>
                        {orders.next_page_url ? (
                            <Link href={orders.next_page_url} preserveScroll className="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm font-bold text-zinc-700 transition-colors hover:bg-zinc-50">
                                {t('next')} <FaChevronRight className="text-[10px]" />
                            </Link>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 rounded-xl border border-zinc-100 px-4 py-2 text-sm font-bold text-zinc-300">
                                {t('next')} <FaChevronRight className="text-[10px]" />
                            </span>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    )
}
