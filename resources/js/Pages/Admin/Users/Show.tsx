import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaUser, 
    FaEnvelope, 
    FaPhone, 
    FaCalendar, 
    FaDollarSign, 
    FaShoppingCart, 
    FaHistory, 
    FaChevronLeft, 
    FaPlus, 
    FaMinus, 
    FaLock, 
    FaBan, 
    FaTrash, 
    FaUserSecret, 
    FaWhatsapp, 
    FaShieldAlt,
    FaCheckCircle,
    FaExclamationTriangle,
    FaSync,
    FaTimes,
    FaExternalLinkAlt,
    FaGlobe,
    FaCommentAlt,
    FaMobileAlt,
    FaUserEdit
} from 'react-icons/fa';

export default function UserShow({ targetUser, recent_orders, recent_transactions, order_stats, financial_stats, services }: any) {
    const [showBalanceModal, setShowBalanceModal] = useState(false);
    const [balanceForm, setBalanceForm] = useState({ amount: '', reason: '' });
    const [showRoleModal, setShowRoleModal] = useState(false);
    const [roleForm, setRoleForm] = useState({ role: targetUser.role, admin_role: targetUser.admin_role || 'support', account_type: targetUser.account_type || 'individual' });
    const [confirmModal, setConfirmModal] = useState<{ title: string; message: string; onConfirm: () => void; danger?: boolean } | null>(null);

    if (!targetUser) return <AdminLayout><div className="p-8">Loading...</div></AdminLayout>;

    const adjustBalance = () => {
        if (!balanceForm.amount) return;
        router.post(route('admin.users.balance', { user: targetUser.id }), balanceForm, {
            onSuccess: () => {
                setShowBalanceModal(false);
                setBalanceForm({ amount: '', reason: '' });
            }
        });
    };

    const updateRole = () => {
        router.post(route('admin.users.role', { user: targetUser.id }), roleForm, {
            onSuccess: () => setShowRoleModal(false)
        });
    };

    const statusColors: Record<string, string> = {
        pending: 'bg-amber-50 text-amber-600 border-amber-100',
        processing: 'bg-blue-50 text-blue-600 border-blue-100',
        completed: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        cancelled: 'bg-red-50 text-red-600 border-red-100',
        refunded: 'bg-zinc-100 text-zinc-600 border-zinc-200',
    };

    const adminRoleLabels: Record<string, string> = {
        full: 'Full Administrator',
        support: 'Support Agent',
        finance: 'Financial Controller',
        compliance: 'Compliance Officer'
    };

    return (
        <AdminLayout>
            <Head title={`User: ${targetUser.name}`} />
            
            <div className="p-8 max-w-7xl mx-auto space-y-10">
                {/* Header Navigation */}
                <div className="flex items-center justify-between">
                    <Link href={route('admin.users.index')} className="group flex items-center gap-3 text-sm font-bold text-zinc-400 hover:text-brand-green transition-all uppercase tracking-widest">
                        <div className="p-2 rounded-xl bg-zinc-100 group-hover:bg-brand-green group-hover:text-white transition-all">
                            <FaChevronLeft className="w-3 h-3" />
                        </div>
                        User Directory
                    </Link>
                    
                    <div className="flex gap-4">
                        {targetUser.whatsapp_number && (
                            <a href={`https://wa.me/${targetUser.whatsapp_number}`} target="_blank" rel="noreferrer" className="flex items-center gap-2 px-6 py-3 rounded-2xl bg-emerald-500 text-white text-sm font-black uppercase tracking-widest">
                                <FaWhatsapp className="w-5 h-5" /> WhatsApp
                            </a>
                        )}
                        <button onClick={() => router.post(route('admin.users.impersonate', { user: targetUser.id }))} className="flex items-center gap-2 px-6 py-3 rounded-2xl bg-white border border-zinc-200 text-zinc-700 text-sm font-black uppercase tracking-widest">
                            <FaUserSecret className="w-5 h-5 text-zinc-400" /> Impersonate
                        </button>
                    </div>
                </div>

                {/* Hero Header */}
                <div className="relative rounded-[3rem] border border-zinc-200 bg-white shadow-2xl shadow-zinc-200/40 overflow-hidden">
                    <div className="absolute top-0 left-0 w-full h-48 bg-gradient-to-br from-indigo-600 via-purple-700 to-zinc-900" />
                    
                    <div className="relative pt-24 px-12 pb-12">
                        <div className="flex flex-col lg:flex-row items-center lg:items-end justify-between gap-10">
                            <div className="flex flex-col lg:flex-row items-center lg:items-end gap-10">
                                <div className="h-40 w-40 rounded-[2.5rem] bg-white p-2 shadow-2xl ring-8 ring-zinc-50/50">
                                    <div className="h-full w-full rounded-[2rem] bg-gradient-to-br from-indigo-500 to-purple-800 flex items-center justify-center text-5xl font-black text-white border-4 border-white shadow-inner">
                                        {targetUser.name?.[0]?.toUpperCase()}
                                    </div>
                                </div>
                                <div className="text-center lg:text-left pb-4 space-y-2">
                                    <h1 className="text-5xl font-black text-zinc-900 tracking-tighter">{targetUser.name}</h1>
                                    <div className="flex flex-wrap items-center justify-center lg:justify-start gap-4 text-zinc-400 font-bold text-sm">
                                        <span className="flex items-center gap-2 bg-zinc-50 px-4 py-2 rounded-full border border-zinc-100">
                                            <FaEnvelope className="text-indigo-500" /> {targetUser.email}
                                        </span>
                                        <button 
                                            onClick={() => setShowRoleModal(true)}
                                            className="flex items-center gap-2 bg-indigo-50 text-indigo-600 px-4 py-2 rounded-full border border-indigo-100 uppercase tracking-widest text-[10px] font-black hover:bg-indigo-100 transition-all"
                                        >
                                            <FaShieldAlt /> {targetUser.role} {targetUser.admin_role ? `(${adminRoleLabels[targetUser.admin_role]})` : ''} • {targetUser.account_type}
                                        </button>
                                        {!targetUser.is_active && <span className="px-4 py-2 rounded-full text-[10px] font-black uppercase bg-red-50 text-red-600 border border-red-100 shadow-sm">Banned</span>}
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-col items-center lg:items-end gap-2 bg-zinc-50/50 p-6 rounded-[2rem] border border-zinc-100/50 backdrop-blur-sm">
                                <p className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.3em]">Customer Balance</p>
                                <div className="flex items-center gap-4">
                                    <p className="text-5xl font-black text-indigo-600 font-mono tracking-tighter">
                                        ${Number(targetUser.balance || 0).toFixed(2)}
                                    </p>
                                    <button onClick={() => setShowBalanceModal(true)} className="h-12 w-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-600/30 hover:bg-indigo-700 transition-all">
                                        <FaPlus className="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div className="mt-16 grid grid-cols-2 lg:grid-cols-4 gap-8 pt-12 border-t border-zinc-100">
                            {[
                                { label: 'Total Spent', value: `$${Number(order_stats?.total_spent || 0).toFixed(2)}`, icon: FaDollarSign },
                                { label: 'Orders Placed', value: order_stats?.total || 0, icon: FaShoppingCart },
                                { label: 'Active Tickets', value: targetUser.tickets_count || 0, icon: FaCommentAlt },
                                { label: 'Joined On', value: targetUser.created_at ? new Date(targetUser.created_at).toLocaleDateString() : '—', icon: FaCalendar },
                            ].map((item, idx) => (
                                <div key={idx} className="space-y-2">
                                    <p className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.2em]">{item.label}</p>
                                    <p className="text-xl font-black text-zinc-900 flex items-center gap-3">
                                        <item.icon className="w-5 h-5 text-zinc-200" /> {item.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-10">
                    <div className="lg:col-span-2 space-y-10">
                        {/* Verification & Security */}
                        <div className="bg-white rounded-[2.5rem] border border-zinc-200 p-10 shadow-sm">
                            <h2 className="text-2xl font-black text-zinc-900 flex items-center gap-3 tracking-tight mb-8">
                                <FaShieldAlt className="text-indigo-500" /> Verification & Trust
                            </h2>
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="p-6 rounded-[2rem] bg-zinc-50 border border-zinc-100 flex flex-col justify-between hover:bg-white transition-all group">
                                    <div className="flex items-center gap-5 mb-6">
                                        <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-2xl shadow-sm ${targetUser.email_verified_at ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'}`}>
                                            <FaEnvelope />
                                        </div>
                                        <div>
                                            <p className="font-black text-zinc-900">Email Status</p>
                                            <p className="text-[10px] font-black uppercase tracking-widest mt-1">{targetUser.email_verified_at ? 'Verified' : 'Unverified'}</p>
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        {!targetUser.email_verified_at && (
                                            <button onClick={() => router.post(route('admin.marketers.verify-email', { user: targetUser.id }))} className="flex-1 py-3 rounded-xl bg-white border border-zinc-200 text-[10px] font-black uppercase tracking-widest text-zinc-700 hover:bg-emerald-50 transition-all">Verify</button>
                                        )}
                                        <button onClick={() => router.post(route('admin.marketers.resend-email', { user: targetUser.id }))} className="flex-1 py-3 rounded-xl bg-zinc-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-zinc-800 transition-all">Resend</button>
                                    </div>
                                </div>

                                <div className="p-6 rounded-[2rem] bg-zinc-50 border border-zinc-100 flex flex-col justify-between hover:bg-white transition-all group">
                                    <div className="flex items-center gap-5 mb-6">
                                        <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-2xl shadow-sm ${targetUser.phone ? 'bg-emerald-100 text-emerald-600' : 'bg-zinc-200 text-zinc-400'}`}>
                                            <FaMobileAlt />
                                        </div>
                                        <div>
                                            <p className="font-black text-zinc-900">Mobile Terminal</p>
                                            <p className="text-xs font-bold text-zinc-500 mt-1">{targetUser.phone || 'No phone linked'}</p>
                                        </div>
                                    </div>
                                    {targetUser.phone && (
                                        <button onClick={() => router.post(route('admin.marketers.resend-phone', { user: targetUser.id }))} className="w-full py-3 rounded-xl bg-white border border-zinc-200 text-[10px] font-black uppercase tracking-widest text-zinc-700 hover:bg-zinc-50 transition-all">Resend OTP</button>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Recent Orders */}
                        <div className="bg-white rounded-[2.5rem] border border-zinc-200 shadow-sm overflow-hidden">
                            <div className="p-10 border-b border-zinc-100 flex items-center justify-between">
                                <h2 className="text-2xl font-black text-zinc-900 tracking-tight flex items-center gap-3">
                                    <FaShoppingCart className="text-zinc-300" /> Recent Service Orders
                                </h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-zinc-50 border-b border-zinc-100 text-[10px] font-black text-zinc-400 uppercase tracking-[0.3em]">
                                        <tr>
                                            <th className="px-10 py-6">Service Type</th>
                                            <th className="px-10 py-6 text-right">Charge</th>
                                            <th className="px-10 py-6 text-center">Status</th>
                                            <th className="px-10 py-6 text-right">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-50">
                                        {(recent_orders || []).map((o: any) => (
                                            <tr key={o.id} className="hover:bg-zinc-50/50 transition-colors">
                                                <td className="px-10 py-8">
                                                    <p className="text-lg font-black text-zinc-900 tracking-tight">{o.service?.name || 'Unknown'}</p>
                                                    <p className="text-xs text-zinc-500 font-bold uppercase tracking-[0.2em] mt-1">{o.service?.category}</p>
                                                </td>
                                                <td className="px-10 py-8 text-right font-black text-zinc-900 font-mono">
                                                    ${Number(o.charge).toFixed(2)}
                                                </td>
                                                <td className="px-10 py-8 text-center">
                                                    <span className={`px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border ${statusColors[o.status]}`}>
                                                        {o.status}
                                                    </span>
                                                </td>
                                                <td className="px-10 py-8 text-right text-zinc-400 font-black font-mono text-xs">
                                                    {new Date(o.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-10">
                        {/* Profile Info */}
                        <div className="bg-zinc-900 rounded-[2.5rem] p-10 text-white shadow-2xl relative overflow-hidden group">
                            <h3 className="text-[10px] font-black text-indigo-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-2">
                                <FaUser /> Intelligence
                            </h3>
                            <div className="space-y-6 relative z-10">
                                <div>
                                    <p className="text-[10px] font-black text-zinc-500 uppercase tracking-widest mb-2">Internal Notes</p>
                                    <p className="text-sm text-zinc-300 leading-relaxed font-medium italic">
                                        {targetUser.admin_notes || "No internal notes have been filed."}
                                    </p>
                                </div>
                                <div className="pt-6 border-t border-zinc-800 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <span className="text-[10px] font-black text-zinc-500 uppercase">Account ID</span>
                                        <span className="text-xs font-black text-zinc-300 font-mono">#{targetUser.id.toString().padStart(6, '0')}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-[10px] font-black text-zinc-500 uppercase">Active Status</span>
                                        <span className={`h-2 w-2 rounded-full ${targetUser.is_active ? 'bg-emerald-500 shadow-[0_0_8px_#10b981]' : 'bg-red-500 shadow-[0_0_8px_#ef4444]'}`} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Security Actions */}
                        <div className="bg-white rounded-[2.5rem] border border-zinc-200 p-10 shadow-sm">
                            <h3 className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.3em] mb-10">Security</h3>
                            <div className="space-y-4">
                                <button onClick={() => setConfirmModal({ title: 'Reset Password', message: 'Send a password reset email to this user?', onConfirm: () => router.post(route('admin.users.reset-password', { user: targetUser.id })) })} className="w-full p-5 rounded-2xl bg-zinc-50 text-zinc-700 text-xs font-black uppercase tracking-widest hover:bg-zinc-100 transition-all border border-zinc-100 shadow-sm">
                                    <FaLock className="opacity-30 inline mr-2" /> Reset Password
                                </button>
                                <button onClick={() => setConfirmModal({ title: targetUser.is_active ? 'Ban Account' : 'Revoke Ban', message: targetUser.is_active ? 'Ban this user account? They will lose access immediately.' : 'Revoke the ban on this account?', onConfirm: () => router.post(route('admin.users.ban', { user: targetUser.id })), danger: targetUser.is_active })} className={`w-full p-5 rounded-2xl text-xs font-black uppercase tracking-widest transition-all border shadow-sm ${targetUser.is_active ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'}`}>
                                    {targetUser.is_active ? 'Ban Account' : 'Revoke Ban'}
                                </button>
                                <button onClick={() => setShowRoleModal(true)} className="w-full p-5 rounded-2xl bg-indigo-50 text-indigo-700 text-xs font-black uppercase tracking-widest hover:bg-indigo-100 border border-indigo-200 transition-all">
                                    <FaUserEdit className="inline mr-2" /> Change Permissions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Balance Modal */}
                <AnimatePresence>
                    {showBalanceModal && (
                        <div className="fixed inset-0 z-[100] flex items-center justify-center p-6">
                            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={() => setShowBalanceModal(false)} className="absolute inset-0 bg-zinc-900/80 backdrop-blur-xl" />
                            <motion.div initial={{ opacity: 0, scale: 0.9, y: 20 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.9, y: 20 }} className="relative bg-white rounded-[3rem] w-full max-w-lg shadow-2xl overflow-hidden">
                                <div className="p-10 border-b border-zinc-100 flex items-center justify-between">
                                    <h3 className="text-3xl font-black text-zinc-900 tracking-tight">Adjustment</h3>
                                    <button onClick={() => setShowBalanceModal(false)} className="text-2xl font-black font-mono">&times;</button>
                                </div>
                                <div className="p-10 space-y-8">
                                    <input type="number" step="0.01" value={balanceForm.amount} onChange={e => setBalanceForm(f => ({...f, amount: e.target.value}))} placeholder="0.00" className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-[1.8rem] px-8 py-8 text-5xl font-black text-zinc-900 font-mono focus:outline-none focus:border-indigo-500" />
                                    <textarea value={balanceForm.reason} onChange={e => setBalanceForm(f => ({...f, reason: e.target.value}))} placeholder="Reason..." rows={3} className="w-full bg-zinc-50 border border-zinc-200 rounded-[1.5rem] px-8 py-6 text-sm font-bold text-zinc-600 focus:outline-none" />
                                </div>
                                <div className="p-10 bg-zinc-50 border-t border-zinc-100">
                                    <button onClick={adjustBalance} disabled={!balanceForm.amount || !balanceForm.reason} className="w-full bg-indigo-600 text-white py-6 rounded-[1.8rem] font-black text-sm uppercase tracking-widest">Execute</button>
                                </div>
                            </motion.div>
                        </div>
                    )}
                </AnimatePresence>

                {/* Role/RBAC Modal */}
                <AnimatePresence>
                    {showRoleModal && (
                        <div className="fixed inset-0 z-[100] flex items-center justify-center p-6">
                            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={() => setShowRoleModal(false)} className="absolute inset-0 bg-zinc-900/80 backdrop-blur-xl" />
                            <motion.div initial={{ opacity: 0, scale: 0.9, y: 20 }} animate={{ opacity: 1, scale: 1, y: 0 }} exit={{ opacity: 0, scale: 0.9, y: 20 }} className="relative bg-white rounded-[3rem] w-full max-w-lg shadow-2xl overflow-hidden">
                                <div className="p-10 border-b border-zinc-100 flex items-center justify-between">
                                    <h3 className="text-2xl font-black text-zinc-900 tracking-tight">Permissions & RBAC</h3>
                                    <button onClick={() => setShowRoleModal(false)} className="text-2xl font-black font-mono">&times;</button>
                                </div>
                                <div className="p-10 space-y-6">
                                    <div>
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">Account Type</label>
                                        <select 
                                            value={roleForm.account_type}
                                            onChange={e => setRoleForm(f => ({...f, account_type: e.target.value}))}
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-indigo-500 appearance-none mb-6"
                                        >
                                            <option value="individual">Individual Account</option>
                                            <option value="business">Business Account</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">System Role</label>
                                        <select 
                                            value={roleForm.role}
                                            onChange={e => setRoleForm(f => ({...f, role: e.target.value}))}
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-indigo-500 appearance-none"
                                        >
                                            <option value="user">Regular Customer</option>
                                            <option value="marketer">Social Partner (Marketer)</option>
                                            <option value="reseller">Reseller Agency</option>
                                            <option value="admin">System Administrator</option>
                                        </select>
                                    </div>

                                    {roleForm.role === 'admin' && (
                                        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
                                            <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">Admin Privilege Level</label>
                                            <select 
                                                value={roleForm.admin_role}
                                                onChange={e => setRoleForm(f => ({...f, admin_role: e.target.value}))}
                                                className="w-full bg-indigo-50 border-2 border-indigo-100 rounded-2xl px-6 py-4 font-bold text-indigo-900 focus:outline-none focus:border-indigo-500 appearance-none"
                                            >
                                                <option value="full">Full Administrator (Super)</option>
                                                <option value="support">Support Agent (Helpdesk)</option>
                                                <option value="finance">Financial Controller (Treasury)</option>
                                                <option value="compliance">Compliance Admin (Trust & Safety)</option>
                                            </select>
                                        </motion.div>
                                    )}
                                </div>
                                <div className="p-10 bg-zinc-50 border-t border-zinc-100">
                                    <button onClick={updateRole} className="w-full bg-zinc-900 text-indigo-400 py-6 rounded-[1.8rem] font-black text-sm uppercase tracking-widest shadow-2xl">Update Permissions</button>
                                </div>
                            </motion.div>
                        </div>
                    )}
                </AnimatePresence>
            </div>
            
            {confirmModal && (
                <ConfirmModal
                    open
                    title={confirmModal.title}
                    message={confirmModal.message}
                    danger={confirmModal.danger}
                    onConfirm={() => { confirmModal.onConfirm(); setConfirmModal(null); }}
                    onCancel={() => setConfirmModal(null)}
                />
            )}
        </AdminLayout>
    );
}
