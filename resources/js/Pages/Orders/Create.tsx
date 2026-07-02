import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaInstagram, FaYoutube, FaTiktok, FaFacebook, FaTwitter, 
    FaTelegram, FaSpotify, FaGlobe, FaCheckCircle, FaExclamationCircle, 
    FaShoppingCart, FaLink, FaListOl, FaBolt, FaWallet
} from 'react-icons/fa';

interface Service {
    id: number; 
    name: string; 
    category: string;
    min_qty: number; 
    max_qty: number; 
    rate: number; 
    description?: string;
}

interface Props extends PageProps {
    services: Service[];
    categories: string[];
    selected?: Service | null;
}

// Map category names to icons and brand colors
const getCategoryIconAndColor = (category: string) => {
    const lower = category.toLowerCase();
    if (lower.includes('instagram')) return { icon: FaInstagram, color: 'text-pink-500', bg: 'bg-pink-50', border: 'border-pink-200' };
    if (lower.includes('youtube')) return { icon: FaYoutube, color: 'text-red-500', bg: 'bg-red-50', border: 'border-red-200' };
    if (lower.includes('tiktok')) return { icon: FaTiktok, color: 'text-black', bg: 'bg-zinc-100', border: 'border-zinc-300' };
    if (lower.includes('twitter') || lower.includes('x /') || lower === 'x') return { icon: FaTwitter, color: 'text-blue-400', bg: 'bg-blue-50', border: 'border-blue-200' };
    if (lower.includes('facebook')) return { icon: FaFacebook, color: 'text-blue-600', bg: 'bg-blue-50', border: 'border-blue-200' };
    if (lower.includes('telegram')) return { icon: FaTelegram, color: 'text-sky-500', bg: 'bg-sky-50', border: 'border-sky-200' };
    if (lower.includes('spotify')) return { icon: FaSpotify, color: 'text-green-500', bg: 'bg-green-50', border: 'border-green-200' };
    return { icon: FaGlobe, color: 'text-emerald-500', bg: 'bg-emerald-50', border: 'border-emerald-200' };
};

export default function OrderCreate({ auth, services, categories, selected }: Props) {
    const { t } = useTranslation();
    const [category, setCategory] = useState(selected?.category ?? categories[0] ?? '');
    const filteredServices = services.filter((s) => s.category === category);

    const { data, setData, post, processing, errors } = useForm({
        service_id: selected?.id?.toString() ?? '',
        link: '',
        quantity: '1000',
    });

    const chosenService = services.find((s) => s.id === Number(data.service_id));
    const serviceRate = chosenService ? Number(chosenService.rate) : 0;
    const rawCharge = chosenService ? ((Number(data.quantity) / 1000) * serviceRate) : 0;
    const charge = rawCharge.toFixed(4);
    
    const userBalance = auth.user?.balance !== undefined ? Number(auth.user.balance) : 0;
    const balanceAfter = userBalance - rawCharge;
    const isInsufficient = balanceAfter < 0;

    // Live quantity bounds check so we block invalid submits before the server round-trip.
    const qty = Number(data.quantity);
    const minQty = chosenService ? Number(chosenService.min_qty) : 1;
    const maxQty = chosenService ? Number(chosenService.max_qty) : Infinity;
    const isQtyInvalid = Boolean(chosenService) && (!Number.isFinite(qty) || qty < minQty || qty > maxQty);

    // Reset service when category changes
    useEffect(() => {
        if (!filteredServices.find(s => s.id.toString() === data.service_id)) {
            setData('service_id', '');
        }
    }, [category]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('orders.store'), {
            preserveScroll: true
        });
    }

    return (
        <AuthenticatedLayout>
            <Head title={t('deploy_campaign')} />

            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">

                {/* Header */}
                <div className="mb-10 text-center max-w-2xl mx-auto">
                    <h1 className="text-4xl font-black tracking-tight text-zinc-900 mb-4">{t('deploy_campaign')}</h1>
                    <p className="text-zinc-500 font-medium">{t('deploy_campaign_subtitle')}</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
                    
                    {/* Left Column: Form & Configuration */}
                    <div className="lg:col-span-8 space-y-10">
                        <form id="order-form" onSubmit={submit} className="space-y-10">
                            
                            {/* Step 1: Category Selection */}
                            <section>
                                <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span className="flex items-center justify-center w-6 h-6 rounded-full bg-zinc-100 text-zinc-500">1</span>
                                    {t('select_platform')}
                                </h3>
                                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                    {categories.map((cat) => {
                                        const { icon: Icon, color, bg, border } = getCategoryIconAndColor(cat);
                                        const isSelected = category === cat;
                                        return (
                                            <button
                                                key={cat}
                                                type="button"
                                                onClick={() => setCategory(cat)}
                                                className={`flex flex-col items-center gap-3 p-5 rounded-2xl border-2 transition-all duration-200 ${
                                                    isSelected 
                                                        ? `${border} ${bg} shadow-md scale-[1.02]` 
                                                        : 'border-zinc-100 bg-white hover:border-zinc-200 hover:bg-zinc-50'
                                                }`}
                                            >
                                                <Icon className={`text-4xl ${isSelected ? color : 'text-zinc-300'}`} />
                                                <span className={`text-[10px] font-black uppercase tracking-widest text-center ${isSelected ? 'text-zinc-900' : 'text-zinc-500'}`}>
                                                    {cat}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </section>

                            <AnimatePresence mode="wait">
                                {category && (
                                    <motion.section 
                                        key="services"
                                        initial={{ opacity: 0, y: 10 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        className="space-y-6"
                                    >
                                        <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                            <span className="flex items-center justify-center w-6 h-6 rounded-full bg-zinc-100 text-zinc-500">2</span>
                                            {t('configure_metrics')}
                                        </h3>
                                        
                                        <div className="bg-white p-6 rounded-3xl border border-zinc-200 shadow-sm space-y-6">
                                            {/* Service Selection */}
                                            <div>
                                                <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest">{t('select_service')}</label>
                                                <select
                                                    value={data.service_id}
                                                    onChange={(e) => setData('service_id', e.target.value)}
                                                    className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-4 font-bold text-zinc-900 focus:border-emerald-500 focus:bg-white focus:outline-none transition-all"
                                                    required
                                                >
                                                    <option value="" disabled>{t('select_service_placeholder')}</option>
                                                    {filteredServices.map((s) => (
                                                        <option key={s.id} value={s.id}>ID: {s.id} - {s.name} - ${s.rate}/k</option>
                                                    ))}
                                                </select>
                                                {errors.service_id && <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle/> {errors.service_id}</p>}
                                            </div>

                                            {/* Active Service Card */}
                                            {chosenService && (
                                                <motion.div 
                                                    initial={{ opacity: 0, height: 0 }} 
                                                    animate={{ opacity: 1, height: 'auto' }}
                                                    className="overflow-hidden"
                                                >
                                                    <div className="rounded-2xl bg-zinc-50 border border-zinc-200 p-5 space-y-3">
                                                        <div className="flex flex-wrap gap-2">
                                                            <span className="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold flex items-center gap-1"><FaBolt className="text-emerald-500"/> ${serviceRate} {t('per_1000')}</span>
                                                            <span className="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-xs font-bold">{t('min_short')}: {Number(chosenService.min_qty).toLocaleString()}</span>
                                                            <span className="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-bold">{t('max_short')}: {Number(chosenService.max_qty).toLocaleString()}</span>
                                                        </div>
                                                        {chosenService.description && (
                                                            <p className="text-sm font-medium text-zinc-600 leading-relaxed bg-white p-4 rounded-xl border border-zinc-100">
                                                                {chosenService.description}
                                                            </p>
                                                        )}
                                                    </div>
                                                </motion.div>
                                            )}

                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                                                {/* Link Input */}
                                                <div className="md:col-span-2">
                                                    <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest flex items-center gap-2"><FaLink className="text-zinc-400"/> {t('target_url')}</label>
                                                    <input
                                                        type="url"
                                                        value={data.link}
                                                        onChange={(e) => setData('link', e.target.value)}
                                                        placeholder="https://"
                                                        className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-4 font-medium text-zinc-900 focus:border-emerald-500 focus:bg-white focus:outline-none transition-all"
                                                        required
                                                    />
                                                    {errors.link && <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle/> {errors.link}</p>}
                                                </div>

                                                {/* Quantity Input */}
                                                <div className="md:col-span-2">
                                                    <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest flex items-center gap-2"><FaListOl className="text-zinc-400"/> {t('order_quantity')}</label>
                                                    <div className="relative">
                                                        <input
                                                            type="number"
                                                            value={data.quantity}
                                                            onChange={(e) => setData('quantity', e.target.value)}
                                                            min={chosenService?.min_qty ?? 1}
                                                            max={chosenService?.max_qty ?? 9999999}
                                                            className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-4 font-black text-zinc-900 text-xl focus:border-emerald-500 focus:bg-white focus:outline-none transition-all"
                                                            required
                                                        />
                                                        <div className="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                                                            <span className="text-zinc-400 font-bold uppercase text-[10px] tracking-widest">{t('metrics')}</span>
                                                        </div>
                                                    </div>
                                                    {isQtyInvalid && !errors.quantity && (
                                                        <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1">
                                                            <FaExclamationCircle/> {t('quantity_between', { min: minQty.toLocaleString(), max: maxQty.toLocaleString() })}
                                                        </p>
                                                    )}
                                                    {errors.quantity && <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle/> {errors.quantity}</p>}
                                                </div>
                                            </div>
                                        </div>
                                    </motion.section>
                                )}
                            </AnimatePresence>
                        </form>
                    </div>

                    {/* Right Column: Sticky Summary Panel */}
                    <div className="lg:col-span-4">
                        <div className="sticky top-8 space-y-6">
                            
                            {/* Summary Card */}
                            <div className="bg-zinc-950 text-white rounded-[2rem] p-8 shadow-2xl relative overflow-hidden">
                                <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-500/20 to-blue-500/20 rounded-full blur-[60px] pointer-events-none" />
                                
                                <div className="relative z-10 space-y-6">
                                    <div className="flex items-center gap-3">
                                        <div className="p-3 bg-white/10 rounded-xl">
                                            <FaShoppingCart className="text-xl text-emerald-400" />
                                        </div>
                                        <h3 className="text-xl font-black">{t('order_summary')}</h3>
                                    </div>

                                    <div className="space-y-4 pt-4 border-t border-white/10">
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-zinc-400 font-medium">{t('service_rate')}</span>
                                            <span className="font-bold">${serviceRate.toFixed(4)} <span className="text-zinc-500 font-normal">/ 1k</span></span>
                                        </div>
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-zinc-400 font-medium">{t('quantity')}</span>
                                            <span className="font-bold text-zinc-300">{Number(data.quantity || 0).toLocaleString()}</span>
                                        </div>
                                    </div>

                                    <div className="pt-4 border-t border-white/10">
                                        <div className="flex justify-between items-end">
                                            <span className="text-zinc-400 font-bold text-xs uppercase tracking-widest mb-1">{t('charge')}</span>
                                            <span className="text-4xl font-black text-emerald-400">${charge}</span>
                                        </div>
                                    </div>

                                    <button
                                        type="submit"
                                        form="order-form"
                                        disabled={processing || !chosenService || isInsufficient || isQtyInvalid}
                                        className="w-full py-4 px-6 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-black uppercase tracking-widest text-sm rounded-xl transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 disabled:cursor-not-allowed flex items-center justify-center gap-2 shadow-[0_0_40px_-10px_rgba(16,185,129,0.5)]"
                                    >
                                        {processing ? t('processing_btn') : t('confirm_payment')}
                                    </button>
                                </div>
                            </div>

                            {/* Wallet Status Card */}
                            <div className={`p-6 rounded-[2rem] border-2 transition-colors ${isInsufficient ? 'bg-red-50 border-red-200' : 'bg-white border-zinc-200'}`}>
                                <div className="flex items-center gap-3 mb-4">
                                    <FaWallet className={isInsufficient ? 'text-red-500' : 'text-zinc-400'} />
                                    <h4 className="text-xs font-black uppercase tracking-widest text-zinc-900">{t('wallet_status')}</h4>
                                </div>
                                
                                <div className="space-y-3">
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-zinc-500 font-medium">{t('current_balance')}</span>
                                        <span className="font-bold">${userBalance.toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between items-center text-sm pt-2 border-t border-zinc-100">
                                        <span className="text-zinc-500 font-medium">{t('remaining_after')}</span>
                                        <span className={`font-black ${isInsufficient ? 'text-red-600' : 'text-emerald-600'}`}>
                                            ${balanceAfter.toFixed(2)}
                                        </span>
                                    </div>
                                </div>

                                {isInsufficient && (
                                    <div className="mt-4 p-3 bg-red-100 text-red-800 rounded-xl text-xs font-bold flex gap-2">
                                        <FaExclamationCircle className="shrink-0 mt-0.5" />
                                        <span>{t('insufficient_funds_campaign')}</span>
                                    </div>
                                )}
                                {(errors as any).balance && (
                                    <div className="mt-4 p-3 bg-red-100 text-red-800 rounded-xl text-xs font-bold flex gap-2">
                                        <FaExclamationCircle className="shrink-0 mt-0.5" />
                                        <span>{(errors as any).balance}</span>
                                    </div>
                                )}
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
