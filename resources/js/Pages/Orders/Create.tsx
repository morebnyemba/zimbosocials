import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { useTranslation } from '@/lib/i18n';
import { getPlatformMeta } from '@/lib/platformIcons';
import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    FaCheckCircle, FaExclamationCircle, FaShoppingCart, FaLink, FaListOl, FaBolt, FaWallet,
    FaSyncAlt, FaTachometerAlt, FaClock, FaArrowsAltH,
} from 'react-icons/fa';

interface PromoBundle {
    id: number;
    quantity: number;
    price: number;
    label: string | null;
}

interface Service {
    id: number;
    name: string;
    category: string;
    platform?: string | null;
    min_qty: number;
    max_qty: number;
    rate: number;
    description?: string;
    is_refill?: boolean;
    is_dripfeed?: boolean;
    refill_days?: number;
    avg_time_minutes?: number;
    promo_bundles?: PromoBundle[];
}

interface Props extends PageProps {
    services: Service[];
    platforms: string[];
    selected?: Service | null;
}

export default function OrderCreate({ auth, services, platforms, selected }: Props) {
    const { t } = useTranslation();

    // Three levels: platform (Instagram/YouTube/...) → category (whatever an
    // admin has grouped that platform's services into — Followers, Likes, a
    // custom name, anything) → the specific service.
    const [platform, setPlatform] = useState(selected?.platform ?? platforms[0] ?? '');
    const platformServices = useMemo(() => services.filter((s) => s.platform === platform), [services, platform]);
    const categoriesForPlatform = useMemo(
        () => Array.from(new Set(platformServices.map((s) => s.category))),
        [platformServices]
    );

    const [category, setCategory] = useState(selected?.category ?? '');
    // Keep the chosen category valid as the platform changes — falls back to
    // that platform's first category rather than leaving a stale selection.
    useEffect(() => {
        if (!categoriesForPlatform.includes(category)) {
            setCategory(categoriesForPlatform[0] ?? '');
        }
    }, [platform, categoriesForPlatform]);

    const filteredServices = platformServices.filter((s) => s.category === category);

    const { data, setData, post, processing, errors } = useForm({
        service_id: selected?.id?.toString() ?? '',
        link: '',
        quantity: '1000',
    });

    const chosenService = services.find((s) => s.id === Number(data.service_id));
    const serviceRate = chosenService ? Number(chosenService.rate) : 0;
    const bundles = chosenService?.promo_bundles ?? [];
    // A flat-price deal for this EXACT quantity — mirrors Service::calculateCharge
    // server-side, so the quote shown here never disagrees with what's charged.
    const activeBundle = bundles.find((b) => b.quantity === Number(data.quantity));
    const rawCharge = chosenService
        ? (activeBundle ? Number(activeBundle.price) : (Number(data.quantity) / 1000) * serviceRate)
        : 0;
    // Money shows 2dp; fall back to 4dp only when 2dp would round a real
    // amount down to $0.00 (tiny quantities on cheap services).
    const money = (n: number) => (n > 0 && n < 0.01 ? n.toFixed(4) : n.toFixed(2));
    const charge = money(rawCharge);
    
    const userBalance = auth.user?.balance !== undefined ? Number(auth.user.balance) : 0;
    const balanceAfter = userBalance - rawCharge;
    const isInsufficient = balanceAfter < 0;

    // Live quantity bounds check so we block invalid submits before the server round-trip.
    const qty = Number(data.quantity);
    const minQty = chosenService ? Number(chosenService.min_qty) : 1;
    const maxQty = chosenService ? Number(chosenService.max_qty) : Infinity;
    const isQtyInvalid = Boolean(chosenService) && (!Number.isFinite(qty) || qty < minQty || qty > maxQty);

    // Quick-pick amounts so buyers can tap instead of typing. Only show presets
    // that actually fall within the chosen service's allowed range. Bundle
    // quantities (flat-price deals) are merged in so a deal is never hidden
    // behind a quantity the buyer wasn't going to think to type.
    const PRESET_AMOUNTS = [100, 250, 500, 1000, 2500, 5000, 10000];
    const bundleQuantities = bundles.map((b) => b.quantity);
    const presets = chosenService
        ? Array.from(new Set([...PRESET_AMOUNTS, ...bundleQuantities])).filter((p) => p >= minQty && p <= maxQty).sort((a, b) => a - b)
        : [];

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

    const formatTime = (minutes?: number) => {
        if (!minutes) return null;
        if (minutes < 60) return `~${minutes}m`;
        if (minutes < 1440) return `~${Math.round(minutes / 60)}h`;
        return `~${Math.round(minutes / 1440)}d`;
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('deploy_campaign')} />

            <div className="mx-auto max-w-7xl px-4 py-12 pb-28 lg:pb-12 sm:px-6 lg:px-8">

                {/* Header */}
                <div className="mb-10 text-center max-w-2xl mx-auto">
                    <h1 className="text-4xl font-black tracking-tight text-zinc-900 mb-4">{t('deploy_campaign')}</h1>
                    <p className="text-zinc-500 font-medium">{t('deploy_campaign_subtitle')}</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
                    
                    {/* Left Column: Form & Configuration */}
                    <div className="lg:col-span-8 space-y-10">
                        <form id="order-form" onSubmit={submit} className="space-y-10">
                            
                            {/* Step 1: Platform Selection */}
                            <section>
                                <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span className="flex items-center justify-center w-6 h-6 rounded-full bg-zinc-100 text-zinc-500">1</span>
                                    {t('select_platform')}
                                </h3>
                                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                    {platforms.map((p) => {
                                        const { icon: Icon, color, bg, border, label } = getPlatformMeta(p);
                                        const isSelected = platform === p;
                                        return (
                                            <button
                                                key={p}
                                                type="button"
                                                onClick={() => setPlatform(p)}
                                                className={`flex flex-col items-center gap-3 p-5 rounded-2xl border-2 transition-all duration-200 ${
                                                    isSelected
                                                        ? `${border} ${bg} shadow-md scale-[1.02]`
                                                        : 'border-zinc-100 bg-white hover:border-zinc-200 hover:bg-zinc-50'
                                                }`}
                                            >
                                                <Icon className={`text-4xl ${isSelected ? color : 'text-zinc-300'}`} />
                                                <span className={`text-[10px] font-black uppercase tracking-widest text-center ${isSelected ? 'text-zinc-900' : 'text-zinc-500'}`}>
                                                    {label}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </section>

                            {/* Step 2: Category Selection — whatever an admin has grouped
                                this platform's services into (Followers, Likes, a custom
                                name, anything). A platform with only one category still
                                shows it, so the grouping is always visible and real. */}
                            <AnimatePresence mode="wait">
                                {platform && categoriesForPlatform.length > 0 && (
                                    <motion.section
                                        key="categories"
                                        initial={{ opacity: 0, y: 10 }}
                                        animate={{ opacity: 1, y: 0 }}
                                    >
                                        <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                            <span className="flex items-center justify-center w-6 h-6 rounded-full bg-zinc-100 text-zinc-500">2</span>
                                            {t('select_category')}
                                        </h3>
                                        <div className="flex flex-wrap gap-2">
                                            {categoriesForPlatform.map((cat) => {
                                                const isSelected = category === cat;
                                                return (
                                                    <button
                                                        key={cat}
                                                        type="button"
                                                        onClick={() => setCategory(cat)}
                                                        className={`px-5 py-2.5 rounded-full text-sm font-bold transition-all ${
                                                            isSelected
                                                                ? 'bg-zinc-900 text-white shadow-md'
                                                                : 'bg-white text-zinc-500 border border-zinc-200 hover:border-zinc-300'
                                                        }`}
                                                    >
                                                        {cat}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </motion.section>
                                )}
                            </AnimatePresence>

                            <AnimatePresence mode="wait">
                                {category && (
                                    <motion.section
                                        key="services"
                                        initial={{ opacity: 0, y: 10 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        className="space-y-6"
                                    >
                                        <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                            <span className="flex items-center justify-center w-6 h-6 rounded-full bg-zinc-100 text-zinc-500">3</span>
                                            {t('configure_metrics')}
                                        </h3>
                                        
                                        <div className="bg-white p-6 rounded-3xl border border-zinc-200 shadow-sm space-y-6">
                                            {/* Service Selection — a clickable list rather than a native <select>,
                                                so every service carries its own platform icon (a <select><option>
                                                can't render one). */}
                                            <div>
                                                <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest">{t('select_service')}</label>
                                                {filteredServices.length === 0 ? (
                                                    <p className="rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-center text-sm font-medium text-zinc-400">
                                                        {t('select_service_placeholder')}
                                                    </p>
                                                ) : (
                                                    <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                                                        {filteredServices.map((s) => {
                                                            const meta = getPlatformMeta(s.platform);
                                                            const isSelected = data.service_id === String(s.id);
                                                            return (
                                                                <button
                                                                    key={s.id}
                                                                    type="button"
                                                                    onClick={() => setData('service_id', String(s.id))}
                                                                    className={`w-full flex items-center gap-3 rounded-2xl border-2 px-4 py-3 text-left transition-all ${
                                                                        isSelected
                                                                            ? 'border-emerald-500 bg-emerald-50/60'
                                                                            : 'border-zinc-100 bg-zinc-50 hover:border-zinc-200 hover:bg-white'
                                                                    }`}
                                                                >
                                                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${meta.bg}`}>
                                                                        <meta.icon className={`text-lg ${meta.color}`} />
                                                                    </span>
                                                                    <span className="min-w-0 flex-1">
                                                                        <span className="block truncate text-sm font-bold text-zinc-900">{s.name}</span>
                                                                        <span className="block text-xs font-medium text-zinc-400">${money(Number(s.rate))} {t('per_1000')}</span>
                                                                    </span>
                                                                    {isSelected && <FaCheckCircle className="shrink-0 text-emerald-500" />}
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                                {errors.service_id && <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle/> {errors.service_id}</p>}
                                            </div>

                                            {/* Active Service Card: badges, description, and an auto-built
                                                "how it works" summary from data already on the service — no
                                                separate instructions field to maintain. */}
                                            {chosenService && (
                                                <motion.div
                                                    initial={{ opacity: 0, height: 0 }}
                                                    animate={{ opacity: 1, height: 'auto' }}
                                                    className="overflow-hidden"
                                                >
                                                    <div className="rounded-2xl bg-zinc-50 border border-zinc-200 p-5 space-y-4">
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
                                                        <div className="bg-white p-4 rounded-xl border border-zinc-100">
                                                            <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-2.5">{t('how_it_works')}</p>
                                                            <ul className="space-y-2 text-xs font-medium text-zinc-600">
                                                                <li className="flex items-center gap-2">
                                                                    <FaSyncAlt className={chosenService.is_refill ? 'text-emerald-500' : 'text-zinc-300'} />
                                                                    {chosenService.is_refill
                                                                        ? t('instructions_refill', { days: chosenService.refill_days ?? 30 })
                                                                        : t('instructions_no_refill')}
                                                                </li>
                                                                <li className="flex items-center gap-2">
                                                                    <FaTachometerAlt className={chosenService.is_dripfeed ? 'text-emerald-500' : 'text-zinc-300'} />
                                                                    {chosenService.is_dripfeed ? t('instructions_dripfeed') : t('instructions_instant')}
                                                                </li>
                                                                {formatTime(chosenService.avg_time_minutes) && (
                                                                    <li className="flex items-center gap-2">
                                                                        <FaClock className="text-emerald-500" />
                                                                        {t('instructions_delivery', { time: formatTime(chosenService.avg_time_minutes) as string })}
                                                                    </li>
                                                                )}
                                                                <li className="flex items-center gap-2">
                                                                    <FaArrowsAltH className="text-emerald-500" />
                                                                    {t('instructions_range', {
                                                                        min: Number(chosenService.min_qty).toLocaleString(),
                                                                        max: Number(chosenService.max_qty).toLocaleString(),
                                                                    })}
                                                                </li>
                                                            </ul>
                                                        </div>
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
                                                    {presets.length > 0 && (
                                                        <div className="flex flex-wrap gap-2 mb-3">
                                                            {presets.map((p) => {
                                                                const isActive = qty === p;
                                                                const deal = bundles.find((b) => b.quantity === p);
                                                                return (
                                                                    <button
                                                                        key={p}
                                                                        type="button"
                                                                        onClick={() => setData('quantity', String(p))}
                                                                        className={`relative px-4 py-2 rounded-xl text-sm font-black transition-all ${
                                                                            isActive
                                                                                ? 'bg-zinc-900 text-white shadow-md'
                                                                                : deal
                                                                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-300 hover:border-emerald-400'
                                                                                : 'bg-zinc-50 text-zinc-600 border border-zinc-200 hover:border-zinc-300'
                                                                        }`}
                                                                    >
                                                                        {deal && (
                                                                            <span className="absolute -top-2 -right-2 px-1.5 py-0.5 rounded-full bg-emerald-500 text-white text-[9px] font-black uppercase tracking-wide">
                                                                                {t('deal_tag')}
                                                                            </span>
                                                                        )}
                                                                        {p.toLocaleString()}
                                                                        {deal && <span className="ml-1.5 font-mono opacity-75">${money(Number(deal.price))}</span>}
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
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
                                            <span className="font-bold">${money(serviceRate)} <span className="text-zinc-500 font-normal">/ 1k</span></span>
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

            {/* Sticky order bar — mobile only, keeps the total and buy button in reach */}
            <div className="lg:hidden fixed bottom-0 inset-x-0 z-40 border-t border-zinc-200 bg-white/95 backdrop-blur px-4 py-3 flex items-center gap-4 shadow-[0_-8px_30px_-12px_rgba(0,0,0,0.25)]">
                <div className="flex-1 min-w-0">
                    <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('charge')}</p>
                    <p className="text-2xl font-black text-zinc-900 leading-none">${charge}</p>
                    {isInsufficient && <p className="text-[10px] font-bold text-red-500 mt-1">{t('insufficient_funds_campaign')}</p>}
                </div>
                <button
                    type="submit"
                    form="order-form"
                    disabled={processing || !chosenService || isInsufficient || isQtyInvalid}
                    className="shrink-0 py-3.5 px-8 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-black uppercase tracking-widest text-sm rounded-xl transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {processing ? t('processing_btn') : t('confirm_payment')}
                </button>
            </div>
        </AuthenticatedLayout>
    );
}
