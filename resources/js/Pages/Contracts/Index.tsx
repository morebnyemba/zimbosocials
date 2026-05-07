import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaBriefcase, 
    FaRocket, 
    FaClock, 
    FaGlobe, 
    FaPlus, 
    FaCheckCircle, 
    FaExclamationCircle, 
    FaChevronRight,
    FaRegHandshake,
    FaTimes,
    FaArrowRight,
    FaDollarSign,
    FaStar,
    FaMedal,
} from 'react-icons/fa';

interface TopMarketer {
    id: number;
    name: string;
    company_name?: string | null;
    avg_rating: number | null;
    review_count: number;
    completed_contracts: number;
}

interface Contract { 
    id: number; 
    title: string; 
    platform?: string; 
    description: string; 
    budget?: string; 
    slots?: number; 
    deadline_at?: string; 
    status: string; 
    applications_count?: number; 
    pending_applications_count?: number; 
    business?: { id: number; name: string; company_name?: string }; 
    created_at: string; 
}

interface Props { 
    my_contracts: { data: Contract[]; links: any[] }; 
    available_contracts: { data: Contract[]; links: any[] }; 
    top_marketers: TopMarketer[];
}

const statusStyles: Record<string, { bg: string; text: string; icon: any }> = { 
    open: { bg: 'bg-emerald-500/10', text: 'text-emerald-500', icon: FaCheckCircle }, 
    filled: { bg: 'bg-blue-500/10', text: 'text-blue-500', icon: FaRocket }, 
    closed: { bg: 'bg-zinc-500/10', text: 'text-zinc-500', icon: FaTimes } 
};

export default function ContractsIndex({ my_contracts, available_contracts, top_marketers }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const user = auth.user;
    const isBusinessAccount = user.account_type === 'business';
    
    const [tab, setTab] = useState<'available' | 'my' | 'rankings'>(isBusinessAccount ? 'my' : 'available');
    const [showCreate, setShowCreate] = useState(false);
    const [applyingContract, setApplyingContract] = useState<Contract | null>(null);
    const [closingContractId, setClosingContractId] = useState<number | null>(null);

    const { data, setData, post, processing, reset, errors } = useForm({ 
        title: '', 
        platform: '', 
        description: '', 
        budget: '', 
        slots: '1', 
        deadline_at: '' 
    });

    const { data: applyData, setData: setApplyData, post: postApply, processing: applying } = useForm({
        pitch: ''
    });

    const submitCreate = (e: React.FormEvent) => { 
        e.preventDefault(); 
        post(route('contracts.store'), { 
            preserveScroll: true, 
            onSuccess: () => { 
                setShowCreate(false); 
                reset(); 
            } 
        }); 
    };

    const submitApply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!applyingContract) return;
        postApply(route('contracts.apply', applyingContract.id), {
            preserveScroll: true,
            onSuccess: () => setApplyingContract(null)
        });
    };

    const closeContract = (id: number) => { 
        setClosingContractId(id);
    };

    const confirmCloseContract = () => {
        if (!closingContractId) return;
        router.delete(route('contracts.destroy', closingContractId), {
            preserveScroll: true,
            onFinish: () => setClosingContractId(null),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('contracts_marketplace_title')} />
            
            <div className="max-w-7xl mx-auto px-6 lg:px-12 py-12">
                {/* Header Section */}
                <header className="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-8">
                    <div className="space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="h-12 w-12 rounded-2xl bg-zinc-900 flex items-center justify-center shadow-lg">
                                <FaBriefcase className="text-emerald-400 text-xl" />
                            </div>
                            <span className="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">{t('contracts_opportunities_terminal')}</span>
                        </div>
                        <h1 className="text-5xl font-black text-zinc-900 tracking-tighter">
                            {t('contracts_marketplace_title')}
                        </h1>
                        <p className="text-zinc-500 font-medium max-w-xl leading-relaxed">
                            {isBusinessAccount 
                                ? t('contracts_business_subtitle') 
                                : t('contracts_marketer_subtitle')}
                        </p>
                    </div>

                    {isBusinessAccount && (
                        <button 
                            onClick={() => setShowCreate(true)} 
                            className="group relative px-8 py-4 rounded-2xl bg-zinc-950 text-white font-black uppercase tracking-widest text-xs shadow-2xl hover:scale-105 transition-all flex items-center gap-3 overflow-hidden"
                        >
                            <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 opacity-90 group-hover:opacity-100 transition-opacity" />
                            <FaPlus className="relative z-10" />
                            <span className="relative z-10">{t('post_new_opportunity')}</span>
                        </button>
                    )}
                </header>

                {/* Tabs */}
                <div className="flex items-center gap-4 mb-10 p-1.5 rounded-2xl bg-zinc-100 w-fit">
                    <button 
                        onClick={() => setTab('available')} 
                        className={`px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all ${tab === 'available' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-400 hover:text-zinc-600'}`}
                    >
                        {t('marketplace')}
                    </button>
                    <button 
                        onClick={() => setTab('my')} 
                        className={`px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all ${tab === 'my' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-400 hover:text-zinc-600'}`}
                    >
                        {isBusinessAccount ? t('my_postings') : t('my_applications')}
                    </button>
                    <button
                        onClick={() => setTab('rankings')}
                        className={`px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all flex items-center gap-2 ${tab === 'rankings' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-400 hover:text-zinc-600'}`}
                    >
                        <FaMedal className="text-amber-400" /> Rankings
                    </button>
                </div>

                {/* Content Area */}
                <AnimatePresence mode="wait">
                    <motion.div
                        key={tab}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                        transition={{ duration: 0.3 }}
                        className={tab === 'rankings' ? 'space-y-4 max-w-3xl' : 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8'}
                    >
                        {tab === 'available' ? (
                            available_contracts.data.length > 0 ? (
                                available_contracts.data.map(contract => (
                                    <AvailableContractCard 
                                        key={contract.id} 
                                        contract={contract} 
                                        onApply={() => setApplyingContract(contract)} 
                                    />
                                ))
                            ) : (
                                <EmptyState message={t('marketplace_empty')} />
                            )
                        ) : tab === 'rankings' ? (
                            top_marketers.length > 0 ? (
                                top_marketers.map((m, idx) => (
                                    <MarketerRankCard key={m.id} marketer={m} rank={idx + 1} />
                                ))
                            ) : (
                                <div className="col-span-full py-20 text-center">
                                    <FaMedal className="mx-auto text-4xl text-zinc-200 mb-4" />
                                    <p className="text-zinc-400 font-bold text-sm">No ranked marketers yet. Rankings appear once reviews are submitted.</p>
                                </div>
                            )
                        ) : (
                            my_contracts.data.length > 0 ? (
                                my_contracts.data.map(contract => (
                                    <MyContractCard 
                                        key={contract.id} 
                                        contract={contract} 
                                        onClose={closeContract} 
                                    />
                                ))
                            ) : (
                                <EmptyState message={isBusinessAccount ? t('my_contracts_empty_business') : t('my_contracts_empty_marketer')} />
                            )
                        )}
                    </motion.div>
                </AnimatePresence>

                {/* Pagination (Simple for now) */}
                {(tab === 'available' ? available_contracts.links : my_contracts.links).length > 3 && (
                    <div className="mt-12 flex justify-center gap-2">
                        {/* Pagination component would go here */}
                    </div>
                )}
            </div>

            {/* Application Modal */}
            <Modal show={!!applyingContract} onClose={() => setApplyingContract(null)}>
                <form onSubmit={submitApply} className="p-8 space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('contract_application')}</h3>
                            <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">{t('submit_your_pitch')}</p>
                        </div>
                        <button type="button" onClick={() => setApplyingContract(null)} className="h-10 w-10 rounded-xl bg-zinc-100 flex items-center justify-center hover:bg-zinc-200">
                            <FaTimes />
                        </button>
                    </div>

                    <div className="p-5 rounded-2xl bg-zinc-50 border border-zinc-200">
                        <p className="text-sm font-bold text-zinc-900">{applyingContract?.title}</p>
                        <p className="text-xs text-zinc-500 mt-1">{applyingContract?.business?.company_name || applyingContract?.business?.name}</p>
                    </div>

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('your_pitch_proposal')}</label>
                        <textarea 
                            value={applyData.pitch}
                            onChange={e => setApplyData('pitch', e.target.value)}
                            placeholder={t('your_pitch_placeholder')}
                            rows={5}
                            className="w-full rounded-2xl border-zinc-200 focus:border-emerald-500 focus:ring-emerald-500 transition-all"
                            required
                        />
                    </div>

                    <button 
                        disabled={applying}
                        className="w-full py-4 rounded-2xl bg-zinc-900 text-white font-black uppercase tracking-widest text-xs shadow-xl hover:bg-zinc-800 disabled:opacity-50 flex items-center justify-center gap-3"
                    >
                        {applying ? t('submitting') : <><FaRocket /> {t('submit_application')}</>}
                    </button>
                </form>
            </Modal>

            {/* Create Modal */}
            <Modal show={showCreate} onClose={() => setShowCreate(false)}>
                <form onSubmit={submitCreate} className="p-8 space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('post_opportunity')}</h3>
                            <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">{t('define_campaign_requirements')}</p>
                        </div>
                        <button type="button" onClick={() => setShowCreate(false)} className="h-10 w-10 rounded-xl bg-zinc-100 flex items-center justify-center hover:bg-zinc-200">
                            <FaTimes />
                        </button>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('contract_title_label')}</label>
                            <input 
                                value={data.title}
                                onChange={e => setData('title', e.target.value)}
                                placeholder={t('contract_title_placeholder')}
                                className="w-full rounded-2xl border-zinc-200 focus:border-emerald-500 focus:ring-emerald-500"
                                required
                            />
                            {errors.title && <p className="text-[10px] text-red-500 font-bold uppercase">{errors.title}</p>}
                        </div>
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('target_platform')}</label>
                            <input 
                                value={data.platform}
                                onChange={e => setData('platform', e.target.value)}
                                placeholder={t('target_platform_placeholder_short')}
                                className="w-full rounded-2xl border-zinc-200 focus:border-emerald-500 focus:ring-emerald-500"
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('detailed_description')}</label>
                        <textarea 
                            value={data.description}
                            onChange={e => setData('description', e.target.value)}
                            placeholder={t('deliverables_placeholder')}
                            rows={4}
                            className="w-full rounded-2xl border-zinc-200 focus:border-emerald-500 focus:ring-emerald-500"
                            required
                        />
                    </div>

                    <div className="grid gap-6 sm:grid-cols-3">
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('budget_per_slot')}</label>
                            <div className="relative">
                                <FaDollarSign className="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
                                <input 
                                    type="number"
                                    step="0.01"
                                    value={data.budget}
                                    onChange={e => setData('budget', e.target.value)}
                                    className="w-full pl-10 rounded-2xl border-zinc-200 focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="0.00"
                                    required
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('total_slots')}</label>
                            <input 
                                type="number"
                                value={data.slots}
                                onChange={e => setData('slots', e.target.value)}
                                placeholder="1"
                                className="w-full rounded-2xl border-zinc-200 focus:border-emerald-500 focus:ring-emerald-500"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('submission_deadline')}</label>
                            <input 
                                type="date"
                                value={data.deadline_at}
                                onChange={e => setData('deadline_at', e.target.value)}
                                className="w-full rounded-2xl border-zinc-200 focus:border-emerald-500 focus:ring-emerald-500"
                            />
                        </div>
                    </div>

                    {Number(data.budget) > 0 && (
                        <div className="p-6 rounded-[2rem] bg-zinc-900 text-white flex items-center justify-between shadow-xl">
                            <div className="space-y-1">
                                <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500">{t('total_campaign_value')}</p>
                                <p className="text-xl font-black tracking-tighter">
                                    ${(Number(data.budget) * (Number(data.slots) || 1)).toFixed(2)}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500">{t('your_wallet')}</p>
                                <p className={`text-xs font-black ${Number(user.balance) < (Number(data.budget) * (Number(data.slots) || 1)) ? 'text-red-400' : 'text-emerald-400'}`}>
                                    ${Number(user.balance).toFixed(2)}
                                </p>
                            </div>
                        </div>
                    )}

                    <button 
                        disabled={processing}
                        className="w-full py-4 rounded-2xl bg-zinc-900 text-white font-black uppercase tracking-widest text-xs shadow-xl hover:bg-zinc-800 disabled:opacity-50 flex items-center justify-center gap-3"
                    >
                        {processing ? t('launching') : <><FaPlus /> {t('launch_contract')}</>}
                    </button>
                </form>
            </Modal>

            <Modal show={closingContractId !== null} onClose={() => setClosingContractId(null)}>
                <div className="p-8 space-y-6">
                    <div className="space-y-2">
                        <h3 className="text-2xl font-black tracking-tight text-zinc-900">{t('confirm')}</h3>
                        <p className="text-sm font-medium text-zinc-500">{t('confirm_close_contract')}</p>
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setClosingContractId(null)}
                            className="h-11 rounded-2xl border border-zinc-200 px-5 text-[10px] font-black uppercase tracking-widest text-zinc-500 transition-colors hover:bg-zinc-50"
                        >
                            {t('cancel')}
                        </button>
                        <button
                            type="button"
                            onClick={confirmCloseContract}
                            className="h-11 rounded-2xl bg-zinc-900 px-6 text-[10px] font-black uppercase tracking-widest text-white transition-colors hover:bg-zinc-700"
                        >
                            {t('confirm')}
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

function AvailableContractCard({ contract, onApply }: { contract: Contract; onApply: () => void }) {
    const { t } = useTranslation();
    return (
        <motion.div 
            whileHover={{ y: -5 }}
            className="group bg-white rounded-[2.5rem] p-8 border border-zinc-200 shadow-xl shadow-zinc-200/40 flex flex-col h-full"
        >
            <div className="flex items-center justify-between mb-6">
                <div className="h-10 w-10 rounded-xl bg-zinc-50 flex items-center justify-center border border-zinc-100">
                    <FaGlobe className="text-zinc-400 text-sm" />
                </div>
                <div className="flex items-center gap-3">
                    <div className="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-500 text-[10px] font-black uppercase tracking-widest border border-emerald-500/20 flex items-center gap-2">
                        <FaCheckCircle className="text-[8px]" /> {t('funds_verified')}
                    </div>
                    <div className="h-1 w-1 rounded-full bg-zinc-200" />
                    <span className="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">{contract.business?.company_name || contract.business?.name}</span>
                </div>
                <div className="text-right">
                    <p className="text-[9px] font-black uppercase tracking-widest text-zinc-400">{t('fixed_budget')}</p>
                    <p className="text-lg font-black text-emerald-500 tracking-tighter">${Number(contract.budget || 0).toFixed(2)}</p>
                </div>
            </div>

            <div className="space-y-4 mb-8 flex-1">
                <div>
                    <h3 className="text-xl font-black text-zinc-900 tracking-tight leading-snug group-hover:text-emerald-600 transition-colors">
                        {contract.title}
                    </h3>
                    <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-1">
                        {contract.business?.company_name || contract.business?.name} {contract.platform && `· ${contract.platform}`}
                    </p>
                </div>
                <p className="text-sm text-zinc-500 font-medium leading-relaxed line-clamp-3">
                    {contract.description}
                </p>
            </div>

            <div className="pt-6 border-t border-zinc-100 mt-auto flex items-center justify-between">
                <div className="flex items-center gap-2 text-zinc-400 text-[10px] font-bold uppercase tracking-widest">
                    <FaClock className="text-emerald-500/50" />
                    {contract.deadline_at ? t('due_on', { date: new Date(contract.deadline_at).toLocaleDateString() }) : t('no_deadline')}
                </div>
                <button 
                    onClick={onApply}
                    className="h-10 px-6 rounded-xl bg-zinc-900 text-white text-[10px] font-black uppercase tracking-[0.2em] shadow-lg hover:bg-emerald-500 transition-colors"
                >
                    {t('apply')}
                </button>
            </div>
        </motion.div>
    );
}

function MyContractCard({ contract, onClose }: { contract: Contract; onClose: (id: number) => void }) {
    const { t } = useTranslation();
    const style = statusStyles[contract.status] || statusStyles.closed;
    const StatusIcon = style.icon;
    const statusLabel = contract.status === 'open'
        ? t('contract_status_open')
        : contract.status === 'filled'
            ? t('contract_status_filled')
            : t('contract_status_closed');

    return (
        <div className="bg-zinc-50 rounded-[2.5rem] p-8 border border-zinc-200">
            <div className="flex items-center justify-between mb-6">
                <div className={`px-4 py-1.5 rounded-full ${style.bg} ${style.text} flex items-center gap-2 text-[9px] font-black uppercase tracking-widest`}>
                    <StatusIcon /> {statusLabel}
                </div>
                <p className="text-lg font-black text-zinc-900 tracking-tighter">${Number(contract.budget || 0).toFixed(2)}</p>
            </div>

            <div className="space-y-4 mb-8">
                <h3 className="text-xl font-black text-zinc-900 tracking-tight leading-snug">
                    {contract.title}
                </h3>
                <div className="grid grid-cols-2 gap-4">
                    <div className="p-4 rounded-2xl bg-white border border-zinc-100">
                        <p className="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-1">{t('applications')}</p>
                        <p className="text-xl font-black text-zinc-900">{contract.applications_count || 0}</p>
                    </div>
                    <div className="p-4 rounded-2xl bg-white border border-zinc-100">
                        <p className="text-[9px] font-black uppercase tracking-widest text-emerald-400 mb-1">{t('pending')}</p>
                        <p className="text-xl font-black text-emerald-500">{contract.pending_applications_count || 0}</p>
                    </div>
                </div>
            </div>

            <div className="pt-6 border-t border-zinc-200 mt-auto flex items-center gap-3">
                <Link 
                    href={route('contracts.show', contract.id)}
                    className="flex-1 h-12 rounded-2xl bg-white border border-zinc-200 text-zinc-600 text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 hover:bg-zinc-100 transition-colors"
                >
                    {t('manage_applications')}
                </Link>
                {contract.status === 'open' && (
                    <button 
                        onClick={() => onClose(contract.id)}
                        className="h-12 w-12 rounded-2xl bg-red-50 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all"
                    >
                        <FaTimes />
                    </button>
                )}
            </div>
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    const { t } = useTranslation();
    return (
        <div className="col-span-full py-24 flex flex-col items-center text-center space-y-6">
            <div className="h-24 w-24 rounded-full bg-zinc-50 border border-zinc-100 flex items-center justify-center">
                <FaRegHandshake className="text-4xl text-zinc-200" />
            </div>
            <div className="space-y-2">
                <h4 className="text-xl font-black text-zinc-900">{t('quiet_for_now')}</h4>
                <p className="text-sm text-zinc-400 max-w-xs mx-auto font-medium">{message}</p>
            </div>
        </div>
    );
}

function MarketerRankCard({ marketer, rank }: { marketer: TopMarketer; rank: number }) {
    const medalColors: Record<number, string> = { 1: 'text-amber-400', 2: 'text-zinc-400', 3: 'text-amber-700' };
    const medalColor = medalColors[rank] ?? 'text-zinc-300';
    const avg = marketer.avg_rating ?? 0;
    return (
        <motion.div
            initial={{ opacity: 0, x: -12 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: rank * 0.04 }}
            className="flex items-center gap-6 bg-white rounded-2xl border border-zinc-100 px-6 py-5 shadow-sm hover:shadow-md transition-shadow"
        >
            {/* Rank */}
            <div className="flex-shrink-0 w-10 text-center">
                {rank <= 3 ? (
                    <FaMedal className={`text-2xl mx-auto ${medalColor}`} />
                ) : (
                    <span className="text-lg font-black text-zinc-300">#{rank}</span>
                )}
            </div>

            {/* Info */}
            <div className="flex-1 min-w-0">
                <p className="font-black text-zinc-900 tracking-tight truncate">{marketer.name}</p>
                {marketer.company_name && (
                    <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest truncate">{marketer.company_name}</p>
                )}
            </div>

            {/* Stars */}
            <div className="flex-shrink-0 flex flex-col items-end gap-1">
                <div className="flex items-center gap-0.5">
                    {[1,2,3,4,5].map(s => (
                        <FaStar key={s} className={`text-xs ${s <= Math.round(avg) ? 'text-amber-400' : 'text-zinc-200'}`} />
                    ))}
                    <span className="ml-1.5 text-xs font-black text-zinc-600">{avg > 0 ? avg.toFixed(1) : '—'}</span>
                </div>
                <div className="flex items-center gap-3 text-[9px] font-black text-zinc-400 uppercase tracking-widest">
                    <span>{marketer.review_count} review{marketer.review_count !== 1 ? 's' : ''}</span>
                    <span>{marketer.completed_contracts} completed</span>
                </div>
            </div>
        </motion.div>
    );
}


// Simple Modal Component
function Modal({ show, onClose, children }: { show: boolean; onClose: () => void; children: React.ReactNode }) {
    return (
        <AnimatePresence>
            {show && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-6">
                    <motion.div 
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={onClose}
                        className="absolute inset-0 bg-zinc-900/60 backdrop-blur-sm" 
                    />
                    <motion.div 
                        initial={{ opacity: 0, scale: 0.9, y: 20 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.9, y: 20 }}
                        className="relative w-full max-w-2xl bg-white rounded-[3rem] shadow-2xl overflow-hidden"
                    >
                        {children}
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}

