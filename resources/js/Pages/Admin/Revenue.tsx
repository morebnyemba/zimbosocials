import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, Users, Package, TrendingUp, Calendar, DollarSign, Trophy, PieChart } from 'lucide-react';

interface DailyRevenue { date: string; total: string; count: number; }
interface TopService { id: number; name: string; category: string; orders_count: number; orders_sum_charge: string; }
interface NewUser { date: string; count: number; }
interface OrderStatus { status: string; count: number; revenue: string; }
interface Summary { total_revenue: string; month_revenue: string; today_revenue: string; total_users: number; total_orders: number; avg_order_value: string; }

interface Props { daily_revenue: DailyRevenue[]; top_services: TopService[]; new_users: NewUser[]; orders_by_status: OrderStatus[]; summary: Summary; days: number; }

function BarChart({ data, maxH = 64 }: { data: { label: string; value: number }[]; maxH?: number }) {
    const max = Math.max(...data.map(d => d.value), 1);
    return (
        <div className="flex items-end gap-[2px] h-16">
            {data.map((d, i) => (
                <div key={i} className="flex-1 group relative">
                    <div className="w-full rounded-t bg-brand-green/80 group-hover:bg-brand-green transition-all" style={{ height: `${Math.max((d.value / max) * maxH, 2)}px` }} />
                    <div className="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-900 text-[10px] text-white px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 pointer-events-none">{d.label}: ${d.value.toFixed(2)}</div>
                </div>
            ))}
        </div>
    );
}

export default function Revenue({ daily_revenue, top_services, new_users, orders_by_status, summary, days }: Props) {
    const revData = daily_revenue.map(d => ({ label: d.date.slice(5), value: Number(d.total) }));
    const userGrowth = new_users.map(d => ({ label: d.date.slice(5), value: d.count }));
    const maxUsers = Math.max(...userGrowth.map(d => d.value), 1);

    return (
        <AdminLayout>
            <Head title="Revenue Analytics" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Revenue Analytics</h1>
                        <p className="text-gray-500 text-sm mt-1">Showing data for the last {days} days</p>
                    </div>
                    <div className="flex gap-2">
                        {[7, 14, 30, 90].map(d => (
                            <button key={d} onClick={() => router.get(route('admin.revenue'), { days: d }, { preserveState: true })}
                                className={`px-4 py-1.5 text-xs font-medium rounded-full border transition-all ${days === d ? 'bg-brand-green/10 text-brand-green border-brand-green/30 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'}`}>{d} Days</button>
                        ))}
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    {[
                        { label: 'All-Time Revenue', value: `$${Number(summary.total_revenue).toFixed(2)}`, icon: DollarSign, color: 'text-brand-green', bg: 'bg-brand-green/10' },
                        { label: 'This Month', value: `$${Number(summary.month_revenue).toFixed(2)}`, icon: Calendar, color: 'text-blue-600', bg: 'bg-blue-50' },
                        { label: 'Today', value: `$${Number(summary.today_revenue).toFixed(2)}`, icon: TrendingUp, color: 'text-brand-orange', bg: 'bg-brand-orange/10' },
                        { label: 'Total Users', value: summary.total_users, icon: Users, color: 'text-indigo-600', bg: 'bg-indigo-50' },
                        { label: 'Total Orders', value: summary.total_orders, icon: Package, color: 'text-rose-600', bg: 'bg-rose-50' },
                        { label: 'Avg Order', value: `$${Number(summary.avg_order_value).toFixed(2)}`, icon: BarChart3, color: 'text-purple-600', bg: 'bg-purple-50' },
                    ].map(s => (
                        <div key={s.label} className="overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                            <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${s.bg} ${s.color} mb-3`}>
                                <s.icon size={20} strokeWidth={2.5} />
                            </div>
                            <p className="text-xl font-bold text-gray-900">{s.value}</p>
                            <p className="text-xs font-medium text-gray-500 mt-1 uppercase tracking-wider">{s.label}</p>
                        </div>
                    ))}
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div className="flex items-center gap-2 mb-6">
                            <TrendingUp size={18} className="text-gray-400" />
                            <h2 className="text-lg font-bold text-gray-900 tracking-tight">Daily Revenue</h2>
                        </div>
                        {revData.length > 0 ? <BarChart data={revData} /> : <p className="text-gray-500 text-sm italic text-center py-4">No revenue data available for this period.</p>}
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div className="flex items-center gap-2 mb-6">
                            <Users size={18} className="text-gray-400" />
                            <h2 className="text-lg font-bold text-gray-900 tracking-tight">User Growth</h2>
                        </div>
                        <div className="flex items-end gap-[2px] h-16">
                            {userGrowth.map((d, i) => (
                                <div key={i} className="flex-1 group relative">
                                    <div className="w-full rounded-t bg-brand-orange/80 group-hover:bg-brand-orange transition-all" style={{ height: `${Math.max((d.value / maxUsers) * 64, 2)}px` }} />
                                    <div className="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-900 text-[10px] text-white px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10 pointer-events-none">{d.label}: {d.value}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Top Services + Orders by Status */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div className="flex items-center gap-2 mb-6">
                            <Trophy size={18} className="text-amber-500" />
                            <h2 className="text-lg font-bold text-gray-900 tracking-tight">Top Services</h2>
                        </div>
                        <div className="space-y-3">
                            {top_services.map((s, i) => (
                                <div key={s.id} className="flex items-center gap-4 p-3 rounded-xl border border-gray-100 hover:bg-gray-50 transition-colors">
                                    <span className="flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-500 text-xs font-bold">{i + 1}</span>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{s.name}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">{s.category} · {s.orders_count} orders</p>
                                    </div>
                                    <span className="text-sm font-bold text-brand-green font-mono">${Number(s.orders_sum_charge || 0).toFixed(2)}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div className="flex items-center gap-2 mb-6">
                            <PieChart size={18} className="text-gray-400" />
                            <h2 className="text-lg font-bold text-gray-900 tracking-tight">Orders by Status</h2>
                        </div>
                        <div className="space-y-4">
                            {orders_by_status.map(o => {
                                const total = orders_by_status.reduce((a, b) => a + b.count, 0);
                                return (
                                    <div key={o.status} className="flex items-center gap-4">
                                        <span className="text-xs font-medium text-gray-600 uppercase tracking-wider w-24">{o.status}</span>
                                        <div className="flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div className="h-full rounded-full bg-brand-green" style={{ width: `${(o.count / total) * 100}%` }} />
                                        </div>
                                        <div className="flex flex-col items-end w-20">
                                            <span className="text-xs font-bold text-gray-900">{o.count}</span>
                                            <span className="text-[10px] font-medium text-gray-500">${Number(o.revenue || 0).toFixed(0)}</span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
