import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PageProps } from '@/types'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { ArrowRight, BriefcaseBusiness, CheckCircle, CircleDollarSign, ExternalLink, Link2, LifeBuoy, Plus, Trash2, Wallet } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from '@/lib/i18n'

interface Service { name: string; category: string }
interface Order {
    id: number; link: string; quantity: number; charge: number
    status: string; created_at: string; service?: Service
}

interface SocialLink {
    id: number
    platform: string
    handle: string
    profile_url?: string | null
    follower_count: number
    verified: boolean
}

interface ProofSubmission {
    id: number
    proof_url: string
    status: string
    notes?: string | null
}

interface ApprovedApp {
    id: number
    status: string
    created_at: string
    contract: {
        id: number
        title: string
        platform?: string | null
    }
    proof_submissions: ProofSubmission[]
}

interface AvailableContract {
    id: number
    title: string
    platform?: string | null
    description: string
    budget?: number | null
    slots: number
    status: string
    deadline_at?: string | null
    created_at: string
    business: {
        id: number
        name: string
        company_name?: string | null
    }
    applications: Array<{
        id: number
        status: string
        created_at: string
    }>
}

interface MyContractApplication {
    id: number
    status: string
    created_at: string
    contract: {
        id: number
        title: string
        platform?: string | null
        status: string
        business: {
            id: number
            name: string
            company_name?: string | null
        }
    }
}

interface MarketerReviewItem {
    id: number;
    rating: number;
    comment?: string | null;
    created_at: string;
    reviewer?: { id: number; name: string; company_name?: string } | null;
    contract?: { id: number; title: string } | null;
}

interface Props extends PageProps {
    stats: {
        total_orders: number
        active_orders: number
        total_spend: number
        balance: number
        contract_earnings: number
        withdrawn: number
        client_orders_this_month: number
        avg_rating: number
        review_count: number
    }
    recent_orders: Order[]
    available_contracts: AvailableContract[]
    my_contract_applications: MyContractApplication[]
    contract_stats: {
        open_contracts_available: number
        my_pending_applications: number
        my_approved_contracts: number
    }
    social_links: SocialLink[]
    approved_apps: ApprovedApp[]
    recent_reviews: MarketerReviewItem[]
}

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    failed: 'bg-red-100 text-red-700',
}

const applicationStatusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    denied: 'bg-rose-100 text-rose-700',
    ignored: 'bg-slate-100 text-slate-600',
}

const PLATFORMS = ['instagram', 'tiktok', 'youtube', 'twitter', 'facebook', 'telegram']

const platformColors: Record<string, string> = {
    instagram: 'bg-pink-100 text-pink-700',
    tiktok: 'bg-slate-100 text-slate-700',
    youtube: 'bg-red-100 text-red-700',
    twitter: 'bg-sky-100 text-sky-700',
    facebook: 'bg-blue-100 text-blue-700',
    telegram: 'bg-cyan-100 text-cyan-700',
}

function formatCurrency(value: number) {
    return `$${Number(value ?? 0).toFixed(2)}`
}

function SocialLinksPanel({ social_links }: { social_links: SocialLink[] }) {
    const { t } = useTranslation()
    const [showForm, setShowForm] = useState(false)
    const { data, setData, post, processing, errors, reset } = useForm({
        platform: 'instagram',
        handle: '',
        profile_url: '',
        follower_count: '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('social-links.store'), {
            preserveScroll: true,
            onSuccess: () => { reset(); setShowForm(false) },
        })
    }

    function remove(id: number) {
        router.delete(route('social-links.destroy', id), { preserveScroll: true })
    }

    return (
        <div className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="flex items-center gap-2 text-lg font-semibold text-slate-900">
                        <Link2 className="h-5 w-5 text-slate-400" />
                        {t('my_managed_social_accounts')}
                    </h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {t('managed_social_accounts_detail')}
                    </p>
                </div>
                <button
                    onClick={() => setShowForm(v => !v)}
                    className="shrink-0 rounded-full bg-sky-600 px-4 py-2 text-xs font-semibold text-white hover:bg-sky-700"
                >
                    {showForm ? t('cancel') : t('add_account')}
                </button>
            </div>

            {social_links.length === 0 && !showForm && (
                <div className="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    {t('no_social_accounts_warning')}
                </div>
            )}

            {showForm && (
                <form onSubmit={submit} className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-5 space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="text-xs font-semibold text-slate-600">{t('platform')}</label>
                            <select
                                value={data.platform}
                                onChange={e => setData('platform', e.target.value)}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-300"
                            >
                                {PLATFORMS.map(p => (
                                    <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>
                                ))}
                            </select>
                            {errors.platform && <p className="mt-1 text-xs text-red-600">{errors.platform}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-slate-600">{t('handle_username')}</label>
                            <input
                                type="text"
                                value={data.handle}
                                onChange={e => setData('handle', e.target.value)}
                                placeholder="@yourhandle"
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-300"
                            />
                            {errors.handle && <p className="mt-1 text-xs text-red-600">{errors.handle}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-slate-600">{t('profile_url_optional')}</label>
                            <input
                                type="url"
                                value={data.profile_url}
                                onChange={e => setData('profile_url', e.target.value)}
                                placeholder="https://instagram.com/yourhandle"
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-300"
                            />
                            {errors.profile_url && <p className="mt-1 text-xs text-red-600">{errors.profile_url}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-semibold text-slate-600">{t('follower_count_approx')}</label>
                            <input
                                type="number"
                                value={data.follower_count}
                                onChange={e => setData('follower_count', e.target.value)}
                                placeholder="e.g. 5000"
                                min={0}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-300"
                            />
                            {errors.follower_count && <p className="mt-1 text-xs text-red-600">{errors.follower_count}</p>}
                        </div>
                    </div>
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-full bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-60"
                    >
                        {t('save_social_account')}
                    </button>
                </form>
            )}

            {social_links.length > 0 && (
                <div className="mt-5 flex flex-wrap gap-3">
                    {social_links.map(link => (
                        <div key={link.id} className="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${platformColors[link.platform] ?? 'bg-slate-100 text-slate-700'}`}>
                                {link.platform}
                            </span>
                            <div>
                                <p className="text-sm font-semibold text-slate-800">{link.handle}</p>
                                {link.follower_count > 0 && (
                                    <p className="text-xs text-slate-500">{t('followers_count', { count: link.follower_count.toLocaleString() })}</p>
                                )}
                            </div>
                            {link.verified && (
                                <CheckCircle className="h-4 w-4 text-emerald-500" />
                            )}
                            {link.profile_url && (
                                <a href={link.profile_url} target="_blank" rel="noreferrer" className="text-slate-400 hover:text-sky-600">
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            )}
                            <button
                                onClick={() => remove(link.id)}
                                className="text-slate-300 hover:text-rose-500 transition"
                                title={t('remove')}
                            >
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    )
}

function ProofSubmitPanel({ approved_apps }: { approved_apps: ApprovedApp[] }) {
    const { t } = useTranslation()
    const [activeApp, setActiveApp] = useState<number | null>(null)
    const { data, setData, post, processing, errors, reset } = useForm({
        proof_url: '',
        notes: '',
    })

    if (approved_apps.length === 0) return null

    const needsProof = approved_apps.filter(app => {
        const latest = app.proof_submissions[0]
        return !latest || latest.status === 'rejected'
    })

    const allProved = needsProof.length === 0

    function submitProof(e: React.FormEvent, appId: number) {
        e.preventDefault()
        post(route('proof.store', appId), {
            preserveScroll: true,
            onSuccess: () => { reset(); setActiveApp(null) },
        })
    }

    return (
        <div className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-semibold text-slate-900">{t('proof_of_work_approved_contracts')}</h3>
                    <p className="mt-1 text-sm text-slate-500">
                        {t('proof_of_work_detail')}
                    </p>
                </div>
                {!allProved && (
                    <span className="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                        {t('pending_count', { count: needsProof.length })}
                    </span>
                )}
                {allProved && (
                    <span className="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                        {t('all_submitted')}
                    </span>
                )}
            </div>

            <div className="mt-5 space-y-3">
                {approved_apps.map(app => {
                    const latest = app.proof_submissions[0]
                    const proofStatus = latest?.status ?? null
                    const isOpen = activeApp === app.id

                    return (
                        <div key={app.id} className="rounded-2xl border border-slate-200 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="text-sm font-semibold text-slate-900">{app.contract.title}</p>
                                    {app.contract.platform && (
                                        <p className="text-xs text-slate-500 capitalize">{app.contract.platform}</p>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    {proofStatus === 'approved' && (
                                        <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">{t('proof_approved')}</span>
                                    )}
                                    {proofStatus === 'pending' && (
                                        <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{t('proof_pending_review')}</span>
                                    )}
                                    {proofStatus === 'rejected' && (
                                        <span className="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">{t('proof_rejected_resubmit')}</span>
                                    )}
                                    {!proofStatus && (
                                        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{t('no_proof_yet')}</span>
                                    )}
                                    {(proofStatus === null || proofStatus === 'rejected') && (
                                        <button
                                            onClick={() => setActiveApp(isOpen ? null : app.id)}
                                            className="rounded-full bg-sky-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-sky-700"
                                        >
                                            {isOpen ? t('cancel') : t('submit_proof')}
                                        </button>
                                    )}
                                    {proofStatus === 'pending' && latest?.proof_url && (
                                        <a href={latest.proof_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-sky-600 hover:underline">
                                            {t('view')} <ExternalLink className="h-3 w-3" />
                                        </a>
                                    )}
                                </div>
                            </div>

                            {isOpen && (
                                <form onSubmit={e => submitProof(e, app.id)} className="mt-4 space-y-3">
                                    <div>
                                        <label className="text-xs font-semibold text-slate-600">{t('proof_link_label')}</label>
                                        <input
                                            type="url"
                                            value={data.proof_url}
                                            onChange={e => setData('proof_url', e.target.value)}
                                            placeholder={t('proof_link_placeholder')}
                                            className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-300"
                                        />
                                        {errors.proof_url && <p className="mt-1 text-xs text-red-600">{errors.proof_url}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-600">{t('notes_optional')}</label>
                                        <textarea
                                            value={data.notes}
                                            onChange={e => setData('notes', e.target.value)}
                                            rows={2}
                                            placeholder={t('notes_for_admin_placeholder')}
                                            className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-300"
                                        />
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                                    >
                                        {t('submit_proof')}
                                    </button>
                                </form>
                            )}
                        </div>
                    )
                })}
            </div>
        </div>
    )
}

export default function MarketerDashboard({
    auth,
    stats,
    recent_orders,
    available_contracts,
    my_contract_applications,
    contract_stats,
    social_links,
    approved_apps,
    recent_reviews,
}: Props) {
    const { t } = useTranslation()

    function applyToContract(contractId: number) {
        router.post(route('contracts.apply', contractId), {}, { preserveScroll: true })
    }

    const statCards = [
        { label: t('wallet_balance'), value: formatCurrency(stats.balance), detail: t('marketer_wallet_ready'), icon: Wallet, accent: 'from-emerald-100 to-lime-50 text-emerald-700' },
        { label: t('total_orders'), value: stats.total_orders, detail: t('marketer_total_orders_detail'), icon: BriefcaseBusiness, accent: 'from-sky-100 to-cyan-50 text-sky-700' },
        { label: t('active_orders'), value: stats.active_orders, detail: t('marketer_active_orders_detail'), icon: CircleDollarSign, accent: 'from-amber-100 to-orange-50 text-amber-700' },
        { label: t('contract_earnings'), value: formatCurrency(stats.contract_earnings), detail: t('marketer_contract_earnings_detail'), icon: CircleDollarSign, accent: 'from-violet-100 to-fuchsia-50 text-violet-700' },
        { label: t('withdrawn'), value: formatCurrency(stats.withdrawn), detail: t('marketer_withdrawn_detail'), icon: Wallet, accent: 'from-slate-100 to-slate-50 text-slate-700' },
        { label: t('available_contracts'), value: contract_stats.open_contracts_available, detail: t('marketer_available_contracts_detail'), icon: BriefcaseBusiness, accent: 'from-indigo-100 to-blue-50 text-indigo-700' },
        { label: t('orders_this_month'), value: stats.client_orders_this_month, detail: t('marketer_orders_this_month_detail'), icon: Plus, accent: 'from-teal-100 to-cyan-50 text-teal-700' },
    ]

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center gap-2">
                <h2 className="text-xl font-bold text-slate-900">{t('dashboard')}</h2>
                <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700 capitalize">
                    {auth.user.role ?? 'marketer'}
                </span>
            </div>
        }>
            <Head title={t('dashboard')} />

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                {(auth.user as any).marketer_status !== 'approved' && (
                    <div className={`p-4 rounded-2xl border flex items-center gap-4 ${
                        (auth.user as any).marketer_status === 'rejected'
                        ? 'bg-red-50 border-red-200 text-red-800'
                        : 'bg-amber-50 border-amber-200 text-amber-800'
                    }`}>
                        <div className={`h-10 w-10 rounded-full flex items-center justify-center shrink-0 ${
                            (auth.user as any).marketer_status === 'rejected' ? 'bg-red-100' : 'bg-amber-100'
                        }`}>
                            <LifeBuoy className="w-5 h-5" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-sm font-bold uppercase tracking-wider">
                                {(auth.user as any).marketer_status === 'rejected' ? t('account_rejected') : t('account_pending')}
                            </h3>
                            <p className="text-sm opacity-90">
                                {(auth.user as any).marketer_status === 'rejected' ? t('marketer_account_rejected_detail') : t('marketer_account_pending_detail')}
                            </p>
                        </div>
                    </div>
                )}

                <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.22),_transparent_30%),linear-gradient(135deg,#082f49_0%,#0f172a_45%,#172554_100%)] text-white shadow-xl shadow-slate-900/10">
                    <div className="grid gap-8 px-5 py-6 sm:px-8 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,0.95fr)] lg:px-10 lg:py-10">
                        <div className="min-w-0 space-y-5">
                            <span className="inline-flex w-fit items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-sky-100">
                                {t('partner_workspace')}
                            </span>
                            <div className="space-y-3">
                                <h1 className="max-w-3xl break-words text-3xl font-semibold tracking-tight sm:text-4xl">
                                    {t('marketer_hero_title')}
                                </h1>
                                <p className="max-w-2xl break-words text-sm leading-6 text-slate-300 sm:text-base">
                                    {t('marketer_hero_subtitle')}
                                </p>
                            </div>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <Link href={route('orders.create')} className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-sky-300 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-200">
                                    <Plus className="h-4 w-4" />
                                    {t('new_order')}
                                </Link>
                                <Link href={route('wallet.index')} className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/15 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">
                                    <Wallet className="h-4 w-4" />
                                    {t('wallet')}
                                </Link>
                                <Link href={route('tickets.index')} className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                                    <LifeBuoy className="h-4 w-4" />
                                    {t('support')}
                                </Link>
                            </div>
                        </div>

                        <div className="min-w-0 rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur">
                            <p className="text-sm font-medium text-slate-300">{t('wallet_balance')}</p>
                            <p className="mt-2 break-all text-3xl font-semibold tracking-tight sm:text-4xl">{formatCurrency(stats.balance)}</p>
                            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                <div className="min-w-0 rounded-2xl border border-white/10 bg-slate-950/20 p-4">
                                    <p className="truncate text-xs uppercase tracking-[0.2em] text-slate-400">{t('approved_contracts')}</p>
                                    <p className="mt-2 text-2xl font-semibold">{contract_stats.my_approved_contracts}</p>
                                </div>
                                <div className="min-w-0 rounded-2xl border border-white/10 bg-slate-950/20 p-4">
                                    <p className="truncate text-xs uppercase tracking-[0.2em] text-slate-400">{t('social_accounts')}</p>
                                    <p className="mt-2 text-2xl font-semibold">{social_links.length}</p>
                                </div>
                            </div>
                            <Link href={route('services.index')} className="mt-5 inline-flex items-center gap-2 text-sm font-medium text-sky-200 transition hover:text-white">
                                {t('browse_services')}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </section>

                <SocialLinksPanel social_links={social_links} />

                {approved_apps.length > 0 && (
                    <ProofSubmitPanel approved_apps={approved_apps} />
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((card) => {
                        const Icon = card.icon
                        return (
                            <div key={card.label} className="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/70">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">{card.label}</p>
                                        <p className="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{card.value}</p>
                                    </div>
                                    <div className={`rounded-2xl bg-gradient-to-br p-3 ${card.accent}`}>
                                        <Icon className="h-5 w-5" />
                                    </div>
                                </div>
                                <p className="mt-4 text-sm leading-6 text-slate-500">{card.detail}</p>
                            </div>
                        )
                    })}
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.95fr)]">
                    {/* Rating summary widget */}
                    {stats.review_count > 0 && (
                        <div className="xl:col-span-2 rounded-[24px] border border-amber-100 bg-gradient-to-br from-amber-50 to-white p-6 shadow-sm flex flex-wrap items-center justify-between gap-6">
                            <div className="flex items-center gap-5">
                                <div className="flex flex-col items-center justify-center h-16 w-16 rounded-2xl bg-amber-400/10 border border-amber-200">
                                    <span className="text-2xl font-black text-amber-500">{stats.avg_rating.toFixed(1)}</span>
                                    <span className="text-[9px] font-black text-amber-400 uppercase tracking-widest">/ 5</span>
                                </div>
                                <div>
                                    <p className="text-base font-black text-slate-900">Your Rating</p>
                                    <div className="flex items-center gap-0.5 mt-1">
                                        {[1,2,3,4,5].map(s => (
                                            <svg key={s} className={`w-4 h-4 ${s <= Math.round(stats.avg_rating) ? 'text-amber-400' : 'text-slate-200'}`} viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        ))}
                                    </div>
                                    <p className="text-xs text-slate-400 mt-1 font-medium">Based on {stats.review_count} review{stats.review_count !== 1 ? 's' : ''} from businesses</p>
                                </div>
                            </div>
                            {recent_reviews.length > 0 && (
                                <div className="flex flex-col gap-2 max-w-sm w-full">
                                    {recent_reviews.slice(0, 3).map(review => (
                                        <div key={review.id} className="flex items-start gap-3 rounded-xl bg-white border border-amber-100 p-3">
                                            <div className="flex-shrink-0 flex gap-0.5 mt-0.5">
                                                {[1,2,3,4,5].map(s => (
                                                    <svg key={s} className={`w-3 h-3 ${s <= review.rating ? 'text-amber-400' : 'text-slate-200'}`} viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                ))}
                                            </div>
                                            <div className="min-w-0">
                                                {review.comment && <p className="text-xs text-slate-600 font-medium italic truncate">"{review.comment}"</p>}
                                                <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">
                                                    {review.reviewer?.name ?? 'Business'}{review.contract ? ` · ${review.contract.title}` : ''}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                    <div className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/70">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">{t('available_contracts')}</h3>
                                <p className="text-sm text-slate-500">{t('marketer_contracts_subtitle')}</p>
                            </div>
                            <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">
                                {contract_stats.my_pending_applications} {t('pending')}
                            </span>
                        </div>

                        {social_links.length === 0 && (
                            <div className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                {t('marketer_add_social_warning')}
                            </div>
                        )}

                        <div className="mt-5 space-y-3">
                            {available_contracts.length === 0 && (
                                <p className="text-sm text-slate-500">{t('no_open_contracts')}</p>
                            )}

                            {available_contracts.map((contract) => {
                                const myApplication = contract.applications[0]
                                const hasApplied = !!myApplication

                                return (
                                    <div key={contract.id} className="rounded-2xl border border-slate-200 p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-semibold text-slate-900">{contract.title}</p>
                                            {hasApplied ? (
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${applicationStatusColors[myApplication.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                                    {myApplication.status}
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">{t('open')}</span>
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {contract.business.company_name || contract.business.name}
                                            {contract.platform ? ` · ${contract.platform}` : ''}
                                            {contract.budget ? ` · $${Number(contract.budget).toFixed(2)} ${t('budget')}` : ''}
                                        </p>
                                        <p className="mt-2 line-clamp-2 text-sm text-slate-600">{contract.description}</p>

                                        {!hasApplied && (
                                            <div className="mt-3">
                                                <button
                                                    type="button"
                                                    onClick={() => applyToContract(contract.id)}
                                                    className="rounded-full bg-sky-600 px-4 py-2 text-xs font-semibold text-white hover:bg-sky-700"
                                                >
                                                    {t('apply')}
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )
                            })}
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/70">
                        <h3 className="text-lg font-semibold text-slate-900">{t('my_contract_applications')}</h3>
                        <p className="mt-1 text-sm text-slate-500">{t('marketer_applications_subtitle')}</p>

                        <div className="mt-5 space-y-3">
                            {my_contract_applications.length === 0 && (
                                <p className="text-sm text-slate-500">{t('no_contract_applications')}</p>
                            )}
                            {my_contract_applications.map((application) => (
                                <div key={application.id} className="rounded-2xl border border-slate-200 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-semibold text-slate-900">{application.contract.title}</p>
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${applicationStatusColors[application.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                            {application.status}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {application.contract.business.company_name || application.contract.business.name}
                                        {application.contract.platform ? ` · ${application.contract.platform}` : ''}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,0.9fr)]">
                    <div className="rounded-[24px] border border-slate-200 bg-white shadow-sm shadow-slate-200/70">
                        <div className="flex items-center justify-between px-6 pt-6 pb-4">
                            <div>
                                <h3 className="text-lg font-semibold text-slate-900">{t('recent_orders')}</h3>
                                <p className="text-sm text-slate-500">{t('marketer_recent_orders_subtitle')}</p>
                            </div>
                            <Link href={route('orders.index')} className="text-sm font-medium text-sky-700 transition hover:text-sky-900">{t('view_all')}</Link>
                        </div>
                        <div className="divide-y divide-slate-100">
                            {recent_orders.length === 0 && (
                                <p className="px-6 py-8 text-sm text-slate-500">{t('no_orders')}</p>
                            )}
                            {recent_orders.map((order) => (
                                <Link key={order.id} href={route('orders.show', order.id)} className="flex flex-col gap-3 px-6 py-4 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="space-y-1">
                                        <p className="text-sm font-semibold text-slate-900">{order.service?.name ?? `Order #${order.id}`}</p>
                                        <p className="text-xs text-slate-500">Qty {order.quantity} · {formatCurrency(order.charge)} · {new Date(order.created_at).toLocaleDateString()}</p>
                                        <p className="max-w-xl truncate text-xs text-slate-400">{order.link}</p>
                                    </div>
                                    <span className={`inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold ${statusColors[order.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                        {order.status}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-[24px] border border-slate-200 bg-slate-950 p-6 text-white shadow-lg shadow-slate-900/10">
                            <p className="text-sm uppercase tracking-[0.22em] text-slate-400">{t('how_it_works')}</p>
                            <div className="mt-4 space-y-3 text-sm text-slate-300">
                                <p>{t('how_it_works_1')}</p>
                                <p>{t('how_it_works_2')}</p>
                                <p>{t('how_it_works_3')}</p>
                                <p>{t('how_it_works_4')}</p>
                                <p>{t('how_it_works_5')}</p>
                                <Link href={route('wallet.index')} className="inline-flex items-center gap-2 text-sky-300 hover:text-white">
                                    {t('open_wallet_withdrawals')}
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    )
}

