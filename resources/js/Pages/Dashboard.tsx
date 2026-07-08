import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import CreateContractSlideOver from '@/Components/CreateContractSlideOver';
import { motion, AnimatePresence } from 'framer-motion';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
import {
    FaRocket, 
    FaWallet, 
    FaClock, 
    FaCheckCircle, 
    FaChartLine, 
    FaBriefcase, 
    FaPlus, 
    FaArrowRight, 
    FaHistory,
    FaRegLightbulb,
    FaShareAlt,
    FaHandshake,
    FaInfoCircle,
    FaExclamationCircle,
    FaHeadset,
    FaTrophy
} from 'react-icons/fa';

interface Order {
    id: number;
    link: string;
    quantity: number;
    charge: number;
    status: string;
    created_at: string;
    service?: { name: string; category: string };
}

interface Transaction {
    id: number;
    type: string;
    amount: number;
    description: string;
    created_at: string;
}

interface BusinessContract {
    id: number;
    title: string;
    platform?: string | null;
    description: string;
    budget?: number | null;
    slots: number;
    status: string;
    deadline_at?: string | null;
    applications_count?: number;
    pending_applications_count?: number;
    created_at: string;
}

interface RecommendedService {
    id: number;
    name: string;
    category: string;
    description?: string | null;
    min_qty?: number;
    max_qty?: number;
    rate?: number;
    avg_time_minutes?: number | null;
    reason?: string | null;
}

interface Props {
    auth: any;
    stats: {
        total_orders: number;
        active_orders: number;
        completed_orders: number;
        total_spent: number;
    };
    recent_orders: Order[];
    recent_transactions: Transaction[];
    category_counts: Record<string, number>;
    business_contracts: BusinessContract[];
    incoming_contract_applications: any[];
    contract_stats: {
        open_contracts: number;
        total_contracts: number;
        pending_applications: number;
    };
    recommended_services?: RecommendedService[];
    myLeaderboardRanks?: Record<string, { rank: number; score: number } | null>;
}

const statusThemes: Record<string, { bg: string; text: string; icon: any }> = {
    pending: { bg: 'bg-amber-50', text: 'text-amber-600', icon: FaClock },
    processing: { bg: 'bg-emerald-50', text: 'text-emerald-600', icon: FaRocket },
    completed: { bg: 'bg-emerald-100', text: 'text-emerald-700', icon: FaCheckCircle },
    cancelled: { bg: 'bg-red-50', text: 'text-red-600', icon: FaExclamationCircle },
    failed: { bg: 'bg-red-50', text: 'text-red-600', icon: FaExclamationCircle },
};


export default function Dashboard(props: Props) {
    const { auth } = props;
    const user = auth.user;

    if (user.account_type === 'business') {
        return <BusinessDashboardView {...props} />;
    }

    return <CustomerDashboardView {...props} />;
}

function BusinessDashboardView({
    auth,
    stats,
    recent_orders,
    recent_transactions,
    category_counts,
    business_contracts,
    incoming_contract_applications,
    contract_stats,
    recommended_services = [],
}: Props) {
    const user = auth.user;
    const { t } = useTranslation();
    const { formatUSD } = useCurrency();
    const firstName = (user?.name ?? '').trim().split(' ')[0] || 'User';
    
    
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
const contractForm = useForm({
        title: '',
        platform: '',
        description: '',
        budget: '',
        slots: '1',
        deadline_at: '',
    });

    const submitContract = (e: React.FormEvent) => {
        e.preventDefault();
        contractForm.post(route('contracts.store'), {
            onSuccess: () => contractForm.reset(),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('dashboard') + " - Business"} />

            <div className="space-y-12">
                {/* Business Hero */}
                <section className="relative overflow-hidden rounded-[3rem] bg-zinc-950 text-white shadow-2xl shadow-zinc-900/40">
                    <div className="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-blue-500/10 via-amber-500/5 to-transparent pointer-events-none" />
                    <div className="absolute -bottom-24 -left-24 w-64 h-64 bg-emerald-500/10 rounded-full blur-[100px] pointer-events-none" />
                    
                    <div className="relative px-12 py-16 grid lg:grid-cols-[1.5fr,1fr] gap-12 items-center">
                        <div className="space-y-8">
                            <motion.div 
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                className="space-y-4"
                            >
                                <span className="px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[10px] font-black uppercase tracking-widest">
                                      {t('business_panel')}
                                </span>
                                <h1 className="text-4xl lg:text-6xl font-black tracking-tighter leading-tight">
                                    {t('welcome_home', { name: firstName })}, <br />
                                    <span className="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-200">
                                        Business Portal
                                    </span>
                                </h1>
                                <p className="text-zinc-400 text-lg max-w-xl font-medium leading-relaxed">
                                    Manage your business contracts, review talent applications, and oversee your entire talent pipeline.
                                </p>
                            </motion.div>

                            <motion.div 
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.1 }}
                                className="flex flex-wrap gap-4"
                            >
                                <Link href={route('contracts.index')} className="px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 text-zinc-900 font-black text-sm uppercase tracking-widest shadow-xl shadow-amber-500/20 hover:scale-105 transition-all">
                                    Marketplace
                                </Link>
                                <Link href={route('wallet.index')} className="px-8 py-4 rounded-2xl bg-white/10 text-white border border-white/20 font-black text-sm uppercase tracking-widest hover:bg-white/20 transition-all">
                                    {t('top_up_wallet')}
                                </Link>
                            </motion.div>
                        </div>

                        <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 space-y-8">
                            <div>
                                <p className="text-zinc-400 text-[10px] font-black uppercase tracking-widest mb-2">{t('available_budget')}</p>
                                <div className="flex items-end gap-3">
                                    <span className="text-5xl font-black tracking-tighter">{formatUSD(Number(user.balance || 0))}</span>
                                    <span className="text-amber-400 text-sm font-black mb-1">USD</span>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="p-5 rounded-3xl bg-zinc-800/50 border border-white/5">
                                    <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">{t('active_contracts')}</p>
                                    <p className="text-xl font-black">{contract_stats.open_contracts}</p>
                                </div>
                                <div className="p-5 rounded-3xl bg-zinc-800/50 border border-white/5">
                                    <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">{t('pending_apps')}</p>
                                    <p className="text-xl font-black text-amber-400">{contract_stats.pending_applications}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="grid gap-10 lg:grid-cols-[1.6fr,1fr]">
                    <div className="space-y-10">
                        {/* Contract Terminal */}
                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                            <div className="flex items-center justify-between mb-8">
                                <div>
                                    <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('contract_terminal')}</h3>
                                    <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">{t('contract_terminal_subtitle')}</p>
                                </div>
                                <div className="h-12 w-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500">
                                    <FaPlus />
                                </div>
                            </div>

                            
                            <div className="bg-zinc-50 border-2 border-dashed border-zinc-200 rounded-[2rem] p-10 text-center space-y-6">
                                <div className="h-16 w-16 bg-white rounded-2xl mx-auto flex items-center justify-center shadow-sm border border-zinc-100 text-amber-500">
                                    <FaRocket className="text-2xl" />
                                </div>
                                <div>
                                    <h4 className="text-lg font-black text-zinc-900">{t('need_fresh_talent')}</h4>
                                    <p className="text-zinc-500 text-sm font-medium mt-1">{t('deploy_new_mission')}</p>
                                </div>
                                <button 
                                    onClick={() => setIsCreateModalOpen(true)}
                                    className="px-10 py-4 rounded-xl bg-zinc-900 text-white font-black text-xs uppercase tracking-widest shadow-xl hover:bg-zinc-800 transition-all hover:scale-105 active:scale-95"
                                >
                                    Draft New Mission
                                </button>
                            </div>

                        </div>

                        {/* Active Contracts */}
                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                            <div className="flex items-center justify-between mb-10">
                                <div>
                                    <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('active_contracts')}</h3>
                                    <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">{t('active_contracts_subtitle')}</p>
                                </div>
                                <Link href={route('contracts.index')} className="text-[10px] font-black uppercase tracking-widest text-emerald-600">{t('archive')}</Link>
                            </div>

                            <div className="space-y-4">
                                {business_contracts.length === 0 && (
                                    <p className="text-zinc-400 text-sm font-medium italic p-6 border-2 border-dashed border-zinc-100 rounded-3xl text-center">{t('no_contracts_active')}</p>
                                )}
                                {business_contracts.map((contract) => (
                                    <div key={contract.id} className="p-6 rounded-[2rem] bg-zinc-50 border border-zinc-100">
                                        <div className="flex items-center justify-between mb-2">
                                            <p className="font-black text-zinc-900">{contract.title}</p>
                                            <span className="text-[8px] font-black uppercase tracking-widest px-3 py-1 bg-white rounded-full text-zinc-500 border border-zinc-100">{contract.status}</span>
                                        </div>
                                        <p className="text-xs text-zinc-500 line-clamp-2 mb-3">{contract.description}</p>
                                        <div className="flex items-center gap-4 text-[9px] font-black uppercase tracking-widest text-zinc-400">
                                            <span className="flex items-center gap-1.5"><FaBriefcase className="text-emerald-500" /> {t('pending_count', { count: contract.pending_applications_count || 0 })}</span>
                                            <span className="h-1 w-1 rounded-full bg-zinc-200" />
                                            <span>{t('budget_amount', { amount: contract.budget || 0 })}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-10">
                        {/* Pending Talent */}
                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                            <div className="mb-10">
                                <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('pending_talent')}</h3>
                                <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">{t('pending_talent_subtitle')}</p>
                            </div>

                            <div className="space-y-6">
                                {incoming_contract_applications.length === 0 && (
                                    <p className="text-zinc-400 text-sm font-medium italic p-6 border-2 border-dashed border-zinc-100 rounded-3xl text-center">{t('no_pending_talent')}</p>
                                )}
                                {incoming_contract_applications.map((app) => {
                                    const marketerName = app?.marketer?.name || 'Unknown';
                                    const contractTitle = app?.contract?.title || 'Contract';
                                    const contractId = app?.contract?.id;
                                    const appId = app?.id;

                                    return (
                                    <div key={app.id} className="p-8 rounded-[2.5rem] border-2 border-amber-100 bg-amber-50/30">
                                        <div className="flex items-center gap-4 mb-4">
                                            <div className="h-12 w-12 rounded-2xl bg-white border border-amber-100 flex items-center justify-center font-black text-amber-600 shadow-sm">
                                                {marketerName[0]}
                                            </div>
                                            <div>
                                                <p className="text-sm font-black text-zinc-900">{marketerName}</p>
                                                <p className="text-[10px] font-bold text-amber-600 uppercase tracking-widest">{t('applying_for', { title: contractTitle })}</p>
                                            </div>
                                        </div>
                                        <p className="text-xs text-zinc-600 bg-white p-4 rounded-2xl border border-amber-100 mb-6 italic leading-relaxed">
                                            "{app.pitch || t('no_pitch_provided')}"
                                        </p>
                                        <div className="flex gap-2">
                                            <button 
                                                onClick={() => {
                                                    if (!contractId || !appId) return;
                                                    router.post(route('contracts.applications.decision', [contractId, appId]), { decision: 'approved' });
                                                }}
                                                disabled={!contractId || !appId}
                                                className="flex-1 py-3 rounded-xl bg-amber-500 text-white text-[10px] font-black uppercase tracking-widest shadow-lg shadow-amber-500/20"
                                            >
                                                {t('approve')}
                                            </button>
                                            <button 
                                                onClick={() => {
                                                    if (!contractId || !appId) return;
                                                    router.post(route('contracts.applications.decision', [contractId, appId]), { decision: 'denied' });
                                                }}
                                                disabled={!contractId || !appId}
                                                className="flex-1 py-3 rounded-xl bg-white border border-red-100 text-red-600 text-[10px] font-black uppercase tracking-widest disabled:opacity-50"
                                            >
                                                {t('decline')}
                                            </button>
                                        </div>
                                    </div>
                                );})}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
            <CreateContractSlideOver 
                isOpen={isCreateModalOpen} 
                onClose={() => setIsCreateModalOpen(false)} 
                t={t} 
            />
</AuthenticatedLayout>
    );
}

function CustomerDashboardView({
    auth,
    stats,
    recent_orders,
    recent_transactions,
    category_counts,
    recommended_services = [],
    myLeaderboardRanks = {},
}: Props) {
    const user = auth.user;
    const { t } = useTranslation();
    const { formatUSD } = useCurrency();
    const firstName = (user?.name ?? '').trim().split(' ')[0] || 'User';

    const topCategories = Object.entries(category_counts ?? {})
        .sort(([, a], [, b]) => b - a)
        .slice(0, 4);

    return (
        <AuthenticatedLayout>
            <Head title={t('dashboard')} />

            <div className="space-y-12">
                {/* Immersive Welcome Hero */}
                <section className="relative overflow-hidden rounded-[3rem] bg-zinc-950 text-white shadow-2xl shadow-zinc-900/40">
                    <div className="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-emerald-500/10 via-amber-500/5 to-transparent pointer-events-none" />
                    <div className="absolute -bottom-24 -left-24 w-64 h-64 bg-red-500/10 rounded-full blur-[100px] pointer-events-none" />
                    <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-gradient-to-br from-emerald-600/5 via-amber-500/5 to-red-600/5 pointer-events-none" />
                    
                    <div className="relative px-12 py-16 grid lg:grid-cols-[1.5fr,1fr] gap-12 items-center">
                        <div className="space-y-8">
                            <motion.div 
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                className="space-y-4"
                            >
                                <span className="px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase tracking-widest">
                                      {t('your_performance')}
                                </span>
                                <h1 className="text-4xl lg:text-6xl font-black tracking-tighter leading-tight">
                                    {t('welcome_home', { name: firstName })}, <br />
                                    <span className="text-transparent bg-clip-text bg-gradient-to-r from-white to-zinc-500">
                                        {t('dashboard')}
                                    </span>
                                </h1>
                                <p className="text-zinc-400 text-lg max-w-xl font-medium leading-relaxed">
                                    {t('dashboard_hero_subtitle')}
                                </p>
                            </motion.div>

                            <motion.div 
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.1 }}
                                className="flex flex-wrap gap-4"
                            >
                                <Link href={route('orders.create')} className="px-8 py-4 rounded-2xl bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white font-black text-sm uppercase tracking-widest shadow-xl shadow-emerald-500/20 hover:scale-105 transition-all">
                                    {t('new_growth_campaign')}
                                </Link>
                                <Link href={route('wallet.index')} className="px-8 py-4 rounded-2xl bg-white/10 text-white border border-white/20 font-black text-sm uppercase tracking-widest hover:bg-white/20 transition-all">
                                    {t('top_up_wallet')}
                                </Link>
                            </motion.div>
                        </div>

                        <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 space-y-8">
                            <div>
                                <p className="text-zinc-400 text-[10px] font-black uppercase tracking-widest mb-2">{t('power_reserve')}</p>
                                <div className="flex items-end gap-3">
                                    <span className="text-5xl font-black tracking-tighter">{formatUSD(Number(user.balance || 0))}</span>
                                    <span className="text-emerald-400 text-sm font-black mb-1">USD</span>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="p-5 rounded-3xl bg-zinc-800/50 border border-white/5">
                                    <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">{t('efficiency')}</p>
                                    <p className="text-xl font-black">
                                        {stats.total_orders > 0 ? Math.round((stats.completed_orders / stats.total_orders) * 100) : 0}%
                                    </p>
                                </div>
                                <div className="p-5 rounded-3xl bg-zinc-800/50 border border-white/5">
                                    <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">{t('in_flight')}</p>
                                    <p className="text-xl font-black text-emerald-400">{stats.active_orders}</p>
                                </div>
                            </div>
                            <Link href={route('orders.index')} className="flex items-center justify-between group px-2">
                                <span className="text-[10px] font-black uppercase tracking-widest text-zinc-400 group-hover:text-white transition-colors">{t('audit_full_activity')}</span>
                                <FaArrowRight className="text-zinc-600 group-hover:text-emerald-400 transition-all group-hover:translate-x-2" />
                            </Link>
                        </div>
                    </div>
                </section>

                {/* Leaderboard Ranks */}
                <section className="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    {[
                        { key: 'referrals', label: 'Top Referrers', color: 'text-emerald-500', bg: 'bg-emerald-500/10' },
                        { key: 'orders', label: 'Top Orderers', color: 'text-blue-500', bg: 'bg-blue-500/10' },
                        { key: 'deposits', label: 'Top Depositors', color: 'text-amber-500', bg: 'bg-amber-500/10' }
                    ].map(cat => {
                        const rank = myLeaderboardRanks[cat.key];
                        return (
                            <Link key={cat.key} href={route('leaderboard.index')} className={`group p-6 rounded-[2rem] border border-zinc-100 bg-white hover:border-zinc-200 shadow-xl shadow-zinc-200/20 transition-all flex items-center justify-between`}>
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-1">{cat.label}</p>
                                    <div className="flex items-center gap-2">
                                        <div className={`h-8 w-8 rounded-full flex items-center justify-center ${cat.bg} ${cat.color}`}>
                                            <FaTrophy className="text-sm" />
                                        </div>
                                        {rank ? (
                                            <p className="text-2xl font-black text-zinc-900 group-hover:text-amber-500 transition-colors">
                                                #{rank.rank}
                                            </p>
                                        ) : (
                                            <p className="text-sm font-bold text-zinc-300">{t('unranked')}</p>
                                        )}
                                    </div>
                                </div>
                                <FaArrowRight className="text-zinc-200 group-hover:text-amber-500 group-hover:translate-x-1 transition-all" />
                            </Link>
                        );
                    })}
                </section>

                {/* Core KPI Grid */}
                <section className="grid grid-cols-2 lg:grid-cols-2 gap-6">
                    <StatCard label={t('total_spent')} value={`$${Number(stats.total_spent || 0).toFixed(2)}`} icon={FaChartLine} color="text-indigo-500" />
                    <StatCard label={t('total_orders')} value={stats.total_orders} icon={FaRocket} color="text-emerald-500" />
                </section>

                {/* AI Recommendations */}
                {recommended_services.length > 0 && (
                    <section className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                        <div className="flex items-center justify-between mb-8">
                            <div>
                                <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('recommended_for_you')}</h3>
                                <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">{t('ai_recommendations_subtitle')}</p>
                            </div>
                            <FaRegLightbulb className="text-emerald-500 text-2xl" />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {recommended_services.map((service) => (
                                <Link
                                    key={service.id}
                                    href={route('orders.create', { service_id: service.id })}
                                    className="group p-6 rounded-[2rem] border border-zinc-100 bg-zinc-50 hover:border-emerald-200 hover:bg-emerald-50/30 transition-all"
                                >
                                    <p className="text-xs font-black uppercase tracking-widest text-emerald-600 mb-2">{service.category}</p>
                                    <p className="font-black text-zinc-900 mb-2 line-clamp-2 group-hover:text-emerald-700 transition-colors">{service.name}</p>
                                    {service.reason && <p className="text-xs text-zinc-500 mb-3 line-clamp-2">{service.reason}</p>}
                                    <div className="flex items-center justify-between text-[10px] font-bold text-zinc-400 uppercase tracking-widest">
                                        <span>{t('from_qty', { qty: service.min_qty ?? 1 })}</span>
                                        <FaArrowRight className="text-zinc-300 group-hover:text-emerald-500 group-hover:translate-x-1 transition-all" />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                <div className="grid gap-10 grid-cols-1">
                    {/* Active Work Section */}
                    <div className="space-y-10">
                        {/* Recent Activity Table */}
                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                            <div className="flex items-center justify-between mb-10">
                                <div>
                                        <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('recent_orders')}</h3>
                                        <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">{t('active_orders')}</p>
                                </div>
                                <Link href={route('orders.index')} className="text-[10px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-700 transition-colors">
                                    {t('manage')}
                                </Link>
                            </div>

                            <div className="space-y-4">
                                {recent_orders.length === 0 && (
                                    <div className="p-12 text-center bg-zinc-50 rounded-[2rem] border-2 border-dashed border-zinc-200">
                                        <p className="text-zinc-400 font-bold text-sm">{t('no_orders')}</p>
                                    </div>
                                )}
                                {recent_orders.map((order) => {
                                    const theme = statusThemes[order.status] || statusThemes.pending;
                                    const Icon = theme.icon;
                                    return (
                                        <Link key={order.id} href={route('orders.show', order.id)} className="flex items-center gap-6 p-6 rounded-[2rem] border border-zinc-50 hover:border-zinc-200 hover:bg-zinc-50 transition-all group">
                                            <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-xl shadow-sm ${theme.bg} ${theme.text}`}>
                                                <Icon />
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="font-black text-zinc-900 truncate group-hover:text-emerald-600 transition-colors">{order.service?.name || `Order #${order.id}`}</p>
                                                <div className="flex items-center gap-4 text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-1">
                                                    <span>Qty: {order.quantity}</span>
                                                    <span className="h-1 w-1 rounded-full bg-zinc-200" />
                                                    <span>Cost: ${Number(order.charge || 0).toFixed(2)}</span>
                                                    <span className="h-1 w-1 rounded-full bg-zinc-200" />
                                                    <span>{new Date(order.created_at).toLocaleDateString()}</span>
                                                </div>
                                            </div>
                                            <div className={`px-4 py-2 rounded-full text-[8px] font-black uppercase tracking-widest ${theme.bg} ${theme.text}`}>
                                                {order.status}
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    {/* Analytics & Side Panels */}
                    <div className="space-y-10">
                        {/* Demand Mix Visualization */}
                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                            <div className="flex items-center justify-between mb-10">
                                <h3 className="text-xl font-black text-zinc-900 tracking-tight">{t('demand_mix')}</h3>
                                <FaRegLightbulb className="text-zinc-300" />
                            </div>
                            <div className="space-y-8">
                                {topCategories.length === 0 && (
                                    <p className="text-zinc-400 text-sm font-medium italic">{t('demand_mix_empty')}</p>
                                )}
                                {topCategories.map(([category, count], idx) => {
                                    const max = topCategories[0][1];
                                    const percentage = (count / max) * 100;
                                    return (
                                        <div key={category} className="space-y-3">
                                            <div className="flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                                                <span className="text-zinc-900">{idx + 1}. {category}</span>
                                                <span className="text-zinc-400">{t('volumes_count', { count })}</span>
                                            </div>
                                            <div className="h-2 w-full bg-zinc-50 rounded-full overflow-hidden border border-zinc-100">
                                                <motion.div 
                                                    initial={{ width: 0 }}
                                                    animate={{ width: `${percentage}%` }}
                                                    transition={{ duration: 1, ease: "easeOut" }}
                                                    className="h-full bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 rounded-full"
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Recent Transactions */}
                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40 overflow-hidden">
                            <div className="flex items-center justify-between mb-10 px-2">
                                  <h3 className="text-xl font-black text-zinc-900 tracking-tight">{t('recent_tx')}</h3>
                                  <Link href={route('wallet.index')} className="text-[10px] font-black uppercase tracking-widest text-emerald-600">{t('view_all')}</Link>
                            </div>
                            <div className="space-y-1">
                                {recent_transactions.length === 0 && (
                                     <p className="text-zinc-400 text-sm font-medium italic px-2">{t('no_transactions')}</p>
                                )}
                                {recent_transactions.map((tx) => (
                                    <div key={tx.id} className="flex items-center justify-between p-4 hover:bg-zinc-50 rounded-2xl transition-all">
                                        <div className="flex items-center gap-4">
                                            <div className={`h-2 w-2 rounded-full ${tx.type === 'deposit' ? 'bg-emerald-500' : tx.type === 'refund' ? 'bg-amber-400' : 'bg-zinc-400'}`} />
                                            <div>
                                                <p className="text-xs font-black text-zinc-800 truncate max-w-[150px]">{tx.description || tx.type}</p>
                                                <p className="text-[8px] font-bold text-zinc-400 uppercase tracking-widest">{new Date(tx.created_at).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                        <span className={`text-xs font-mono font-black ${Number(tx.amount || 0) >= 0 ? 'text-emerald-600' : 'text-zinc-900'}`}>
                                            {Number(tx.amount || 0) >= 0 ? '+' : ''}{Number(tx.amount || 0).toFixed(2)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Support Card */}
                        <div className="rounded-[3rem] bg-zinc-950 p-10 text-white shadow-xl shadow-zinc-900/40 relative overflow-hidden group">
                            <div className="absolute inset-0 bg-gradient-to-br from-emerald-600/10 via-amber-500/10 to-red-600/10 opacity-50 group-hover:opacity-100 transition-opacity" />
                                <h3 className="text-xl font-black mb-4 relative z-10">{t('support')}</h3>
                                <p className="text-zinc-400 text-sm font-medium leading-relaxed mb-8 relative z-10">{t('your_performance')}</p>
                            <Link href={route('tickets.index')} className="relative z-10 inline-flex items-center gap-3 px-8 py-3 bg-white text-zinc-900 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-105 transition-all">
                                    {t('new_ticket')} <FaHeadset />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value, icon: Icon, color }: any) {
    return (
        <div className="bg-white rounded-[2.5rem] p-8 border border-zinc-200 shadow-xl shadow-zinc-200/40 group hover:-translate-y-1 transition-all relative overflow-hidden">
            <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 opacity-0 group-hover:opacity-100 transition-opacity" />
            <div className="flex items-center justify-between mb-4">
                <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{label}</p>
                <div className={`h-10 w-10 rounded-xl bg-zinc-50 flex items-center justify-center ${color} group-hover:scale-110 transition-transform`}>
                    <Icon />
                </div>
            </div>
            <p className="text-3xl font-black text-zinc-900 tracking-tighter">{value}</p>
        </div>
    );
}

function InputWrapper({ label, children }: any) {
    return (
        <div className="space-y-2">
            <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">{label}</label>
            {children}
        </div>
    );
}
