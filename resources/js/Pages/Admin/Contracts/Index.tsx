import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, ExternalLink, Trash2, Briefcase, Users, Calendar, DollarSign } from 'lucide-react';

interface Contract {
    id: number;
    title: string;
    description: string;
    platform: string;
    budget: string;
    slots: number;
    status: string;
    created_at: string;
    business?: {
        id: number;
        name: string;
        company_name?: string;
    };
    applications_count: number;
}

interface Props {
    contracts: {
        data: Contract[];
        links: any[];
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
    status_counts: Record<string, number>;
}

export default function ContractsIndex({ contracts, filters, status_counts }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const applySearch = () => {
        router.get(route('admin.contracts.index'), { ...filters, search }, { preserveState: true });
    };

    const setFilter = (k: string, v: string) => {
        router.get(route('admin.contracts.index'), { ...filters, [k]: v || undefined }, { preserveState: true });
    };

    const deleteContract = (id: number) => {
        if (confirm('Are you sure you want to delete this contract? This cannot be undone.')) {
            router.delete(route('admin.contracts.destroy', id));
        }
    };

    const statusBadge = (status: string) => {
        switch (status) {
            case 'open': return <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 border border-emerald-200 uppercase tracking-wider">Open</span>;
            case 'filled': return <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700 border border-blue-200 uppercase tracking-wider">Filled</span>;
            case 'closed': return <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 text-zinc-700 border border-zinc-200 uppercase tracking-wider">Closed</span>;
            default: return <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200 uppercase tracking-wider">{status}</span>;
        }
    };

    return (
        <AdminLayout>
            <Head title="Contract Administration" />
            
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900 tracking-tight">Campaign Contracts</h1>
                    <p className="text-zinc-500 text-sm mt-1">Monitor and manage business-to-marketer contracts</p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {['all', 'open', 'filled', 'closed'].map(s => (
                        <button 
                            key={s}
                            onClick={() => setFilter('status', s === 'all' ? '' : s)}
                            className={`px-4 py-1.5 text-sm font-semibold rounded-full border transition-all ${
                                (filters.status === s || (!filters.status && s === 'all'))
                                ? 'bg-brand-green text-white border-brand-green shadow-sm'
                                : 'bg-white text-zinc-600 border-zinc-200 hover:border-zinc-300'
                            }`}
                        >
                            {s.charAt(0).toUpperCase() + s.slice(1)} ({status_counts[s] || 0})
                        </button>
                    ))}
                </div>

                <div className="flex gap-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
                        <input 
                            type="text" 
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applySearch()}
                            placeholder="Search by title, business, or description..." 
                            className="w-full pl-10 rounded-xl bg-white border border-zinc-200 text-zinc-900 text-sm py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20"
                        />
                    </div>
                    <button onClick={applySearch} className="px-6 py-2.5 rounded-xl bg-zinc-900 text-white text-sm font-bold hover:bg-zinc-800 transition-colors">Search</button>
                </div>

                <div className="rounded-xl border border-zinc-200 bg-white shadow-sm overflow-hidden">
                    <table className="w-full text-sm text-left">
                        <thead className="bg-zinc-50 border-b border-zinc-200">
                            <tr className="text-zinc-500 font-bold text-xs uppercase tracking-wider">
                                <th className="px-6 py-4">Contract Brief</th>
                                <th className="px-6 py-4">Posted By</th>
                                <th className="px-6 py-4 text-right">Budget</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4 text-center">Apps</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100">
                            {contracts.data.length === 0 && (
                                <tr><td colSpan={6} className="px-6 py-12 text-center text-zinc-500">No contracts found.</td></tr>
                            )}
                            {contracts.data.map(c => (
                                <tr key={c.id} className="hover:bg-zinc-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div>
                                            <Link href={route('admin.contracts.show', c.id)} className="font-bold text-zinc-900 hover:text-brand-green">{c.title}</Link>
                                            <p className="text-xs text-zinc-500 uppercase font-bold tracking-widest mt-1">{c.platform}</p>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-zinc-600">
                                        <div className="font-medium text-zinc-900">{c.business?.company_name || c.business?.name}</div>
                                        <div className="text-xs text-zinc-500">{c.business?.name}</div>
                                    </td>
                                    <td className="px-6 py-4 text-right font-mono font-bold text-zinc-900">${Number(c.budget).toFixed(2)}</td>
                                    <td className="px-6 py-4 text-center">{statusBadge(c.status)}</td>
                                    <td className="px-6 py-4 text-center">
                                        <span className="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-zinc-100 text-zinc-700 text-xs font-bold">
                                            <Users className="w-3 h-3" /> {c.applications_count}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Link 
                                                href={route('admin.contracts.show', c.id)}
                                                className="p-2 rounded-lg bg-zinc-100 text-zinc-600 hover:bg-zinc-200 transition-colors"
                                            >
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                            <button 
                                                onClick={() => deleteContract(c.id)}
                                                className="p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
