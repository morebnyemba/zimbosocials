import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface User { id: number; name: string; email: string; role: string; balance: string; is_active: boolean; company_name?: string; phone?: string; created_at: string; orders_count: number; tickets_count: number; transactions_count: number; }
interface PaginatedUsers { data: User[]; links: any[]; current_page: number; last_page: number; total: number; }
interface Filters { search?: string; role?: string; status?: string; sort?: string; dir?: string; }
interface RoleCounts { all: number; user: number; marketer: number; reseller: number; admin: number; }
interface Props { users: PaginatedUsers; filters: Filters; role_counts: RoleCounts; }

const roleBadge: Record<string, string> = {
    admin: 'bg-red-100 text-red-700 border-red-200',
    marketer: 'bg-brand-green/10 text-brand-green border-brand-green/20',
    reseller: 'bg-blue-100 text-blue-700 border-blue-200',
    user: 'bg-gray-100 text-gray-700 border-gray-200',
};

export default function UsersIndex({ users, filters, role_counts }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [showBalanceModal, setShowBalanceModal] = useState<User | null>(null);
    const [balanceAmount, setBalanceAmount] = useState('');
    const [balanceReason, setBalanceReason] = useState('');
    const [showRoleModal, setShowRoleModal] = useState<User | null>(null);
    const [newRole, setNewRole] = useState('');

    const applySearch = () => router.get(route('admin.users.index'), { ...filters, search }, { preserveState: true });
    const setFilter = (key: string, val: string) => router.get(route('admin.users.index'), { ...filters, [key]: val || undefined }, { preserveState: true });

    const toggleActive = (user: User) => router.post(route('admin.users.toggle', user.id), {}, { preserveScroll: true });

    const submitBalance = () => {
        if (!showBalanceModal) return;
        router.post(route('admin.users.balance', showBalanceModal.id), { amount: balanceAmount, reason: balanceReason }, {
            preserveScroll: true,
            onSuccess: () => { setShowBalanceModal(null); setBalanceAmount(''); setBalanceReason(''); },
        });
    };

    const submitRole = () => {
        if (!showRoleModal) return;
        router.post(route('admin.users.role', showRoleModal.id), { role: newRole }, {
            preserveScroll: true,
            onSuccess: () => { setShowRoleModal(null); setNewRole(''); },
        });
    };

    return (
        <AdminLayout>
            <Head title="User Management" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">User Management</h1>
                        <p className="text-gray-500 mt-1 text-sm">{users.total} users total</p>
                    </div>
                    <Link 
                        href={route('admin.users.create')}
                        className="flex items-center gap-2 px-6 py-3 rounded-2xl bg-brand-green text-white text-sm font-black uppercase tracking-widest shadow-lg shadow-brand-green/20 hover:bg-emerald-600 transition-all active:scale-95"
                    >
                        + Create Account
                    </Link>
                </div>

                {/* Role Tabs */}
                <div className="flex gap-2 flex-wrap">
                    {Object.entries(role_counts).map(([role, count]) => (
                        <button key={role} onClick={() => setFilter('role', role === 'all' ? '' : role)}
                            className={`px-4 py-1.5 text-sm font-medium rounded-full border transition-all ${
                                (filters.role === role || (!filters.role && role === 'all'))
                                    ? 'bg-brand-green/10 text-brand-green border-brand-green/30'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 shadow-sm'
                            }`}>
                            {role.charAt(0).toUpperCase() + role.slice(1)} ({count})
                        </button>
                    ))}
                </div>

                {/* Search */}
                <div className="flex gap-3">
                    <input type="text" value={search} onChange={e => setSearch(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && applySearch()}
                        placeholder="Search by name, email, or ID..."
                        className="flex-1 rounded-xl bg-white border border-gray-200 text-gray-900 text-sm px-4 py-2.5 focus:border-brand-green focus:ring-1 focus:ring-brand-green/20 outline-none transition-all placeholder:text-gray-400 shadow-sm" />
                    <button onClick={applySearch} className="px-5 py-2.5 rounded-xl bg-brand-green text-white font-medium text-sm hover:bg-brand-green/90 transition-colors shadow-sm">Search</button>
                </div>

                {/* Table */}
                <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-gray-500 font-medium">
                                    <th className="py-3 px-5">User</th>
                                    <th className="py-3 px-5">Role</th>
                                    <th className="text-right py-3 px-5">Balance</th>
                                    <th className="text-center py-3 px-5">Orders</th>
                                    <th className="text-center py-3 px-5">Status</th>
                                    <th className="text-center py-3 px-5">Joined</th>
                                    <th className="text-right py-3 px-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {users.data.map(u => (
                                    <tr key={u.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="py-3 px-5">
                                            <Link href={u.role === 'marketer' || u.role === 'reseller' ? route('admin.marketers.show', { id: u.id }) : route('admin.users.show', u.id)} className="flex items-center gap-3 group">
                                                <div className="w-8 h-8 rounded-full bg-brand-green/10 flex items-center justify-center text-brand-green font-bold flex-shrink-0 group-hover:bg-brand-green/20 transition-colors">
                                                    {u.name.charAt(0).toUpperCase()}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-gray-900 font-medium group-hover:text-brand-green transition-colors truncate">{u.name}</p>
                                                    <p className="text-xs text-gray-500 truncate">{u.email}</p>
                                                </div>
                                            </Link>
                                        </td>
                                        <td className="py-3 px-5">
                                            <button onClick={() => { setShowRoleModal(u); setNewRole(u.role); }}
                                                className={`px-2.5 py-0.5 text-xs font-medium rounded-full border cursor-pointer hover:opacity-80 uppercase tracking-wider ${roleBadge[u.role] || roleBadge.user}`}>{u.role}</button>
                                        </td>
                                        <td className="py-3 px-5 text-right">
                                            <button onClick={() => setShowBalanceModal(u)} className="text-gray-900 hover:text-brand-green transition-colors font-mono font-medium">${Number(u.balance).toFixed(2)}</button>
                                        </td>
                                        <td className="py-3 px-5 text-center text-gray-600">{u.orders_count}</td>
                                        <td className="py-3 px-5 text-center">
                                            <button onClick={() => toggleActive(u)}
                                                className={`w-10 h-5 rounded-full transition-all relative ${u.is_active ? 'bg-brand-green' : 'bg-gray-300'}`}>
                                                <div className={`w-4 h-4 rounded-full bg-white transition-transform absolute top-0.5 ${u.is_active ? 'left-[22px]' : 'left-0.5'}`} />
                                            </button>
                                        </td>
                                        <td className="py-3 px-5 text-center text-gray-500 text-xs">{new Date(u.created_at).toLocaleDateString()}</td>
                                        <td className="py-3 px-5 text-right flex items-center justify-end gap-3">
                                            {u.id !== (usePage().props.auth as any).user.id && (
                                                <button 
                                                    onClick={() => router.post(route('admin.users.impersonate', u.id))}
                                                    className="text-amber-600 hover:text-amber-700 font-medium text-xs flex items-center gap-1"
                                                    title="Impersonate User"
                                                >
                                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                                    </svg>
                                                    Login
                                                </button>
                                            )}
                                            <Link href={u.role === 'marketer' || u.role === 'reseller' ? route('admin.marketers.show', { id: u.id }) : route('admin.users.show', u.id)} className="text-brand-green hover:text-brand-green/80 font-medium text-xs">View →</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {/* Pagination */}
                    {users.last_page > 1 && (
                        <div className="flex justify-center gap-1 py-4 border-t border-gray-100 bg-gray-50">
                            {users.links.map((link: any, i: number) => (
                                <Link key={i} href={link.url || '#'}
                                    className={`px-3 py-1.5 text-xs font-medium rounded-lg ${link.active ? 'bg-brand-green text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    )}
                </div>

                {/* Balance Modal */}
                {showBalanceModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={() => setShowBalanceModal(null)}>
                        <div className="bg-white border border-gray-200 rounded-2xl p-6 w-full max-w-md shadow-xl" onClick={e => e.stopPropagation()}>
                            <h3 className="text-lg font-bold text-gray-900 mb-4">Adjust Balance — {showBalanceModal.name}</h3>
                            <p className="text-sm text-gray-500 mb-4">Current: <span className="text-gray-900 font-bold font-mono">${Number(showBalanceModal.balance).toFixed(2)}</span></p>
                            <input type="number" step="0.01" value={balanceAmount} onChange={e => setBalanceAmount(e.target.value)} placeholder="Amount (negative to debit)" className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-2.5 mb-3 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20" />
                            <input type="text" value={balanceReason} onChange={e => setBalanceReason(e.target.value)} placeholder="Reason (required)" className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-2.5 mb-4 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20" />
                            <div className="flex gap-3 justify-end">
                                <button onClick={() => setShowBalanceModal(null)} className="px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100">Cancel</button>
                                <button onClick={submitBalance} disabled={!balanceAmount || !balanceReason} className="px-4 py-2 text-sm font-medium rounded-xl bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50 shadow-sm">Adjust</button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Role Modal */}
                {showRoleModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={() => setShowRoleModal(null)}>
                        <div className="bg-white border border-gray-200 rounded-2xl p-6 w-full max-w-md shadow-xl" onClick={e => e.stopPropagation()}>
                            <h3 className="text-lg font-bold text-gray-900 mb-4">Change Role — {showRoleModal.name}</h3>
                            <select value={newRole} onChange={e => setNewRole(e.target.value)} className="w-full rounded-xl bg-gray-50 border border-gray-200 text-gray-900 text-sm px-4 py-2.5 mb-4 outline-none focus:border-brand-green focus:ring-1 focus:ring-brand-green/20">
                                <option value="user">User</option>
                                <option value="marketer">Marketer</option>
                                <option value="reseller">Reseller</option>
                                <option value="admin">Admin</option>
                            </select>
                            <div className="flex gap-3 justify-end">
                                <button onClick={() => setShowRoleModal(null)} className="px-4 py-2 text-sm font-medium rounded-xl text-gray-600 hover:bg-gray-100">Cancel</button>
                                <button onClick={submitRole} className="px-4 py-2 text-sm font-medium rounded-xl bg-brand-green text-white hover:bg-brand-green/90 shadow-sm">Save</button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
