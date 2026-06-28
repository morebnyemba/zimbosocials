import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { Lock, Unlock, TrendingUp, Wallet, ShoppingCart, CheckCircle, ExternalLink, User, Headphones, Link2 } from 'lucide-react';

interface Service {
    id: number;
    name: string;
    min_qty: number;
    max_qty: number;
    rate: number;
}

interface Requirement {
    key: string;
    label: string;
    target: number | null;
    current: number;
    order_quantity: number;
    stat_quantity: number;
    progress: number;
    gap: number | null;
    services: Service[];
}

interface Platform {
    key: string;
    label: string;
    requirements: Record<string, Requirement>;
}

interface Manager {
    name: string;
    email: string;
    phone?: string;
}

interface Props {
    isUnlocked: boolean;
    canUnlock: boolean;
    threshold: number;
    lookbackDays: number;
    activityTotal: number;
    progress: number;
    platforms: Record<string, Platform>;
    handles: {
        youtube_channel_id: string | null;
        facebook_page_id: string | null;
        tiktok_username: string | null;
        instagram_username: string | null;
        x_username: string | null;
    };
    managers: {
        account: Manager | null;
        support: Manager | null;
    };
    hasReachedPlatformThreshold: boolean;
}

export default function Index({
    isUnlocked,
    canUnlock,
    threshold,
    lookbackDays,
    activityTotal,
    progress,
    platforms,
    handles,
    managers,
    hasReachedPlatformThreshold,
}: Props) {
    const { t } = useTranslation();
    const [unlocking, setUnlocking] = useState(false);

    const profileForm = useForm({
        youtube_channel_id: handles.youtube_channel_id ?? '',
        facebook_page_id: handles.facebook_page_id ?? '',
        tiktok_username: handles.tiktok_username ?? '',
        instagram_username: handles.instagram_username ?? '',
        x_username: handles.x_username ?? '',
    });

    const manualForm = useForm({
        platform: '',
        metric_key: '',
        value: '',
    });

    const unlock = () => {
        if (!canUnlock || unlocking) return;
        setUnlocking(true);
        router.post(route('monetizer.unlock'), {}, {
            onFinish: () => setUnlocking(false),
        });
    };

    const submitProfiles = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.post(route('monetizer.profiles.update'), { preserveScroll: true });
    };

    const submitManual = (e: React.FormEvent) => {
        e.preventDefault();
        manualForm.post(route('monetizer.manual-stats.update'), {
            preserveScroll: true,
            onSuccess: () => manualForm.reset(),
        });
    };

    const remaining = Math.max(0, threshold - activityTotal);

    return (
        <AuthenticatedLayout>
            <Head title={t('monetizer_title')} />

            <div className="max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                        {isUnlocked ? <Unlock className="w-6 h-6 text-brand-green" /> : <Lock className="w-6 h-6 text-brand-green" />}
                        {t('monetizer_title')}
                    </h1>
                    <p className="text-gray-500 text-sm mt-1">{t('monetizer_subtitle')}</p>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-widest text-gray-500">{t('monetizer_threshold_label')}</p>
                            <p className="text-2xl font-bold text-gray-900">${threshold.toFixed(2)}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs font-bold uppercase tracking-widest text-gray-500">{t('monetizer_window_label')}</p>
                            <p className="text-2xl font-bold text-gray-900">{lookbackDays} {t('monetizer_days')}</p>
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between text-sm mb-2">
                            <span className="font-medium text-gray-700">{t('monetizer_progress_label')}</span>
                            <span className="font-bold text-brand-green">{progress}%</span>
                        </div>
                        <div className="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-brand-green rounded-full transition-all duration-500"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="p-4 rounded-lg bg-gray-50 flex items-center gap-3">
                            <Wallet className="w-5 h-5 text-brand-green" />
                            <div>
                                <p className="text-xs font-bold uppercase tracking-widest text-gray-500">{t('monetizer_deposits_label')}</p>
                                <p className="text-sm font-semibold text-gray-900">${activityTotal.toFixed(2)}</p>
                            </div>
                        </div>
                        <div className="p-4 rounded-lg bg-gray-50 flex items-center gap-3">
                            <ShoppingCart className="w-5 h-5 text-brand-green" />
                            <div>
                                <p className="text-xs font-bold uppercase tracking-widest text-gray-500">{t('monetizer_remaining_label')}</p>
                                <p className="text-sm font-semibold text-gray-900">${remaining.toFixed(2)}</p>
                            </div>
                        </div>
                    </div>

                    {!isUnlocked && (
                        <button
                            onClick={unlock}
                            disabled={!canUnlock || unlocking}
                            className="w-full flex items-center justify-center gap-2 px-5 py-3 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-60 transition-colors"
                        >
                            {unlocking && <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />}
                            {canUnlock ? t('monetizer_unlock_button') : t('monetizer_keep_earning')}
                        </button>
                    )}

                    {isUnlocked && (
                        <div className="flex items-center gap-2 text-sm font-medium text-brand-green">
                            <CheckCircle className="w-4 h-4" />
                            {t('monetizer_unlocked_message')}
                        </div>
                    )}
                </div>

                {hasReachedPlatformThreshold && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
                        <h2 className="text-lg font-bold text-emerald-900 mb-4 flex items-center gap-2">
                            <User className="w-5 h-5" />
                            {t('monetizer_managers_title')}
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {managers.account && (
                                <div className="p-4 rounded-lg bg-white border border-emerald-100">
                                    <p className="text-xs font-bold uppercase tracking-widest text-emerald-600 mb-1">{t('monetizer_account_manager')}</p>
                                    <p className="font-semibold text-gray-900">{managers.account.name}</p>
                                    <p className="text-sm text-gray-600">{managers.account.email}</p>
                                    {managers.account.phone && <p className="text-sm text-gray-600">{managers.account.phone}</p>}
                                </div>
                            )}
                            {managers.support && (
                                <div className="p-4 rounded-lg bg-white border border-emerald-100">
                                    <p className="text-xs font-bold uppercase tracking-widest text-emerald-600 mb-1">{t('monetizer_support_manager')}</p>
                                    <p className="font-semibold text-gray-900">{managers.support.name}</p>
                                    <p className="text-sm text-gray-600">{managers.support.email}</p>
                                    {managers.support.phone && <p className="text-sm text-gray-600">{managers.support.phone}</p>}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                    <h2 className="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <Link2 className="w-5 h-5 text-brand-green" />
                        {t('monetizer_profiles_title')}
                    </h2>
                    <p className="text-sm text-gray-500">{t('monetizer_profiles_help')}</p>

                    <form onSubmit={submitProfiles} className="grid gap-4 md:grid-cols-2">
                        {([
                            { key: 'youtube_channel_id', label: 'YouTube channel ID or @handle' },
                            { key: 'facebook_page_id', label: 'Facebook page ID' },
                            { key: 'tiktok_username', label: 'TikTok @username' },
                            { key: 'instagram_username', label: 'Instagram @username or account ID' },
                            { key: 'x_username', label: 'X / Twitter @username' },
                        ] as const).map(({ key, label }) => (
                            <div key={key}>
                                <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">{label}</label>
                                <input
                                    type="text"
                                    value={profileForm.data[key]}
                                    onChange={e => profileForm.setData(key, e.target.value)}
                                    className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                                />
                            </div>
                        ))}
                        <div className="md:col-span-2">
                            <button
                                type="submit"
                                disabled={profileForm.processing}
                                className="px-5 py-2.5 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-60 transition-colors"
                            >
                                {profileForm.processing ? t('ai_loading') : t('monetizer_save_profiles')}
                            </button>
                        </div>
                    </form>
                </div>

                <div>
                    <h2 className="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <TrendingUp className="w-5 h-5 text-brand-green" />
                        {t('monetizer_platforms_title')}
                    </h2>

                    <div className="grid gap-4 md:grid-cols-2">
                        {Object.values(platforms).map(platform => (
                            <div
                                key={platform.key}
                                className="relative rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                            >
                                {!isUnlocked && (
                                    <div className="absolute inset-0 z-10 flex flex-col items-center justify-center bg-white/70 backdrop-blur-[2px] p-4 text-center rounded-xl">
                                        <Lock className="w-8 h-8 text-gray-400 mb-2" />
                                        <p className="text-xs font-bold uppercase tracking-widest text-gray-500">
                                            {t('monetizer_unlock_to_access')}
                                        </p>
                                    </div>
                                )}

                                <h3 className="font-bold text-gray-900 mb-4">{platform.label}</h3>

                                <div className="space-y-4">
                                    {Object.values(platform.requirements).map(req => (
                                        <div key={req.key} className="space-y-2">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="font-medium text-gray-700">{req.label}</span>
                                                <span className="text-xs text-gray-500">
                                                    {req.target !== null
                                                        ? `${req.current.toLocaleString()} / ${req.target.toLocaleString()}`
                                                        : req.current.toLocaleString()}
                                                </span>
                                            </div>

                                            {req.target !== null && (
                                                <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                                    <div
                                                        className="h-full bg-brand-green rounded-full transition-all duration-500"
                                                        style={{ width: `${req.progress}%` }}
                                                    />
                                                </div>
                                            )}

                                            <div className="flex items-center gap-3 text-xs text-gray-500">
                                                <span>{t('monetizer_from_orders')}: {req.order_quantity.toLocaleString()}</span>
                                                <span>{t('monetizer_from_stats')}: {req.stat_quantity.toLocaleString()}</span>
                                            </div>

                                            {req.gap !== null && req.gap > 0 && req.services.length > 0 && (
                                                <div className="pt-1">
                                                    <p className="text-xs text-gray-500 mb-2">
                                                        {t('monetizer_need_more', { count: req.gap.toLocaleString() })}
                                                    </p>
                                                    <div className="flex flex-wrap gap-2">
                                                        {req.services.slice(0, 2).map(service => (
                                                            <Link
                                                                key={service.id}
                                                                href={route('orders.create', { service_id: service.id })}
                                                                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-brand-green/10 text-brand-green hover:bg-brand-green/20 transition-colors"
                                                            >
                                                                {service.name}
                                                                <ExternalLink className="w-3 h-3" />
                                                            </Link>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {req.target !== null && req.current >= req.target && (
                                                <p className="text-xs font-medium text-brand-green flex items-center gap-1">
                                                    <CheckCircle className="w-3 h-3" />
                                                    {t('monetizer_requirement_met')}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                    <h2 className="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <Headphones className="w-5 h-5 text-brand-green" />
                        {t('monetizer_manual_stats_title')}
                    </h2>
                    <p className="text-sm text-gray-500">{t('monetizer_manual_stats_help')}</p>

                    <form onSubmit={submitManual} className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">{t('monetizer_platform')}</label>
                            <select
                                value={manualForm.data.platform}
                                onChange={e => manualForm.setData('platform', e.target.value)}
                                className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            >
                                <option value="">—</option>
                                {Object.values(platforms).map(p => (
                                    <option key={p.key} value={p.key}>{p.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">{t('monetizer_metric')}</label>
                            <input
                                type="text"
                                value={manualForm.data.metric_key}
                                onChange={e => manualForm.setData('metric_key', e.target.value)}
                                placeholder="e.g. watch_hours"
                                className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">{t('monetizer_value')}</label>
                            <input
                                type="number"
                                min={0}
                                value={manualForm.data.value}
                                onChange={e => manualForm.setData('value', e.target.value)}
                                className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={manualForm.processing}
                            className="px-5 py-2.5 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-60 transition-colors"
                        >
                            {manualForm.processing ? t('ai_loading') : t('monetizer_save_metric')}
                        </button>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
