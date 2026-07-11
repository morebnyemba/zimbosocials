import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FaArrowLeft, FaBook, FaPlus, FaTrash, FaPen, FaTimes } from 'react-icons/fa';

interface Entry {
    id: number;
    title: string;
    question: string;
    answer: string;
    keywords: string | null;
    category: string | null;
    status: boolean;
    hits: number;
}

interface Props {
    entries: Entry[];
}

const blank = { title: '', question: '', answer: '', keywords: '', category: '', status: true };

export default function KnowledgeBase({ entries }: Props) {
    const [editing, setEditing] = useState<number | null>(null);
    const { data, setData, post, put, delete: destroy, processing, reset } = useForm<any>({ ...blank });

    const startNew = () => { setEditing(0); reset(); setData({ ...blank }); };
    const startEdit = (e: Entry) => {
        setEditing(e.id);
        setData({ title: e.title, question: e.question, answer: e.answer, keywords: e.keywords ?? '', category: e.category ?? '', status: e.status });
    };
    const cancel = () => { setEditing(null); reset(); };

    const save = (ev: React.FormEvent) => {
        ev.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => cancel() };
        if (editing && editing > 0) put(route('admin.whatsapp.kb.update', editing), opts);
        else post(route('admin.whatsapp.kb.store'), opts);
    };

    const remove = (id: number) => {
        if (confirm('Delete this FAQ entry?')) destroy(route('admin.whatsapp.kb.destroy', id), { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="WhatsApp Knowledge Base" />
            <div className="p-6 md:p-8 max-w-5xl mx-auto space-y-6">
                <Link href={route('admin.whatsapp.conversations')} className="inline-flex items-center gap-2 text-sm font-bold text-zinc-500 hover:text-zinc-900">
                    <FaArrowLeft /> Back to conversations
                </Link>

                <div className="flex items-center gap-3">
                    <div className="h-12 w-12 rounded-2xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center text-2xl"><FaBook /></div>
                    <div>
                        <h1 className="text-2xl font-black text-zinc-900 tracking-tight">Knowledge Base</h1>
                        <p className="text-sm text-zinc-500 font-medium">Answers the bot gives before falling back to AI</p>
                    </div>
                    {editing === null && (
                        <button onClick={startNew} className="ml-auto inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 text-white text-sm font-bold hover:bg-emerald-600 transition">
                            <FaPlus /> New entry
                        </button>
                    )}
                </div>

                {/* Editor */}
                {editing !== null && (
                    <form onSubmit={save} className="bg-white rounded-2xl border-2 border-emerald-200 p-6 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-black text-zinc-900">{editing > 0 ? 'Edit entry' : 'New entry'}</h2>
                            <button type="button" onClick={cancel} className="text-zinc-400 hover:text-zinc-900"><FaTimes /></button>
                        </div>
                        <div className="grid md:grid-cols-2 gap-4">
                            <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Title (e.g. Delivery time)" className="rounded-xl border-2 border-zinc-100 bg-zinc-50 px-4 py-3 font-bold text-zinc-900 focus:border-emerald-500 focus:outline-none" required />
                            <input value={data.category} onChange={(e) => setData('category', e.target.value)} placeholder="Category (optional)" className="rounded-xl border-2 border-zinc-100 bg-zinc-50 px-4 py-3 font-medium text-zinc-900 focus:border-emerald-500 focus:outline-none" />
                        </div>
                        <textarea value={data.question} onChange={(e) => setData('question', e.target.value)} placeholder="Question / phrasing the user might send" rows={2} className="w-full rounded-xl border-2 border-zinc-100 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-900 focus:border-emerald-500 focus:outline-none" required />
                        <input value={data.keywords} onChange={(e) => setData('keywords', e.target.value)} placeholder="Keywords (space-separated — used for matching)" className="w-full rounded-xl border-2 border-zinc-100 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-900 focus:border-emerald-500 focus:outline-none" />
                        <textarea value={data.answer} onChange={(e) => setData('answer', e.target.value)} placeholder="Answer the bot sends" rows={3} className="w-full rounded-xl border-2 border-zinc-100 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-900 focus:border-emerald-500 focus:outline-none" required />
                        <div className="flex items-center justify-between">
                            <label className="flex items-center gap-2 text-sm font-bold text-zinc-600">
                                <input type="checkbox" checked={!!data.status} onChange={(e) => setData('status', e.target.checked)} className="rounded" /> Active
                            </label>
                            <button type="submit" disabled={processing} className="px-6 py-3 rounded-xl bg-zinc-900 text-white text-sm font-bold hover:bg-zinc-800 disabled:opacity-40 transition">Save entry</button>
                        </div>
                    </form>
                )}

                {/* List */}
                <div className="space-y-3">
                    {entries.length === 0 && <p className="text-center text-zinc-400 py-12 font-medium bg-white rounded-2xl border border-zinc-200">No FAQ entries yet. Run the seeder or add one above.</p>}
                    {entries.map((e) => (
                        <div key={e.id} className="bg-white rounded-2xl border border-zinc-200 p-5 flex gap-4">
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <h3 className="font-black text-zinc-900">{e.title}</h3>
                                    {!e.status && <span className="px-2 py-0.5 rounded-md bg-zinc-100 text-zinc-400 text-[10px] font-black uppercase">Inactive</span>}
                                    {e.category && <span className="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase">{e.category}</span>}
                                    <span className="ml-auto text-[10px] font-black text-zinc-300 uppercase">{e.hits} hits</span>
                                </div>
                                <p className="text-sm text-zinc-600 line-clamp-2">{e.answer}</p>
                                {e.keywords && <p className="text-[11px] text-zinc-400 mt-1 font-mono">{e.keywords}</p>}
                            </div>
                            <div className="flex flex-col gap-2">
                                <button onClick={() => startEdit(e)} className="h-9 w-9 rounded-lg bg-zinc-100 text-zinc-500 hover:bg-zinc-200 flex items-center justify-center"><FaPen className="text-xs" /></button>
                                <button onClick={() => remove(e.id)} className="h-9 w-9 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center"><FaTrash className="text-xs" /></button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
