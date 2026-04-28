import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PageProps } from '@/types'
import { Head, useForm } from '@inertiajs/react'
import { useState } from 'react'

interface Service {
    id: number; name: string; category: string
    min_qty: number; max_qty: number; rate: number; description?: string
}

interface Props extends PageProps {
    services: Service[]
    categories: string[]
    selected?: Service | null
}

export default function OrderCreate({ auth, services, categories, selected }: Props) {
    const [category, setCategory] = useState(selected?.category ?? categories[0] ?? '')
    const filtered = services.filter((s) => s.category === category)

    const { data, setData, post, processing, errors } = useForm({
        service_id: selected?.id?.toString() ?? '',
        link: '',
        quantity: '1000',
    })

    const chosenService = services.find((s) => s.id === Number(data.service_id))
    const charge = chosenService ? ((Number(data.quantity) / 1000) * chosenService.rate).toFixed(4) : '0.0000'

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('orders.store'))
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-bold text-slate-900">New Order</h2>}>
            <Head title="New Order" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p className="mb-5 text-sm text-slate-600">
                        Balance: <span className="font-semibold text-emerald-700">${typeof auth.user.balance === 'number' ? auth.user.balance.toFixed(2) : '0.00'}</span>
                    </p>

                    <form onSubmit={submit} className="space-y-5">
                        {/* Category tabs */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">Category</label>
                            <div className="flex flex-wrap gap-2">
                                {categories.map((cat) => (
                                    <button
                                        key={cat} type="button"
                                        onClick={() => { setCategory(cat); setData('service_id', '') }}
                                        className={`rounded-full px-3 py-1 text-sm font-medium transition ${cat === category ? 'bg-emerald-600 text-white' : 'border border-slate-300 text-slate-600 hover:bg-slate-50'}`}
                                    >{cat}</button>
                                ))}
                            </div>
                        </div>

                        {/* Service select */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">Service</label>
                            <select
                                value={data.service_id}
                                onChange={(e) => setData('service_id', e.target.value)}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                required
                            >
                                <option value="">— Select a service —</option>
                                {filtered.map((s) => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                            {errors.service_id && <p className="mt-1 text-xs text-red-600">{errors.service_id}</p>}
                        </div>

                        {chosenService && (
                            <div className="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 space-y-0.5">
                                <p>Min: {chosenService.min_qty.toLocaleString()} · Max: {chosenService.max_qty.toLocaleString()}</p>
                                {chosenService.description && <p className="text-slate-500">{chosenService.description}</p>}
                            </div>
                        )}

                        {/* Link */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">Link / URL</label>
                            <input
                                type="url"
                                value={data.link}
                                onChange={(e) => setData('link', e.target.value)}
                                placeholder="https://…"
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                required
                            />
                            {errors.link && <p className="mt-1 text-xs text-red-600">{errors.link}</p>}
                        </div>

                        {/* Quantity */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">Quantity</label>
                            <input
                                type="number"
                                value={data.quantity}
                                onChange={(e) => setData('quantity', e.target.value)}
                                min={chosenService?.min_qty ?? 1}
                                max={chosenService?.max_qty ?? 99999}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                                required
                            />
                            {errors.quantity && <p className="mt-1 text-xs text-red-600">{errors.quantity}</p>}
                        </div>

                        {/* Charge estimate */}
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <p className="text-sm font-semibold text-emerald-700">Estimated charge: ${charge}</p>
                            {(errors as any).balance && <p className="mt-1 text-xs text-red-600">{(errors as any).balance}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        >
                            {processing ? 'Placing…' : 'Place Order'}
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    )
}
