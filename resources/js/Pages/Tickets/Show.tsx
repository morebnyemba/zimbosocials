import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PageProps } from '@/types'
import { Head, useForm } from '@inertiajs/react'

interface User { id: number; name: string }
interface Reply { id: number; message: string; is_admin: boolean; created_at: string; user?: User }
interface Ticket {
    id: number; subject: string; status: string; message: string; created_at: string
    replies: Reply[]
}

interface Props extends PageProps {
    ticket: Ticket
}

const statusColors: Record<string, string> = {
    open: 'bg-blue-100 text-blue-800',
    answered: 'bg-emerald-100 text-emerald-700',
    closed: 'bg-slate-100 text-slate-600',
}

export default function TicketShow({ auth, ticket }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({ message: '' })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('tickets.reply', ticket.id), { onSuccess: () => reset() })
    }

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center gap-3">
                <h2 className="text-xl font-bold text-slate-900">{ticket.subject}</h2>
                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusColors[ticket.status] ?? 'bg-slate-100 text-slate-600'}`}>
                    {ticket.status}
                </span>
            </div>
        }>
            <Head title={ticket.subject} />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 space-y-4">
                {/* Original message */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-semibold text-slate-800">{auth.user.name} <span className="font-normal text-slate-400">(you)</span></span>
                        <span className="text-xs text-slate-400">{new Date(ticket.created_at).toLocaleString()}</span>
                    </div>
                    <p className="text-sm text-slate-700 whitespace-pre-wrap">{ticket.message}</p>
                </div>

                {/* Replies */}
                {ticket.replies.map((r) => (
                    <div key={r.id} className={`rounded-xl border p-5 shadow-sm ${r.is_admin ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'}`}>
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm font-semibold text-slate-800">
                                {r.is_admin ? 'Support Team' : (r.user?.name ?? 'You')}
                                {r.is_admin && <span className="ms-1 text-xs font-normal text-emerald-600">(Staff)</span>}
                            </span>
                            <span className="text-xs text-slate-400">{new Date(r.created_at).toLocaleString()}</span>
                        </div>
                        <p className="text-sm text-slate-700 whitespace-pre-wrap">{r.message}</p>
                    </div>
                ))}

                {/* Reply form */}
                {ticket.status !== 'closed' && (
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 className="mb-3 font-semibold text-slate-900">Add Reply</h3>
                        <form onSubmit={submit} className="space-y-3">
                            <textarea value={data.message} onChange={(e) => setData('message', e.target.value)} rows={4}
                                placeholder="Type your reply…"
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-emerald-500"
                                required />
                            {errors.message && <p className="text-xs text-red-600">{errors.message}</p>}
                            <button type="submit" disabled={processing}
                                className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                                {processing ? 'Sending…' : 'Send Reply'}
                            </button>
                        </form>
                    </div>
                )}

                {ticket.status === 'closed' && (
                    <p className="text-center text-sm text-slate-500">This ticket is closed.</p>
                )}
            </div>
        </AuthenticatedLayout>
    )
}
