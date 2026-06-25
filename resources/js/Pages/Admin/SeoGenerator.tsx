import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Loader2, Sparkles, Copy, Check } from 'lucide-react';

interface Props {
    categories: string[];
}

export default function SeoGenerator({ categories }: Props) {
    const [type, setType] = useState<'category' | 'faq'>('category');
    const [category, setCategory] = useState(categories[0] ?? '');
    const [angle, setAngle] = useState('');
    const [count, setCount] = useState(5);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<
        | { type: 'category'; headline: string; body: string; meta_title: string; meta_description: string }
        | { type: 'faq'; faqs: { question: string; answer: string }[] }
        | null
    >(null);
    const [copied, setCopied] = useState<string | null>(null);

    const generate = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await fetch(route('admin.seo.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    type,
                    category: type === 'category' ? category : undefined,
                    angle: angle || undefined,
                    count: type === 'faq' ? count : undefined,
                }),
            });
            const json = await res.json();
            if (!res.ok) {
                setError(json.message ?? 'Failed to generate SEO content.');
                setResult(null);
                return;
            }
            setResult(type === 'faq' ? { type: 'faq', faqs: json.faqs } : { type: 'category', ...json });
        } catch (e) {
            setError('Failed to generate SEO content.');
            setResult(null);
        } finally {
            setLoading(false);
        }
    };

    const copy = (text: string, key: string) => {
        navigator.clipboard.writeText(text);
        setCopied(key);
        setTimeout(() => setCopied(null), 1500);
    };

    const isCategoryResult = (r: typeof result): r is { type: 'category'; headline: string; body: string; meta_title: string; meta_description: string } =>
        r !== null && r.type === 'category';

    return (
        <AdminLayout>
            <Head title="AI SEO Generator" />
            <div className="max-w-3xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                        <Sparkles className="w-6 h-6 text-brand-green" />
                        AI SEO Generator
                    </h1>
                    <p className="text-gray-500 text-sm mt-1">Generate marketing copy and FAQ content for public pages.</p>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                    <div>
                        <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Content Type</label>
                        <div className="flex gap-2">
                            {(['category', 'faq'] as const).map(t => (
                                <button
                                    key={t}
                                    type="button"
                                    onClick={() => setType(t)}
                                    className={`px-4 py-2 text-xs font-semibold rounded-lg border transition-colors ${
                                        type === t
                                            ? 'bg-brand-green text-white border-brand-green'
                                            : 'bg-white text-gray-600 border-gray-300 hover:border-brand-green/50'
                                    }`}
                                >
                                    {t === 'category' ? 'Category Page' : 'FAQ Entries'}
                                </button>
                            ))}
                        </div>
                    </div>

                    {type === 'category' && (
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">Category</label>
                            <select
                                value={category}
                                onChange={e => setCategory(e.target.value)}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            >
                                {categories.map(c => <option key={c} value={c}>{c}</option>)}
                            </select>
                        </div>
                    )}

                    {type === 'faq' && (
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">Number of FAQs</label>
                            <input
                                type="number"
                                min={1}
                                max={10}
                                value={count}
                                onChange={e => setCount(Math.min(10, Math.max(1, Number(e.target.value))))}
                                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                            />
                        </div>
                    )}

                    <div>
                        <label className="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-1.5">Optional Angle</label>
                        <input
                            type="text"
                            value={angle}
                            onChange={e => setAngle(e.target.value)}
                            placeholder="e.g. small businesses launching a new product"
                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-green/30 focus:border-brand-green"
                        />
                    </div>

                    <button
                        onClick={generate}
                        disabled={loading || (type === 'category' && !category)}
                        className="flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-60 transition-colors"
                    >
                        {loading && <Loader2 className="w-4 h-4 animate-spin" />}
                        {loading ? 'Generating…' : 'Generate Content'}
                    </button>

                    {error && <p className="text-xs text-red-600">{error}</p>}
                </div>

                {result && isCategoryResult(result) && (
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                        <h2 className="text-lg font-bold text-gray-900">Generated Category Content</h2>
                        {(
                            [
                                ['headline', 'Headline'],
                                ['body', 'Body'],
                                ['meta_title', 'Meta Title'],
                                ['meta_description', 'Meta Description'],
                            ] as const
                        ).map(([key, label]) => (
                            <div key={key} className="space-y-1">
                                <div className="flex items-center justify-between">
                                    <label className="text-xs font-bold uppercase tracking-widest text-gray-500">{label}</label>
                                    <button
                                        onClick={() => copy(result[key], key)}
                                        className="text-xs text-brand-green hover:text-brand-green/80 flex items-center gap-1"
                                    >
                                        {copied === key ? <Check className="w-3 h-3" /> : <Copy className="w-3 h-3" />}
                                        {copied === key ? 'Copied' : 'Copy'}
                                    </button>
                                </div>
                                <div className="p-3 rounded-lg bg-gray-50 text-sm text-gray-800 whitespace-pre-wrap">
                                    {result[key]}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {result && result.type === 'faq' && (
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
                        <h2 className="text-lg font-bold text-gray-900">Generated FAQs</h2>
                        {result.faqs.map((faq, idx) => (
                            <div key={idx} className="p-4 rounded-lg bg-gray-50 space-y-2">
                                <div className="flex items-start justify-between gap-3">
                                    <p className="text-sm font-bold text-gray-900">{faq.question}</p>
                                    <button
                                        onClick={() => copy(`${faq.question}\n\n${faq.answer}`, `faq-${idx}`)}
                                        className="text-xs text-brand-green hover:text-brand-green/80 flex items-center gap-1 shrink-0"
                                    >
                                        {copied === `faq-${idx}` ? <Check className="w-3 h-3" /> : <Copy className="w-3 h-3" />}
                                        {copied === `faq-${idx}` ? 'Copied' : 'Copy'}
                                    </button>
                                </div>
                                <p className="text-sm text-gray-700 leading-relaxed">{faq.answer}</p>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
