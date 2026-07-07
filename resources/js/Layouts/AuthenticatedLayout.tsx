import { Link, usePage, router } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState, useEffect, useRef } from 'react';
import { useTranslation } from '@/lib/i18n';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaChartPie, 
    FaBox, 
    FaWallet, 
    FaFileContract, 
    FaHeadset, 
    FaCode, 
    FaCog, 
    FaBell, 
    FaUserCircle, 
    FaSignOutAlt, 
    FaBars, 
    FaTimes, 
    FaChevronDown,
    FaShieldAlt,
    FaRocket,
    FaArrowRight, 
    FaHistory,
    FaCheckCircle,
    FaExclamationTriangle,
    FaPlus,
    FaGlobe,
    FaUsers,
    FaCalendarAlt,
    FaDollarSign,
    FaPen,
    FaTrophy,
    FaLanguage,
} from 'react-icons/fa';

function LangSwitcher() {
    const { locale, t } = useTranslation();
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const current = locale ?? 'sn';

    const langs = [
        { code: 'sn', short: 'SN', label: t('shona') },
        { code: 'nd', short: 'ND', label: t('ndebele') },
        { code: 'en', short: 'EN', label: t('english') },
    ];

    const currentLang = langs.find((l) => l.code === current) ?? langs[0];

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const switchLang = (code: string) => {
        setOpen(false);
        router.post(route('locale.switch'), { locale: code }, { preserveScroll: true });
    };

    return (
        <div ref={ref} className="relative flex">
            <button
                onClick={() => setOpen((v) => !v)}
                className="flex items-center gap-1 rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 shadow-sm hover:border-zinc-300 focus:outline-none"
                aria-label="Switch language"
            >
                <FaGlobe className="text-[10px] text-zinc-400" />
                <span className="text-[9px] font-black uppercase tracking-wide text-zinc-700">{currentLang.short}</span>
                <FaChevronDown className={`text-[8px] text-zinc-400 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            <AnimatePresence>
                {open && (
                    <motion.div
                        initial={{ opacity: 0, y: -4 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -4 }}
                        transition={{ duration: 0.12 }}
                        className="absolute right-0 top-full mt-1 z-50 min-w-[110px] rounded-md border border-zinc-200 bg-white py-1 shadow-md"
                    >
                        {langs.map((lang) => (
                            <button
                                key={lang.code}
                                onClick={() => switchLang(lang.code)}
                                className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs hover:bg-zinc-50 ${lang.code === current ? 'font-bold text-zinc-900' : 'text-zinc-600'}`}
                            >
                                <span className="w-5 text-[9px] font-black uppercase tracking-wide text-zinc-400">{lang.short}</span>
                                {lang.label}
                            </button>
                        ))}
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }: PropsWithChildren<{ header?: ReactNode }>) {
    const { t } = useTranslation();
    const user = usePage().props.auth.user;
    const flash = (usePage().props as any).flash as { success?: string; error?: string; info?: string } | undefined;
    const notificationsCount = (usePage().props as any).notifications_count || 0;

    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const [userDropdownOpen, setUserDropdownOpen] = useState(false);
    const [exploreDropdownOpen, setExploreDropdownOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    // Flag the mobile bottom tab bar's presence so the fixed WhatsApp button
    // lifts above it (CSS rule in app.css) instead of covering it. Tawk.to is
    // offset separately in app.blade.php — its customStyle API only applies
    // when set before the embed script loads. Guest/marketing pages don't set
    // this class, so their widgets keep the default bottom offset.
    useEffect(() => {
        document.body.classList.add('has-mobile-tabbar');

        return () => document.body.classList.remove('has-mobile-tabbar');
    }, []);

    const navLinks = [
        { href: route('dashboard'), name: 'dashboard', label: 'Dashboard', icon: FaChartPie },
        { href: route('services.index'), name: 'services.index', label: 'Services', icon: FaBox },
        { href: route('orders.index'), name: 'orders.index', label: 'Orders', icon: FaRocket },
        { href: route('wallet.index'), name: 'wallet.index', label: 'Wallet', icon: FaWallet },
        { href: route('referrals.index'), name: 'referrals.index', label: 'Referrals', icon: FaUsers },
        { href: route('leaderboard.index'), name: 'leaderboard.index', label: 'Leaderboard', icon: FaTrophy },
        ...(user.account_type !== 'individual' ? [{ href: route('contracts.index'), name: 'contracts.index', label: 'Contracts', icon: FaFileContract }] : []),
        ...(user.role === 'marketer' || user.role === 'reseller' ? [{ href: route('marketer.content-calendar'), name: 'marketer.content-calendar', label: 'AI Content Calendar', icon: FaCalendarAlt }] : []),
        ...(user.role === 'marketer' || user.role === 'reseller' ? [{ href: route('marketer.portfolio-caption'), name: 'marketer.portfolio-caption', label: 'AI Portfolio Caption', icon: FaPen }] : []),
        ...(user.role === 'marketer' || user.role === 'reseller' ? [{ href: route('monetizer.index'), name: 'monetizer.index', label: 'Monetizer', icon: FaDollarSign }] : []),
        { href: route('tickets.index'), name: 'tickets.index', label: 'Support', icon: FaHeadset },
    ];

    return (
        <div className="min-h-screen bg-[#F8FAFC]">
            {/* Impersonation Banner */}
            {(usePage().props.auth as any).is_impersonating && (
                <div className="bg-gradient-to-r from-amber-600 to-orange-600 text-white px-6 py-2.5 flex items-center justify-between shadow-lg sticky top-0 z-[100] backdrop-blur-md">
                    <div className="flex items-center gap-3 text-sm font-black uppercase tracking-widest">
                        <FaShieldAlt className="animate-pulse" />
                        Admin Mode: Acting as {user.name}
                    </div>
                    <button onClick={() => router.post(route('admin.users.impersonate.leave'))} className="bg-white/20 hover:bg-white/30 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest transition-all border border-white/20">
                        Leave Session
                    </button>
                </div>
            )}

            {/* Top Logo Bar */}
            <div className="fixed top-0 left-0 z-[60] hidden w-full border-b border-zinc-200 bg-white/95 backdrop-blur-xl md:block">
                <div className="mx-auto flex h-14 max-w-7xl items-center justify-center px-6 lg:px-12">
                    <Link href="/" className="group flex items-center gap-3">
                        <div className="h-9 px-2 flex items-center justify-center rounded-lg border border-zinc-200 bg-white shadow-sm transition-transform duration-300 group-hover:scale-105">
                            <img src="/images/zimbosocials.png" alt="Zimbo Socials" className="h-full w-auto object-contain" />
                        </div>
                    </Link>
                </div>
            </div>

            {/* Main Header */}
            <nav className={`fixed left-0 top-0 md:top-14 w-full z-50 transition-all duration-500 ${scrolled ? 'bg-white/80 backdrop-blur-2xl border-b border-zinc-200 shadow-sm py-2' : 'bg-transparent py-5'}`}>
                <div className="max-w-7xl mx-auto px-6 lg:px-12 flex items-center justify-between gap-4">
                    {/* Logo Area */}
                    <div className="flex min-w-0 flex-1 items-center gap-6 xl:gap-12">
                        <Link href="/" className="group md:hidden flex items-center gap-2">
                            <div className="h-8 px-2 flex items-center justify-center rounded-lg border border-zinc-200 bg-white shadow-sm">
                                <img src="/images/zimbosocials.png" alt="Zimbo Socials" className="h-full w-auto object-contain" />
                            </div>
                        </Link>

                        {/* Desktop Navigation */}
                        <div className="hidden min-w-0 flex-1 lg:flex">
                            <div className="flex min-w-0 items-center gap-2 overflow-x-auto py-1 pr-2">
                            {navLinks.map((link) => {
                                const active = route().current(link.name);
                                return (
                                    <Link 
                                        key={link.name} 
                                        href={link.href}
                                        className={`shrink-0 px-4 xl:px-5 py-2.5 rounded-2xl text-[11px] font-black uppercase tracking-widest transition-all flex items-center gap-2.5 whitespace-nowrap ${active ? 'bg-zinc-950 text-white shadow-xl shadow-zinc-900/40 ring-2 ring-emerald-500/20' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'}`}
                                    >
                                        <link.icon className={`text-sm ${active ? 'text-amber-400' : 'text-zinc-300'}`} />
                                        {link.label}
                                    </Link>
                                );
                            })}
                            </div>
                        </div>
                    </div>

                    {/* Right Actions */}
                    <div className="flex shrink-0 items-center gap-3 md:gap-4 xl:gap-6">
                        {/* Compact Balance (mobile/tablet — the bottom tab bar covers Wallet nav) */}
                        <Link href={route('wallet.index')} className="flex lg:hidden items-center gap-1.5 bg-zinc-950 px-3 py-2 rounded-xl">
                            <FaWallet className="text-amber-400 text-[10px]" />
                            <span className="text-white font-mono font-black text-xs tracking-tighter">
                                ${Number(user.balance || 0).toFixed(2)}
                            </span>
                        </Link>

                        {/* Wallet Badge (desktop) */}
                        <Link href={route('wallet.index')} className="hidden lg:flex items-center gap-3 bg-zinc-950 px-5 py-2.5 rounded-2xl border border-zinc-800 shadow-xl group relative overflow-hidden">
                            <div className="absolute inset-0 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 opacity-10 group-hover:opacity-20 transition-opacity" />
                            <FaWallet className="text-amber-400 text-xs relative z-10" />
                            <span className="text-white font-mono font-black text-sm tracking-tighter relative z-10">
                                ${Number(user.balance || 0).toFixed(2)}
                            </span>
                            <div className="p-1 rounded-lg bg-white/10 group-hover:bg-white/20 transition-colors relative z-10">
                                <FaPlus className="text-[10px] text-white" />
                            </div>
                        </Link>

                        {/* Notifications (desktop — mobile reaches this via the avatar dropdown) */}
                        <Link href={route('notifications.index')} className="hidden lg:flex relative h-12 w-12 rounded-2xl bg-white border border-zinc-200 items-center justify-center text-zinc-500 hover:text-emerald-500 hover:border-emerald-200 transition-all shadow-sm">
                                <FaBell className="text-sm" />
                            {notificationsCount > 0 && (
                                <span className="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-emerald-500 text-white text-[10px] font-black border-4 border-white flex items-center justify-center animate-bounce">
                                    {notificationsCount > 9 ? '9+' : notificationsCount}
                                </span>
                            )}
                        </Link>

                            {/* Language Switcher */}
                            <LangSwitcher />

                        {/* User Profile Dropdown */}
                        <div className="relative">
                            <button
                                onClick={() => setUserDropdownOpen(!userDropdownOpen)}
                                className="relative h-12 w-12 rounded-2xl bg-zinc-100 border border-zinc-200 overflow-hidden hover:border-emerald-300 transition-all ring-offset-2 hover:ring-2 ring-emerald-500/20"
                            >
                                {user.profile_image_url ? (
                                    <img src={user.profile_image_url} alt="" className="h-full w-full object-cover" />
                                ) : (
                                    <div className="h-full w-full flex items-center justify-center bg-gradient-to-br from-zinc-700 to-zinc-900 text-white font-black">
                                        {(user.name?.[0] ?? 'U').toUpperCase()}
                                    </div>
                                )}
                                {notificationsCount > 0 && (
                                    <span className="lg:hidden absolute top-0.5 right-0.5 h-3 w-3 rounded-full bg-emerald-500 border-2 border-white" />
                                )}
                            </button>

                            <AnimatePresence>
                                {userDropdownOpen && (
                                    <>
                                        <div className="fixed inset-0 z-10" onClick={() => setUserDropdownOpen(false)} />
                                        <motion.div
                                            initial={{ opacity: 0, scale: 0.95, y: 10 }}
                                            animate={{ opacity: 1, scale: 1, y: 0 }}
                                            exit={{ opacity: 0, scale: 0.95, y: 10 }}
                                            className="absolute right-0 mt-4 w-72 bg-white rounded-3xl border border-zinc-200 shadow-2xl z-20 overflow-hidden py-4"
                                        >
                                            <div className="px-6 py-4 border-b border-zinc-50 mb-4">
                                                <p className="text-sm font-black text-zinc-900">{user.name}</p>
                                                <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest truncate">{user.email}</p>
                                            </div>
                                            <div className="px-3 space-y-1">
                                                <Link href={route('notifications.index')} className="lg:hidden flex items-center justify-between gap-3 px-4 py-3 rounded-2xl text-xs font-black text-zinc-600 hover:bg-zinc-50 transition-all">
                                                    <span className="flex items-center gap-3"><FaBell className="text-zinc-300" /> Notifications</span>
                                                    {notificationsCount > 0 && (
                                                        <span className="h-5 w-5 rounded-full bg-emerald-500 text-white text-[10px] font-black flex items-center justify-center">
                                                            {notificationsCount > 9 ? '9+' : notificationsCount}
                                                        </span>
                                                    )}
                                                </Link>
                                                <Link href={route('settings.index')} className="flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-black text-zinc-600 hover:bg-zinc-50 transition-all">
                                                    <FaCog className="text-zinc-300" /> Account Settings
                                                </Link>
                                                <Link href={route('developer.api')} className="flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-black text-zinc-600 hover:bg-zinc-50 transition-all">
                                                    <FaCode className="text-zinc-300" /> Developer Terminal
                                                </Link>
                                                <Link href={route('translations.index')} className="flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-black text-zinc-600 hover:bg-zinc-50 transition-all">
                                                    <FaLanguage className="text-zinc-300" /> {t('help_translate')}
                                                </Link>
                                                {user.role === 'admin' && (
                                                    <Link href={route('admin.dashboard')} className="flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-black text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-all">
                                                        <FaShieldAlt /> System Command Center
                                                    </Link>
                                                )}
                                                <Link method="post" href={route('logout')} as="button" className="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-black text-red-600 hover:bg-red-50 transition-all mt-2">
                                                    <FaSignOutAlt className="opacity-30" /> Terminate Session
                                                </Link>
                                            </div>
                                        </motion.div>
                                    </>
                                )}
                            </AnimatePresence>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Mobile Sidebar Overlay */}
            <AnimatePresence>
                {isMobileMenuOpen && (
                    <div className="fixed inset-0 z-[100] lg:hidden">
                        <motion.div 
                            initial={{ opacity: 0 }} 
                            animate={{ opacity: 1 }} 
                            exit={{ opacity: 0 }} 
                            onClick={() => setIsMobileMenuOpen(false)}
                            className="absolute inset-0 bg-zinc-900/60 backdrop-blur-xl"
                        />
                        <motion.div 
                            initial={{ x: '100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '100%' }}
                            transition={{ type: 'spring', damping: 25, stiffness: 200 }}
                            className="absolute right-0 top-0 bottom-0 w-[85%] max-w-sm bg-white shadow-2xl flex flex-col"
                        >
                            <div className="p-8 border-b border-zinc-100 flex items-center justify-between">
                                <span className="text-xl font-black text-zinc-900 tracking-tighter">Menu</span>
                                <button onClick={() => setIsMobileMenuOpen(false)} className="h-10 w-10 rounded-xl bg-zinc-100 flex items-center justify-center">
                                    <FaTimes />
                                </button>
                            </div>
                            <div className="flex-1 overflow-y-auto p-8 space-y-2">
                                {navLinks.map((link) => (
                                    <Link 
                                        key={link.name} 
                                        href={link.href}
                                        onClick={() => setIsMobileMenuOpen(false)}
                                        className={`flex items-center gap-4 p-5 rounded-3xl text-sm font-black uppercase tracking-widest ${route().current(link.name) ? 'bg-emerald-500 text-white shadow-xl shadow-emerald-500/30' : 'text-zinc-500 hover:bg-zinc-50'}`}
                                    >
                                        <link.icon className={route().current(link.name) ? 'text-white' : 'text-emerald-500'} />
                                        {link.label}
                                    </Link>
                                ))}
                                <div className="h-px bg-zinc-100 my-6" />
                                <Link href={route('wallet.index')} className="flex items-center justify-between p-6 rounded-3xl bg-zinc-900 text-white shadow-xl">
                                    <div className="flex items-center gap-4 font-black uppercase tracking-widest text-xs">
                                        <FaWallet className="text-emerald-400" /> My Wallet
                                    </div>
                                    <span className="font-mono text-xl font-black tracking-tighter">${Number(user.balance || 0).toFixed(2)}</span>
                                </Link>
                                <div className="rounded-2xl border border-zinc-200 p-4">
                                    <label className="mb-2 block text-[10px] font-black uppercase tracking-widest text-zinc-500">Language</label>
                                    <select
                                        aria-label="Language"
                                        value={(usePage().props as any).locale || 'sn'}
                                        onChange={(e) => router.post(route('locale.switch'), { locale: e.target.value }, { preserveScroll: true })}
                                        className="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold text-zinc-700"
                                    >
                                        <option value="sn">Shona</option>
                                        <option value="nd">IsiNdebele</option>
                                        <option value="en">English</option>
                                    </select>
                                </div>
                            </div>
                            <div className="p-8 bg-zinc-50 border-t border-zinc-100">
                                <div className="flex items-center gap-4 mb-6">
                                    <div className="h-12 w-12 rounded-2xl bg-zinc-900 border border-zinc-800 text-white flex items-center justify-center font-black">
                                        {(user.name?.[0] ?? 'U').toUpperCase()}
                                    </div>
                                    <div>
                                        <p className="text-sm font-black text-zinc-900">{user.name}</p>
                                        <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{user.email}</p>
                                    </div>
                                </div>
                                <Link method="post" href={route('logout')} as="button" className="w-full p-5 rounded-2xl bg-white border border-red-100 text-red-600 text-xs font-black uppercase tracking-widest hover:bg-red-50 transition-all flex items-center justify-center gap-3">
                                    <FaSignOutAlt /> Sign Out Securely
                                </Link>
                            </div>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>

            {/* Flash Messaging */}
            <AnimatePresence>
                {(flash?.success || flash?.error || flash?.info) && (
                    <motion.div 
                        initial={{ opacity: 0, y: -20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.9 }}
                        className="fixed top-24 left-1/2 -translate-x-1/2 z-[60] w-full max-w-lg px-6"
                    >
                        {flash?.success && (
                            <div className="bg-emerald-500 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3">
                                <FaCheckCircle className="shrink-0" />
                                <span className="text-xs font-black uppercase tracking-widest">{flash.success}</span>
                            </div>
                        )}
                        {flash?.error && (
                            <div className="bg-red-500 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3">
                                <FaExclamationTriangle className="shrink-0" />
                                <span className="text-xs font-black uppercase tracking-widest">{flash.error}</span>
                            </div>
                        )}
                    </motion.div>
                )}
            </AnimatePresence>

            {/* Main Content Area */}
            <main className="pt-28 md:pt-36 pb-24 lg:pb-12">
                {header && (
                    <div className="max-w-7xl mx-auto px-6 lg:px-12 mb-10">
                        <motion.div
                            initial={{ opacity: 0, x: -20 }}
                            animate={{ opacity: 1, x: 0 }}
                        >
                            {header}
                        </motion.div>
                    </div>
                )}
                <div className="max-w-7xl mx-auto px-6 lg:px-12">
                    {children}
                </div>
            </main>

            {/* Mobile Bottom Tab Bar — quick one-tap access without opening the drawer */}
            <nav className="fixed inset-x-0 bottom-0 z-40 flex border-t border-zinc-200 bg-white/95 backdrop-blur-xl pb-[env(safe-area-inset-bottom)] lg:hidden">
                {[
                    { href: route('dashboard'), name: 'dashboard', label: 'Home', icon: FaChartPie },
                    { href: route('services.index'), name: 'services.index', label: 'Services', icon: FaBox },
                    { href: route('orders.index'), name: 'orders.index', label: 'Orders', icon: FaRocket },
                    { href: route('wallet.index'), name: 'wallet.index', label: 'Wallet', icon: FaWallet },
                ].map((link) => {
                    const active = route().current(link.name);
                    return (
                        <Link
                            key={link.name}
                            href={link.href}
                            className={`flex flex-1 flex-col items-center gap-1 py-2.5 text-[10px] font-black uppercase tracking-wide transition-colors ${active ? 'text-emerald-600' : 'text-zinc-400'}`}
                        >
                            <span className={`flex h-8 w-11 items-center justify-center rounded-xl ${active ? 'bg-emerald-50' : ''}`}>
                                <link.icon className="text-base" />
                            </span>
                            {link.label}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setIsMobileMenuOpen(true)}
                    className="flex flex-1 flex-col items-center gap-1 py-2.5 text-[10px] font-black uppercase tracking-wide text-zinc-400 transition-colors"
                >
                    <span className="flex h-8 w-11 items-center justify-center rounded-xl">
                        <FaBars className="text-base" />
                    </span>
                    More
                </button>
            </nav>

            {/* Global Footer */}
            <footer className="bg-white border-t border-zinc-200 pt-16 pb-12">
                <div className="max-w-7xl mx-auto px-6 lg:px-12 grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                    <div className="col-span-2 space-y-6">
                        <Link href="/" className="flex items-center gap-3 group">
                            <div className="h-10 w-10 rounded-2xl bg-zinc-900 flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                <span className="text-white font-black text-xl">Z</span>
                            </div>
                            <span className="text-xl font-black text-zinc-900 tracking-tighter">Zimbo<span className="text-emerald-500">Socials</span></span>
                        </Link>
                        <p className="text-zinc-500 text-sm font-medium leading-relaxed max-w-sm">
                            The premium hub for digital growth in Zimbabwe. Elevate your social presence with elite-level marketing solutions.
                        </p>
                    </div>
                    <div className="space-y-6">
                        <h4 className="text-[10px] font-black uppercase tracking-widest text-zinc-400">Resources</h4>
                        <ul className="space-y-4">
                            <li><Link href={route('marketing.help')} className="text-sm font-bold text-zinc-600 hover:text-emerald-500 transition-colors">Help Center</Link></li>
                            <li><Link href={route('developer.api')} className="text-sm font-bold text-zinc-600 hover:text-emerald-500 transition-colors">API Documentation</Link></li>
                        </ul>
                    </div>
                    <div className="space-y-6">
                        <h4 className="text-[10px] font-black uppercase tracking-widest text-zinc-400">Legal</h4>
                        <ul className="space-y-4">
                            <li><Link href={route('marketing.privacy')} className="text-sm font-bold text-zinc-600 hover:text-emerald-500 transition-colors">Privacy Policy</Link></li>
                            <li><Link href={route('marketing.terms')} className="text-sm font-bold text-zinc-600 hover:text-emerald-500 transition-colors">Terms of Service</Link></li>
                        </ul>
                    </div>
                </div>
                <div className="max-w-7xl mx-auto px-6 lg:px-12 pt-12 border-t border-zinc-100 flex flex-col md:flex-row items-center justify-between gap-6">
                    <p className="text-xs font-black text-zinc-400 uppercase tracking-widest">
                        © {new Date().getFullYear()} ZimboSocials Terminal. All Rights Reserved.
                    </p>
                    <div className="flex items-center gap-6">
                        <div className="flex items-center gap-2 text-xs font-bold text-zinc-500">
                            <span className="h-2 w-2 rounded-full bg-emerald-500" /> System Online
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}

