import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { Check, Copy, Loader2, Sparkles } from 'lucide-react';

interface CaptionResult {
    caption_en: string;
    caption_sn: string;
    caption_nd: string;
}

const platforms = [
    { value: 'instagram', label: 'Instagram' },
    { value: 'tiktok', label: 'TikTok' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'x', label: 'X / Twitter' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'whatsapp', label: 'WhatsApp Status' },
];

const locales = [
    { key: 'en', label: 'ai_caption_en' },
    { key: 'sn', label: 'ai_caption_sn' },
    { key: 'nd', label: 'ai_caption_nd' },
] as const;

export default function PortfolioCaption() {
    const { t } = useTranslation();
    const [title, setTitle] = useState('');
    const [platform, setPlatform] = useState('instagram');
    const [tone, setTone] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<CaptionResult | null>(null);
    const [copied, setCopied] = useState<string | null>(null);

    const generate = async () => {
        setLoading(true);
        setError(null);
        setResult(null);

        try {
            const res = await fetch(route('marketer.portfolio-caption.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    title,
                    platform,
                    tone: tone || undefined,
                }),
            });

            const json = await res.json();

            if (!res.ok) {
                setError(json.message ?? t('ai_error'));
                return;
            }

            if (json.caption_en) {
                setResult(json as CaptionResult);
            } else {
                setError(t('ai_error'));
            }
        } catch (e) {
            setError(t('ai_error'));
        } finally {
            setLoading(false);
        }
    };

    const copy = (text: string, key: string) => {
        navigator.clipboard.writeText(text);
        setCopied(key);
        setTimeout(() => setCopied(null), 1500);
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('ai_portfolio_caption')} />

            <div className="max-w-4xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                        <Sparkles className="w-6 h-6 text-brand-green" />
                        {t('ai_portfolio_caption')}
                    </h1>
                    <p className="text-gray-500 text-sm mt-1">{t('ai_portfolio_caption_subtitle')}</p>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                    <div>
                        <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">
                            {t('ai_portfolio_title')}
                        </label>
                        <input
                            type="text"
                            value={title}
                            onChange={e => setTitle(e.target.value)}
                            placeholder={t('ai_portfolio_title_placeholder')}
                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">
                                {t('ai_portfolio_platform')}
                            </label>
                            <select
                                value={platform}
                                onChange={e => setPlatform(e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            >
                                {platforms.map(p => (
                                    <option key={p.value} value={p.value}>{p.label}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">
                                {t('ai_portfolio_tone')}
                            </label>
                            <input
                                type="text"
                                value={tone}
                                onChange={e => setTone(e.target.value)}
                                placeholder={t('ai_portfolio_tone_placeholder')}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            />
                        </div>
                    </div>

                    <button
                        onClick={generate}
                        disabled={loading || title.trim() === ''}
                        className="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-60 transition-colors"
                    >
                        {loading && <Loader2 className="w-4 h-4 animate-spin" />}
                        {loading ? t('ai_loading') : t('ai_generate_caption')}
                    </button>

                    {error && <p className="text-xs text-red-600">{error}</p>}
                </div>

                {result && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-bold text-gray-900">
                            {t('ai_generate_caption')}
                        </h2>

                        <div className="grid gap-4 md:grid-cols-3">
                            {locales.map(({ key, label }) => {
                                const text = result[`caption_${key}`];
                                const copyKey = `caption-${key}`;

                                return (
                                    <div key={key} className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-bold uppercase tracking-widest text-gray-500">
                                                {t(label)}
                                            </span>
                                            <button
                                                onClick={() => copy(text, copyKey)}
                                                className="text-xs text-brand-green hover:text-brand-green/80 flex items-center gap-1"
                                            >
                                                {copied === copyKey ? (
                                                    <Check className="w-3 h-3" />
                                                ) : (
                                                    <Copy className="w-3 h-3" />
                                                )}
                                                {copied === copyKey ? 'Copied' : 'Copy'}
                                            </button>
                                        </div>
                                        <p className="text-sm text-gray-800 whitespace-pre-wrap">{text}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
