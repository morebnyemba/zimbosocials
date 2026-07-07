import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Loader2, Sparkles, Wand2, FileText, X } from 'lucide-react';

interface Reply { id: number; message: string; is_admin: boolean; user?: { id: number; name: string }; created_at: string; }
interface Ticket { id: number; subject: string; message: string; status: string; priority?: string; user?: { id: number; name: string; email: string }; replies: Reply[]; created_at: string; }
interface Props { ticket: Ticket; }

const TONES = [
    { value: '', label: 'Default (professional & empathetic)' },
    { value: 'warm, friendly, casual but respectful', label: 'Friendly' },
    { value: 'formal, precise, businesslike', label: 'Formal' },
    { value: 'apologetic, reassuring, very empathetic', label: 'Apologetic' },
    { value: 'short, direct, to the point', label: 'Short & direct' },
];

async function aiPost(url: string, body: Record<string, unknown>) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message ?? Object.values(json.errors ?? {}).flat().join(' ') ?? 'AI request failed.');
    return json;
}

export default function TicketShow({ ticket }: Props) {
    const { data, setData, post, processing } = useForm({ message: '' });
    const [aiBusy, setAiBusy] = useState<null | 'draft' | 'enhance' | 'summary'>(null);
    const [aiError, setAiError] = useState<string | null>(null);

    // Intent modal: the AI never writes a reply without the admin saying
    // what it should communicate.
    const [showIntentModal, setShowIntentModal] = useState(false);
    const [intent, setIntent] = useState('');
    const [tone, setTone] = useState('');

    // Thread summary panel
    const [summary, setSummary] = useState<string | null>(null);

    const submit = (e: React.FormEvent) => { e.preventDefault(); post(route('admin.tickets.reply', ticket.id), { preserveScroll: true, onSuccess: () => setData('message', '') }); };
    const close = () => router.post(route('admin.tickets.close', ticket.id));

    const generateDraft = async () => {
        if (intent.trim().length < 5) { setAiError('Tell the AI what you want to say first (a few words is enough).'); return; }
        setAiBusy('draft');
        setAiError(null);
        try {
            const json = await aiPost(route('admin.tickets.draft-reply', ticket.id), { intent, tone: tone || undefined });
            setData('message', json.draft ?? '');
            setShowIntentModal(false);
            setIntent('');
        } catch (e: any) {
            setAiError(e.message);
        } finally {
            setAiBusy(null);
        }
    };

    const enhanceDraft = async () => {
        setAiBusy('enhance');
        setAiError(null);
        try {
            const json = await aiPost(route('admin.tickets.enhance-reply', ticket.id), { message: data.message, tone: tone || undefined });
            setData('message', json.draft ?? data.message);
        } catch (e: any) {
            setAiError(e.message);
        } finally {
            setAiBusy(null);
        }
    };

    const summarize = async () => {
        setAiBusy('summary');
        setAiError(null);
        try {
            const json = await aiPost(route('admin.tickets.summarize', ticket.id), {});
            setSummary(json.summary ?? null);
        } catch (e: any) {
            setAiError(e.message);
        } finally {
            setAiBusy(null);
        }
    };

    return (
        <AdminLayout>
            <Head title={`Ticket: ${ticket.subject}`} />
            <div className="max-w-4xl mx-auto space-y-6">
                <Link href={route('admin.tickets.index')} className="text-sm font-medium text-brand-green hover:text-brand-green/80 transition-colors">
                    ← Back to Tickets
                </Link>

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between mb-4 pb-4 border-b border-gray-100">
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 tracking-tight">{ticket.subject}</h1>
                            <p className="text-sm text-gray-500 mt-1"><span className="font-medium text-gray-700">{ticket.user?.name}</span> · {ticket.user?.email}</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={summarize}
                                disabled={aiBusy !== null}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50 transition-colors"
                            >
                                {aiBusy === 'summary' ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <FileText className="w-3.5 h-3.5" />}
                                AI Summary
                            </button>
                            <span className={`px-3 py-1 text-xs font-medium uppercase tracking-wider rounded-full border ${ticket.status === 'open' ? 'bg-brand-green/10 text-brand-green border-brand-green/20' : ticket.status === 'closed' ? 'bg-gray-100 text-gray-600 border-gray-200' : 'bg-amber-100 text-amber-800 border-amber-200'}`}>{ticket.status}</span>
                            {ticket.status !== 'closed' && <button onClick={close} className="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition-colors">Close Ticket</button>}
                        </div>
                    </div>
                    {summary && (
                        <div className="mb-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-sm text-indigo-900 whitespace-pre-wrap leading-relaxed relative">
                            <button onClick={() => setSummary(null)} className="absolute top-2 right-2 text-indigo-300 hover:text-indigo-500"><X className="w-4 h-4" /></button>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-indigo-400 mb-2">AI Thread Summary</p>
                            {summary}
                        </div>
                    )}
                    <div className="p-5 rounded-xl bg-gray-50 text-gray-800 text-sm whitespace-pre-wrap leading-relaxed border border-gray-100">{ticket.message}</div>
                    <p className="text-xs font-medium text-gray-400 mt-3">{new Date(ticket.created_at).toLocaleString()}</p>
                </div>

                {/* Replies */}
                <div className="space-y-4">
                    {ticket.replies.map(r => (
                        <div key={r.id} className={`p-5 rounded-xl border shadow-sm ${r.is_admin ? 'border-brand-green/20 bg-brand-green/5 ml-8' : 'border-gray-200 bg-white mr-8'}`}>
                            <div className="flex items-center gap-2 mb-3">
                                <span className="text-sm font-bold text-gray-900">{r.user?.name}</span>
                                {r.is_admin && <span className="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20">Admin</span>}
                                <span className="text-xs font-medium text-gray-500 ml-auto">{new Date(r.created_at).toLocaleString()}</span>
                            </div>
                            <p className="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{r.message}</p>
                        </div>
                    ))}
                </div>

                {/* Reply Form */}
                {ticket.status !== 'closed' && (
                    <form onSubmit={submit} className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-3">
                        <div className="flex items-center justify-between flex-wrap gap-2">
                            <label className="text-sm font-medium text-gray-700">Reply</label>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => { setAiError(null); setShowIntentModal(true); }}
                                    disabled={aiBusy !== null}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-brand-green/30 text-brand-green hover:bg-brand-green/5 disabled:opacity-50 transition-colors"
                                >
                                    <Sparkles className="w-3.5 h-3.5" />
                                    AI Draft Reply
                                </button>
                                <button
                                    type="button"
                                    onClick={enhanceDraft}
                                    disabled={aiBusy !== null || data.message.trim().length < 5}
                                    title="Improve grammar, clarity and tone of what you've written — facts stay exactly as-is"
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50 disabled:opacity-50 transition-colors"
                                >
                                    {aiBusy === 'enhance' ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Wand2 className="w-3.5 h-3.5" />}
                                    {aiBusy === 'enhance' ? 'Enhancing…' : 'Enhance My Answer'}
                                </button>
                            </div>
                        </div>
                        <textarea value={data.message} onChange={e => setData('message', e.target.value)} rows={5} placeholder="Write your reply — or use AI Draft (tell it what to say) / write rough notes and hit Enhance..." className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-3 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 placeholder:text-gray-400 resize-none transition-shadow" />
                        {aiError && <p className="text-xs text-red-600">{aiError}</p>}
                        <div className="flex justify-end">
                            <button type="submit" disabled={processing || !data.message} className="px-6 py-2.5 text-sm font-medium rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50 transition-colors shadow-sm">Send Reply</button>
                        </div>
                    </form>
                )}
            </div>

            {/* Intent modal — the AI asks what you want to say BEFORE writing */}
            {showIntentModal && (
                <div className="fixed inset-0 z-[120] flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={() => setShowIntentModal(false)} />
                    <div className="relative z-10 w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-gray-200 space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <Sparkles className="w-5 h-5 text-brand-green" /> What do you want to tell the customer?
                            </h3>
                            <button onClick={() => setShowIntentModal(false)} className="text-gray-300 hover:text-gray-500"><X className="w-5 h-5" /></button>
                        </div>
                        <p className="text-sm text-gray-500">
                            Give the AI your talking points — rough notes are fine. It will write a polished reply
                            that says exactly this, and nothing it made up on its own.
                        </p>
                        <textarea
                            value={intent}
                            onChange={e => setIntent(e.target.value)}
                            rows={4}
                            autoFocus
                            placeholder={'e.g. "order 123 was refunded yesterday, money is in their wallet, apologise for the delay, ask them to check and confirm"'}
                            className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-3 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 resize-none"
                        />
                        <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Tone</label>
                            <select
                                value={tone}
                                onChange={e => setTone(e.target.value)}
                                className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-2.5 outline-none focus:border-brand-green"
                            >
                                {TONES.map(t => <option key={t.label} value={t.value}>{t.label}</option>)}
                            </select>
                        </div>
                        {aiError && <p className="text-xs text-red-600">{aiError}</p>}
                        <div className="flex justify-end gap-2">
                            <button onClick={() => setShowIntentModal(false)} className="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">Cancel</button>
                            <button
                                onClick={generateDraft}
                                disabled={aiBusy !== null || intent.trim().length < 5}
                                className="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50 transition-colors"
                            >
                                {aiBusy === 'draft' ? <Loader2 className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                                {aiBusy === 'draft' ? 'Writing…' : 'Generate Draft'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
