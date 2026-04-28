import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PageProps } from '@/types'
import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

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
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    failed: 'bg-red-100 text-red-700',
}

export default function OrdersIndex({ orders, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '')
    const [status, setStatus] = useState(filters.status ?? '')

    function applyFilters() {
        router.get(route('orders.index'), { search: search || undefined, status: status || undefined }, { preserveState: true })
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-bold text-slate-900">Orders</h2>}>
            <Head title="Orders" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
                <div className="flex flex-wrap items-center gap-3">
                    <input
                        type="text"
                        placeholder="Search link or ID…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                        className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                    />
                    <select
                        value={status}
                        onChange={(e) => { setStatus(e.target.value); router.get(route('orders.index'), { search: search || undefined, status: e.target.value || undefined }, { preserveState: true }) }}
                        className="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:outline-none"
                    >
                        {STATUS_OPTIONS.map((s) => <option key={s} value={s}>{s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All statuses'}</option>)}
                    </select>
                    <Link href={route('orders.create')} className="ms-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        + New Order
                    </Link>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                {['#', 'Service', 'Link', 'Qty', 'Charge', 'Status', 'Date', ''].map((h) => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {orders.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-6 text-center text-slate-500">No orders found.</td></tr>
                            )}
                            {orders.data.map((o) => (
                                <tr key={o.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-3 font-mono text-xs text-slate-500">#{o.id}</td>
                                    <td className="px-4 py-3 font-medium text-slate-800">{o.service?.name ?? '—'}</td>
                                    <td className="max-w-[160px] truncate px-4 py-3 text-slate-600">{o.link}</td>
                                    <td className="px-4 py-3">{o.quantity}</td>
                                    <td className="px-4 py-3">${Number(o.charge).toFixed(2)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[o.status] ?? 'bg-slate-100 text-slate-700'}`}>{o.status}</span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-slate-500">{new Date(o.created_at).toLocaleDateString()}</td>
                                    <td className="px-4 py-3">
                                        <Link href={route('orders.show', o.id)} className="text-emerald-600 hover:text-emerald-800 text-xs font-medium">View</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {orders.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {orders.prev_page_url && (
                            <Link href={orders.prev_page_url} className="rounded border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50">Prev</Link>
                        )}
                        <span className="px-3 py-1 text-sm text-slate-600">Page {orders.current_page} of {orders.last_page}</span>
                        {orders.next_page_url && (
                            <Link href={orders.next_page_url} className="rounded border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50">Next</Link>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    )
}
