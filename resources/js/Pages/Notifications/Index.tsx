import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaBox, FaHistory, FaCheckCircle, FaExclamationTriangle, 
    FaWallet, FaFileContract, FaUserCircle, FaHeadset, 
    FaLock, FaBell, FaCheckDouble, FaInbox
} from 'react-icons/fa';

interface Notification { id: string; type: string; title: string; body: string; data?: any; read_at?: string; created_at: string; }
interface Props { notifications: { data: Notification[]; links: any[]; total: number }; }

const getIconForType = (type: string) => {
    switch (type) {
        case 'order_status_changed': return <FaBox className="text-blue-500" />;
        case 'order_refunded': return <FaHistory className="text-amber-500" />;
        case 'deposit_confirmed': return <FaCheckCircle className="text-emerald-500" />;
        case 'deposit_rejected': return <FaExclamationTriangle className="text-red-500" />;
        case 'withdrawal_processed': return <FaWallet className="text-emerald-600" />;
        case 'contract_application': return <FaFileContract className="text-indigo-500" />;
        case 'role_changed': return <FaUserCircle className="text-violet-500" />;
        case 'balance_adjusted': return <FaWallet className="text-amber-600" />;
        case 'ticket_reply': return <FaHeadset className="text-cyan-500" />;
        case 'ticket_closed': return <FaLock className="text-zinc-500" />;
        default: return <FaBell className="text-zinc-400" />;
    }
};

const getBgForType = (type: string) => {
    switch (type) {
        case 'order_status_changed': return 'bg-blue-50 border-blue-100';
        case 'order_refunded': return 'bg-amber-50 border-amber-100';
        case 'deposit_confirmed': return 'bg-emerald-50 border-emerald-100';
        case 'deposit_rejected': return 'bg-red-50 border-red-100';
        case 'withdrawal_processed': return 'bg-emerald-50 border-emerald-100';
        case 'contract_application': return 'bg-indigo-50 border-indigo-100';
        case 'role_changed': return 'bg-violet-50 border-violet-100';
        case 'balance_adjusted': return 'bg-amber-50 border-amber-100';
        case 'ticket_reply': return 'bg-cyan-50 border-cyan-100';
        case 'ticket_closed': return 'bg-zinc-100 border-zinc-200';
        default: return 'bg-zinc-50 border-zinc-100';
    }
};

export default function NotificationsIndex({ notifications }: Props) {
    const markRead = (id: string) => router.post(route('notifications.read', id), {}, { preserveScroll: true });
    const markAll = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });

    const unreadCount = notifications.data.filter(n => !n.read_at).length;

    return (
        <AuthenticatedLayout>
            <Head title="Notifications" />
            
            <div className="max-w-4xl mx-auto space-y-8">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-6 bg-white p-8 rounded-3xl border border-zinc-200 shadow-sm relative overflow-hidden">
                    <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-500/5 to-amber-500/5 rounded-full blur-3xl -mr-20 -mt-20 pointer-events-none" />
                    
                    <div className="relative z-10">
                        <div className="flex items-center gap-3 mb-2">
                            <div className="h-10 w-10 rounded-2xl bg-zinc-900 flex items-center justify-center shadow-lg">
                                <FaBell className="text-white text-lg" />
                            </div>
                            <h1 className="text-3xl font-black text-zinc-900 tracking-tight">Activity Hub</h1>
                        </div>
                        <p className="text-zinc-500 font-medium ml-13">
                            You have <strong className="text-zinc-900">{unreadCount} unread</strong> notification{unreadCount !== 1 && 's'} out of {notifications.total} total.
                        </p>
                    </div>

                    <button 
                        onClick={markAll} 
                        disabled={unreadCount === 0}
                        className={`relative z-10 flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-black uppercase tracking-widest transition-all ${
                            unreadCount > 0 
                                ? 'bg-zinc-900 text-white shadow-xl shadow-zinc-900/20 hover:bg-zinc-800 active:scale-95' 
                                : 'bg-zinc-100 text-zinc-400 cursor-not-allowed'
                        }`}
                    >
                        <FaCheckDouble /> Mark All Read
                    </button>
                </div>

                {/* Notifications List */}
                <div className="bg-white border border-zinc-200 rounded-3xl shadow-sm overflow-hidden">
                    <AnimatePresence mode="popLayout">
                        {notifications.data.length > 0 ? (
                            notifications.data.map((n, i) => (
                                <motion.div 
                                    key={n.id}
                                    initial={{ opacity: 0, y: 10 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={{ opacity: 0, scale: 0.95 }}
                                    transition={{ delay: i * 0.05 }}
                                    onClick={() => !n.read_at && markRead(n.id)}
                                    className={`group flex items-start gap-5 p-6 transition-all border-b border-zinc-100 last:border-0 ${
                                        !n.read_at 
                                            ? 'bg-white cursor-pointer hover:bg-zinc-50' 
                                            : 'bg-zinc-50/50 opacity-75'
                                    }`}
                                >
                                    <div className={`shrink-0 h-12 w-12 rounded-2xl flex items-center justify-center border shadow-sm transition-transform group-hover:scale-110 ${
                                        !n.read_at ? getBgForType(n.type) : 'bg-zinc-100 border-zinc-200 grayscale opacity-70'
                                    }`}>
                                        <span className="text-lg">
                                            {getIconForType(n.type)}
                                        </span>
                                    </div>
                                    
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between gap-4 mb-1">
                                            <h3 className={`text-base font-black truncate ${!n.read_at ? 'text-zinc-900' : 'text-zinc-600'}`}>
                                                {n.title}
                                            </h3>
                                            <span className="shrink-0 text-[11px] font-bold uppercase tracking-widest text-zinc-400">
                                                {new Date(n.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        </div>
                                        <p className={`text-sm leading-relaxed ${!n.read_at ? 'text-zinc-600 font-medium' : 'text-zinc-500'}`}>
                                            {n.body}
                                        </p>
                                    </div>

                                    {!n.read_at && (
                                        <div className="shrink-0 mt-2">
                                            <div className="h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_12px_rgba(16,185,129,0.8)] animate-pulse" />
                                        </div>
                                    )}
                                </motion.div>
                            ))
                        ) : (
                            <div className="flex flex-col items-center justify-center py-24 px-6 text-center">
                                <div className="h-20 w-20 rounded-full bg-zinc-50 border-2 border-dashed border-zinc-200 flex items-center justify-center mb-6">
                                    <FaInbox className="text-3xl text-zinc-300" />
                                </div>
                                <h3 className="text-xl font-black text-zinc-900 mb-2 tracking-tight">All Caught Up!</h3>
                                <p className="text-zinc-500 font-medium max-w-sm">
                                    You don't have any notifications at the moment. We'll alert you when something important happens.
                                </p>
                            </div>
                        )}
                    </AnimatePresence>
                </div>

                {/* Pagination */}
                {notifications.links && notifications.links.length > 3 && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {notifications.links.map((l: any, i: number) => (
                            <Link 
                                key={i} 
                                href={l.url || '#'} 
                                className={`px-4 py-2 text-xs font-black uppercase tracking-widest rounded-xl transition-all ${
                                    l.active 
                                        ? 'bg-zinc-900 text-white shadow-lg shadow-zinc-900/20' 
                                        : !l.url
                                            ? 'text-zinc-400 cursor-not-allowed opacity-50'
                                            : 'bg-white text-zinc-600 border border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-900'
                                }`}
                                dangerouslySetInnerHTML={{ __html: l.label }} 
                            />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
