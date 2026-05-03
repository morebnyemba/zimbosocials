import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import ToastContainer, { ToastKind } from '@/Components/Toast';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';

interface UpstreamProvider { id: number; name: string; url: string; }
interface ServiceUpstream { id?: number; upstream_provider_id: number; external_service_id: string; priority: number; provider?: UpstreamProvider; }
interface Service { id: number; name: string; name_sn?: string; category: string; type: string; rate: string; min_qty: number; max_qty: number; is_active: boolean; is_dripfeed: boolean; is_refill: boolean; refill_days?: number; avg_time_minutes?: number; display_order?: number; orders_count: number; description?: string; description_sn?: string; upstreams?: ServiceUpstream[]; }
interface Props { services: { data: Service[]; links: any[]; total: number }; categories: string[]; providers: UpstreamProvider[]; stats: { total: number; active: number; inactive: number }; filters: Record<string, string>; }

const emptyForm = { name: '', name_sn: '', description: '', description_sn: '', category: '', type: 'default', rate: '', min_qty: '100', max_qty: '10000', is_active: true, is_dripfeed: false, is_refill: false, refill_days: '', avg_time_minutes: '', display_order: '0', upstreams: [] as ServiceUpstream[] };

export default function ServicesIndex({ services, categories, providers, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState<any>(emptyForm);
    const [pendingDeactivateId, setPendingDeactivateId] = useState<number | null>(null);
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

    return (
        <AdminLayout>
            <Head title="Service Management" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Services</h1>
                        <p className="text-gray-500 text-sm mt-1">{stats.active} active · {stats.inactive} inactive</p>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={openCreate} className="flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-brand-green text-white hover:bg-brand-green/90 shadow-sm transition-colors">
                            <Plus size={16} /> Add Service
                        </button>
                    </div>
                </div>

                {/* Category Tabs */}
                <div className="flex gap-2 flex-wrap">
                    <button onClick={() => filterCategory('')} className={`px-4 py-1.5 text-sm font-medium rounded-full border transition-all ${!filters.category ? 'bg-brand-green/10 text-brand-green border-brand-green/30' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 shadow-sm'}`}>All</button>
                    {categories.map(c => (
                        <button key={c} onClick={() => filterCategory(c)} className={`px-4 py-1.5 text-sm font-medium rounded-full border transition-all ${filters.category === c ? 'bg-brand-green/10 text-brand-green border-brand-green/30' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 shadow-sm'}`}>{c}</button>
                    ))}
                </div>

                {/* Search */}
                <div className="flex gap-3">
                    <input type="text" value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && applySearch()} placeholder="Search services..." className="flex-1 rounded-xl bg-white border border-gray-200 text-gray-900 text-sm px-4 py-2.5 focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 outline-none transition-all placeholder:text-gray-400 shadow-sm" />
                    <button onClick={applySearch} className="px-5 py-2.5 rounded-xl bg-brand-green text-white font-medium text-sm hover:bg-brand-green/90 transition-colors shadow-sm">Search</button>
                </div>

                {/* Table */}
                <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-gray-500 font-medium text-xs uppercase tracking-wider">
                                    <th className="py-3 px-5">ID</th><th className="py-3 px-5">Name</th><th className="py-3 px-5">Providers</th>
                                    <th className="text-right py-3 px-5">Rate/1K</th><th className="text-center py-3 px-5">Min–Max</th><th className="text-center py-3 px-5">Orders</th>
                                    <th className="text-center py-3 px-5">Active</th><th className="text-right py-3 px-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {services.data.map(s => (
                                    <tr key={s.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="py-3 px-5 font-medium text-gray-500">#{s.id}</td>
                                        <td className="py-3 px-5 text-gray-900 truncate max-w-[200px]">
                                            <div className="font-medium">{s.name}</div>
                                            <div className="text-xs text-gray-500 mt-0.5">{s.category}</div>
                                        </td>
                                        <td className="py-3 px-5">
                                            <div className="flex flex-col gap-1.5">
                                                {s.upstreams?.map((u, i) => (
                                                    <span key={i} className={`inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border ${i === 0 ? 'bg-brand-green/10 text-brand-green border-brand-green/20' : 'bg-gray-100 text-gray-600 border-gray-200'} w-max`}>
                                                        {u.provider?.name || 'Unknown'} (ID: {u.external_service_id})
                                                    </span>
                                                ))}
                                                {(!s.upstreams || s.upstreams.length === 0) && <span className="text-xs text-gray-400 italic">None</span>}
                                            </div>
                                        </td>
                                        <td className="py-3 px-5 text-right text-gray-900 font-mono font-bold">${Number(s.rate).toFixed(2)}</td>
                                        <td className="py-3 px-5 text-center text-gray-500 text-xs font-medium">{s.min_qty}–{s.max_qty}</td>
                                        <td className="py-3 px-5 text-center text-gray-600 font-medium">{s.orders_count}</td>
                                        <td className="py-3 px-5 text-center">{s.is_active ? <span className="text-brand-green font-bold">✓</span> : <span className="text-red-500 font-bold">✗</span>}</td>
                                        <td className="py-3 px-5 text-right space-x-3">
                                            <button onClick={() => openEdit(s)} className="text-brand-green hover:text-brand-green/80 font-medium text-xs">Edit</button>
                                            {s.is_active && <button onClick={() => deactivate(s.id)} className="text-red-600 hover:text-red-800 font-medium text-xs">Deactivate</button>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {services.links && services.data.length > 0 && (
                        <div className="flex justify-center gap-1 py-4 border-t border-gray-100 bg-gray-50">
                            {services.links.map((l: any, i: number) => <Link key={i} href={l.url || '#'} className={`px-3 py-1.5 text-xs font-medium rounded-lg ${l.active ? 'bg-brand-green text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200'}`} dangerouslySetInnerHTML={{ __html: l.label }} />)}
                        </div>
                    )}
                </div>

                {/* Create/Edit Modal */}
                {showForm && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 backdrop-blur-sm overflow-y-auto py-8 px-4" onClick={() => setShowForm(false)}>
                        <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-full" onClick={e => e.stopPropagation()}>
                            <div className="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h3 className="text-lg font-bold text-gray-900">{editingId ? 'Edit Service' : 'Add Service'}</h3>
                                <button type="button" onClick={() => setShowForm(false)} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                            </div>
                            
                            <div className="p-5 overflow-y-auto flex-1 space-y-5">
                                {/* Basic Fields */}
                                <div className="grid grid-cols-2 gap-4">
                                    {['name', 'category', 'type', 'rate', 'min_qty', 'max_qty', 'display_order'].map(field => (
                                        <div key={field} className={field === 'name' ? 'col-span-2' : ''}>
                                            <label className="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1.5">{field.replace(/_/g, ' ')}</label>
                                            <input type={['rate', 'min_qty', 'max_qty', 'display_order'].includes(field) ? 'number' : 'text'} value={form[field]} onChange={e => setForm({ ...form, [field]: e.target.value })} className="w-full rounded-lg bg-white border border-gray-300 text-gray-900 text-sm px-3 py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 shadow-sm transition-shadow" />
                                        </div>
                                    ))}
                                </div>

                                {/* Upstream Providers Config */}
                                <div className="rounded-xl border border-gray-200 bg-gray-50 p-5">
                                    <div className="flex items-center justify-between mb-4">
                                        <h4 className="text-sm font-bold text-gray-900">Upstream API Providers</h4>
                                        <button type="button" onClick={addUpstream} className="inline-flex items-center gap-1 text-xs font-medium text-brand-green hover:text-brand-green/80">
                                            <Plus className="h-3.5 w-3.5" /> Add Provider Fallback
                                        </button>
                                    </div>
                                    <div className="space-y-3">
                                        {form.upstreams.length === 0 ? (
                                            <p className="text-sm text-gray-500 italic bg-white p-4 rounded-lg border border-gray-200 text-center">No providers mapped. This service will be fulfilled manually.</p>
                                        ) : (
                                            form.upstreams.map((upstream: ServiceUpstream, index: number) => (
                                                <div key={index} className="flex items-center gap-3 bg-white p-3.5 rounded-lg border border-gray-200 shadow-sm">
                                                    <div className="w-16">
                                                        <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Pri</label>
                                                        <input type="number" min="1" value={upstream.priority} onChange={e => updateUpstream(index, 'priority', parseInt(e.target.value))} className="w-full bg-white text-gray-900 text-sm rounded-md border border-gray-300 px-2.5 py-1.5 focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 outline-none" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Provider</label>
                                                        <select value={upstream.upstream_provider_id} onChange={e => updateUpstream(index, 'upstream_provider_id', parseInt(e.target.value))} className="w-full bg-white text-gray-900 text-sm rounded-md border border-gray-300 px-2.5 py-1.5 focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 outline-none">
                                                            {providers.map(p => (
                                                                <option key={p.id} value={p.id}>{p.name}</option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                    <div className="flex-1">
                                                        <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Ext ID</label>
                                                        <input type="text" value={upstream.external_service_id} onChange={e => updateUpstream(index, 'external_service_id', e.target.value)} placeholder="e.g. 1024" className="w-full bg-white text-gray-900 text-sm rounded-md border border-gray-300 px-2.5 py-1.5 focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 outline-none" />
                                                    </div>
                                                    <button type="button" onClick={() => removeUpstream(index)} className="mt-5 p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors" title="Remove upstream">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            ))
                                        )}
                                        {form.upstreams.length > 0 && (
                                            <p className="text-xs mt-2 flex items-center gap-1.5 bg-blue-50 text-blue-700 p-2.5 rounded-lg border border-blue-100">
                                                <span className="font-bold">Info:</span> Orders will route to Priority 1 first. If failed, it tries Priority 2, etc.
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-5 pt-3">
                                    {['is_active', 'is_dripfeed', 'is_refill'].map(field => (
                                        <label key={field} className="flex items-center gap-2.5 text-sm font-medium text-gray-700 cursor-pointer">
                                            <input type="checkbox" checked={form[field]} onChange={e => setForm({ ...form, [field]: e.target.checked })} className="w-4 h-4 rounded border-gray-300 text-brand-green focus:ring-brand-green cursor-pointer" />
                                            {field.replace(/^is_/, '').charAt(0).toUpperCase() + field.replace(/^is_/, '').slice(1)}
                                        </label>
                                    ))}
                                </div>
                            </div>
                            
                            <div className="p-5 border-t border-gray-100 bg-gray-50 flex gap-3 justify-end">
                                <button onClick={() => setShowForm(false)} className="px-5 py-2.5 text-sm font-medium rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm">Cancel</button>
                                <button onClick={submitForm} className="px-5 py-2.5 text-sm font-medium rounded-lg bg-brand-green text-white hover:bg-brand-green/90 shadow-sm">Save Service</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {pendingDeactivateId !== null && (
                <ConfirmModal
                    open
                    title="Deactivate Service"
                    message="Deactivate this service? It will no longer be visible to users."
                    confirmLabel="Deactivate"
                    danger
                    onConfirm={() => { router.delete(route('admin.services.destroy', pendingDeactivateId), { preserveScroll: true }); setPendingDeactivateId(null); }}
                    onCancel={() => setPendingDeactivateId(null)}
                />
            )}
            <ToastContainer toast={activeToast} onClose={() => setActiveToast(null)} />
        </AdminLayout>
    );
}
