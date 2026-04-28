import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PageProps } from '@/types'
import { Head, useForm } from '@inertiajs/react'
import { useState } from 'react'

interface Ticket {
    id: number; subject: string; status: string; created_at: string
    replies_count?: number
}

interface Props extends PageProps {
    tickets: {
        data: Ticket[]
    }
}

const statusColors: Record<string, string> = {
    open: 'bg-blue-100 text-blue-800',
    answered: 'bg-emerald-100 text-emerald-700',
    closed: 'bg-slate-100 text-slate-600',
}

export default function TicketsIndex({ tickets }: Props) {
    const ticketItems = tickets?.data ?? []
    const [showForm, setShowForm] = useState(false)
    const { data, setData, post, processing, errors, reset } = useForm({
        subject: '',
        message: '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('tickets.store'), {
            onSuccess: () => { reset(); setShowForm(false) },
        })
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-bold text-slate-900">Support Tickets</h2>}>
            <Head title="Support" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
                {/* New ticket */}
                {!showForm ? (
                    <button onClick={() => setShowForm(true)}
                        className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                        + New Ticket
                    </button>
                ) : (
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                        <h3 className="font-semibold text-slate-900">New Support Ticket</h3>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-700">Subject</label>
                                <input type="text" value={data.subject} onChange={(e) => setData('subject', e.target.value)}
                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-emerald-500"
                                    required />
                                {errors.subject && <p className="mt-1 text-xs text-red-600">{errors.subject}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-700">Message</label>
                                <textarea value={data.message} onChange={(e) => setData('message', e.target.value)} rows={5}
                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-emerald-500"
                                    required />
                                {errors.message && <p className="mt-1 text-xs text-red-600">{errors.message}</p>}
                            </div>
                            <div className="flex gap-3">
                                <button type="submit" disabled={processing}
                                    className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                                    {processing ? 'Sending…' : 'Submit Ticket'}
                                </button>
                                <button type="button" onClick={() => setShowForm(false)}
                                    className="rounded-lg border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Ticket list */}
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
                    {ticketItems.length === 0 && (
                        <p className="px-5 py-8 text-center text-sm text-slate-500">No tickets yet.</p>
                    )}
                    {ticketItems.map((t) => (
                        <a key={t.id} href={route('tickets.show', t.id)} className="flex items-center justify-between px-5 py-4 hover:bg-slate-50 transition-colors">
                            <div>
                                <p className="font-medium text-slate-900">{t.subject}</p>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {new Date(t.created_at).toLocaleDateString()}
                                    {typeof t.replies_count === 'number' && ` · ${t.replies_count} repl${t.replies_count === 1 ? 'y' : 'ies'}`}
                                </p>
                            </div>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[t.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                {t.status}
                            </span>
                        </a>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    )
}
