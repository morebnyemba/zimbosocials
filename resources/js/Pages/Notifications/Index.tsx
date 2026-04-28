import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Notification { id: string; type: string; title: string; body: string; data?: any; read_at?: string; created_at: string; }
interface Props { notifications: { data: Notification[]; links: any[]; total: number }; }

const typeIcons: Record<string, string> = {
    order_status_changed: '📦', order_refunded: '💸', deposit_confirmed: '✅', deposit_rejected: '❌',
    withdrawal_processed: '🏧', contract_application: '📋', role_changed: '👤', balance_adjusted: '💰',
    ticket_reply: '🎫', ticket_closed: '🔒', default: '🔔',
};

export default function NotificationsIndex({ notifications }: Props) {
    const markRead = (id: string) => router.post(route('notifications.read', id), {}, { preserveScroll: true });
    const markAll = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });

    return (
        <AuthenticatedLayout>
            <Head title="Notifications" />
            <div className="max-w-3xl mx-auto px-4 sm:px-6 py-8 space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold bg-gradient-to-r from-violet-400 to-indigo-400 bg-clip-text text-transparent">Notifications</h1>
                    <button onClick={markAll} className="text-sm text-violet-400 hover:text-violet-300">Mark all as read</button>
                </div>

                <div className="space-y-2">
                    {notifications.data.map(n => (
                        <div key={n.id} onClick={() => !n.read_at && markRead(n.id)}
                            className={`flex gap-4 p-4 rounded-2xl border transition-all cursor-pointer ${n.read_at ? 'border-white/5 bg-gray-900/40 opacity-60' : 'border-violet-500/20 bg-violet-500/5 hover:bg-violet-500/10'}`}>
                            <span className="text-2xl">{typeIcons[n.type] || typeIcons.default}</span>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-white">{n.title}</p>
                                <p className="text-xs text-gray-400 mt-0.5">{n.body}</p>
                                <p className="text-[10px] text-gray-600 mt-1">{new Date(n.created_at).toLocaleString()}</p>
                            </div>
                            {!n.read_at && <div className="w-2 h-2 rounded-full bg-violet-500 mt-2 flex-shrink-0" />}
                        </div>
                    ))}
                    {notifications.data.length === 0 && <p className="text-center text-gray-500 py-12">No notifications yet</p>}
                </div>

                {notifications.links && (<div className="flex justify-center gap-1 py-4">{notifications.links.map((l: any, i: number) => <Link key={i} href={l.url || '#'} className={`px-3 py-1 text-xs rounded-lg ${l.active ? 'bg-violet-600 text-white' : 'text-gray-400 hover:bg-gray-800'}`} dangerouslySetInnerHTML={{ __html: l.label }} />)}</div>)}
            </div>
        </AuthenticatedLayout>
    );
}
