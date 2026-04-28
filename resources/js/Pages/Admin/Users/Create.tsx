import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { FaUserPlus, FaEnvelope, FaLock, FaUserShield, FaWallet, FaChevronLeft, FaSave, FaShieldAlt } from 'react-icons/fa';

export default function UserCreate() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        role: 'user',
        admin_role: 'support',
        balance: '0',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.users.store'));
    };

    return (
        <AdminLayout>
            <Head title="Create New Account" />

            <div className="p-8 max-w-4xl mx-auto space-y-8">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <Link href={route('admin.users.index')} className="group flex items-center gap-3 text-sm font-bold text-zinc-400 hover:text-brand-green transition-all uppercase tracking-widest mb-4">
                            <div className="p-2 rounded-xl bg-zinc-100 group-hover:bg-brand-green group-hover:text-white transition-all">
                                <FaChevronLeft className="w-3 h-3" />
                            </div>
                            Back to Directory
                        </Link>
                        <h1 className="text-4xl font-black text-zinc-900 tracking-tight">Onboard New User</h1>
                        <p className="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-2">Manual Account Provisioning</p>
                    </div>
                </div>

                <motion.div 
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="bg-white rounded-[3rem] border border-zinc-200 shadow-2xl shadow-zinc-200/40 overflow-hidden"
                >
                    <form onSubmit={submit} className="p-12 space-y-10">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-10">
                            {/* Basic Info */}
                            <div className="space-y-6">
                                <h2 className="text-[10px] font-black text-brand-green uppercase tracking-[0.3em] border-b border-zinc-100 pb-4 flex items-center gap-2">
                                    <FaUserShield /> Identity & Access
                                </h2>
                                
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">Full Name</label>
                                        <div className="relative">
                                            <FaUserPlus className="absolute left-6 top-1/2 -translate-y-1/2 text-zinc-300" />
                                            <input 
                                                type="text"
                                                value={data.name}
                                                onChange={e => setData('name', e.target.value)}
                                                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl pl-14 pr-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-brand-green transition-all"
                                                placeholder="Enter display name"
                                            />
                                        </div>
                                        {errors.name && <p className="text-red-500 text-[10px] font-bold mt-2 ml-4">{errors.name}</p>}
                                    </div>

                                    <div>
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">Email Address</label>
                                        <div className="relative">
                                            <FaEnvelope className="absolute left-6 top-1/2 -translate-y-1/2 text-zinc-300" />
                                            <input 
                                                type="email"
                                                value={data.email}
                                                onChange={e => setData('email', e.target.value)}
                                                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl pl-14 pr-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-brand-green transition-all"
                                                placeholder="user@example.com"
                                            />
                                        </div>
                                        {errors.email && <p className="text-red-500 text-[10px] font-bold mt-2 ml-4">{errors.email}</p>}
                                    </div>

                                    <div>
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">Initial Password</label>
                                        <div className="relative">
                                            <FaLock className="absolute left-6 top-1/2 -translate-y-1/2 text-zinc-300" />
                                            <input 
                                                type="password"
                                                value={data.password}
                                                onChange={e => setData('password', e.target.value)}
                                                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl pl-14 pr-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-brand-green transition-all"
                                                placeholder="Minimum 8 characters"
                                            />
                                        </div>
                                        {errors.password && <p className="text-red-500 text-[10px] font-bold mt-2 ml-4">{errors.password}</p>}
                                    </div>
                                </div>
                            </div>

                            {/* Role & Treasury */}
                            <div className="space-y-6">
                                <h2 className="text-[10px] font-black text-indigo-500 uppercase tracking-[0.3em] border-b border-zinc-100 pb-4 flex items-center gap-2">
                                    <FaWallet /> Governance & Treasury
                                </h2>

                                <div className="space-y-4">
                                    <div>
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">System Role</label>
                                        <select 
                                            value={data.role}
                                            onChange={e => setData('role', e.target.value)}
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-brand-green transition-all appearance-none"
                                        >
                                            <option value="user">Regular Customer</option>
                                            <option value="marketer">Social Partner (Marketer)</option>
                                            <option value="reseller">Reseller Agency</option>
                                            <option value="admin">System Administrator</option>
                                        </select>
                                    </div>

                                    {data.role === 'admin' && (
                                        <motion.div 
                                            initial={{ opacity: 0, height: 0 }}
                                            animate={{ opacity: 1, height: 'auto' }}
                                        >
                                            <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">Admin Level (RBAC)</label>
                                            <select 
                                                value={data.admin_role}
                                                onChange={e => setData('admin_role', e.target.value)}
                                                className="w-full bg-indigo-50 border-2 border-indigo-100 rounded-2xl px-6 py-4 font-bold text-indigo-900 focus:outline-none focus:border-indigo-500 transition-all appearance-none"
                                            >
                                                <option value="full">Full Administrator (Super)</option>
                                                <option value="support">Support Agent (Helpdesk)</option>
                                                <option value="finance">Financial Controller (Treasury)</option>
                                                <option value="compliance">Compliance Admin (Trust & Safety)</option>
                                            </select>
                                        </motion.div>
                                    )}

                                    <div>
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 mb-2 block">Opening Balance (USD)</label>
                                        <div className="relative">
                                            <span className="absolute left-6 top-1/2 -translate-y-1/2 text-lg font-bold text-zinc-300">$</span>
                                            <input 
                                                type="number"
                                                step="0.01"
                                                value={data.balance}
                                                onChange={e => setData('balance', e.target.value)}
                                                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl pl-12 pr-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-brand-green transition-all"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="pt-10 border-t border-zinc-100 flex items-center justify-between">
                            <div className="flex items-center gap-4 text-zinc-400 text-xs font-medium">
                                <FaShieldAlt className="text-brand-green" />
                                All actions are logged for audit compliance.
                            </div>
                            <button 
                                type="submit"
                                disabled={processing}
                                className="flex items-center gap-3 px-12 py-5 rounded-3xl bg-zinc-900 text-white text-sm font-black uppercase tracking-[0.2em] shadow-2xl hover:bg-zinc-800 transition-all active:scale-95 disabled:opacity-50"
                            >
                                <FaSave className="text-brand-green" /> Create Account
                            </button>
                        </div>
                    </form>
                </motion.div>
            </div>
        </AdminLayout>
    );
}
