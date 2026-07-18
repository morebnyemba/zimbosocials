import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import ToastContainer, { ToastKind } from '@/Components/Toast';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Trash2, Search, Filter, Edit2, X, AlertCircle, Merge, MessageCircle, Copy, Sparkles, Loader2 } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

interface UpstreamProvider { id: number; name: string; url: string; }
interface ServiceUpstream { id?: number; upstream_provider_id: number; external_service_id: string; priority: number; external_rate?: string | number | null; provider?: UpstreamProvider; }

/**
 * Cost of the primary upstream route (lowest priority number wins, matching
 * dispatch order). A rate of 0 means "cost unknown" — the column defaults to 0
 * and is refreshed daily by upstream:sync-services.
 */
const primaryCost = (upstreams?: ServiceUpstream[]): number | null => {
    const primary = [...(upstreams ?? [])]
        .sort((a, b) => a.priority - b.priority)
        .find(u => Number(u.external_rate) > 0);
    return primary ? Number(primary.external_rate) : null;
};
interface Service { id: number; name: string; name_sn?: string; category: string; type: string; rate: string; min_qty: number; max_qty: number; is_active: boolean; is_dripfeed: boolean; is_refill: boolean; refill_days?: number; avg_time_minutes?: number; display_order?: number; orders_count: number; description?: string; description_sn?: string; upstreams?: ServiceUpstream[]; }
interface Props { services: { data: Service[]; links: any[]; total: number }; categories: string[]; categoryCounts: Record<string, number>; providers: UpstreamProvider[]; stats: { total: number; active: number; inactive: number }; filters: Record<string, string>; }

const emptyForm = { name: '', name_sn: '', description: '', description_sn: '', category: '', type: 'default', rate: '', min_qty: '100', max_qty: '10000', is_active: true, is_dripfeed: false, is_refill: false, refill_days: '', avg_time_minutes: '', display_order: '0', upstreams: [] as ServiceUpstream[] };

export default function ServicesIndex({ services, categories, categoryCounts, providers, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [enhancingId, setEnhancingId] = useState<number | null>(null);

    const enhanceNames = (id: number) => {
        setEnhancingId(id);
        router.post(route('admin.services.enhance-names'), { service_ids: [id] }, {
            preserveScroll: true,
            onFinish: () => setEnhancingId(null),
        });
    };
    const [form, setForm] = useState<any>(emptyForm);
    const [pendingDeactivateId, setPendingDeactivateId] = useState<number | null>(null);
    const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const [selectedCategories, setSelectedCategories] = useState<string[]>([]);
    const [mergeTarget, setMergeTarget] = useState('');
    const [merging, setMerging] = useState(false);
    const [showExportModal, setShowExportModal] = useState(false);
    const [exportCategory, setExportCategory] = useState('');
    const [exportPlatform, setExportPlatform] = useState('plain');
    const [exportText, setExportText] = useState('');
    const [exportLoading, setExportLoading] = useState(false);
    const [exportAiUsed, setExportAiUsed] = useState<boolean | null>(null);
    const [activeToast, setActiveToast] = useState<{ kind: ToastKind; message: string } | null>(null);

    const showToast = (kind: ToastKind, message: string) => {
        setActiveToast({ kind, message });
        setTimeout(() => setActiveToast(null), 4000);
    };

    const applySearch = () => router.get(route('admin.services.index'), { ...filters, search }, { preserveState: true });
    const filterCategory = (cat: string) => router.get(route('admin.services.index'), { ...filters, category: cat || undefined }, { preserveState: true });

    const openCreate = () => { setForm({ ...emptyForm, upstreams: [] }); setEditingId(null); setShowForm(true); };
    const openEdit = (s: Service) => {
        setForm({
            name: s.name, name_sn: s.name_sn || '', description: s.description || '', description_sn: s.description_sn || '',
            category: s.category, type: s.type, rate: s.rate, min_qty: String(s.min_qty), max_qty: String(s.max_qty),
            is_active: s.is_active, is_dripfeed: s.is_dripfeed, is_refill: s.is_refill,
            refill_days: s.refill_days ? String(s.refill_days) : '',
            avg_time_minutes: s.avg_time_minutes ? String(s.avg_time_minutes) : '',
            display_order: String(s.display_order ?? 0),
            upstreams: s.upstreams ? [...s.upstreams] : [],
        });
        setEditingId(s.id); setShowForm(true);
    };

    const submitForm = () => {
        const data = { ...form, min_qty: Number(form.min_qty), max_qty: Number(form.max_qty), rate: Number(form.rate), display_order: Number(form.display_order || 0) };
        if (editingId) {
            router.put(route('admin.services.update', editingId), data, { preserveScroll: true, onSuccess: () => setShowForm(false) });
        } else {
            router.post(route('admin.services.store'), data, { preserveScroll: true, onSuccess: () => setShowForm(false) });
        }
    };

    const deactivate = (id: number) => { setPendingDeactivateId(id); };

    const addUpstream = () => {
        if (providers.length === 0) return showToast('warn', 'No active providers available.');
        setForm({
            ...form,
            upstreams: [...form.upstreams, { upstream_provider_id: providers[0].id, external_service_id: '', priority: form.upstreams.length + 1 }]
        });
    };

    const removeUpstream = (index: number) => {
        const newUpstreams = [...form.upstreams];
        newUpstreams.splice(index, 1);
        setForm({ ...form, upstreams: newUpstreams });
    };

    const updateUpstream = (index: number, field: string, value: any) => {
        const newUpstreams = [...form.upstreams];
        newUpstreams[index] = { ...newUpstreams[index], [field]: value };
        setForm({ ...form, upstreams: newUpstreams });
    };

    const bulkDeleteInactive = () => {
        if (bulkDeleting) return;
        setBulkDeleting(true);
        router.delete(route('admin.services.bulk-delete-inactive'), {
            preserveScroll: true,
            onFinish: () => { setBulkDeleting(false); setShowBulkDeleteConfirm(false); },
        });
    };

    const openCategoryModal = () => {
        setSelectedCategories([]);
        setMergeTarget('');
        setShowCategoryModal(true);
    };

    const toggleCategorySelection = (cat: string) => {
        setSelectedCategories(current => {
            const next = current.includes(cat) ? current.filter(c => c !== cat) : [...current, cat];
            // Default the target name to the first-selected category so a
            // 2-way merge is a single click; admin can still overwrite it.
            if (!mergeTarget && next.length > 0) setMergeTarget(next[0]);
            return next;
        });
    };

    const mergeCategories = () => {
        if (merging || selectedCategories.length < 2 || !mergeTarget.trim()) return;
        setMerging(true);
        router.post(route('admin.services.merge-categories'), { categories: selectedCategories, target: mergeTarget.trim() }, {
            preserveScroll: true,
            onSuccess: () => setShowCategoryModal(false),
            onFinish: () => setMerging(false),
        });
    };

    const fetchExportList = (category: string, platform: string) => {
        setExportLoading(true);
        setExportAiUsed(null);

        // Plain (mechanical) list: simple GET, no AI involved.
        if (platform === 'plain') {
            fetch(route('admin.services.export-list', category ? { category } : {}), { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(data => setExportText(data.text))
                .finally(() => setExportLoading(false));
            return;
        }

        // Platform-specific: POST so we can pass platform/category, Gemini-backed
        // with graceful fallback to the plain list if AI is unavailable/fails.
        fetch(route('admin.services.export-list-ai'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ platform, category: category || undefined }),
        })
            .then(r => r.json())
            .then(data => {
                setExportText(data.text);
                setExportAiUsed(data.ai_used ?? false);
                if (data.ai_used === false) {
                    showToast('warn', 'AI enhancement unavailable — showing the plain list instead.');
                }
            })
            .finally(() => setExportLoading(false));
    };

    const openExportModal = () => {
        setExportCategory('');
        setExportPlatform('plain');
        setShowExportModal(true);
        fetchExportList('', 'plain');
    };

    const copyExportList = () => {
        navigator.clipboard.writeText(exportText);
        showToast('success', 'Copied to clipboard — ready to paste into WhatsApp.');
    };

    return (
        <AdminLayout>
            <Head title="Service Management" />
            <div className="space-y-8 pb-12">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-zinc-900 tracking-tight">Service Catalog</h1>
                        <p className="text-zinc-500 font-medium text-sm mt-1">{stats.active} Active Services · {stats.inactive} Inactive</p>
                    </div>
                    <div className="flex flex-col sm:flex-row gap-3">
                        <button onClick={openExportModal} className="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 font-bold rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 transition-all active:scale-95">
                            <MessageCircle size={18} /> Export List
                        </button>
                        <button onClick={openCategoryModal} className="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 font-bold rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-200 hover:bg-indigo-100 transition-all active:scale-95">
                            <Merge size={18} /> Merge Categories
                        </button>
                        {stats.inactive > 0 && (
                            <button onClick={() => setShowBulkDeleteConfirm(true)} className="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 font-bold rounded-2xl bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition-all active:scale-95">
                                <Trash2 size={18} /> Delete {stats.inactive} Inactive
                            </button>
                        )}
                        <button onClick={openCreate} className="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-3 font-bold rounded-2xl bg-zinc-900 text-white hover:bg-emerald-500 transition-all shadow-xl hover:shadow-emerald-500/20 active:scale-95">
                            <Plus size={18} /> Deploy New Service
                        </button>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex flex-col md:flex-row gap-4 p-4 bg-white rounded-3xl border border-zinc-200 shadow-sm">
                    <div className="relative w-full md:w-64">
                        <Filter className="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 w-4 h-4" />
                        <select
                            value={filters.category || ''}
                            onChange={e => filterCategory(e.target.value)}
                            className="w-full pl-10 pr-4 py-3 rounded-2xl bg-zinc-50 border-none font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500 transition-all cursor-pointer appearance-none"
                        >
                            <option value="">All Categories</option>
                            {categories.map(c => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                    </div>

                    <div className="flex-1 flex gap-2">
                        <div className="relative flex-1">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 w-4 h-4" />
                            <input 
                                type="text" 
                                value={search} 
                                onChange={e => setSearch(e.target.value)} 
                                onKeyDown={e => e.key === 'Enter' && applySearch()} 
                                placeholder="Search by name, category, service ID, or upstream ID..."
                                className="w-full pl-10 pr-4 py-3 rounded-2xl bg-zinc-50 border-none font-medium text-zinc-900 focus:ring-2 focus:ring-emerald-500 transition-all placeholder:text-zinc-400"
                            />
                        </div>
                        <button onClick={applySearch} className="px-6 py-3 rounded-2xl bg-emerald-50 text-emerald-600 font-black hover:bg-emerald-100 transition-colors">
                            Search
                        </button>
                    </div>
                </div>

                {/* Table */}
                <div className="rounded-[2.5rem] border border-zinc-200 bg-white shadow-xl shadow-zinc-200/20 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-zinc-50/50 border-b border-zinc-200">
                                <tr className="text-zinc-400 font-black text-[10px] uppercase tracking-widest">
                                    <th className="py-5 px-6">Service</th>
                                    <th className="py-5 px-6">Pricing</th>
                                    <th className="py-5 px-6">Limits</th>
                                    <th className="py-5 px-6">Upstream Nodes</th>
                                    <th className="py-5 px-6 text-center">Status</th>
                                    <th className="py-5 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100">
                                {services.data.map(s => (
                                    <tr key={s.id} className="hover:bg-zinc-50/80 transition-colors group">
                                        <td className="py-4 px-6">
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 rounded-xl bg-zinc-100 flex items-center justify-center text-xs font-black text-zinc-400 group-hover:bg-emerald-100 group-hover:text-emerald-600 transition-colors">
                                                    {s.id}
                                                </div>
                                                <div>
                                                    <div className="font-bold text-zinc-900 text-sm max-w-[250px] truncate">{s.name}</div>
                                                    <div className="text-xs font-medium text-zinc-500 mt-0.5 uppercase tracking-wider">{s.category}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="py-4 px-6">
                                            <div className="font-black text-emerald-600">${Number(s.rate).toFixed(2)}</div>
                                            {(() => {
                                                const cost = primaryCost(s.upstreams);
                                                if (cost === null) return <div className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Per 1k</div>;
                                                const sell = Number(s.rate);
                                                const belowCost = sell < cost;
                                                return (
                                                    <div className={`text-[10px] font-bold uppercase tracking-widest ${belowCost ? 'text-red-500' : 'text-zinc-400'}`}>
                                                        Cost ${cost.toFixed(4)}{cost > 0 && <> · {belowCost ? 'LOSS' : `+${Math.round(((sell - cost) / cost) * 100)}%`}</>}
                                                    </div>
                                                );
                                            })()}
                                        </td>
                                        <td className="py-4 px-6">
                                            <div className="font-bold text-zinc-700">{Number(s.min_qty).toLocaleString()}</div>
                                            <div className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">to {Number(s.max_qty).toLocaleString()}</div>
                                        </td>
                                        <td className="py-4 px-6">
                                            <div className="flex flex-col gap-1.5 max-w-[200px]">
                                                {s.upstreams?.map((u, i) => (
                                                    <div key={i} className={`text-[10px] font-bold px-2 py-1 rounded-md border truncate ${i === 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-zinc-50 text-zinc-500 border-zinc-200'}`}>
                                                        <span className="opacity-50 mr-1">#{u.priority}</span>
                                                        {u.provider?.name || 'Unknown'} <span className="ml-1 font-mono opacity-80" title="Upstream service ID">ID {u.external_service_id}</span>
                                                    </div>
                                                ))}
                                                {(!s.upstreams || s.upstreams.length === 0) && <span className="text-xs font-bold text-zinc-400 italic">Local / Manual</span>}
                                            </div>
                                        </td>
                                        <td className="py-4 px-6 text-center">
                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${s.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'}`}>
                                                <span className={`w-1.5 h-1.5 rounded-full ${s.is_active ? 'bg-emerald-500' : 'bg-zinc-400'}`}></span>
                                                {s.is_active ? 'Active' : 'Offline'}
                                            </span>
                                        </td>
                                        <td className="py-4 px-6 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => enhanceNames(s.id)}
                                                    disabled={enhancingId === s.id}
                                                    title="AI: clean up this service name + translations"
                                                    className="p-2 text-zinc-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all disabled:opacity-50"
                                                >
                                                    {enhancingId === s.id ? <Loader2 size={16} className="animate-spin" /> : <Sparkles size={16} />}
                                                </button>
                                                <button onClick={() => openEdit(s)} className="p-2 text-zinc-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-xl transition-all">
                                                    <Edit2 size={16} />
                                                </button>
                                                {s.is_active && (
                                                    <button onClick={() => deactivate(s.id)} className="p-2 text-zinc-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                                                        <Trash2 size={16} />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {services.links && services.data.length > 0 && (
                        <div className="flex justify-center gap-1 p-6 border-t border-zinc-100 bg-zinc-50/50">
                            {services.links.map((l: any, i: number) => (
                                <Link 
                                    key={i} 
                                    href={l.url || '#'} 
                                    className={`px-4 py-2 text-xs font-black rounded-xl transition-all ${l.active ? 'bg-zinc-900 text-white shadow-md scale-105' : 'text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900'}`} 
                                    dangerouslySetInnerHTML={{ __html: l.label }} 
                                />
                            ))}
                        </div>
                    )}
                </div>

                {/* Premium Slide-Over Editor */}
                <AnimatePresence>
                    {showForm && (
                        <>
                            <motion.div 
                                initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                                className="fixed inset-0 bg-zinc-950/40 backdrop-blur-sm z-40"
                                onClick={() => setShowForm(false)}
                            />
                            <motion.div
                                initial={{ x: '100%' }} animate={{ x: 0 }} exit={{ x: '100%' }}
                                transition={{ type: "spring", bounce: 0, duration: 0.4 }}
                                className="fixed inset-y-0 right-0 w-full md:w-[600px] bg-white shadow-2xl z-50 flex flex-col border-l border-zinc-200"
                            >
                                <div className="p-6 border-b border-zinc-100 flex justify-between items-center bg-white sticky top-0 z-10">
                                    <div>
                                        <h2 className="text-xl font-black text-zinc-900">{editingId ? 'Edit Configuration' : 'Deploy New Service'}</h2>
                                        <p className="text-xs font-bold text-zinc-500 uppercase tracking-widest mt-1">
                                            {editingId ? `Service Node #${editingId}` : 'Create Local Node'}
                                        </p>
                                    </div>
                                    <button onClick={() => setShowForm(false)} className="p-2 bg-zinc-100 text-zinc-500 hover:text-zinc-900 rounded-full transition-colors">
                                        <X size={20} />
                                    </button>
                                </div>

                                <div className="flex-1 overflow-y-auto p-6 space-y-8 bg-zinc-50/50">
                                    
                                    {/* Core Details */}
                                    <section className="space-y-4">
                                        <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest flex items-center gap-2">
                                            <span className="w-5 h-5 rounded-full bg-zinc-200 text-zinc-600 flex items-center justify-center">1</span> Core Identity
                                        </h3>
                                        <div className="bg-white p-5 rounded-3xl border border-zinc-200 shadow-sm space-y-4">
                                            <div>
                                                <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Service Name</label>
                                                <input type="text" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} className="w-full bg-zinc-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500" />
                                            </div>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Category</label>
                                                    <input type="text" value={form.category} onChange={e => setForm({ ...form, category: e.target.value })} className="w-full bg-zinc-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500" placeholder="e.g. Instagram" />
                                                </div>
                                                <div>
                                                    <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Type</label>
                                                    <select value={form.type} onChange={e => setForm({ ...form, type: e.target.value })} className="w-full bg-zinc-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500">
                                                        <option value="default">Default</option>
                                                        <option value="custom_data">Custom Data</option>
                                                        <option value="package">Package</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    {/* Pricing & Limits */}
                                    <section className="space-y-4">
                                        <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest flex items-center gap-2">
                                            <span className="w-5 h-5 rounded-full bg-zinc-200 text-zinc-600 flex items-center justify-center">2</span> Pricing & Capacity
                                        </h3>
                                        <div className="bg-white p-5 rounded-3xl border border-zinc-200 shadow-sm grid grid-cols-2 gap-4">
                                            <div className="col-span-2">
                                                <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Rate per 1000 ($)</label>
                                                <input type="number" step="0.0001" value={form.rate} onChange={e => setForm({ ...form, rate: e.target.value })} className="w-full bg-emerald-50/50 border-none rounded-xl px-4 py-3 text-lg font-black text-emerald-700 focus:ring-2 focus:ring-emerald-500" />
                                            </div>
                                            {(() => {
                                                const cost = primaryCost(form.upstreams);
                                                if (cost === null) return null;
                                                const sell = Number(form.rate) || 0;
                                                const profit = sell - cost;
                                                const belowCost = sell > 0 && profit < 0;
                                                return (
                                                    <div className={`col-span-2 rounded-2xl border-2 p-4 ${belowCost ? 'border-red-200 bg-red-50' : 'border-zinc-100 bg-zinc-50'}`}>
                                                        <div className="flex items-center justify-between gap-4 text-sm">
                                                            <div>
                                                                <div className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">Provider Cost / 1k</div>
                                                                <div className="font-black text-zinc-900">${cost.toFixed(4)}</div>
                                                            </div>
                                                            <div className="text-right">
                                                                <div className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">Your Margin</div>
                                                                <div className={`font-black ${belowCost ? 'text-red-600' : 'text-emerald-600'}`}>
                                                                    {sell > 0 ? <>{profit >= 0 ? '+' : ''}${profit.toFixed(4)} ({Math.round((profit / cost) * 100)}%)</> : '—'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        {belowCost && (
                                                            <p className="mt-2 text-xs font-bold text-red-600 flex items-center gap-1.5">
                                                                <AlertCircle size={14} /> Selling below provider cost — every order loses money.
                                                            </p>
                                                        )}
                                                        <p className="mt-2 text-[10px] font-medium text-zinc-400">Cost from the priority-1 route; auto-refreshed daily at 02:00 by the provider sync.</p>
                                                    </div>
                                                );
                                            })()}
                                            <div>
                                                <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Min Quantity</label>
                                                <input type="number" value={form.min_qty} onChange={e => setForm({ ...form, min_qty: e.target.value })} className="w-full bg-zinc-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500" />
                                            </div>
                                            <div>
                                                <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Max Quantity</label>
                                                <input type="number" value={form.max_qty} onChange={e => setForm({ ...form, max_qty: e.target.value })} className="w-full bg-zinc-50 border-none rounded-xl px-4 py-3 text-sm font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500" />
                                            </div>
                                        </div>
                                    </section>

                                    {/* Upstream Routing */}
                                    <section className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-xs font-black text-zinc-400 uppercase tracking-widest flex items-center gap-2">
                                                <span className="w-5 h-5 rounded-full bg-zinc-200 text-zinc-600 flex items-center justify-center">3</span> Routing Nodes
                                            </h3>
                                            <button type="button" onClick={addUpstream} className="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition-colors uppercase tracking-widest">
                                                + Add Node
                                            </button>
                                        </div>
                                        <div className="space-y-3">
                                            {form.upstreams.length === 0 ? (
                                                <div className="bg-white p-6 rounded-3xl border border-zinc-200 shadow-sm text-center">
                                                    <AlertCircle className="w-8 h-8 text-amber-500 mx-auto mb-2 opacity-50" />
                                                    <p className="text-sm font-bold text-zinc-700">No Routing Nodes Active</p>
                                                    <p className="text-xs font-medium text-zinc-400 mt-1">Orders for this service will require manual fulfillment.</p>
                                                </div>
                                            ) : (
                                                form.upstreams.map((upstream: ServiceUpstream, index: number) => (
                                                    <div key={index} className="bg-white p-4 rounded-3xl border border-zinc-200 shadow-sm relative group overflow-hidden">
                                                        <div className={`absolute top-0 left-0 bottom-0 w-1 ${index === 0 ? 'bg-emerald-500' : 'bg-zinc-300'}`}></div>
                                                        <div className="flex items-start gap-3 pl-2">
                                                            <div className="w-16">
                                                                <label className="text-[9px] font-black text-zinc-400 uppercase tracking-widest block mb-1">Priority</label>
                                                                <input type="number" min="1" value={upstream.priority} onChange={e => updateUpstream(index, 'priority', parseInt(e.target.value))} className="w-full bg-zinc-50 border-none rounded-lg px-2 py-2 text-xs font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500" />
                                                            </div>
                                                            <div className="flex-1">
                                                                <label className="text-[9px] font-black text-zinc-400 uppercase tracking-widest block mb-1">Provider ID</label>
                                                                <select value={upstream.upstream_provider_id} onChange={e => updateUpstream(index, 'upstream_provider_id', parseInt(e.target.value))} className="w-full bg-zinc-50 border-none rounded-lg px-2 py-2 text-xs font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500">
                                                                    {providers.map(p => (
                                                                        <option key={p.id} value={p.id}>{p.name}</option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                            <div className="flex-1">
                                                                <label className="text-[9px] font-black text-zinc-400 uppercase tracking-widest block mb-1">Ext Service ID</label>
                                                                <input type="text" value={upstream.external_service_id} onChange={e => updateUpstream(index, 'external_service_id', e.target.value)} className="w-full bg-zinc-50 border-none rounded-lg px-2 py-2 text-xs font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500" />
                                                            </div>
                                                            <button type="button" onClick={() => removeUpstream(index)} className="mt-5 p-2 text-zinc-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all">
                                                                <Trash2 size={14} />
                                                            </button>
                                                        </div>
                                                        {upstream.external_rate !== null && upstream.external_rate !== undefined && (
                                                            <div className="mt-2 pl-2 text-[10px] font-bold text-zinc-400 uppercase tracking-widest">
                                                                Provider cost: <span className="text-zinc-700">${Number(upstream.external_rate).toFixed(4)}</span> / 1k
                                                            </div>
                                                        )}
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </section>

                                    {/* Toggles */}
                                    <section className="bg-white p-5 rounded-3xl border border-zinc-200 shadow-sm">
                                        <div className="flex flex-wrap gap-4">
                                            {['is_active', 'is_dripfeed', 'is_refill'].map(field => (
                                                <label key={field} className={`flex-1 flex items-center justify-center gap-2 p-3 rounded-2xl border-2 cursor-pointer transition-all ${form[field] ? 'border-emerald-500 bg-emerald-50' : 'border-zinc-100 bg-zinc-50'}`}>
                                                    <input type="checkbox" checked={form[field]} onChange={e => setForm({ ...form, [field]: e.target.checked })} className="hidden" />
                                                    <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center ${form[field] ? 'border-emerald-500 bg-emerald-500' : 'border-zinc-300'}`}>
                                                        {form[field] && <div className="w-1.5 h-1.5 rounded-full bg-white" />}
                                                    </div>
                                                    <span className={`text-xs font-bold uppercase tracking-widest ${form[field] ? 'text-emerald-700' : 'text-zinc-500'}`}>
                                                        {field.replace(/^is_/, '')}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </section>
                                </div>
                                
                                <div className="p-6 border-t border-zinc-100 bg-white">
                                    <button onClick={submitForm} className="w-full py-4 bg-zinc-900 hover:bg-emerald-500 text-white font-black uppercase tracking-widest rounded-2xl transition-all shadow-xl hover:shadow-emerald-500/20 active:scale-95">
                                        {editingId ? 'Update Node Configuration' : 'Deploy Node Configuration'}
                                    </button>
                                </div>
                            </motion.div>
                        </>
                    )}
                </AnimatePresence>

            </div>

            {pendingDeactivateId !== null && (
                <ConfirmModal
                    open
                    title="Halt Service"
                    message="Are you sure you want to take this service offline? Users will no longer be able to place orders for it."
                    confirmLabel="Take Offline"
                    danger
                    onConfirm={() => { router.delete(route('admin.services.destroy', pendingDeactivateId), { preserveScroll: true }); setPendingDeactivateId(null); }}
                    onCancel={() => setPendingDeactivateId(null)}
                />
            )}
            {showBulkDeleteConfirm && (
                <ConfirmModal
                    open
                    title="Delete Inactive Services"
                    message={`Permanently delete all ${stats.inactive} inactive service(s)? Services that have order history are kept automatically (deactivated, not deletable) — only inactive services with zero orders ever placed are removed. This cannot be undone.`}
                    confirmLabel={bulkDeleting ? 'Deleting…' : 'Delete Inactive Services'}
                    danger
                    onConfirm={bulkDeleteInactive}
                    onCancel={() => setShowBulkDeleteConfirm(false)}
                />
            )}
            {/* Export List (WhatsApp-ready plain text) */}
            <AnimatePresence>
                {showExportModal && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-zinc-950/40 backdrop-blur-sm z-40"
                            onClick={() => setShowExportModal(false)}
                        />
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95, y: 20 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.95, y: 20 }}
                            className="fixed inset-0 z-50 flex items-center justify-center p-6"
                        >
                            <div className="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
                                <div className="p-6 border-b border-zinc-100 flex justify-between items-start">
                                    <div>
                                        <h3 className="text-xl font-black text-zinc-900">Export Service List</h3>
                                        <p className="text-xs font-medium text-zinc-500 mt-1">
                                            Clean, WhatsApp-ready text — service name, price per 1000, and minimum order.
                                        </p>
                                    </div>
                                    <button onClick={() => setShowExportModal(false)} className="p-2 bg-zinc-100 text-zinc-500 hover:text-zinc-900 rounded-full transition-colors">
                                        <X size={16} />
                                    </button>
                                </div>

                                <div className="p-6 border-b border-zinc-100 grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Category (optional)</label>
                                        <select
                                            value={exportCategory}
                                            onChange={e => { setExportCategory(e.target.value); fetchExportList(e.target.value, exportPlatform); }}
                                            className="w-full pl-4 pr-4 py-3 rounded-2xl bg-zinc-50 border-none font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500 transition-all cursor-pointer appearance-none"
                                        >
                                            <option value="">All Categories</option>
                                            {categories.map(c => (
                                                <option key={c} value={c}>{c}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Platform</label>
                                        <select
                                            value={exportPlatform}
                                            onChange={e => { setExportPlatform(e.target.value); fetchExportList(exportCategory, e.target.value); }}
                                            className="w-full pl-4 pr-4 py-3 rounded-2xl bg-zinc-50 border-none font-bold text-zinc-900 focus:ring-2 focus:ring-emerald-500 transition-all cursor-pointer appearance-none"
                                        >
                                            <option value="plain">Plain Text</option>
                                            <option value="WhatsApp">WhatsApp</option>
                                            <option value="Telegram">Telegram</option>
                                            <option value="Twitter/X">Twitter / X</option>
                                            <option value="Instagram">Instagram</option>
                                            <option value="Facebook">Facebook</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="flex-1 overflow-y-auto p-6">
                                    {exportPlatform !== 'plain' && exportAiUsed !== null && (
                                        <p className={`mb-2 text-[10px] font-black uppercase tracking-widest ${exportAiUsed ? 'text-emerald-600' : 'text-amber-600'}`}>
                                            {exportAiUsed ? '✨ AI-enhanced — double check before sending' : 'AI unavailable — showing plain list'}
                                        </p>
                                    )}
                                    <textarea
                                        readOnly
                                        value={exportLoading ? 'Loading…' : exportText}
                                        className="w-full h-64 bg-zinc-50 border-none rounded-2xl p-4 text-xs font-mono text-zinc-800 focus:ring-2 focus:ring-emerald-500 resize-none"
                                    />
                                </div>

                                <div className="p-6 bg-zinc-50 border-t border-zinc-100">
                                    <button
                                        onClick={copyExportList}
                                        disabled={exportLoading || !exportText}
                                        className="w-full flex items-center justify-center gap-2 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm uppercase tracking-widest rounded-xl transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        <Copy size={16} /> Copy to Clipboard
                                    </button>
                                </div>
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Merge Categories */}
            <AnimatePresence>
                {showCategoryModal && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-zinc-950/40 backdrop-blur-sm z-40"
                            onClick={() => setShowCategoryModal(false)}
                        />
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95, y: 20 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.95, y: 20 }}
                            className="fixed inset-0 z-50 flex items-center justify-center p-6"
                        >
                            <div className="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
                                <div className="p-6 border-b border-zinc-100 flex justify-between items-start">
                                    <div>
                                        <h3 className="text-xl font-black text-zinc-900">Merge Categories</h3>
                                        <p className="text-xs font-medium text-zinc-500 mt-1">
                                            Select 2 or more categories that represent the same platform, then choose the name they should all become.
                                        </p>
                                    </div>
                                    <button onClick={() => setShowCategoryModal(false)} className="p-2 bg-zinc-100 text-zinc-500 hover:text-zinc-900 rounded-full transition-colors">
                                        <X size={16} />
                                    </button>
                                </div>

                                <div className="flex-1 overflow-y-auto p-6 space-y-2">
                                    {categories.map(cat => (
                                        <label key={cat} className={`flex items-center justify-between gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all ${selectedCategories.includes(cat) ? 'border-indigo-400 bg-indigo-50' : 'border-zinc-100 bg-zinc-50 hover:border-zinc-200'}`}>
                                            <div className="flex items-center gap-3 min-w-0">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedCategories.includes(cat)}
                                                    onChange={() => toggleCategorySelection(cat)}
                                                    className="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                                                />
                                                <span className="text-sm font-bold text-zinc-900 truncate">{cat}</span>
                                            </div>
                                            <span className="text-[10px] font-black text-zinc-400 uppercase tracking-widest shrink-0">{categoryCounts[cat] ?? 0} services</span>
                                        </label>
                                    ))}
                                </div>

                                <div className="p-6 bg-zinc-50 border-t border-zinc-100 space-y-3">
                                    <div>
                                        <label className="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Merge into category name</label>
                                        <input
                                            type="text"
                                            value={mergeTarget}
                                            onChange={e => setMergeTarget(e.target.value)}
                                            placeholder="e.g. Instagram"
                                            className="w-full bg-white border-2 border-zinc-200 rounded-xl px-4 py-3 text-sm font-bold text-zinc-900 focus:outline-none focus:border-indigo-500"
                                        />
                                    </div>
                                    <button
                                        onClick={mergeCategories}
                                        disabled={merging || selectedCategories.length < 2 || !mergeTarget.trim()}
                                        className="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm uppercase tracking-widest rounded-xl transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        {merging ? 'Merging…' : selectedCategories.length >= 2 ? `Merge ${selectedCategories.length} Categories` : 'Select at least 2 categories'}
                                    </button>
                                </div>
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            <ToastContainer toast={activeToast} onClose={() => setActiveToast(null)} />
        </AdminLayout>
    );
}
