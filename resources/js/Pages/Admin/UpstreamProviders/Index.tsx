import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import Modal from '@/Components/Modal';
import { PageProps } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import { Edit2, Plus, Trash2, CheckCircle2, XCircle, RefreshCcw, Download, LoaderCircle, Search } from 'lucide-react';

interface UpstreamProvider {
    id: number;
    name: string;
    url: string;
    api_key: string;
    is_active: boolean;
    balance: string;
    created_at: string;
}

interface ImportableService {
    external_service_id: string;
    name: string;
    description: string;
    category: string;
    type: string;
    external_rate: number;
    min_qty: number;
    max_qty: number;
    is_dripfeed: boolean;
    is_refill: boolean;
    already_imported: boolean;
    existing_service_name?: string | null;
    default_markup_type?: 'percentage' | 'fixed';
    default_markup_value?: number;
}

interface ImportSelection {
    selected: boolean;
    markup_type: 'percentage' | 'fixed';
    markup_value: string;
}

export default function UpstreamProvidersIndex({ auth, providers, aiEnrichmentEnabled }: PageProps<{ providers: UpstreamProvider[]; aiEnrichmentEnabled?: boolean }>) {
    const [editingProvider, setEditingProvider] = useState<UpstreamProvider | null>(null);
    const [isCreating, setIsCreating] = useState(false);
    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);
    const [importProvider, setImportProvider] = useState<UpstreamProvider | null>(null);
    const [availableServices, setAvailableServices] = useState<ImportableService[]>([]);
    const [serviceSelections, setServiceSelections] = useState<Record<string, ImportSelection>>({});
    const [loadingImportServices, setLoadingImportServices] = useState(false);
    const [importingServices, setImportingServices] = useState(false);
    const [importSearch, setImportSearch] = useState('');
    const [importCategory, setImportCategory] = useState('');
    const [hideAlreadyImported, setHideAlreadyImported] = useState(false);
    const [bulkMarkupType, setBulkMarkupType] = useState<'percentage' | 'fixed'>('percentage');
    const [bulkMarkupValue, setBulkMarkupValue] = useState('');
    const [importError, setImportError] = useState('');
    const [enrichWithAi, setEnrichWithAi] = useState(false);
    const [collapsedCategories, setCollapsedCategories] = useState<Record<string, boolean>>({});

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

    const closeImportModal = () => {
        setImportProvider(null);
        setAvailableServices([]);
        setServiceSelections({});
        setLoadingImportServices(false);
        setImportingServices(false);
        setImportSearch('');
        setImportCategory('');
        setHideAlreadyImported(false);
        setBulkMarkupType('percentage');
        setBulkMarkupValue('');
        setImportError('');
        setEnrichWithAi(false);
        setCollapsedCategories({});
    };

    const openImportModal = async (provider: UpstreamProvider) => {
        setImportProvider(provider);
        setLoadingImportServices(true);
        setAvailableServices([]);
        setServiceSelections({});
        setImportSearch('');
        setImportCategory('');
        setHideAlreadyImported(false);
        setBulkMarkupType('percentage');
        setBulkMarkupValue('');
        setImportError('');
        setCollapsedCategories({});

        try {
            const response = await fetch(route('admin.upstream-providers.available-services', provider.id), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Failed to load services from provider.');
            }

            const services = Array.isArray(payload.services) ? payload.services as ImportableService[] : [];
            const initialSelections = services.reduce<Record<string, ImportSelection>>((acc, service) => {
                acc[service.external_service_id] = {
                    selected: !service.already_imported,
                    markup_type: service.default_markup_type || 'percentage',
                    markup_value: String(service.default_markup_value || 0),
                };

                return acc;
            }, {});

            setAvailableServices(services);
            setServiceSelections(initialSelections);
            setBulkMarkupType(services[0]?.default_markup_type || 'percentage');
            setBulkMarkupValue(services[0] ? String(services[0].default_markup_value || 0) : '');
        } catch (error) {
            setImportError(error instanceof Error ? error.message : 'Failed to load services from provider.');
        } finally {
            setLoadingImportServices(false);
        }
    };

    const updateServiceSelection = (externalServiceId: string, patch: Partial<ImportSelection>) => {
        setServiceSelections(current => ({
            ...current,
            [externalServiceId]: {
                ...current[externalServiceId],
                ...patch,
            },
        }));
    };

    // Categories present in this provider's catalog, alphabetically, with counts —
    // drives the category filter dropdown and the grouped table below.
    const categoryCounts = useMemo(() => {
        const counts: Record<string, number> = {};
        availableServices.forEach(service => {
            counts[service.category] = (counts[service.category] ?? 0) + 1;
        });
        return counts;
    }, [availableServices]);

    const categories = useMemo(
        () => Object.keys(categoryCounts).sort((a, b) => a.localeCompare(b)),
        [categoryCounts]
    );

    const visibleServices = useMemo(() => {
        const query = importSearch.trim().toLowerCase();

        return availableServices.filter(service => {
            if (importCategory && service.category !== importCategory) return false;
            if (hideAlreadyImported && service.already_imported) return false;
            if (!query) return true;

            return (
                service.name.toLowerCase().includes(query)
                || service.category.toLowerCase().includes(query)
                || service.type.toLowerCase().includes(query)
                || service.external_service_id.toLowerCase().includes(query)
            );
        });
    }, [availableServices, importSearch, importCategory, hideAlreadyImported]);

    // Visible services grouped by category, in the same alphabetical order as
    // the category filter, each with its own selectable/selected counts.
    const groupedVisibleServices = useMemo(() => {
        const groups = new Map<string, ImportableService[]>();
        visibleServices.forEach(service => {
            const list = groups.get(service.category) ?? [];
            list.push(service);
            groups.set(service.category, list);
        });
        return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [visibleServices]);

    const selectableVisibleServices = visibleServices.filter(service => !service.already_imported);
    const allVisibleSelected = selectableVisibleServices.length > 0
        && selectableVisibleServices.every(service => serviceSelections[service.external_service_id]?.selected);

    const selectedCount = availableServices.filter(service => (
        !service.already_imported && serviceSelections[service.external_service_id]?.selected
    )).length;

    const toggleVisibleSelections = (selected: boolean) => {
        setServiceSelections(current => {
            const next = { ...current };

            selectableVisibleServices.forEach(service => {
                next[service.external_service_id] = {
                    ...next[service.external_service_id],
                    selected,
                };
            });

            return next;
        });
    };

    const toggleCategorySelections = (category: string, selected: boolean) => {
        setServiceSelections(current => {
            const next = { ...current };

            visibleServices
                .filter(service => service.category === category && !service.already_imported)
                .forEach(service => {
                    next[service.external_service_id] = {
                        ...next[service.external_service_id],
                        selected,
                    };
                });

            return next;
        });
    };

    const toggleCategoryCollapsed = (category: string) => {
        setCollapsedCategories(current => ({ ...current, [category]: !current[category] }));
    };

    const applyBulkMarkup = () => {
        if (bulkMarkupValue.trim() === '') {
            return;
        }

        setServiceSelections(current => {
            const next = { ...current };

            availableServices.forEach(service => {
                if (service.already_imported || !next[service.external_service_id]?.selected) {
                    return;
                }

                next[service.external_service_id] = {
                    ...next[service.external_service_id],
                    markup_type: bulkMarkupType,
                    markup_value: bulkMarkupValue,
                };
            });

            return next;
        });
    };

    const submitImport = () => {
        if (!importProvider) {
            return;
        }

        const services = availableServices
            .filter(service => !service.already_imported && serviceSelections[service.external_service_id]?.selected)
            .map(service => ({
                external_service_id: service.external_service_id,
                markup_type: serviceSelections[service.external_service_id]?.markup_type ?? 'percentage',
                markup_value: Number(serviceSelections[service.external_service_id]?.markup_value ?? 0),
            }));

        if (services.length === 0) {
            setImportError('Select at least one service to import.');
            return;
        }

        setImportingServices(true);
        setImportError('');

        router.post(route('admin.upstream-providers.import-services', importProvider.id), { services, enrich_with_ai: enrichWithAi }, {
            preserveScroll: true,
            onSuccess: () => closeImportModal(),
            onError: (formErrors) => {
                const firstError = Object.values(formErrors)[0];
                setImportError(Array.isArray(firstError) ? firstError[0] : firstError || 'Import failed.');
            },
            onFinish: () => setImportingServices(false),
        });
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
                        <div className="overflow-x-auto mt-4">
                            <table className="min-w-[800px] text-left text-sm">
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
                                                        onClick={() => openImportModal(p)}
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

            <Modal show={importProvider !== null} onClose={closeImportModal} maxWidth="2xl">
                <div className="space-y-5 p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-bold text-gray-900">Import Provider Services</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                {importProvider
                                    ? `Choose which ${importProvider.name} services to import and set their markup percentage.`
                                    : 'Choose services and markup percentages before importing.'}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={closeImportModal}
                            className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                        >
                            Close
                        </button>
                    </div>

                    <div className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <XCircle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                        <span>
                            Imported services are added <strong>inactive</strong>. The markup you set here is applied to
                            the selling price — review it and activate each service from the <strong>Services</strong> page
                            before it goes live to customers.
                        </span>
                    </div>

                    {loadingImportServices ? (
                        <div className="flex items-center justify-center gap-3 rounded-xl border border-dashed border-gray-300 px-6 py-14 text-sm text-gray-500">
                            <LoaderCircle className="h-5 w-5 animate-spin" />
                            Loading provider services...
                        </div>
                    ) : (
                        <>
                            <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_200px]">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="text"
                                        value={importSearch}
                                        onChange={e => setImportSearch(e.target.value)}
                                        placeholder="Search by name, category, type, or service ID..."
                                        className="w-full rounded-xl border border-gray-300 px-10 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-green focus:ring-1 focus:ring-brand-green/20"
                                    />
                                </div>
                                <select
                                    value={importCategory}
                                    onChange={e => setImportCategory(e.target.value)}
                                    className="rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none transition focus:border-brand-green focus:ring-1 focus:ring-brand-green/20"
                                >
                                    <option value="">All categories ({availableServices.length})</option>
                                    {categories.map(category => (
                                        <option key={category} value={category}>{category} ({categoryCounts[category]})</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center">
                                <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={hideAlreadyImported}
                                        onChange={e => setHideAlreadyImported(e.target.checked)}
                                        className="rounded border-gray-300 text-brand-green focus:ring-brand-green"
                                    />
                                    Hide already-imported services
                                </label>
                                <div className="flex items-center rounded-xl border border-gray-300 bg-white overflow-hidden focus-within:border-brand-green focus-within:ring-1 focus-within:ring-brand-green/20">
                                    <select
                                        value={bulkMarkupType}
                                        onChange={e => setBulkMarkupType(e.target.value as 'percentage' | 'fixed')}
                                        className="border-none bg-gray-50 px-3 py-2.5 text-sm text-gray-700 outline-none focus:ring-0"
                                    >
                                        <option value="percentage">%</option>
                                        <option value="fixed">$</option>
                                    </select>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={bulkMarkupValue}
                                        onChange={e => setBulkMarkupValue(e.target.value)}
                                        placeholder={bulkMarkupType === 'percentage' ? "Markup %" : "Fixed Amount"}
                                        className="w-28 border-none px-3 py-2.5 text-sm text-gray-900 outline-none focus:ring-0"
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={applyBulkMarkup}
                                    className="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                >
                                    Apply to Selected
                                </button>
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 px-4 py-3 text-sm">
                                <label className="inline-flex items-center gap-2 text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={allVisibleSelected}
                                        onChange={e => toggleVisibleSelections(e.target.checked)}
                                        disabled={selectableVisibleServices.length === 0}
                                        className="rounded border-gray-300 text-brand-green focus:ring-brand-green"
                                    />
                                    Select visible importable services
                                </label>
                                <span className="font-medium text-gray-600">
                                    {selectedCount} selected for import
                                </span>
                            </div>

                            {importError && (
                                <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {importError}
                                </div>
                            )}

                            <div className="max-h-[28rem] overflow-y-auto rounded-xl border border-gray-200">
                                <table className="min-w-full text-left text-sm">
                                    <thead className="sticky top-0 bg-gray-50 text-gray-500">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Import</th>
                                            <th className="px-4 py-3 font-medium">Service</th>
                                            <th className="px-4 py-3 font-medium">Provider Rate</th>
                                            <th className="px-4 py-3 font-medium">Markup</th>
                                            <th className="px-4 py-3 font-medium">Local Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 bg-white">
                                        {visibleServices.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="px-4 py-8 text-center text-gray-500">
                                                    No services match this search.
                                                </td>
                                            </tr>
                                        ) : (
                                            groupedVisibleServices.map(([category, servicesInCategory]) => {
                                                const selectableInCategory = servicesInCategory.filter(s => !s.already_imported);
                                                const allCategorySelected = selectableInCategory.length > 0
                                                    && selectableInCategory.every(s => serviceSelections[s.external_service_id]?.selected);
                                                const selectedInCategory = selectableInCategory.filter(s => serviceSelections[s.external_service_id]?.selected).length;
                                                const isCollapsed = Boolean(collapsedCategories[category]);

                                                return (
                                                    <Fragment key={category}>
                                                        <tr className="bg-gray-100/80">
                                                            <td className="px-4 py-2.5 align-middle">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={allCategorySelected}
                                                                    disabled={selectableInCategory.length === 0}
                                                                    onChange={e => toggleCategorySelections(category, e.target.checked)}
                                                                    className="rounded border-gray-300 text-brand-green focus:ring-brand-green"
                                                                />
                                                            </td>
                                                            <td colSpan={4} className="px-4 py-2.5 align-middle">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => toggleCategoryCollapsed(category)}
                                                                    className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-700 hover:text-gray-900"
                                                                >
                                                                    <span className={`transition-transform ${isCollapsed ? '-rotate-90' : ''}`}>▾</span>
                                                                    {category}
                                                                    <span className="font-normal normal-case text-gray-500">
                                                                        {servicesInCategory.length} service{servicesInCategory.length === 1 ? '' : 's'}
                                                                        {selectedInCategory > 0 ? ` • ${selectedInCategory} selected` : ''}
                                                                    </span>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        {!isCollapsed && servicesInCategory.map(service => {
                                                            const selection = serviceSelections[service.external_service_id] ?? {
                                                                selected: false,
                                                                markup_type: service.default_markup_type || 'percentage',
                                                                markup_value: String(service.default_markup_value || 0),
                                                            };
                                                            const markupType = selection.markup_type || 'percentage';
                                                            const markupVal = Number(selection.markup_value || 0);
                                                            const localRate = markupType === 'percentage'
                                                                ? service.external_rate * (1 + (markupVal / 100))
                                                                : service.external_rate + markupVal;

                                                            return (
                                                                <tr key={service.external_service_id} className={service.already_imported ? 'bg-gray-50/80' : ''}>
                                                                    <td className="px-4 py-4 align-top">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={service.already_imported ? true : selection.selected}
                                                                            disabled={service.already_imported}
                                                                            onChange={e => updateServiceSelection(service.external_service_id, { selected: e.target.checked })}
                                                                            className="mt-1 rounded border-gray-300 text-brand-green focus:ring-brand-green"
                                                                        />
                                                                    </td>
                                                                    <td className="px-4 py-4 align-top">
                                                                        <div className="font-semibold text-gray-900">{service.name}</div>
                                                                        <div className="mt-1 text-xs text-gray-500">
                                                                            ID {service.external_service_id} • {service.type}
                                                                        </div>
                                                                        <div className="mt-1 text-xs text-gray-500">
                                                                            Qty {service.min_qty} - {service.max_qty}
                                                                            {service.is_refill ? ' • Refill' : ''}
                                                                            {service.is_dripfeed ? ' • Dripfeed' : ''}
                                                                        </div>
                                                                        {service.description && (
                                                                            <p className="mt-2 line-clamp-2 text-xs text-gray-500">{service.description}</p>
                                                                        )}
                                                                        {service.already_imported && (
                                                                            <p className="mt-2 text-xs font-medium text-amber-700">
                                                                                Already linked{service.existing_service_name ? ` to ${service.existing_service_name}` : ''}.
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-4 py-4 align-top font-mono text-gray-900">
                                                                        ${service.external_rate.toFixed(4)}
                                                                    </td>
                                                                    <td className="px-4 py-4 align-top">
                                                                        <div className="flex items-center rounded-lg border border-gray-300 bg-white focus-within:border-brand-green focus-within:ring-1 focus-within:ring-brand-green/20 overflow-hidden w-max">
                                                                            <select
                                                                                value={markupType}
                                                                                disabled={service.already_imported || !selection.selected}
                                                                                onChange={e => updateServiceSelection(service.external_service_id, { markup_type: e.target.value as 'percentage' | 'fixed' })}
                                                                                className="border-none bg-gray-50 px-2 py-2 text-sm text-gray-700 outline-none focus:ring-0 disabled:bg-gray-100 disabled:text-gray-400"
                                                                            >
                                                                                <option value="percentage">%</option>
                                                                                <option value="fixed">$</option>
                                                                            </select>
                                                                            <input
                                                                                type="number"
                                                                                min="0"
                                                                                step="0.01"
                                                                                value={selection.markup_value}
                                                                                disabled={service.already_imported || !selection.selected}
                                                                                onChange={e => updateServiceSelection(service.external_service_id, { markup_value: e.target.value })}
                                                                                className="w-20 border-none px-2 py-2 text-sm text-gray-900 outline-none focus:ring-0 disabled:bg-gray-100 disabled:text-gray-400"
                                                                            />
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-4 py-4 align-top font-mono text-gray-900">
                                                                        ${localRate.toFixed(4)}
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </Fragment>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                {aiEnrichmentEnabled ? (
                                    <label className="inline-flex items-start gap-2 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            checked={enrichWithAi}
                                            onChange={e => setEnrichWithAi(e.target.checked)}
                                            className="mt-0.5 rounded border-gray-300 text-brand-green focus:ring-brand-green"
                                        />
                                        <span>
                                            Enhance &amp; translate with AI
                                            <span className="block text-xs text-gray-500">Cleans names and adds Shona &amp; Ndebele translations on import.</span>
                                        </span>
                                    </label>
                                ) : <span />}
                                <div className="flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={closeImportModal}
                                        className="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={submitImport}
                                        disabled={importingServices || loadingImportServices}
                                        className="inline-flex items-center gap-2 rounded-xl bg-brand-green px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-green/90 disabled:opacity-50"
                                    >
                                        {importingServices && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                        Import Selected Services
                                    </button>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </Modal>
        </AdminLayout>
    );
}
