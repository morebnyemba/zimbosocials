import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

interface Reply { id: number; message: string; is_admin: boolean; user?: { id: number; name: string }; created_at: string; }
interface Ticket { id: number; subject: string; message: string; status: string; priority?: string; user?: { id: number; name: string; email: string }; replies: Reply[]; created_at: string; }
interface Props { ticket: Ticket; }

export default function TicketShow({ ticket }: Props) {
    const { data, setData, post, processing } = useForm({ message: '' });

    const submit = (e: React.FormEvent) => { e.preventDefault(); post(route('admin.tickets.reply', ticket.id), { preserveScroll: true, onSuccess: () => setData('message', '') }); };
    const close = () => router.post(route('admin.tickets.close', ticket.id));

    return (
        <AdminLayout>
            <Head title={`Ticket: ${ticket.subject}`} />
            <div className="max-w-4xl mx-auto space-y-6">
                <Link href={route('admin.tickets.index')} className="text-sm font-medium text-brand-green hover:text-brand-green/80 transition-colors">
                    ← Back to Tickets
                </Link>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-4 pb-4 border-b border-gray-100">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 tracking-tight">{ticket.subject}</h1>
                            <p className="text-sm text-gray-500 mt-1"><span className="font-medium text-gray-700">{ticket.user?.name}</span> · {ticket.user?.email}</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className={`px-3 py-1 text-xs font-medium uppercase tracking-wider rounded-full border ${ticket.status === 'open' ? 'bg-brand-green/10 text-brand-green border-brand-green/20' : ticket.status === 'closed' ? 'bg-gray-100 text-gray-600 border-gray-200' : 'bg-amber-100 text-amber-800 border-amber-200'}`}>{ticket.status}</span>
                            {ticket.status !== 'closed' && <button onClick={close} className="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition-colors">Close Ticket</button>}
                        </div>
                    </div>
                    <div className="p-5 rounded-xl bg-gray-50 text-gray-800 text-sm whitespace-pre-wrap leading-relaxed border border-gray-100">{ticket.message}</div>
                    <p className="text-xs font-medium text-gray-400 mt-3">{new Date(ticket.created_at).toLocaleString()}</p>
                </div>

                {/* Replies */}
                <div className="space-y-4">
                    {ticket.replies.map(r => (
                        <div key={r.id} className={`p-5 rounded-xl border shadow-sm ${r.is_admin ? 'border-brand-green/20 bg-brand-green/5 ml-8' : 'border-gray-200 bg-white mr-8'}`}>
                            <div className="flex items-center gap-2 mb-3">
                                <span className="text-sm font-bold text-gray-900">{r.user?.name}</span>
                                {r.is_admin && <span className="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20">Admin</span>}
                                <span className="text-xs font-medium text-gray-500 ml-auto">{new Date(r.created_at).toLocaleString()}</span>
                            </div>
                            <p className="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{r.message}</p>
                        </div>
                    ))}
                </div>

                {/* Reply Form */}
                {ticket.status !== 'closed' && (
                    <form onSubmit={submit} className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <textarea value={data.message} onChange={e => setData('message', e.target.value)} rows={4} placeholder="Write your reply..." className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-3 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 placeholder:text-gray-400 resize-none transition-shadow" />
                        <div className="flex justify-end mt-4">
                            <button type="submit" disabled={processing || !data.message} className="px-6 py-2.5 text-sm font-medium rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50 transition-colors shadow-sm">Send Reply</button>
                        </div>
                    </form>
                )}
            </div>
        </AdminLayout>
    );
}
