import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { Users, ShoppingCart, Activity, DollarSign, Ticket, CreditCard, Box, PieChart, UserCheck, FileText } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';

interface Stats {
    users: number; active_users: number; marketers: number; admins: number;
    services: number; orders: number; active_orders: number; open_tickets: number;
    revenue: number; today_revenue: number; month_revenue: number;
    pending_deposits: number; pending_withdrawals: number;
}

interface Order { id: number; user?: { id: number; name: string; email: string }; service?: { id: number; name: string; category: string }; status: string; charge: string; quantity: number; link: string; created_at: string; }
interface ProofSubmission { id: number; proof_url: string; status: string; marketer?: { id: number; name: string; email: string }; contract_application?: { contract?: { id: number; title: string; platform: string } }; created_at: string; }
interface DailyRevenue { date: string; total: string; count: number; }
interface NewUser { date: string; count: number; }
interface RecentUser { id: number; name: string; email: string; role: string; created_at: string; }

interface Props {
    stats: Stats; recent_orders: Order[]; pending_proofs: ProofSubmission[];
    daily_revenue: DailyRevenue[]; orders_by_status: Record<string, number>;
    new_users_weekly: NewUser[]; recent_users: RecentUser[];
}

const statusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800 border-amber-200',
    processing: 'bg-blue-100 text-blue-800 border-blue-200',
    in_progress: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    completed: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    partial: 'bg-orange-100 text-orange-800 border-orange-200',
    cancelled: 'bg-red-100 text-red-800 border-red-200',
    refunded: 'bg-zinc-100 text-zinc-800 border-zinc-200',
};

function StatCard({ label, value, icon: Icon, href, colorClass = 'text-emerald-600 bg-emerald-50', badge }: { label: string; value: string | number; icon: any; href?: string; colorClass?: string; badge?: number }) {
    const inner = (
        <div className="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all hover:shadow-md hover:border-emerald-200 group">
            <div className="flex items-center justify-between mb-3">
                <div className={`p-2.5 rounded-lg ${colorClass}`}>
                    <Icon className="h-5 w-5" />
                </div>
                {badge !== undefined && badge > 0 && (
                    <span className="px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-700 border border-red-200">{badge}</span>
                )}
            </div>
            <p className="text-2xl font-extrabold text-zinc-900 tracking-tight">{value}</p>
            <p className="text-xs font-medium text-zinc-500 mt-1 uppercase tracking-wider">{label}</p>
        </div>
    );
    return href ? <Link href={href}>{inner}</Link> : inner;
}

function MiniBarChart({ data, maxHeight = 60 }: { data: { label: string; value: number }[]; maxHeight?: number }) {
    const max = Math.max(...data.map(d => d.value), 1);
    return (
        <div className="flex items-end gap-1.5 h-16 w-full">
            {data.map((d, i) => (
                <div key={i} className="flex-1 group relative">
                    <div
                        className="w-full rounded-t bg-emerald-500 transition-all group-hover:bg-emerald-400"
                        style={{ height: `${Math.max((d.value / max) * maxHeight, 4)}px` }}
                    />
                    <div className="absolute -top-8 left-1/2 -translate-x-1/2 bg-zinc-900 text-xs text-white px-2 py-1 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 pointer-events-none">
                        {d.label}: ${Number(d.value).toFixed(2)}
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function AdminDashboard({ stats, recent_orders, pending_proofs, daily_revenue, orders_by_status, recent_users }: Props) {
    const revenueChartData = daily_revenue.map(d => ({ label: d.date, value: Number(d.total) }));
        const { t } = useTranslation();

    return (
        <AdminLayout>
            <Head title={t('admin_panel')} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-zinc-900 tracking-tight">Overview</h1>
                        <p className="text-zinc-500 text-sm mt-1">{t('platform_stats_recent_activity')}</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('admin.revenue')} className="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg bg-brand-green text-white hover:bg-brand-green/90 transition-colors shadow-sm">
                            <PieChart className="h-4 w-4" />
                            {t('revenue_analytics')}
                        </Link>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <StatCard label={t('total_users')} value={stats.users} icon={Users} href={route('admin.users.index')} colorClass="bg-blue-50 text-blue-600" />
                    <StatCard label={t('total_orders')} value={stats.orders} icon={ShoppingCart} href={route('admin.orders.index')} colorClass="bg-brand-green/10 text-brand-green" />
                    <StatCard label={t('active_orders')} value={stats.active_orders} icon={Activity} href={route('admin.orders.index') + '?status=processing'} colorClass="bg-brand-orange/10 text-brand-orange" />
                    <StatCard label={t('revenue')} value={`$${Number(stats.revenue).toFixed(2)}`} icon={DollarSign} href={route('admin.revenue')} colorClass="bg-zinc-100 text-zinc-900" />
                    <StatCard label={t('open_tickets')} value={stats.open_tickets} icon={Ticket} href={route('admin.tickets.index')} colorClass="bg-red-50 text-red-600" badge={stats.open_tickets} />
                    <StatCard label={t('today_revenue')} value={`$${Number(stats.today_revenue).toFixed(2)}`} icon={DollarSign} colorClass="bg-brand-green/10 text-brand-green" />
                    <StatCard label={t('month_revenue')} value={`$${Number(stats.month_revenue).toFixed(2)}`} icon={DollarSign} colorClass="bg-brand-green/10 text-brand-green" />
                    <StatCard label={t('services')} value={stats.services} icon={Box} href={route('admin.services.index')} colorClass="bg-indigo-50 text-indigo-600" />
                    <StatCard label={t('pending_deposits')} value={stats.pending_deposits} icon={CreditCard} href={route('admin.transactions.index') + '?status=pending&type=deposit'} colorClass="bg-brand-orange/10 text-brand-orange" badge={stats.pending_deposits} />
                    <StatCard label={t('pending_withdrawals')} value={stats.pending_withdrawals} icon={CreditCard} href={route('admin.transactions.index') + '?status=pending&type=withdrawal'} colorClass="bg-red-50 text-red-600" badge={stats.pending_withdrawals} />
                    <StatCard label={t('pending_marketer_apps')} value={(stats as any).pending_marketers} icon={UserCheck} href={route('admin.marketers.index') + '?status=pending'} colorClass="bg-brand-green/10 text-brand-green" badge={(stats as any).pending_marketers} />
                    <StatCard label={t('available_contracts')} value={(stats as any).total_contracts} icon={FileText} href={route('admin.contracts.index')} colorClass="bg-indigo-50 text-indigo-600" />
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Revenue Chart */}
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                            <h2 className="text-base font-semibold text-zinc-900 mb-6">{t('month_revenue')}</h2>
                        {revenueChartData.length > 0 ? (
                            <MiniBarChart data={revenueChartData} />
                        ) : (
                                <p className="text-zinc-500 text-sm">{t('no_orders')}</p>
                        )}
                    </div>

                    {/* Orders by Status */}
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                            <h2 className="text-base font-semibold text-zinc-900 mb-4">{t('orders_by_category')}</h2>
                        <div className="space-y-3">
                            {Object.entries(orders_by_status).map(([status, count]) => {
                                const total = Object.values(orders_by_status).reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? (count / total) * 100 : 0;
                                return (
                                    <div key={status} className="flex items-center gap-4">
                                        <span className={`px-2.5 py-1 text-xs font-medium rounded-full border w-24 text-center capitalize ${statusColors[status] || 'bg-zinc-100 text-zinc-800 border-zinc-200'}`}>
                                            {status.replace('_', ' ')}
                                        </span>
                                        <div className="flex-1 h-2.5 bg-zinc-100 rounded-full overflow-hidden">
                                            <div className="h-full rounded-full bg-brand-green transition-all" style={{ width: `${pct}%` }} />
                                        </div>
                                        <span className="text-sm font-medium text-zinc-700 w-12 text-right">{count}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Recent Orders + Recent Users */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 rounded-xl border border-zinc-200 bg-white p-0 shadow-sm overflow-hidden">
                        <div className="flex items-center justify-between p-5 border-b border-zinc-100">
                               <h2 className="text-base font-semibold text-zinc-900">{t('recent_global_orders')}</h2>
                               <Link href={route('admin.orders.index')} className="text-sm font-medium text-brand-green hover:text-brand-green/80">{t('view_all')} &rarr;</Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead className="bg-zinc-50 border-b border-zinc-100">
                                    <tr className="text-zinc-500 font-medium">
                                        <th className="px-5 py-3">Order ID</th>
                                        <th className="px-5 py-3">{t('user')}</th>
                                        <th className="px-5 py-3">{t('service')}</th>
                                        <th className="px-5 py-3 text-right">{t('amount')}</th>
                                        <th className="px-5 py-3 text-center">{t('status')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-100 bg-white">
                                    {recent_orders.length === 0 && (
                                        <tr><td colSpan={5} className="px-5 py-8 text-center text-zinc-500">{t('no_recent_orders')}</td></tr>
                                    )}
                                    {recent_orders.map(o => (
                                        <tr key={o.id} className="hover:bg-zinc-50 transition-colors">
                                            <td className="px-5 py-3 font-medium">
                                                <Link href={route('admin.orders.show', o.id)} className="text-brand-green hover:underline">#{o.id}</Link>
                                            </td>
                                            <td className="px-5 py-3 text-zinc-900 truncate max-w-[140px]">{o.user?.name}</td>
                                            <td className="px-5 py-3 text-zinc-600 truncate max-w-[140px]">{o.service?.name}</td>
                                            <td className="px-5 py-3 text-right font-medium text-zinc-900">${Number(o.charge).toFixed(2)}</td>
                                            <td className="px-5 py-3 text-center">
                                                <span className={`px-2.5 py-1 text-xs font-medium rounded-full border capitalize ${statusColors[o.status] || 'bg-zinc-100 text-zinc-800 border-zinc-200'}`}>{o.status}</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-xl border border-zinc-200 bg-white p-0 shadow-sm overflow-hidden flex flex-col">
                        <div className="flex items-center justify-between p-5 border-b border-zinc-100">
                               <h2 className="text-base font-semibold text-zinc-900">{t('recent_users')}</h2>
                               <Link href={route('admin.users.index')} className="text-sm font-medium text-brand-green hover:text-brand-green/80">{t('view_all')} &rarr;</Link>
                        </div>
                        <div className="p-5 flex-1 overflow-y-auto">
                            <div className="space-y-4">
                                {recent_users.length === 0 && (
                                    <p className="text-center text-zinc-500 text-sm">{t('no_new_users')}</p>
                                )}
                                {recent_users.map(u => (
                                    <Link key={u.id} href={route('admin.users.show', u.id)} className="flex items-center gap-3 group">
                                        <div className="h-10 w-10 rounded-full bg-brand-green/10 flex items-center justify-center text-brand-green font-bold group-hover:bg-brand-green/20 transition-colors">
                                            {u.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-zinc-900 truncate group-hover:text-brand-green transition-colors">{u.name}</p>
                                            <p className="text-xs text-zinc-500 truncate">{u.email}</p>
                                        </div>
                                        <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-1 rounded bg-zinc-100 text-zinc-600">{u.role}</span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Pending Proofs */}
                {pending_proofs.length > 0 && (
                    <div className="rounded-xl border border-brand-orange/30 bg-brand-orange/5 p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-brand-orange mb-4 flex items-center gap-2">
                            <span className="flex h-2 w-2 rounded-full bg-brand-orange"></span>
                                {t('pending_proof_subs')} ({pending_proofs.length})
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {pending_proofs.map(p => (
                                <div key={p.id} className="flex items-center justify-between p-4 rounded-xl bg-white border border-brand-orange/20 shadow-sm hover:shadow transition-shadow">
                                    <div className="overflow-hidden pr-3">
                                        <p className="text-sm font-semibold text-zinc-900 truncate">{p.marketer?.name}</p>
                                        <p className="text-xs text-zinc-500 truncate">{p.contract_application?.contract?.title}</p>
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-brand-orange mt-1">{p.contract_application?.contract?.platform}</p>
                                    </div>
                                    <a href={p.proof_url} target="_blank" rel="noopener" className="shrink-0 text-xs font-semibold text-brand-green hover:text-brand-green/80 px-3 py-1.5 rounded-lg border border-brand-green/20 bg-brand-green/5 hover:bg-brand-green/10 transition-colors">
                                        {t('review')}
                                    </a>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
