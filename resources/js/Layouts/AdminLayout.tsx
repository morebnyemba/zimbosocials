import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';
import { 
    LayoutDashboard, 
    Users, 
    Settings, 
    Box, 
    ShoppingCart, 
    CreditCard, 
    BarChart3, 
    Ticket, 
    MessageSquare, 
    Plug,
    Megaphone,
    Zap,
    UserCheck,
    FileText,
    Menu,
    X,
    LogOut,
    ChevronRight,
    Search,
    Bell,
    PenTool,
    Languages,
    Bot
} from 'lucide-react';
import { cn } from '@/lib/utils';

export default function AdminLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const flash = (usePage().props as any).flash as { success?: string; error?: string; info?: string } | undefined;
        const { locale, t } = useTranslation();
        const langs = [
            { code: 'sn', label: t('shona') },
            { code: 'nd', label: t('ndebele') },
            { code: 'en', label: t('english') },
        ];

    const navLinks = [
        { href: route('admin.dashboard'), name: 'admin.dashboard', label: 'Dashboard', icon: LayoutDashboard },
        { href: route('admin.users.index'), name: 'admin.users.*', label: 'Users', icon: Users },
        { href: route('admin.marketers.index'), name: 'admin.marketers.*', label: 'Marketers', icon: UserCheck },
        { href: route('admin.contracts.index'), name: 'admin.contracts.*', label: 'Contracts', icon: FileText },
        { href: route('admin.services.index'), name: 'admin.services.*', label: 'Services', icon: Box },
        { href: route('admin.orders.index'), name: 'admin.orders.index', label: 'Orders', icon: ShoppingCart },
        { href: route('admin.transactions.index'), name: 'admin.transactions.index', label: 'Transactions', icon: CreditCard },
        { href: route('admin.revenue'), name: 'admin.revenue', label: 'Revenue', icon: BarChart3 },
        { href: route('admin.tickets.index'), name: 'admin.tickets.index', label: 'Tickets', icon: Ticket },
        { href: route('admin.payment-details.index'), name: 'admin.payment-details.index', label: 'Payment Methods', icon: CreditCard },
        { href: route('admin.upstream-providers.index'), name: 'admin.upstream-providers.index', label: 'API Providers', icon: Plug },
        { href: route('admin.whatsapp.templates'), name: 'admin.whatsapp.templates', label: 'WhatsApp', icon: MessageSquare },
        { href: route('admin.whatsapp.conversations'), name: 'admin.whatsapp.conversations', label: 'WA Assistant', icon: Bot },
        { href: route('admin.campaigns.index'), name: 'admin.campaigns.*', label: 'Campaigns', icon: Megaphone },
        { href: route('admin.seo.index'), name: 'admin.seo.index', label: 'AI SEO', icon: PenTool },
        { href: route('admin.translations.index'), name: 'admin.translations.index', label: t('translation_review'), icon: Languages },
        { href: route('admin.settings.index'), name: 'admin.settings.index', label: 'System Settings', icon: Settings },
    ];

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col">
            {(usePage().props.auth as any).is_impersonating && (
                <div className="bg-amber-600 text-white px-4 py-2 flex items-center justify-between shadow-lg sticky top-0 z-[100]">
                    <div className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.248-8.25-3.286zm0 13.036h.008v.008H12v-.008z" />
                        </svg>
                        Admin Impersonation active
                    </div>
                    <Link 
                        href={route('admin.users.impersonate.leave')} 
                        method="post" 
                        as="button"
                        className="bg-white text-amber-600 px-4 py-1.5 rounded-lg text-xs font-black hover:bg-amber-50 transition-colors shadow-sm uppercase"
                    >
                        Exit Impersonation
                    </Link>
                </div>
            )}
            <div className="flex-1 flex">
            {/* Mobile sidebar backdrop */}
            {sidebarOpen && (
                <div 
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside className={cn(
                "fixed inset-y-0 left-0 z-50 w-64 bg-brand-green text-white transition-transform duration-300 lg:static lg:translate-x-0 flex flex-col",
                sidebarOpen ? "translate-x-0" : "-translate-x-full"
            )}>
                <div className="flex h-16 items-center justify-between px-6 border-b border-white/10">
                    <Link href="/admin/dashboard" className="flex items-center gap-2">
                        <span className="text-xl font-bold tracking-tight">Zimbo<span className="text-brand-orange">Admin</span></span>
                    </Link>
                    <button onClick={() => setSidebarOpen(false)} className="lg:hidden text-white/70 hover:text-white">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                    <p className="px-3 text-xs font-semibold uppercase tracking-wider text-white/50 mb-2">Management</p>
                    {navLinks.map((link) => {
                        const Icon = link.icon;
                        const isActive = route().current(link.name);
                        return (
                            <Link
                                key={link.name}
                                href={link.href}
                                className={cn(
                                    "flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors",
                                    isActive 
                                        ? "bg-brand-orange text-white" 
                                        : "hover:bg-white/10 hover:text-white text-white/80"
                                )}
                            >
                                <Icon className={cn("h-5 w-5", isActive ? "text-white" : "text-white/70")} />
                                {link.label}
                            </Link>
                        );
                    })}
                </div>

                <div className="p-4 border-t border-white/10 bg-black/10">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="h-10 w-10 rounded-full bg-brand-orange flex items-center justify-center text-white font-bold">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="overflow-hidden">
                            <p className="text-sm font-medium text-white truncate">{user.name}</p>
                            <p className="text-xs text-white/60 truncate">{user.email}</p>
                        </div>
                    </div>
                    <Link
                        href={route('dashboard')}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
                    >
                        Customer Dashboard
                    </Link>
                    <Link
                        href={route('marketer.dashboard')}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors mt-1"
                    >
                        Partner Dashboard
                    </Link>
                    <Link
                        href={route('settings.index')}
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors mt-1"
                    >
                        Profile Settings
                    </Link>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-300 hover:text-red-200 hover:bg-red-400/20 rounded-lg transition-colors mt-1"
                    >
                        <LogOut className="h-4 w-4" />
                        Sign Out
                    </Link>
                        <div className="mt-2 rounded-md bg-black/20 p-2">
                            <p className="mb-1 text-[10px] font-black uppercase tracking-wide text-white/60">{t('language')}</p>
                            <select
                                aria-label="Language"
                                value={locale || 'sn'}
                                onChange={(e) => router.post(route('locale.switch'), { locale: e.target.value }, { preserveScroll: true })}
                                className="w-full rounded-md border border-white/10 bg-white/10 px-2 py-1.5 text-[11px] font-black text-white outline-none"
                            >
                                {langs.map((lang) => (
                                    <option key={lang.code} value={lang.code} className="text-zinc-900">
                                        {lang.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                </div>
            </aside>

            {/* Main Content */}
            <div className="flex-1 flex flex-col min-h-screen overflow-hidden">
                {/* Mobile Header */}
                <header className="lg:hidden h-16 bg-white border-b border-gray-200 flex items-center px-4">
                    <button 
                        onClick={() => setSidebarOpen(true)}
                        className="p-2 text-gray-600 hover:bg-gray-100 rounded-lg"
                    >
                        <Menu className="h-5 w-5" />
                    </button>
                    <span className="ml-4 font-semibold text-gray-900">Admin Panel</span>
                </header>

                {(flash?.success || flash?.error || flash?.info) && (
                    <div className="px-4 md:px-6 lg:px-8 pt-4">
                        {flash?.success && (
                            <div className="rounded-md border border-brand-green/20 bg-brand-green/10 px-4 py-3 text-sm text-brand-green">{flash.success}</div>
                        )}
                        {flash?.error && (
                            <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{flash.error}</div>
                        )}
                        {flash?.info && (
                            <div className="rounded-md border border-brand-orange/20 bg-brand-orange/10 px-4 py-3 text-sm text-brand-orange">{flash.info}</div>
                        )}
                    </div>
                )}

                {header && (
                    <div className="px-4 md:px-6 lg:px-8 py-4">
                        {header}
                    </div>
                )}

                <main className="flex-1 overflow-y-auto px-4 pb-8 md:px-6 lg:px-8">
                    {children}
                </main>
            </div>
        </div>
    </div>
);
}
