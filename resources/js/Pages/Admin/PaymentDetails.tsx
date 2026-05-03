import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Edit2, Trash2 } from 'lucide-react';

interface PaymentDetail { id: number; method_key: string; label: string; account_name?: string; account_number?: string; instructions?: string; is_active: boolean; sort_order: number; gateway_type?: string | null; }
interface Props { paymentDetails: PaymentDetail[]; }

export default function PaymentDetails({ paymentDetails }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);
    const { data, setData, post, put, processing, reset } = useForm({ method_key: '', label: '', account_name: '', account_number: '', instructions: '', sort_order: 0, is_active: true, gateway_type: '' });

    const openCreate = () => { reset(); setEditingId(null); setShowForm(true); };
    const openEdit = (p: PaymentDetail) => { setData({ method_key: p.method_key, label: p.label, account_name: p.account_name || '', account_number: p.account_number || '', instructions: p.instructions || '', sort_order: p.sort_order, is_active: p.is_active, gateway_type: p.gateway_type || '' }); setEditingId(p.id); setShowForm(true); };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) { put(route('admin.payment-details.update', editingId), { preserveScroll: true, onSuccess: () => setShowForm(false) }); }
        else { post(route('admin.payment-details.store'), { preserveScroll: true, onSuccess: () => { setShowForm(false); reset(); } }); }
    };

    const del = (id: number) => { setPendingDeleteId(id); };

    return (
        <AdminLayout>
            <Head title="Payment Details" />
            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Payment Methods</h1>
                        <p className="text-gray-500 text-sm mt-1">Configure available payment options for users</p>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={openCreate} className="flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-brand-green text-white hover:bg-brand-green/90 shadow-sm transition-colors">
                            <Plus size={16} /> Add Method
                        </button>
                    </div>
                </div>

                <div className="space-y-4">
                    {paymentDetails.map(p => (
                        <div key={p.id} className="flex items-start sm:items-center gap-4 p-5 rounded-xl border border-gray-200 bg-white shadow-sm hover:shadow-md transition-shadow">
                            <div className="flex-1">
                                <div className="flex items-center gap-3">
                                    <span className="text-gray-900 font-bold">{p.label}</span>
                                    <span className="px-2.5 py-0.5 text-xs font-mono font-medium rounded-md bg-gray-100 text-gray-600 border border-gray-200">{p.method_key}</span>
                                    {p.gateway_type === 'paynow' && <span className="px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider rounded-full bg-blue-50 text-blue-600 border border-blue-100">Paynow Gateway</span>}
                                    {!p.is_active && <span className="px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider rounded-full bg-red-50 text-red-600 border border-red-100">Inactive</span>}
                                </div>
                                {p.account_name && <p className="text-sm text-gray-600 mt-2 font-medium">{p.account_name} <span className="text-gray-400 mx-1">•</span> <span className="font-mono">{p.account_number}</span></p>}
                                {p.instructions && <p className="text-xs text-gray-500 mt-2 bg-gray-50 p-2.5 rounded-lg border border-gray-100">{p.instructions}</p>}
                            </div>
                            <div className="flex items-center gap-2 mt-2 sm:mt-0">
                                <button onClick={() => openEdit(p)} className="p-2 text-gray-400 hover:text-brand-green hover:bg-brand-green/10 rounded-lg transition-colors" title="Edit">
                                    <Edit2 size={16} />
                                </button>
                                <button onClick={() => del(p.id)} className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        </div>
                    ))}
                    {paymentDetails.length === 0 && (
                        <div className="text-center py-12 bg-white rounded-xl border border-gray-200 border-dashed">
                            <p className="text-gray-500 font-medium">No payment methods configured</p>
                            <button onClick={openCreate} className="mt-4 text-brand-green font-medium hover:underline">Add your first method</button>
                        </div>
                    )}
                </div>

                {showForm && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 backdrop-blur-sm p-4" onClick={() => setShowForm(false)}>
                        <form onSubmit={submit} className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]" onClick={e => e.stopPropagation()}>
                            <div className="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <h3 className="text-lg font-bold text-gray-900">{editingId ? 'Edit' : 'Add'} Payment Method</h3>
                                <button type="button" onClick={() => setShowForm(false)} className="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                            </div>
                            
                            <div className="p-5 overflow-y-auto flex-1 space-y-4">
                                {['method_key', 'label', 'account_name', 'account_number'].map(f => (
                                    <div key={f}>
                                        <label className="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1.5">{f.replace(/_/g, ' ')}</label>
                                        <input type="text" value={(data as any)[f]} onChange={e => setData(f as any, e.target.value)} className="w-full rounded-lg bg-white border border-gray-300 text-gray-900 text-sm px-3 py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 shadow-sm transition-shadow" placeholder={`Enter ${f.replace(/_/g, ' ')}`} />
                                    </div>
                                ))}
                                <div>
                                    <label className="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1.5">Instructions</label>
                                    <textarea value={data.instructions} onChange={e => setData('instructions', e.target.value)} rows={3} className="w-full rounded-lg bg-white border border-gray-300 text-gray-900 text-sm px-3 py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 shadow-sm transition-shadow resize-none" placeholder="Optional payment instructions for users" />
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-700 uppercase tracking-wider block mb-1.5">Gateway Type</label>
                                    <select value={data.gateway_type} onChange={e => setData('gateway_type', e.target.value)} className="w-full rounded-lg bg-white border border-gray-300 text-gray-900 text-sm px-3 py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 shadow-sm">
                                        <option value="">Manual (bank transfer / cash)</option>
                                        <option value="paynow">Paynow Gateway (automatic)</option>
                                    </select>
                                    <p className="text-xs text-gray-400 mt-1">Paynow Gateway methods redirect users to the Paynow payment portal instead of showing bank details.</p>
                                </div>
                                <label className="flex items-center gap-2.5 text-sm font-medium text-gray-700 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                                    <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="w-4 h-4 rounded border-gray-300 text-brand-green focus:ring-brand-green cursor-pointer" /> 
                                    Method is active and visible to users
                                </label>
                            </div>
                            
                            <div className="p-5 border-t border-gray-100 bg-gray-50 flex gap-3 justify-end">
                                <button type="button" onClick={() => setShowForm(false)} className="px-5 py-2 text-sm font-medium rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm">Cancel</button>
                                <button type="submit" disabled={processing} className="px-5 py-2 text-sm font-medium rounded-lg bg-brand-green text-white hover:bg-brand-green/90 shadow-sm disabled:opacity-50">Save Method</button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
            
            {pendingDeleteId !== null && (
                <ConfirmModal
                    open
                    title="Delete Payment Method"
                    message="Are you sure you want to delete this payment method? This cannot be undone."
                    confirmLabel="Delete"
                    danger
                    onConfirm={() => { router.delete(route('admin.payment-details.destroy', pendingDeleteId), { preserveScroll: true }); setPendingDeleteId(null); }}
                    onCancel={() => setPendingDeleteId(null)}
                />
            )}
        </AdminLayout>
    );
}
