import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { PageProps } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Edit2, Plus, Trash2, CheckCircle2, XCircle, RefreshCcw, Download } from 'lucide-react';

interface UpstreamProvider {
    id: number;
    name: string;
    url: string;
    api_key: string;
    is_active: boolean;
    balance: string;
    created_at: string;
}

export default function UpstreamProvidersIndex({ auth, providers }: PageProps<{ providers: UpstreamProvider[] }>) {
    const [editingProvider, setEditingProvider] = useState<UpstreamProvider | null>(null);
    const [isCreating, setIsCreating] = useState(false);
    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        name: '',
        url: '',
        api_key: '',
        is_active: true,
    });

    const openCreate = () => {
        reset();
        setEditingProvider(null);
        setIsCreating(true);
    };

    const openEdit = (provider: UpstreamProvider) => {
        setData({
            name: provider.name,
            url: provider.url,
            api_key: provider.api_key,
            is_active: provider.is_active,
        });
        setEditingProvider(provider);
        setIsCreating(false);
    };

    const closeForm = () => {
        setEditingProvider(null);
        setIsCreating(false);
        reset();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingProvider) {
            put(route('admin.upstream-providers.update', editingProvider.id), {
                onSuccess: () => closeForm(),
            });
        } else {
            post(route('admin.upstream-providers.store'), {
                onSuccess: () => closeForm(),
            });
        }
    };

    const deleteProvider = (id: number) => {
        setPendingDeleteId(id);
    };

    const syncBalance = (id: number) => {
        router.post(route('admin.upstream-providers.sync-balance', id), {}, {
            preserveScroll: true,
        });
    };

    const importServices = (id: number) => {
        if (confirm('Are you sure you want to import all active services from this provider? This may take a while.')) {
            router.post(route('admin.upstream-providers.import-services', id), {}, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-bold text-gray-900">Upstream Providers</h2>}>
            <Head title="Upstream Providers" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                    
                    <div className="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">API Providers</h3>
                            <p className="text-sm text-gray-500">Manage SMM API providers for order fulfillment</p>
                        </div>
                        <button
                            onClick={openCreate}
                            className="inline-flex items-center gap-2 rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-green/90 shadow-sm"
                        >
                            <Plus className="h-4 w-4" />
                            Add Provider
                        </button>
                    </div>

                    {(isCreating || editingProvider) && (
                        <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-lg font-bold text-gray-900">
                                {editingProvider ? 'Edit Provider' : 'Add New Provider'}
                            </h3>
                            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Provider Name</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        placeholder="e.g. SMMFollows"
                                        className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none transition-all shadow-sm"
                                        required
                                    />
                                    {errors.name && <p className="text-red-600 text-xs mt-1">{errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">API URL</label>
                                    <input
                                        type="url"
                                        value={data.url}
                                        onChange={e => setData('url', e.target.value)}
                                        placeholder="https://provider.com/api/v2"
                                        className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none transition-all shadow-sm"
                                        required
                                    />
                                    {errors.url && <p className="text-red-600 text-xs mt-1">{errors.url}</p>}
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">API Key</label>
                                    <input
                                        type="text"
                                        value={data.api_key}
                                        onChange={e => setData('api_key', e.target.value)}
                                        className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none transition-all shadow-sm"
                                        required
                                    />
                                    {errors.api_key && <p className="text-red-600 text-xs mt-1">{errors.api_key}</p>}
                                </div>
                                <div className="sm:col-span-2 flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        checked={data.is_active}
                                        onChange={e => setData('is_active', e.target.checked)}
                                        className="rounded border-gray-300 text-brand-green focus:ring-brand-green"
                                    />
                                    <label htmlFor="is_active" className="text-sm font-medium text-gray-700">Active (orders will route here)</label>
                                </div>
                                <div className="sm:col-span-2 mt-4 flex gap-3">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-lg bg-brand-green px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-green/90 disabled:opacity-50 shadow-sm"
                                    >
                                        {processing ? 'Saving...' : 'Save Provider'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={closeForm}
                                        className="rounded-lg border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 shadow-sm"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm flex flex-col">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm text-left">
                                <thead className="bg-gray-50 border-b border-gray-200">
                                    <tr className="text-gray-500 font-medium">
                                        <th className="px-6 py-3">Name</th>
                                        <th className="px-6 py-3">URL</th>
                                        <th className="px-6 py-3">Status</th>
                                        <th className="px-6 py-3">Balance</th>
                                        <th className="px-6 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {providers.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-6 py-8 text-center text-gray-500">
                                                No providers found. Add one above.
                                            </td>
                                        </tr>
                                    ) : (
                                        providers.map((p) => (
                                            <tr key={p.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="whitespace-nowrap px-6 py-4 font-medium text-gray-900">{p.name}</td>
                                                <td className="px-6 py-4 text-gray-500">{p.url}</td>
                                                <td className="px-6 py-4">
                                                    {p.is_active ? (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-green/20 bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green uppercase tracking-wider">
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                            Active
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                            <XCircle className="h-3.5 w-3.5" />
                                                            Inactive
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-gray-900 font-bold font-mono">
                                                    ${Number(p.balance).toFixed(4)}
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right">
                                                    <button
                                                        onClick={() => syncBalance(p.id)}
                                                        title="Sync Balance"
                                                        className="inline-flex items-center gap-1 rounded-md p-2 text-blue-600 hover:bg-blue-50 transition-colors"
                                                    >
                                                        <RefreshCcw className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => importServices(p.id)}
                                                        title="Import Services"
                                                        className="inline-flex items-center gap-1 rounded-md p-2 text-indigo-600 hover:bg-indigo-50 ms-1 transition-colors"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => openEdit(p)}
                                                        className="inline-flex items-center gap-1 rounded-md p-2 text-brand-green hover:bg-brand-green/10 transition-colors ms-1"
                                                    >
                                                        <Edit2 className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => deleteProvider(p.id)}
                                                        className="inline-flex items-center gap-1 rounded-md p-2 text-red-600 hover:bg-red-50 ms-1 transition-colors"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
            
            {pendingDeleteId !== null && (
                <ConfirmModal
                    open
                    title="Delete Upstream Provider"
                    message="Are you sure you want to delete this provider? This will break any services linked to it!"
                    confirmLabel="Delete"
                    danger
                    onConfirm={() => { destroy(route('admin.upstream-providers.destroy', pendingDeleteId)); setPendingDeleteId(null); }}
                    onCancel={() => setPendingDeleteId(null)}
                />
            )}
        </AdminLayout>
    );
}
