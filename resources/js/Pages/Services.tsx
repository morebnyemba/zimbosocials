import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { useTranslation } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaInstagram, FaYoutube, FaTiktok, FaFacebook, FaTwitter, 
    FaTelegram, FaWhatsapp, FaRocket, FaSync, FaSearch, FaFilter, FaArrowRight, FaClock, FaGlobe
} from 'react-icons/fa';

interface Service {
    id: number;
    name: string;
    category: string;
    rate: number;
    min_qty: number;
    max_qty: number;
    is_refill: boolean;
    avg_time_minutes: number;
    description?: string | null;
}

interface Props extends PageProps {
    services: Service[];
    categories: string[];
}

const getCategoryMeta = (category: string) => {
    const lower = category.toLowerCase();
    if (lower.includes('instagram')) return { label: 'Instagram', icon: FaInstagram, color: 'text-pink-500', bg: 'bg-pink-500/10' };
    if (lower.includes('youtube')) return { label: 'YouTube', icon: FaYoutube, color: 'text-red-500', bg: 'bg-red-500/10' };
    if (lower.includes('tiktok')) return { label: 'TikTok', icon: FaTiktok, color: 'text-zinc-900', bg: 'bg-zinc-900/10' };
    if (lower.includes('twitter') || lower.includes('x /') || lower === 'x') return { label: 'Twitter / X', icon: FaTwitter, color: 'text-blue-400', bg: 'bg-blue-400/10' };
    if (lower.includes('facebook')) return { label: 'Facebook', icon: FaFacebook, color: 'text-blue-600', bg: 'bg-blue-600/10' };
    if (lower.includes('telegram')) return { label: 'Telegram', icon: FaTelegram, color: 'text-sky-500', bg: 'bg-sky-500/10' };
    if (lower.includes('whatsapp')) return { label: 'WhatsApp', icon: FaWhatsapp, color: 'text-emerald-500', bg: 'bg-emerald-500/10' };
    return { label: category, icon: FaGlobe, color: 'text-emerald-500', bg: 'bg-emerald-500/10' };
};

export default function Services({ services, categories }: Props) {
    const { t } = useTranslation();
    const [active, setActive] = useState(categories[0] ?? '');
    const [search, setSearch] = useState('');

    const filtered = services.filter((s) => 
        s.category === active && 
        (s.name.toLowerCase().includes(search.toLowerCase()) || 
         s.description?.toLowerCase().includes(search.toLowerCase()))
    );

    const formatTime = (minutes: number) => {
        if (!minutes) return t('instant');
        if (minutes < 60) return `~${minutes}m`;
        if (minutes < 1440) return `~${Math.round(minutes / 60)}h`;
        return `~${Math.round(minutes / 1440)}d`;
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('service_catalog')} />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-12">
                
                {/* Hero Section */}
                <div className="relative rounded-[2rem] md:rounded-[3rem] bg-zinc-950 p-8 md:p-16 overflow-hidden shadow-2xl">
                    <div className="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-emerald-500/20 to-blue-500/20 rounded-full blur-[80px] pointer-events-none" />
                    
                    <div className="relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-8">
                        <div className="max-w-2xl">
                            <h1 className="text-4xl md:text-5xl font-black text-white tracking-tight mb-4">{t('services_hero_title')}</h1>
                            <p className="text-zinc-400 font-medium text-sm md:text-base leading-relaxed">
                                {t('services_hero_subtitle')}
                            </p>
                        </div>

                        <div className="relative w-full md:max-w-md group">
                            <FaSearch className="absolute left-6 top-1/2 -translate-y-1/2 text-zinc-400 group-focus-within:text-emerald-400 transition-colors" />
                            <input 
                                type="text" 
                                placeholder={t('search_services')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full bg-white/10 border-2 border-white/10 rounded-full pl-14 pr-8 py-4 font-bold text-white placeholder-zinc-500 focus:outline-none focus:border-emerald-500 focus:bg-white/15 transition-all shadow-xl backdrop-blur-md"
                            />
                        </div>
                    </div>
                </div>

                {/* Categories Wrapper */}
                <div className="flex flex-col sm:flex-row gap-6">
                    {/* Category Mobile Dropdown (Visible on small screens) */}
                    <div className="sm:hidden block w-full">
                        <select
                            value={active}
                            onChange={(e) => setActive(e.target.value)}
                            className="w-full bg-white border-2 border-zinc-200 rounded-2xl px-4 py-4 font-black text-zinc-900 focus:outline-none focus:border-emerald-500 transition-all shadow-sm appearance-none"
                        >
                            {categories.map((cat) => (
                                <option key={cat} value={cat}>{cat}</option>
                            ))}
                        </select>
                    </div>

                    {/* Category Tabs (Visible on larger screens) */}
                    <div className="hidden sm:flex flex-wrap gap-2 w-full">
                        {categories.map((cat) => {
                            const meta = getCategoryMeta(cat);
                            const isSelected = active === cat;
                            return (
                                <button
                                    key={cat}
                                    onClick={() => setActive(cat)}
                                    className={`flex items-center gap-2 px-6 py-3 rounded-full font-bold text-sm transition-all duration-300 ${
                                        isSelected 
                                        ? 'bg-zinc-900 text-white shadow-lg scale-105'
                                        : 'bg-white text-zinc-500 hover:bg-zinc-100 border border-zinc-200'
                                    }`}
                                >
                                    <meta.icon className={isSelected ? 'text-emerald-400' : meta.color} />
                                    {cat}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Services Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <AnimatePresence mode="popLayout">
                        {filtered.length === 0 ? (
                            <motion.div 
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                className="col-span-full py-32 text-center bg-white rounded-[3rem] border-2 border-dashed border-zinc-200"
                            >
                                <div className="h-16 w-16 rounded-3xl bg-zinc-50 flex items-center justify-center mx-auto mb-6 text-zinc-300 text-2xl">
                                    <FaFilter />
                                </div>
                                <h3 className="text-xl font-black text-zinc-900 tracking-tight">{t('no_matching_services')}</h3>
                                <p className="text-zinc-500 text-sm font-medium mt-2">{t('no_matching_services_hint')}</p>
                            </motion.div>
                        ) : (
                            filtered.map((service, idx) => {
                                const meta = getCategoryMeta(service.category);
                                return (
                                    <motion.div
                                        layout
                                        key={service.id}
                                        initial={{ opacity: 0, y: 20 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        transition={{ delay: idx * 0.05 }}
                                        className="bg-white rounded-[2rem] border-2 border-zinc-100 p-6 flex flex-col justify-between hover:border-emerald-500 hover:shadow-2xl hover:shadow-emerald-500/10 transition-all group"
                                    >
                                        <div className="space-y-6">
                                            <div className="flex items-start justify-between">
                                                <div className={`h-12 w-12 rounded-xl flex items-center justify-center text-xl transition-all ${meta.bg} ${meta.color}`}>
                                                    <meta.icon />
                                                </div>
                                                <div className="flex flex-col items-end">
                                                    <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('rate_per_1k')}</p>
                                                    <p className="text-2xl font-black text-emerald-600 tracking-tighter">${Number(service.rate).toFixed(2)}</p>
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <div className="flex gap-2 items-center mb-1">
                                                    <span className="text-[10px] font-black text-zinc-500 bg-zinc-100 px-2 py-0.5 rounded-md uppercase">{t('order_id')}: {service.id}</span>
                                                </div>
                                                <h3 className="text-lg font-black text-zinc-900 leading-tight group-hover:text-emerald-600 transition-colors">{service.name}</h3>
                                                <p className="text-zinc-500 text-xs font-medium leading-relaxed line-clamp-3">
                                                    {service.description || t('service_desc_fallback')}
                                                </p>
                                            </div>

                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-100">
                                                    <p className="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-1">{t('avg_speed')}</p>
                                                    <p className="text-xs font-black text-zinc-900 flex items-center gap-1.5"><FaClock className="text-emerald-500" /> {formatTime(service.avg_time_minutes)}</p>
                                                </div>
                                                <div className="px-3 py-2 rounded-xl bg-zinc-50 border border-zinc-100">
                                                    <p className="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-1">{t('refill')}</p>
                                                    <p className="text-xs font-black text-zinc-900 flex items-center gap-1.5">
                                                        {service.is_refill ? <><FaSync className="text-emerald-500" /> {t('yes')}</> : <span className="text-zinc-400">{t('no')}</span>}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-6 pt-6 border-t border-zinc-100 flex items-center justify-between">
                                            <div className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">
                                                {Number(service.min_qty).toLocaleString()} - {Number(service.max_qty).toLocaleString()}
                                            </div>
                                            <Link 
                                                href={route('orders.create', { service_id: service.id })}
                                                className="h-10 w-10 rounded-xl bg-zinc-900 text-white flex items-center justify-center hover:bg-emerald-500 transition-all shadow-md hover:shadow-emerald-500/30"
                                            >
                                                <FaArrowRight />
                                            </Link>
                                        </div>
                                    </motion.div>
                                );
                            })
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
