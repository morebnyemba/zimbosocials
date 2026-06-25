import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    Megaphone, Plus, X, ChevronDown, ChevronUp,
    Clock, CheckCircle2, XCircle, Loader2, AlertTriangle, Sparkles
} from 'lucide-react';

type Campaign = {
    id: number;
    name: string;
    status: 'queued' | 'running' | 'completed' | 'failed';
    channels: string[];
    subjects: { en: string; sn?: string; nd?: string };
    bodies: { en: string; sn?: string; nd?: string };
    filters: { roles: string[]; account_types: string[] };
    recipients_total: number;
    sent_email: number;
    sent_whatsapp: number;
    sent_in_app: number;
    error_message?: string | null;
    started_at?: string | null;
    created_at: string;
    completed_at?: string | null;
    creator?: { name: string; email: string };
};

type Props = {
    campaigns: {
        data: Campaign[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
        current_page: number;
        last_page: number;
    };
};

const STATUS_CONFIG: Record<Campaign['status'], { label: string; color: string; Icon: React.ElementType }> = {
    queued:    { label: 'Queued',    color: 'bg-amber-100 text-amber-700 border-amber-200',      Icon: Clock },
    running:   { label: 'Running',   color: 'bg-blue-100 text-blue-700 border-blue-200',          Icon: Loader2 },
    completed: { label: 'Completed', color: 'bg-emerald-100 text-emerald-700 border-emerald-200', Icon: CheckCircle2 },
    failed:    { label: 'Failed',    color: 'bg-red-100 text-red-700 border-red-200',             Icon: XCircle },
};

const CHANNELS = [
    { value: 'email',    label: 'Email' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'in_app',   label: 'In-App' },
];

const ROLES = [
    { value: 'all',      label: 'All Roles' },
    { value: 'user',     label: 'User' },
    { value: 'marketer', label: 'Marketer' },
    { value: 'reseller', label: 'Reseller' },
    { value: 'admin',    label: 'Admin' },
];

const ACCOUNT_TYPES = [
    { value: 'all',        label: 'All Types' },
    { value: 'individual', label: 'Individual' },
    { value: 'business',   label: 'Business' },
    { value: 'marketer',   label: 'Marketer' },
];

function CampaignRow({ campaign }: { campaign: Campaign }) {
    const [expanded, setExpanded] = useState(false);
    const cfg = STATUS_CONFIG[campaign.status] ?? STATUS_CONFIG.queued;
    const StatusIcon = cfg.Icon;

    return (
        <div className="border border-gray-200 rounded-xl bg-white shadow-sm overflow-hidden">
            <button
                onClick={() => setExpanded(e => !e)}
                className="w-full flex items-center gap-4 p-4 text-left hover:bg-gray-50 transition-colors"
            >
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className="font-semibold text-gray-900 text-sm">{campaign.name}</span>
                        <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border ${cfg.color}`}>
                            <StatusIcon className={`w-3 h-3 ${campaign.status === 'running' ? 'animate-spin' : ''}`} />
                            {cfg.label}
                        </span>
                        {campaign.channels?.map(ch => (
                            <span key={ch} className="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200 uppercase tracking-wide">
                                {ch === 'in_app' ? 'In-App' : ch}
                            </span>
                        ))}
                    </div>
                    <div className="mt-1 flex items-center gap-4 text-xs text-gray-500 flex-wrap">
                        <span>{campaign.recipients_total} recipients</span>
                        {campaign.sent_email > 0 && <span>✉ {campaign.sent_email}</span>}
                        {campaign.sent_whatsapp > 0 && <span>📱 {campaign.sent_whatsapp}</span>}
                        {campaign.sent_in_app > 0 && <span>🔔 {campaign.sent_in_app}</span>}
                        <span className="text-gray-400">
                            {new Date(campaign.created_at).toLocaleDateString()}
                            {campaign.creator ? ` · ${campaign.creator.name}` : ''}
                        </span>
                    </div>
                </div>
                {expanded
                    ? <ChevronUp className="w-4 h-4 text-gray-400 shrink-0" />
                    : <ChevronDown className="w-4 h-4 text-gray-400 shrink-0" />}
            </button>

            <AnimatePresence>
                {expanded && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="overflow-hidden border-t border-gray-100"
                    >
                        <div className="p-4 space-y-4 bg-gray-50">
                            {campaign.error_message && (
                                <div className="flex items-start gap-2 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                                    <AlertTriangle className="w-4 h-4 mt-0.5 shrink-0" />
                                    <span>{campaign.error_message}</span>
                                </div>
                            )}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                {(['en', 'sn', 'nd'] as const).map(locale => {
                                    const subject = campaign.subjects?.[locale];
                                    const body = campaign.bodies?.[locale];
                                    if (!subject && !body) return null;
                                    return (
                                        <div key={locale} className="bg-white border border-gray-200 rounded-lg p-3 text-xs space-y-1">
                                            <div className="font-bold uppercase tracking-widest text-gray-400 text-[10px]">
                                                {locale === 'en' ? 'English' : locale === 'sn' ? 'Shona' : 'Ndebele'}
                                            </div>
                                            <div className="font-semibold text-gray-800">{subject}</div>
                                            <div className="text-gray-600 line-clamp-3">{body}</div>
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="flex flex-wrap gap-3 text-xs text-gray-600">
                                <span><strong>Roles:</strong> {campaign.filters?.roles?.join(', ') || 'all'}</span>
                                <span><strong>Account Types:</strong> {campaign.filters?.account_types?.join(', ') || 'all'}</span>
                                {campaign.started_at && <span><strong>Started:</strong> {new Date(campaign.started_at).toLocaleString()}</span>}
                                {campaign.completed_at && <span><strong>Completed:</strong> {new Date(campaign.completed_at).toLocaleString()}</span>}
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

type CampaignForm = {
    name: string;
    subject_en: string;
    body_en: string;
    subject_sn: string;
    body_sn: string;
    subject_nd: string;
    body_nd: string;
    channels: string[];
    roles: string[];
    account_types: string[];
};

type AiCopyState = {
    brief: string;
    tone: string;
    loading: boolean;
    error: string | null;
};

export default function CampaignsIndex({ campaigns }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [activeLocale, setActiveLocale] = useState<'en' | 'sn' | 'nd'>('en');
    const [ai, setAi] = useState<AiCopyState>({ brief: '', tone: '', loading: false, error: null });

    const { data, setData, post, processing, errors, reset } = useForm<CampaignForm>({
        name: '',
        subject_en: '',
        body_en: '',
        subject_sn: '',
        body_sn: '',
        subject_nd: '',
        body_nd: '',
        channels: ['email'],
        roles: ['all'],
        account_types: ['all'],
    });

    const toggleArray = (field: 'channels' | 'roles' | 'account_types', value: string) => {
        const arr = data[field] as string[];
        setData(field, arr.includes(value) ? arr.filter(v => v !== value) : [...arr, value]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.campaigns.store'), {
            onSuccess: () => {
                reset();
                setAi({ brief: '', tone: '', loading: false, error: null });
                setShowForm(false);
            },
        });
    };

    const generateCopy = async () => {
        setAi(prev => ({ ...prev, loading: true, error: null }));
        try {
            const res = await fetch(route('admin.campaigns.generate-copy'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    brief: ai.brief,
                    channels: data.channels,
                    tone: ai.tone || undefined,
                }),
            });
            const json = await res.json();
            if (!res.ok) {
                setAi(prev => ({ ...prev, loading: false, error: json.message ?? 'Failed to generate copy.' }));
                return;
            }
            setData('name', json.campaign_name ?? '');
            setData('subject_en', json.subject_en ?? '');
            setData('body_en', json.body_en ?? '');
            setData('subject_sn', json.subject_sn ?? '');
            setData('body_sn', json.body_sn ?? '');
            setData('subject_nd', json.subject_nd ?? '');
            setData('body_nd', json.body_nd ?? '');
            setAi(prev => ({ ...prev, loading: false, error: null }));
        } catch (e) {
            setAi(prev => ({ ...prev, loading: false, error: 'Failed to generate copy.' }));
        }
    };

    return (
        <AdminLayout>
            <Head title="Broadcast Campaigns" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                            <Megaphone className="w-6 h-6 text-brand-green" />
                            Broadcast Campaigns
                        </h1>
                        <p className="text-gray-500 text-sm mt-1">{campaigns.total} total campaigns</p>
                    </div>
                    <button
                        onClick={() => setShowForm(true)}
                        className="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 shadow-sm transition-colors"
                    >
                        <Plus className="w-4 h-4" /> New Campaign
                    </button>
                </div>

                {/* Campaign list */}
                {campaigns.data.length === 0 ? (
                    <div className="text-center py-16 text-gray-400">
                        <Megaphone className="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p className="font-medium">No campaigns yet</p>
                        <p className="text-sm mt-1">Create your first broadcast campaign to reach your users.</p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {campaigns.data.map(campaign => (
                            <CampaignRow key={campaign.id} campaign={campaign} />
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {campaigns.last_page > 1 && (
                    <div className="flex justify-center gap-1 py-4">
                        {campaigns.links?.map((link, i) => (
                            <button
                                key={i}
                                onClick={() => link.url && router.get(link.url)}
                                disabled={!link.url}
                                className={`px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
                                    link.active
                                        ? 'bg-brand-green text-white shadow-sm'
                                        : 'text-gray-600 hover:bg-gray-200 disabled:opacity-40'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* New Campaign Modal */}
            <AnimatePresence>
                {showForm && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                        onClick={e => { if (e.target === e.currentTarget) setShowForm(false); }}
                    >
                        <motion.div
                            initial={{ scale: 0.95, opacity: 0, y: 16 }}
                            animate={{ scale: 1, opacity: 1, y: 0 }}
                            exit={{ scale: 0.95, opacity: 0, y: 16 }}
                            className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto"
                        >
                            <div className="flex items-center justify-between p-6 border-b border-gray-100">
                                <h2 className="text-lg font-bold text-gray-900">New Broadcast Campaign</h2>
                                <button onClick={() => setShowForm(false)} className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                    <X className="w-4 h-4 text-gray-500" />
                                </button>
                            </div>

                            <form onSubmit={submit} className="p-6 space-y-6">
                                {/* Campaign Name */}
                                <div>
                                    <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">Campaign Name</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        placeholder="e.g. January Promo"
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                                    />
                                    {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
                                </div>

                                {/* Channels */}
                                <div>
                                    <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Channels</label>
                                    <div className="flex flex-wrap gap-2">
                                        {CHANNELS.map(ch => (
                                            <button
                                                key={ch.value}
                                                type="button"
                                                onClick={() => toggleArray('channels', ch.value)}
                                                className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors ${
                                                    data.channels.includes(ch.value)
                                                        ? 'bg-brand-green text-white border-brand-green'
                                                        : 'bg-white text-gray-600 border-gray-300 hover:border-brand-green/50'
                                                }`}
                                            >
                                                {ch.label}
                                            </button>
                                        ))}
                                    </div>
                                    {errors.channels && <p className="text-xs text-red-600 mt-1">{errors.channels}</p>}
                                </div>

                                {/* Audience Filters */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Roles</label>
                                        <div className="flex flex-wrap gap-1.5">
                                            {ROLES.map(r => (
                                                <button
                                                    key={r.value}
                                                    type="button"
                                                    onClick={() => toggleArray('roles', r.value)}
                                                    className={`px-2.5 py-1 text-xs font-medium rounded-lg border transition-colors ${
                                                        data.roles.includes(r.value)
                                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                                            : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-300'
                                                    }`}
                                                >
                                                    {r.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Account Types</label>
                                        <div className="flex flex-wrap gap-1.5">
                                            {ACCOUNT_TYPES.map(t => (
                                                <button
                                                    key={t.value}
                                                    type="button"
                                                    onClick={() => toggleArray('account_types', t.value)}
                                                    className={`px-2.5 py-1 text-xs font-medium rounded-lg border transition-colors ${
                                                        data.account_types.includes(t.value)
                                                            ? 'bg-purple-600 text-white border-purple-600'
                                                            : 'bg-white text-gray-600 border-gray-300 hover:border-purple-300'
                                                    }`}
                                                >
                                                    {t.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* AI Copy Generator */}
                                <div className="rounded-xl border border-dashed border-brand-green/30 bg-brand-green/5 p-4 space-y-3">
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="w-4 h-4 text-brand-green" />
                                        <span className="text-xs font-bold uppercase tracking-widest text-brand-green">AI Copywriter</span>
                                    </div>
                                    <textarea
                                        rows={2}
                                        value={ai.brief}
                                        onChange={e => setAi(prev => ({ ...prev, brief: e.target.value }))}
                                        placeholder="e.g. Announce a 10% bonus on all deposits this weekend"
                                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green resize-none"
                                    />
                                    <div className="flex items-center gap-3">
                                        <input
                                            type="text"
                                            value={ai.tone}
                                            onChange={e => setAi(prev => ({ ...prev, tone: e.target.value }))}
                                            placeholder="Optional tone (e.g. playful, urgent)"
                                            className="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                                        />
                                        <button
                                            type="button"
                                            onClick={generateCopy}
                                            disabled={ai.loading || !ai.brief}
                                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50 transition-colors"
                                        >
                                            {ai.loading && <Loader2 className="w-4 h-4 animate-spin" />}
                                            {ai.loading ? 'Generating…' : 'Generate'}
                                        </button>
                                    </div>
                                    {ai.error && <p className="text-xs text-red-600">{ai.error}</p>}
                                </div>

                                {/* Content by Locale */}
                                <div>
                                    <div className="flex items-center justify-between mb-3">
                                        <label className="block text-xs font-bold uppercase tracking-widest text-gray-500">Content</label>
                                        <div className="flex gap-1">
                                            {(['en', 'sn', 'nd'] as const).map(locale => (
                                                <button
                                                    key={locale}
                                                    type="button"
                                                    onClick={() => setActiveLocale(locale)}
                                                    className={`px-3 py-1 text-xs font-semibold rounded-lg border transition-colors ${
                                                        activeLocale === locale
                                                            ? 'bg-gray-900 text-white border-gray-900'
                                                            : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'
                                                    }`}
                                                >
                                                    {locale.toUpperCase()}
                                                    {locale === 'en' && <span className="ml-1 text-red-400">*</span>}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="space-y-3">
                                        <div>
                                            <input
                                                type="text"
                                                value={data[`subject_${activeLocale}` as keyof CampaignForm] as string}
                                                onChange={e => setData(`subject_${activeLocale}` as keyof CampaignForm, e.target.value)}
                                                placeholder={`Subject ${activeLocale === 'en' ? '(required)' : '(optional, falls back to EN)'}`}
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                                            />
                                            {activeLocale === 'en' && errors.subject_en && <p className="text-xs text-red-600 mt-1">{errors.subject_en}</p>}
                                        </div>
                                        <div>
                                            <textarea
                                                rows={6}
                                                value={data[`body_${activeLocale}` as keyof CampaignForm] as string}
                                                onChange={e => setData(`body_${activeLocale}` as keyof CampaignForm, e.target.value)}
                                                placeholder={`Message body ${activeLocale === 'en' ? '(required)' : '(optional, falls back to EN)'}`}
                                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green resize-none"
                                            />
                                            {activeLocale === 'en' && errors.body_en && <p className="text-xs text-red-600 mt-1">{errors.body_en}</p>}
                                        </div>
                                    </div>
                                </div>

                                {/* Submit */}
                                <div className="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                                    <button
                                        type="button"
                                        onClick={() => setShowForm(false)}
                                        className="px-4 py-2 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing || data.channels.length === 0}
                                        className="flex items-center gap-2 px-5 py-2 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 shadow-sm transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                    >
                                        {processing && <Loader2 className="w-4 h-4 animate-spin" />}
                                        {processing ? 'Queuing…' : 'Launch Campaign'}
                                    </button>
                                </div>
                            </form>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>
        </AdminLayout>
    );
}
