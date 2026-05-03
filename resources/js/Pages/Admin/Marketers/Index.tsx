import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, UserCheck, UserX, ExternalLink, ShieldCheck, ShieldAlert, Shield } from 'lucide-react';

interface Marketer {
    id: number;
    name: string;
    email: string;
    company_name?: string;
    role: string;
    marketer_status: string;
    created_at: string;
}

interface Props {
    marketers: {
        data: Marketer[];
        links: any[];
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
    status_counts: Record<string, number>;
}

export default function MarketersIndex({ marketers, filters, status_counts }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [pendingApproveId, setPendingApproveId] = useState<number | null>(null);

    const applySearch = () => {
        router.get(route('admin.marketers.index'), { ...filters, search }, { preserveState: true });
    };

    const setFilter = (k: string, v: string) => {
        router.get(route('admin.marketers.index'), { ...filters, [k]: v || undefined }, { preserveState: true });
    };

    const approve = (id: number) => {
        setPendingApproveId(id);
    };

    const reject = (id: number) => {
        const reason = prompt('Enter reason for rejection (optional):');
        if (reason !== null) {
            router.post(route('admin.marketers.reject', id), { reason });
        }
    };

    const statusBadge = (status: string) => {
        switch (status) {
            case 'approved': return <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 border border-emerald-200 uppercase tracking-wider"><ShieldCheck className="w-3 h-3" /> Approved</span>;
            case 'pending': return <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200 uppercase tracking-wider"><Shield className="w-3 h-3" /> Pending</span>;
            case 'rejected': return <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700 border border-red-200 uppercase tracking-wider"><ShieldAlert className="w-3 h-3" /> Rejected</span>;
            default: return <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200 uppercase tracking-wider">{status}</span>;
        }
    };

    return (
        <AdminLayout>
            <Head title="Marketer Approvals" />
            
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900 tracking-tight">Marketer Management</h1>
                    <p className="text-zinc-500 text-sm mt-1">Review and approve partner marketer accounts</p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {['all', 'pending', 'approved', 'rejected'].map(s => (
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
                            placeholder="Search by name, email, or company..." 
                            className="w-full pl-10 rounded-xl bg-white border border-zinc-200 text-zinc-900 text-sm py-2.5 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20"
                        />
                    </div>
                    <button onClick={applySearch} className="px-6 py-2.5 rounded-xl bg-zinc-900 text-white text-sm font-bold hover:bg-zinc-800 transition-colors">Search</button>
                </div>

                <div className="rounded-xl border border-zinc-200 bg-white shadow-sm overflow-hidden">
                    <table className="w-full text-sm text-left">
                        <thead className="bg-zinc-50 border-b border-zinc-200">
                            <tr className="text-zinc-500 font-bold text-xs uppercase tracking-wider">
                                <th className="px-6 py-4">Marketer</th>
                                <th className="px-6 py-4">Company</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4 text-right">Registered</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100">
                            {marketers.data.length === 0 && (
                                <tr><td colSpan={5} className="px-6 py-12 text-center text-zinc-500">No marketers found.</td></tr>
                            )}
                            {marketers.data.map(m => (
                                <tr key={m.id} className="hover:bg-zinc-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="h-9 w-9 rounded-full bg-brand-green/10 flex items-center justify-center text-brand-green font-bold">
                                                {m.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <Link href={route('admin.marketers.show', { id: m.id })} className="font-bold text-zinc-900 hover:text-brand-green">{m.name}</Link>
                                                <p className="text-xs text-zinc-500">{m.email}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-zinc-600 font-medium">{m.company_name || '—'}</td>
                                    <td className="px-6 py-4 text-center">{statusBadge(m.marketer_status)}</td>
                                    <td className="px-6 py-4 text-right text-zinc-500 font-mono text-xs">{new Date(m.created_at).toLocaleDateString()}</td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex justify-end gap-2">
                                            {m.marketer_status === 'pending' && (
                                                <>
                                                    <button 
                                                        onClick={() => approve(m.id)}
                                                        className="p-2 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition-colors"
                                                        title="Approve"
                                                    >
                                                        <UserCheck className="h-4 w-4" />
                                                    </button>
                                                    <button 
                                                        onClick={() => reject(m.id)}
                                                        className="p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors"
                                                        title="Reject"
                                                    >
                                                        <UserX className="h-4 w-4" />
                                                    </button>
                                                </>
                                            )}
                                            <Link 
                                                href={route('admin.marketers.show', { id: m.id })}
                                                className="p-2 rounded-lg bg-zinc-100 text-zinc-600 hover:bg-zinc-200 transition-colors"
                                                title="View Detailed Profile"
                                            >
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            
            {pendingApproveId !== null && (
                <ConfirmModal
                    open
                    title="Approve Marketer"
                    message="Approve this marketer account? They will gain full platform access."
                    confirmLabel="Approve"
                    onConfirm={() => { router.post(route('admin.marketers.approve', pendingApproveId)); setPendingApproveId(null); }}
                    onCancel={() => setPendingApproveId(null)}
                />
            )}
        </AdminLayout>
    );
}
