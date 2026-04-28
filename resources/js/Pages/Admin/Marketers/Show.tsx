import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaInstagram, 
    FaTwitter, 
    FaYoutube, 
    FaFacebook, 
    FaTiktok, 
    FaTelegram,
    FaGlobe,
    FaPlus,
    FaMinus,
    FaLock,
    FaBan,
    FaUserSecret,
    FaTrash,
    FaWhatsapp,
    FaEnvelope,
    FaBuilding,
    FaBriefcase,
    FaHistory,
    FaChevronLeft,
    FaExternalLinkAlt,
    FaShieldAlt,
    FaChartBar,
    FaIdCard,
    FaTimes,
    FaDollarSign,
    FaCalendar,
    FaCheckCircle,
    FaExclamationTriangle,
    FaSync
} from 'react-icons/fa';
import { HiOutlineBadgeCheck, HiOutlineSparkles, HiOutlineMailOpen, HiOutlineDeviceMobile } from 'react-icons/hi';

export default function MarketerShow({ marketer, stats, recent_applications }: any) {
    const [showBalanceModal, setShowBalanceModal] = useState(false);
    const [balanceForm, setBalanceForm] = useState({ amount: '', type: 'add', note: '' });

    if (!marketer) return <AdminLayout><div className="p-8">Loading...</div></AdminLayout>;

    const adjustBalance = () => {
        if (!balanceForm.amount) return;
        router.post(route('admin.users.adjust-balance', { user: marketer.id }), balanceForm, {
            onSuccess: () => {
                setShowBalanceModal(false);
                setBalanceForm({ amount: '', type: 'add', note: '' });
            }
        });
    };

    const platformIcons: Record<string, any> = {
        instagram: FaInstagram,
        twitter: FaTwitter,
        youtube: FaYoutube,
        facebook: FaFacebook,
        telegram: FaTelegram,
        tiktok: FaTiktok,
    };

    return (
        <AdminLayout>
            <Head title={`Partner: ${marketer.name}`} />
            
            <div className="p-8 max-w-7xl mx-auto space-y-10">
                {/* Header Navigation */}
                <motion.div 
                    initial={{ opacity: 0, y: -20 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex items-center justify-between"
                >
                    <Link href={route('admin.marketers.index')} className="group flex items-center gap-3 text-sm font-black text-zinc-400 hover:text-brand-green transition-all uppercase tracking-widest">
                        <div className="p-2 rounded-xl bg-zinc-100 group-hover:bg-brand-green group-hover:text-white transition-all">
                            <FaChevronLeft className="w-3 h-3" />
                        </div>
                        Partner Ecosystem
                    </Link>
                    
                    <div className="flex gap-4">
                        {marketer.whatsapp_number && (
                            <motion.a 
                                whileHover={{ scale: 1.05 }}
                                whileTap={{ scale: 0.95 }}
                                href={`https://wa.me/${marketer.whatsapp_number.toString().replace(/[^0-9]/g, '')}`}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-2 px-6 py-3 rounded-2xl bg-emerald-500 text-white text-sm font-black uppercase tracking-widest shadow-xl shadow-emerald-500/20"
                            >
                                <FaWhatsapp className="w-5 h-5" /> Direct Chat
                            </motion.a>
                        )}
                        <motion.button 
                            whileHover={{ scale: 1.05 }}
                            whileTap={{ scale: 0.95 }}
                            onClick={() => router.post(route('admin.users.impersonate', { user: marketer.id }))}
                            className="flex items-center gap-2 px-6 py-3 rounded-2xl bg-white border border-zinc-200 text-zinc-700 text-sm font-black uppercase tracking-widest shadow-sm hover:bg-zinc-50 transition-all"
                        >
                            <FaUserSecret className="w-5 h-5 text-zinc-400" /> Impersonate
                        </motion.button>
                    </div>
                </motion.div>

                {/* Hero Header */}
                <motion.div 
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    className="relative rounded-[3rem] border border-zinc-200 bg-white shadow-2xl shadow-zinc-200/40 overflow-hidden"
                >
                    <div className="absolute top-0 left-0 w-full h-48 bg-gradient-to-br from-brand-green via-emerald-800 to-zinc-900" />
                    
                    <div className="relative pt-24 px-12 pb-12">
                        <div className="flex flex-col lg:flex-row items-center lg:items-end justify-between gap-10">
                            <div className="flex flex-col lg:flex-row items-center lg:items-end gap-10">
                                <div className="h-40 w-40 rounded-[2.5rem] bg-white p-2 shadow-2xl ring-8 ring-zinc-50/50">
                                    <div className="h-full w-full rounded-[2rem] bg-gradient-to-br from-brand-green to-emerald-900 flex items-center justify-center text-5xl font-black text-white border-4 border-white shadow-inner">
                                        {marketer.name?.[0].toUpperCase()}
                                    </div>
                                </div>
                                <div className="text-center lg:text-left pb-4 space-y-2">
                                    <h1 className="text-5xl font-black text-zinc-900 tracking-tighter">{marketer.name}</h1>
                                    <div className="flex flex-wrap items-center justify-center lg:justify-start gap-4 text-zinc-400 font-bold text-sm">
                                        <span className="flex items-center gap-2 bg-zinc-50 px-4 py-2 rounded-full border border-zinc-100">
                                            <FaEnvelope className="text-brand-green" /> {marketer.email}
                                        </span>
                                        <span className="flex items-center gap-2 bg-zinc-50 px-4 py-2 rounded-full border border-zinc-100 uppercase tracking-widest text-[10px]">
                                            <FaShieldAlt className="text-amber-500" /> {marketer.role}
                                        </span>
                                        <span className={`px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest border shadow-sm ${
                                            marketer.marketer_status === 'approved' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'
                                        }`}>
                                            {marketer.marketer_status || 'Pending'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-col items-center lg:items-end gap-2 bg-zinc-50/50 p-6 rounded-[2rem] border border-zinc-100/50 backdrop-blur-sm">
                                <p className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.3em]">Available Capital</p>
                                <div className="flex items-center gap-4">
                                    <p className="text-5xl font-black text-brand-green font-mono tracking-tighter">
                                        ${Number(marketer.balance || 0).toFixed(2)}
                                    </p>
                                    <motion.button 
                                        whileHover={{ rotate: 90, scale: 1.1 }}
                                        onClick={() => setShowBalanceModal(true)}
                                        className="h-12 w-12 rounded-2xl bg-brand-green text-white flex items-center justify-center shadow-lg shadow-brand-green/30 hover:bg-emerald-600 transition-all"
                                    >
                                        <FaPlus className="w-5 h-5" />
                                    </motion.button>
                                </div>
                            </div>
                        </div>

                        <div className="mt-16 grid grid-cols-2 lg:grid-cols-4 gap-8 pt-12 border-t border-zinc-100">
                            {[
                                { label: 'Company', value: marketer.company_name || 'Individual', icon: FaBuilding },
                                { label: 'Earnings', value: `$${(stats?.earnings || 0).toFixed(2)}`, icon: FaDollarSign },
                                { label: 'Activity', value: `${stats?.applications || 0} Apps`, icon: FaChartBar },
                                { label: 'Seniority', value: marketer.created_at ? new Date(marketer.created_at).toLocaleDateString() : '—', icon: FaCalendar },
                            ].map((item, idx) => (
                                <div key={idx} className="space-y-2">
                                    <p className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.2em]">{item.label}</p>
                                    <p className="text-xl font-black text-zinc-900 flex items-center gap-3">
                                        <item.icon className="w-5 h-5 text-zinc-200" /> {item.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </motion.div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-10">
                    <div className="lg:col-span-2 space-y-10">
                        {/* Verification Status Card */}
                        <motion.div 
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            className="bg-white rounded-[2.5rem] border border-zinc-200 p-10 shadow-sm"
                        >
                            <h2 className="text-2xl font-black text-zinc-900 flex items-center gap-3 tracking-tight mb-8">
                                <FaShieldAlt className="text-brand-green" /> Verification & Security
                            </h2>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Email Verification */}
                                <div className="p-6 rounded-[2rem] bg-zinc-50 border border-zinc-100 flex flex-col justify-between hover:bg-white hover:shadow-xl transition-all group">
                                    <div className="flex items-center gap-5 mb-6">
                                        <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-2xl shadow-sm ${marketer.email_verified_at ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'}`}>
                                            <HiOutlineMailOpen />
                                        </div>
                                        <div>
                                            <p className="font-black text-zinc-900">Email Address</p>
                                            <div className="flex items-center gap-2 mt-1">
                                                {marketer.email_verified_at ? (
                                                    <span className="flex items-center gap-1.5 text-[10px] font-black text-emerald-600 uppercase tracking-widest">
                                                        <FaCheckCircle /> Verified
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1.5 text-[10px] font-black text-amber-600 uppercase tracking-widest">
                                                        <FaExclamationTriangle /> Unverified
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        {!marketer.email_verified_at && (
                                            <button 
                                                onClick={() => router.post(route('admin.marketers.verify-email', { user: marketer.id }))}
                                                className="flex-1 py-3 rounded-xl bg-white border border-zinc-200 text-[10px] font-black uppercase tracking-widest text-zinc-700 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-all"
                                            >
                                                Manual Verify
                                            </button>
                                        )}
                                        <button 
                                            onClick={() => router.post(route('admin.marketers.resend-email', { user: marketer.id }))}
                                            className="flex-1 py-3 rounded-xl bg-zinc-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-zinc-800 transition-all flex items-center justify-center gap-2"
                                        >
                                            <FaSync className="w-2.5 h-2.5" /> Resend Mail
                                        </button>
                                    </div>
                                </div>

                                {/* Phone Verification */}
                                <div className="p-6 rounded-[2rem] bg-zinc-50 border border-zinc-100 flex flex-col justify-between hover:bg-white hover:shadow-xl transition-all group">
                                    <div className="flex items-center gap-5 mb-6">
                                        <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-2xl shadow-sm ${marketer.phone ? 'bg-emerald-100 text-emerald-600' : 'bg-zinc-200 text-zinc-400'}`}>
                                            <HiOutlineDeviceMobile />
                                        </div>
                                        <div>
                                            <p className="font-black text-zinc-900">Phone / WhatsApp</p>
                                            <div className="flex items-center gap-2 mt-1">
                                                {marketer.phone ? (
                                                    <span className="text-xs font-bold text-zinc-500">{marketer.phone}</span>
                                                ) : (
                                                    <span className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">Not Provided</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    {marketer.phone && (
                                        <button 
                                            onClick={() => router.post(route('admin.marketers.resend-phone', { user: marketer.id }))}
                                            className="w-full py-3 rounded-xl bg-white border border-zinc-200 text-[10px] font-black uppercase tracking-widest text-zinc-700 hover:bg-zinc-50 transition-all flex items-center justify-center gap-2"
                                        >
                                            <FaSync className="w-2.5 h-2.5" /> Resend OTP
                                        </button>
                                    )}
                                </div>
                            </div>
                        </motion.div>

                        {/* Social Presence */}
                        <motion.div 
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            className="bg-white rounded-[2.5rem] border border-zinc-200 p-10 shadow-sm"
                        >
                            <div className="flex items-center justify-between mb-10">
                                <div>
                                    <h2 className="text-2xl font-black text-zinc-900 flex items-center gap-3 tracking-tight">
                                        <FaGlobe className="text-brand-green" /> Digital Footprint
                                    </h2>
                                    <p className="text-zinc-400 text-xs font-bold mt-1 uppercase tracking-widest">Verified Social Media Profiles</p>
                                </div>
                                <HiOutlineBadgeCheck className="w-12 h-12 text-brand-green/10" />
                            </div>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {(!marketer.social_links || marketer.social_links.length === 0) ? (
                                    <div className="col-span-2 py-16 bg-zinc-50 rounded-[2rem] border border-dashed border-zinc-200 text-center flex flex-col items-center gap-3">
                                        <FaGlobe className="w-8 h-8 text-zinc-200" />
                                        <p className="text-zinc-400 font-bold uppercase tracking-widest text-[10px]">No verified profiles found</p>
                                    </div>
                                ) : (
                                    marketer.social_links.map((link: any) => {
                                        const Icon = platformIcons[(link.platform || '').toLowerCase()] || FaGlobe;
                                        return (
                                            <motion.div 
                                                key={link.id} 
                                                whileHover={{ y: -5 }}
                                                className="p-8 rounded-[2rem] bg-zinc-50 border border-zinc-100 flex items-center justify-between hover:bg-white hover:shadow-2xl hover:shadow-zinc-200/50 transition-all group"
                                            >
                                                <div className="flex items-center gap-6">
                                                    <div className="h-14 w-14 rounded-2xl bg-white shadow-sm flex items-center justify-center text-zinc-800 group-hover:text-brand-green transition-all">
                                                        <Icon className="w-7 h-7" />
                                                    </div>
                                                    <div>
                                                        <p className="text-lg font-black text-zinc-900 leading-none mb-1">{link.handle}</p>
                                                        <p className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">{link.platform} · {link.follower_count.toLocaleString()} Reach</p>
                                                    </div>
                                                </div>
                                                {link.profile_url && (
                                                    <a href={link.profile_url} target="_blank" rel="noreferrer" className="h-10 w-10 rounded-xl bg-white border border-zinc-100 flex items-center justify-center text-zinc-300 hover:text-brand-green hover:border-brand-green/20 transition-all shadow-sm">
                                                        <FaExternalLinkAlt className="w-4 h-4" />
                                                    </a>
                                                )}
                                            </motion.div>
                                        );
                                    })
                                )}
                            </div>
                        </motion.div>

                        {/* Portfolio Showcase */}
                        <motion.div 
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            className="bg-white rounded-[2.5rem] border border-zinc-200 p-10 shadow-sm"
                        >
                            <div className="flex items-center justify-between mb-10">
                                <div>
                                    <h2 className="text-2xl font-black text-zinc-900 flex items-center gap-3 tracking-tight">
                                        <FaBriefcase className="text-zinc-400" /> Portfolio Showcase
                                    </h2>
                                    <p className="text-zinc-400 text-xs font-bold mt-1 uppercase tracking-widest">Selected Works & Case Studies</p>
                                </div>
                                <HiOutlineSparkles className="w-10 h-10 text-brand-green/20" />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {(!marketer.portfolios || marketer.portfolios.length === 0) ? (
                                    <div className="col-span-2 py-16 bg-zinc-50 rounded-[2rem] border border-dashed border-zinc-200 text-center">
                                        <p className="text-zinc-400 font-bold uppercase tracking-widest text-[10px]">No portfolio items submitted</p>
                                    </div>
                                ) : (
                                    marketer.portfolios.map((item: any) => (
                                        <motion.div 
                                            key={item.id} 
                                            whileHover={{ y: -8 }}
                                            className="group relative rounded-[2rem] border border-zinc-200 overflow-hidden bg-zinc-100 transition-all"
                                        >
                                            <div className="h-48 overflow-hidden bg-zinc-200">
                                                {item.thumbnail_url ? (
                                                    <img src={item.thumbnail_url} className="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500" alt={item.title} />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center text-zinc-300">
                                                        <FaBriefcase className="w-12 h-12" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="p-6 bg-white border-t border-zinc-100">
                                                <p className="text-lg font-black text-zinc-900 truncate tracking-tight">{item.title}</p>
                                                <p className="text-[10px] text-brand-green font-black uppercase tracking-widest mt-1">{item.platform}</p>
                                            </div>
                                            {item.url && (
                                                <a href={item.url} target="_blank" rel="noreferrer" className="absolute top-4 right-4 h-10 w-10 rounded-xl bg-white/90 backdrop-blur-sm shadow-xl flex items-center justify-center text-zinc-700 hover:text-brand-green opacity-0 group-hover:opacity-100 transition-all">
                                                    <FaExternalLinkAlt className="w-4 h-4" />
                                                </a>
                                            )}
                                        </motion.div>
                                    ))
                                )}
                            </div>
                        </motion.div>

                        {/* Recent History */}
                        <div className="bg-white rounded-[2.5rem] border border-zinc-200 shadow-sm overflow-hidden">
                            <div className="p-10 border-b border-zinc-100 flex items-center gap-4">
                                <div className="h-12 w-12 rounded-2xl bg-zinc-50 flex items-center justify-center text-zinc-300">
                                    <FaHistory />
                                </div>
                                <h2 className="text-2xl font-black text-zinc-900 tracking-tight">Campaign Audit Log</h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-zinc-50 border-b border-zinc-100 text-[10px] font-black text-zinc-400 uppercase tracking-[0.3em]">
                                        <tr>
                                            <th className="px-10 py-6">Campaign Strategy</th>
                                            <th className="px-10 py-6 text-center">Outcome</th>
                                            <th className="px-10 py-6 text-right">Filing Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-50">
                                        {(recent_applications || []).map((app: any) => (
                                            <tr key={app.id} className="hover:bg-zinc-50/50 transition-colors">
                                                <td className="px-10 py-8">
                                                    <p className="text-lg font-black text-zinc-900 tracking-tight">{app.contract?.title || 'System Campaign'}</p>
                                                    <p className="text-xs text-zinc-500 font-bold uppercase tracking-[0.2em] mt-1">{app.contract?.business?.name}</p>
                                                </td>
                                                <td className="px-10 py-8 text-center">
                                                    <span className={`px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest ${
                                                        app.status === 'approved' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' :
                                                        app.status === 'rejected' ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-zinc-100 text-zinc-500'
                                                    }`}>
                                                        {app.status}
                                                    </span>
                                                </td>
                                                <td className="px-10 py-8 text-right text-zinc-400 font-black font-mono text-xs">
                                                    {new Date(app.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-10">
                        {/* Status Sidebar */}
                        <motion.div 
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="bg-zinc-900 rounded-[2.5rem] p-10 text-white shadow-2xl relative overflow-hidden group"
                        >
                            <div className="absolute top-0 right-0 h-32 w-32 bg-brand-green/10 rounded-full blur-3xl -mr-10 -mt-10" />
                            <h3 className="text-[10px] font-black text-brand-green uppercase tracking-[0.3em] mb-6 flex items-center gap-2">
                                <FaIdCard className="text-brand-green" /> Partner Intelligence
                            </h3>
                            <div className="space-y-6 relative z-10">
                                <div>
                                    <p className="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2">Short Bio</p>
                                    <p className="text-sm text-zinc-300 leading-relaxed font-medium italic">
                                        "{marketer.bio || "No professional biography has been established for this partner."}"
                                    </p>
                                </div>
                                <div className="pt-6 border-t border-zinc-800 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-[10px] font-black text-zinc-500 uppercase">System ID</span>
                                        <span className="text-xs font-black text-zinc-300 font-mono">ID-{marketer.id.toString().padStart(5, '0')}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-[10px] font-black text-zinc-500 uppercase">Account Status</span>
                                        <span className={`h-2 w-2 rounded-full ${marketer.is_active ? 'bg-emerald-500 shadow-[0_0_8px_#10b981]' : 'bg-red-500 shadow-[0_0_8px_#ef4444]'}`} />
                                    </div>
                                </div>
                            </div>
                        </motion.div>

                        {/* Admin Action Box */}
                        <div className="bg-white rounded-[2.5rem] border border-zinc-200 p-10 shadow-sm">
                            <h3 className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.3em] mb-10">Security & Governance</h3>
                            
                            <div className="space-y-4">
                                <button 
                                    onClick={() => confirm('Issue password reset?') && router.post(route('admin.users.reset-password', { user: marketer.id }))}
                                    className="flex items-center justify-center gap-3 w-full p-5 rounded-2xl bg-zinc-50 text-zinc-700 text-xs font-black uppercase tracking-widest hover:bg-zinc-100 transition-all border border-zinc-100 shadow-sm active:scale-95"
                                >
                                    <FaLock className="opacity-30" /> Reset Password
                                </button>
                                
                                <button 
                                    onClick={() => confirm('Change access status?') && router.post(route('admin.marketers.suspend', { user: marketer.id }))}
                                    className={`flex items-center justify-center gap-3 w-full p-5 rounded-2xl text-xs font-black uppercase tracking-widest transition-all border shadow-sm active:scale-95 ${
                                        marketer.is_active 
                                        ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' 
                                        : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                                    }`}
                                >
                                    {marketer.is_active ? <><FaBan className="opacity-40" /> Suspend Partner</> : <><FaShieldAlt className="opacity-40" /> Reactivate</>}
                                </button>

                                <button 
                                    onClick={() => confirm('Demote partner to regular user?') && router.post(route('admin.marketers.demote', { user: marketer.id }))}
                                    className="flex items-center justify-center gap-3 w-full p-5 rounded-2xl bg-blue-50 text-blue-700 text-xs font-black uppercase tracking-widest hover:bg-blue-100 transition-all border border-blue-200 shadow-sm active:scale-95"
                                >
                                    <FaBriefcase className="opacity-40" /> Demote Partner
                                </button>

                                <div className="pt-6 mt-6 border-t border-zinc-100">
                                    <button 
                                        onClick={() => confirm('CONFIRM PERMANENT TERMINATION?') && router.delete(route('admin.marketers.terminate', { user: marketer.id }))}
                                        className="flex items-center justify-center gap-3 w-full p-5 rounded-2xl bg-red-50 text-red-600 text-xs font-black uppercase tracking-widest hover:bg-red-100 transition-all border border-red-200 shadow-sm active:scale-95"
                                    >
                                        <FaTrash className="opacity-40" /> Terminate Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Balance Modal */}
                <AnimatePresence>
                    {showBalanceModal && (
                        <div className="fixed inset-0 z-[100] flex items-center justify-center p-6">
                            <motion.div 
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                onClick={() => setShowBalanceModal(false)}
                                className="absolute inset-0 bg-zinc-900/80 backdrop-blur-xl" 
                            />
                            
                            <motion.div 
                                initial={{ opacity: 0, scale: 0.9, y: 20 }}
                                animate={{ opacity: 1, scale: 1, y: 0 }}
                                exit={{ opacity: 0, scale: 0.9, y: 20 }}
                                className="relative bg-white rounded-[3rem] w-full max-w-lg shadow-[0_32px_64px_-16px_rgba(0,0,0,0.3)] overflow-hidden"
                            >
                                <div className="p-10 border-b border-zinc-100 flex items-center justify-between bg-zinc-50/50">
                                    <div>
                                        <h3 className="text-3xl font-black text-zinc-900 tracking-tight">Treasury Control</h3>
                                        <p className="text-zinc-400 text-xs font-bold uppercase tracking-widest mt-1">Manual Balance Adjustment</p>
                                    </div>
                                    <button onClick={() => setShowBalanceModal(false)} className="h-12 w-12 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center hover:bg-red-50 hover:text-red-500 transition-all"><FaTimes className="w-5 h-5" /></button>
                                </div>
                                <div className="p-10 space-y-8">
                                    <div className="flex p-2 bg-zinc-100 rounded-[1.8rem]">
                                        <button 
                                            onClick={() => setBalanceForm(f => ({...f, type: 'add'}))}
                                            className={`flex-1 py-4 rounded-[1.3rem] text-[10px] font-black uppercase tracking-[0.2em] transition-all flex items-center justify-center gap-3 ${balanceForm.type === 'add' ? 'bg-white text-emerald-600 shadow-2xl' : 'text-zinc-400 hover:text-zinc-600'}`}
                                        >
                                            <FaPlus className="w-3 h-3" /> Credit Account
                                        </button>
                                        <button 
                                            onClick={() => setBalanceForm(f => ({...f, type: 'subtract'}))}
                                            className={`flex-1 py-4 rounded-[1.3rem] text-[10px] font-black uppercase tracking-[0.2em] transition-all flex items-center justify-center gap-3 ${balanceForm.type === 'subtract' ? 'bg-white text-red-600 shadow-2xl' : 'text-zinc-400 hover:text-zinc-600'}`}
                                        >
                                            <FaMinus className="w-3 h-3" /> Debit Account
                                        </button>
                                    </div>
                                    
                                    <div className="space-y-4">
                                        <div className="relative">
                                            <span className="absolute left-6 top-1/2 -translate-y-1/2 text-3xl font-black text-zinc-300 font-mono">$</span>
                                            <input 
                                                type="number" 
                                                step="0.01"
                                                value={balanceForm.amount}
                                                onChange={e => setBalanceForm(f => ({...f, amount: e.target.value}))}
                                                placeholder="0.00"
                                                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-[1.8rem] pl-14 pr-8 py-8 text-5xl font-black text-zinc-900 font-mono focus:outline-none focus:border-brand-green transition-all placeholder:text-zinc-200"
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Audit Trait / Reason</label>
                                            <textarea 
                                                value={balanceForm.note}
                                                onChange={e => setBalanceForm(f => ({...f, note: e.target.value}))}
                                                placeholder="Document the reason for this manual adjustment..."
                                                rows={3}
                                                className="w-full bg-zinc-50 border border-zinc-200 rounded-[1.5rem] px-8 py-6 text-sm font-bold text-zinc-600 focus:outline-none focus:border-brand-green transition-all resize-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="p-10 bg-zinc-50 border-t border-zinc-100 flex gap-4">
                                    <button 
                                        onClick={adjustBalance}
                                        disabled={!balanceForm.amount}
                                        className="flex-1 bg-zinc-900 text-brand-green py-6 rounded-[1.8rem] font-black text-sm uppercase tracking-[0.3em] shadow-2xl hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50"
                                    >
                                        Execute Transaction
                                    </button>
                                </div>
                            </motion.div>
                        </div>
                    )}
                </AnimatePresence>
            </div>
        </AdminLayout>
    );
}
