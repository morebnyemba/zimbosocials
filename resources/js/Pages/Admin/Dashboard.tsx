import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Users, ShoppingCart, Activity, DollarSign, Ticket, CreditCard, Box, PieChart, UserCheck, FileText, Sparkles, Loader2, RefreshCw, ShieldAlert } from 'lucide-react';
import { AreaChart, Area, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
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

const fmtChartDate = (d: any): string => {
    const date = new Date(String(d));
    return Number.isNaN(date.getTime()) ? String(d) : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

function RevenueChart({ data }: { data: { date: string; total: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height={220}>
            <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <defs>
                    <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#10b981" stopOpacity={0.35} />
                        <stop offset="100%" stopColor="#10b981" stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f4" vertical={false} />
                <XAxis dataKey="date" tickFormatter={fmtChartDate} tick={{ fontSize: 11, fill: '#71717a' }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11, fill: '#71717a' }} axisLine={false} tickLine={false} tickFormatter={(v) => `$${v}`} width={48} />
                <Tooltip
                    formatter={(value: any) => [`$${Number(value).toFixed(2)}`, 'Revenue']}
                    labelFormatter={fmtChartDate}
                    contentStyle={{ borderRadius: 12, border: '1px solid #e4e4e7', fontSize: 12 }}
                />
                <Area type="monotone" dataKey="total" stroke="#10b981" strokeWidth={2} fill="url(#revenueFill)" />
            </AreaChart>
        </ResponsiveContainer>
    );
}

function NewUsersChart({ data }: { data: { date: string; count: number }[] }) {
    return (
        <ResponsiveContainer width="100%" height={180}>
            <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f4" vertical={false} />
                <XAxis dataKey="date" tickFormatter={fmtChartDate} tick={{ fontSize: 11, fill: '#71717a' }} axisLine={false} tickLine={false} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#71717a' }} axisLine={false} tickLine={false} width={28} />
                <Tooltip
                    formatter={(value: any) => [value, 'New Users']}
                    labelFormatter={fmtChartDate}
                    contentStyle={{ borderRadius: 12, border: '1px solid #e4e4e7', fontSize: 12 }}
                />
                <Line type="monotone" dataKey="count" stroke="#6366f1" strokeWidth={2} dot={{ r: 3 }} />
            </LineChart>
        </ResponsiveContainer>
    );
}

export default function AdminDashboard({ stats, recent_orders, pending_proofs, daily_revenue, orders_by_status, new_users_weekly, recent_users }: Props) {
    const revenueChartData = daily_revenue.map(d => ({ date: d.date, total: Number(d.total) }));
    const newUsersChartData = (new_users_weekly ?? []).map(d => ({ date: d.date, count: Number(d.count) }));
    const { t } = useTranslation();
    const [moderationResults, setModerationResults] = useState<Record<number, { flagged: boolean; reason: string; severity: string }>>({});
    const [moderating, setModerating] = useState<number | null>(null);

    const moderateProof = async (proofId: number) => {
        setModerating(proofId);
        try {
            const res = await fetch(route('admin.moderation.proof', proofId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });
            if (res.ok) {
                const json = await res.json();
                setModerationResults(prev => ({ ...prev, [proofId]: json }));
            }
        } finally {
            setModerating(null);
        }
    };
    const [summary, setSummary] = useState<string | null>(null);
    const [summaryLoading, setSummaryLoading] = useState(false);
    const [summaryError, setSummaryError] = useState<string | null>(null);
    const [summaryDays, setSummaryDays] = useState(7);

    const fetchSummary = async (days: number) => {
        setSummaryLoading(true);
        setSummaryError(null);
        try {
            const res = await fetch(route('admin.analytics.summary', { days }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json();
            if (!res.ok) {
                setSummaryError(json.message ?? t('ai_error'));
                setSummary(null);
                return;
            }
            setSummary(json.summary ?? null);
        } catch (e) {
            setSummaryError(t('ai_error'));
            setSummary(null);
        } finally {
            setSummaryLoading(false);
        }
    };

    useEffect(() => {
        fetchSummary(summaryDays);
    }, [summaryDays]);

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

                {/* AI Summary */}
                <div className="rounded-xl border border-brand-green/20 bg-brand-green/5 p-5 shadow-sm">
                    <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center gap-2">
                            <Sparkles className="h-4 w-4 text-brand-green" />
                            <h2 className="text-sm font-bold text-gray-900">{t('ai_summary')}</h2>
                        </div>
                        <div className="flex items-center gap-2">
                            <select
                                value={summaryDays}
                                onChange={e => setSummaryDays(Number(e.target.value))}
                                className="text-xs border border-gray-300 rounded-lg px-2 py-1 bg-white focus:outline-none focus:border-brand-green"
                            >
                                <option value={7}>7 days</option>
                                <option value={14}>14 days</option>
                                <option value={30}>30 days</option>
                                <option value={90}>90 days</option>
                            </select>
                            <button
                                onClick={() => fetchSummary(summaryDays)}
                                disabled={summaryLoading}
                                className="p-1.5 rounded-lg hover:bg-brand-green/10 text-brand-green disabled:opacity-50"
                                title={t('ai_refresh')}
                            >
                                {summaryLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                            </button>
                        </div>
                    </div>
                    {summary && <p className="text-sm text-gray-700 leading-relaxed">{summary}</p>}
                    {summaryError && <p className="text-xs text-red-600">{summaryError}</p>}
                    {!summary && !summaryError && summaryLoading && <p className="text-xs text-gray-500">{t('ai_loading')}</p>}
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
                            <RevenueChart data={revenueChartData} />
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

                    {/* New Users Trend (previously fetched but unused) */}
                    <div className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-2">
                        <h2 className="text-base font-semibold text-zinc-900 mb-4">{t('new_users_trend')}</h2>
                        {newUsersChartData.length > 0 ? (
                            <NewUsersChart data={newUsersChartData} />
                        ) : (
                            <p className="text-zinc-500 text-sm">{t('no_new_users')}</p>
                        )}
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
                                <div key={p.id} className="flex flex-col p-4 rounded-xl bg-white border border-brand-orange/20 shadow-sm hover:shadow transition-shadow gap-3">
                                    <div className="flex items-center justify-between">
                                        <div className="overflow-hidden pr-3">
                                            <p className="text-sm font-semibold text-zinc-900 truncate">{p.marketer?.name}</p>
                                            <p className="text-xs text-zinc-500 truncate">{p.contract_application?.contract?.title}</p>
                                            <p className="text-[10px] font-bold uppercase tracking-wider text-brand-orange mt-1">{p.contract_application?.contract?.platform}</p>
                                        </div>
                                        <a href={p.proof_url} target="_blank" rel="noopener" className="shrink-0 text-xs font-semibold text-brand-green hover:text-brand-green/80 px-3 py-1.5 rounded-lg border border-brand-green/20 bg-brand-green/5 hover:bg-brand-green/10 transition-colors">
                                            {t('review')}
                                        </a>
                                    </div>
                                    {moderationResults[p.id] && (
                                        <div className={`text-xs p-2 rounded-lg ${moderationResults[p.id].severity === 'high' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-amber-50 text-amber-700 border border-amber-200'}`}>
                                            <span className="font-semibold flex items-center gap-1"><ShieldAlert className="w-3 h-3" /> {t('ai_reason')}:</span> {moderationResults[p.id].reason}
                                        </div>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => moderateProof(p.id)}
                                        disabled={moderating === p.id}
                                        className="self-start inline-flex items-center gap-1.5 text-xs font-medium text-brand-green hover:text-brand-green/80 disabled:opacity-50"
                                    >
                                        {moderating === p.id && <Loader2 className="w-3 h-3 animate-spin" />}
                                        {t('ai_moderate')}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
