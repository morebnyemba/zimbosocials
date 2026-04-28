import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaInstagram, 
    FaYoutube, 
    FaTiktok, 
    FaFacebook, 
    FaTwitter, 
    FaTelegram, 
    FaWhatsapp,
    FaRocket, 
    FaSync, 
    FaInfoCircle, 
    FaSearch,
    FaFilter,
    FaArrowRight,
    FaClock
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

const categoryMeta: Record<string, { label: string; icon: any; color: string; bg: string }> = {
    instagram: { label: 'Instagram', icon: FaInstagram, color: 'text-pink-600', bg: 'bg-pink-50' },
    youtube: { label: 'YouTube', icon: FaYoutube, color: 'text-red-600', bg: 'bg-red-50' },
    tiktok: { label: 'TikTok', icon: FaTiktok, color: 'text-zinc-900', bg: 'bg-zinc-100' },
    facebook: { label: 'Facebook', icon: FaFacebook, color: 'text-blue-600', bg: 'bg-blue-50' },
    twitter: { label: 'Twitter / X', icon: FaTwitter, color: 'text-sky-600', bg: 'bg-sky-50' },
    telegram: { label: 'Telegram', icon: FaTelegram, color: 'text-cyan-600', bg: 'bg-cyan-50' },
    whatsapp: { label: 'WhatsApp', icon: FaWhatsapp, color: 'text-green-600', bg: 'bg-green-50' },
};

export default function Services({ services, categories }: Props) {
    const [active, setActive] = useState(categories[0] ?? '');
    const [search, setSearch] = useState('');

    const filtered = services.filter((s) => 
        s.category === active && 
        (s.name.toLowerCase().includes(search.toLowerCase()) || 
         s.description?.toLowerCase().includes(search.toLowerCase()))
    );

    const formatTime = (minutes: number) => {
        if (minutes < 60) return `~${minutes}m`;
        if (minutes < 1440) return `~${Math.round(minutes / 60)}h`;
        return `~${Math.round(minutes / 1440)}d`;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Premium Services" />

            <div className="space-y-12">
                {/* Header Section */}
                <div className="flex flex-col md:flex-row md:items-end justify-between gap-8">
                    <div>
                        <h1 className="text-4xl font-black text-zinc-900 tracking-tighter">Elite Services</h1>
                        <p className="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-2">Scale your presence with industrial-grade tools</p>
                    </div>

                    <div className="relative w-full max-w-md group">
                        <FaSearch className="absolute left-6 top-1/2 -translate-y-1/2 text-zinc-300 group-focus-within:text-emerald-500 transition-colors" />
                        <input 
                            type="text" 
                            placeholder="Search specific services..." 
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full bg-white border-2 border-zinc-100 rounded-[2rem] pl-14 pr-8 py-4 font-bold text-zinc-900 focus:outline-none focus:border-emerald-500 transition-all shadow-xl shadow-zinc-200/40"
                        />
                    </div>
                </div>

                {/* Category Navigation */}
                <div className="flex gap-3 overflow-x-auto pb-4 scrollbar-hide no-scrollbar">
                    {categories.map((cat) => {
                        const meta = categoryMeta[cat] || { label: cat, icon: FaRocket, color: 'text-emerald-600', bg: 'bg-emerald-50' };
                        const isActive = active === cat;
                        return (
                            <button
                                key={cat}
                                onClick={() => setActive(cat)}
                                className={`flex items-center gap-3 px-8 py-4 rounded-[2rem] text-sm font-black uppercase tracking-widest whitespace-nowrap transition-all border-2 ${isActive ? 'bg-zinc-900 border-zinc-900 text-white shadow-xl shadow-zinc-900/20' : 'bg-white border-zinc-100 text-zinc-500 hover:border-emerald-200 hover:text-emerald-600'}`}
                            >
                                <meta.icon className={isActive ? 'text-emerald-400' : 'opacity-30'} />
                                {meta.label}
                            </button>
                        );
                    })}
                </div>

                {/* Services Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <AnimatePresence mode="popLayout">
                        {filtered.length === 0 ? (
                            <motion.div 
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                className="col-span-full py-32 text-center bg-white rounded-[3rem] border-2 border-dashed border-zinc-100"
                            >
                                <div className="h-16 w-16 rounded-3xl bg-zinc-50 flex items-center justify-center mx-auto mb-6 text-zinc-300 text-2xl">
                                    <FaFilter />
                                </div>
                                <h3 className="text-xl font-black text-zinc-900 tracking-tight">No Matching Services</h3>
                                <p className="text-zinc-500 text-sm font-medium mt-2">Adjust your filters or search terms to find what you need.</p>
                            </motion.div>
                        ) : (
                            filtered.map((service, idx) => (
                                <motion.div
                                    layout
                                    key={service.id}
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: idx * 0.05 }}
                                    className="bg-white rounded-[2.5rem] border border-zinc-200 p-8 flex flex-col justify-between hover:border-emerald-500/20 hover:shadow-2xl hover:shadow-emerald-500/5 transition-all group"
                                >
                                    <div className="space-y-6">
                                        <div className="flex items-start justify-between">
                                            <div className="h-14 w-14 rounded-2xl bg-zinc-50 border border-zinc-100 flex items-center justify-center text-2xl text-zinc-400 group-hover:text-emerald-500 group-hover:bg-emerald-50 transition-all">
                                                {(() => {
                                                    const Icon = categoryMeta[service.category]?.icon || FaRocket;
                                                    return <Icon />;
                                                })()}
                                            </div>
                                            <div className="flex flex-col items-end">
                                                <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400">Rate per 1k</p>
                                                <p className="text-2xl font-black text-emerald-600 tracking-tighter">${Number(service.rate).toFixed(2)}</p>
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <h3 className="text-lg font-black text-zinc-900 leading-tight group-hover:text-emerald-600 transition-colors">{service.name}</h3>
                                            <p className="text-zinc-500 text-xs font-medium leading-relaxed line-clamp-3">
                                                {service.description || 'Professional grade service with high retention and rapid delivery protocols.'}
                                            </p>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="px-4 py-3 rounded-2xl bg-zinc-50 border border-zinc-100">
                                                <p className="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-1">Speed</p>
                                                <p className="text-xs font-black text-zinc-900 flex items-center gap-2"><FaClock className="text-emerald-500" /> {formatTime(service.avg_time_minutes)}</p>
                                            </div>
                                            <div className="px-4 py-3 rounded-2xl bg-zinc-50 border border-zinc-100">
                                                <p className="text-[9px] font-black uppercase tracking-widest text-zinc-400 mb-1">Refill</p>
                                                <p className="text-xs font-black text-zinc-900 flex items-center gap-2">
                                                    {service.is_refill ? <><FaSync className="text-emerald-500 animate-spin-slow" /> Yes</> : <span className="opacity-30">No</span>}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-8 pt-8 border-t border-zinc-50 flex items-center justify-between">
                                        <div className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">
                                            Min: {service.min_qty.toLocaleString()} <span className="mx-2 opacity-30">|</span> Max: {service.max_qty.toLocaleString()}
                                        </div>
                                        <Link 
                                            href={route('orders.create', { service_id: service.id })}
                                            className="h-12 w-12 rounded-xl bg-zinc-900 text-white flex items-center justify-center hover:bg-emerald-500 transition-all shadow-lg hover:shadow-emerald-500/30"
                                        >
                                            <FaArrowRight />
                                        </Link>
                                    </div>
                                </motion.div>
                            ))
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
