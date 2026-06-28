import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { Check, Copy, Loader2, Sparkles } from 'lucide-react';

interface CalendarDay {
    day: number;
    theme: string;
    caption_en: string;
    caption_sn: string;
    caption_nd: string;
    hashtags: string[];
}

interface CalendarResult {
    platform: string;
    days: CalendarDay[];
}

const platforms = [
    { value: '', label: 'ai_calendar_platform_general' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'tiktok', label: 'TikTok' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'x', label: 'X / Twitter' },
    { value: 'whatsapp', label: 'WhatsApp Status' },
];

const locales = [
    { key: 'en', label: 'ai_caption_en' },
    { key: 'sn', label: 'ai_caption_sn' },
    { key: 'nd', label: 'ai_caption_nd' },
] as const;

export default function ContentCalendar() {
    const { t } = useTranslation();
    const [brief, setBrief] = useState('');
    const [platform, setPlatform] = useState('');
    const [tone, setTone] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<CalendarResult | null>(null);
    const [copied, setCopied] = useState<string | null>(null);

    const generate = async () => {
        setLoading(true);
        setError(null);
        setResult(null);

        try {
            const res = await fetch(route('marketer.content-calendar.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    brief,
                    platform: platform || undefined,
                    tone: tone || undefined,
                }),
            });

            const json = await res.json();

            if (!res.ok) {
                setError(json.message ?? t('ai_error'));
                return;
            }

            if (json.days && Array.isArray(json.days)) {
                setResult(json as CalendarResult);
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

    const captionText = (day: CalendarDay, locale: 'en' | 'sn' | 'nd') => {
        const caption = day[`caption_${locale}`];
        const tags = day.hashtags.length > 0 ? `\n\n#${day.hashtags.join(' #')}` : '';
        return `${caption}${tags}`;
    };

    return (
        <AuthenticatedLayout>
            <Head title={t('ai_content_calendar')} />

            <div className="max-w-4xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                        <Sparkles className="w-6 h-6 text-brand-green" />
                        {t('ai_content_calendar')}
                    </h1>
                    <p className="text-gray-500 text-sm mt-1">{t('ai_content_calendar_subtitle')}</p>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                    <div>
                        <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">
                            {t('ai_brief_placeholder')}
                        </label>
                        <textarea
                            value={brief}
                            onChange={e => setBrief(e.target.value)}
                            placeholder={t('ai_calendar_prompt_placeholder')}
                            rows={4}
                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">
                                {t('ai_calendar_platform')}
                            </label>
                            <select
                                value={platform}
                                onChange={e => setPlatform(e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            >
                                {platforms.map(p => (
                                    <option key={p.value} value={p.value}>
                                        {p.value === '' ? t(p.label) : p.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">
                                {t('ai_calendar_tone')}
                            </label>
                            <input
                                type="text"
                                value={tone}
                                onChange={e => setTone(e.target.value)}
                                placeholder={t('ai_calendar_tone_placeholder')}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            />
                        </div>
                    </div>

                    <button
                        onClick={generate}
                        disabled={loading || brief.trim() === ''}
                        className="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-60 transition-colors"
                    >
                        {loading && <Loader2 className="w-4 h-4 animate-spin" />}
                        {loading ? t('ai_loading') : t('ai_generate_calendar')}
                    </button>

                    {error && <p className="text-xs text-red-600">{error}</p>}
                </div>

                {result && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-bold text-gray-900">
                                {t('ai_generate_calendar')}
                            </h2>
                            <span className="text-xs font-semibold uppercase tracking-widest text-gray-500">
                                {result.platform}
                            </span>
                        </div>

                        {result.days.map(day => (
                            <div
                                key={day.day}
                                className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm space-y-4"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="inline-flex items-center justify-center w-7 h-7 rounded-full bg-brand-green text-white text-xs font-bold">
                                        {day.day}
                                    </span>
                                    <h3 className="font-semibold text-gray-900">{day.theme}</h3>
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    {locales.map(({ key, label }) => {
                                        const text = captionText(day, key);
                                        const copyKey = `day-${day.day}-${key}`;

                                        return (
                                            <div key={key} className="rounded-lg bg-gray-50 p-3 space-y-2">
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

                                {day.hashtags.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {day.hashtags.map(tag => (
                                            <span
                                                key={tag}
                                                className="px-2 py-1 text-xs font-medium rounded-md bg-brand-green/10 text-brand-green"
                                            >
                                                #{tag}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
