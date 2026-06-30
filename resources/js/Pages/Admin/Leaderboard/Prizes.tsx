import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import Modal from '@/Components/Modal';
import { Head, useForm, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import { Plus, Edit2, Trash2, Trophy, Award, Gift } from 'lucide-react';

interface Prize {
    id: number;
    category: string;
    rank: number;
    title: string;
    description: string | null;
    service_id: number | null;
    service_quantity: number | null;
    bonus_amount: number;
    is_active: boolean;
}

interface ServiceOption {
    id: number;
    name: string;
}

const CATEGORIES = [
    { key: 'referrals', label: 'Referrals', color: 'emerald' },
    { key: 'orders', label: 'Orders', color: 'blue' },
    { key: 'deposits', label: 'Deposits', color: 'amber' },
];

const ORDINALS = ['', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];

export default function LeaderboardPrizes({ prizes, services }: PageProps<{ prizes: Prize[]; services: ServiceOption[] }>) {
    const [editingPrize, setEditingPrize] = useState<Prize | null>(null);
    const [isCreating, setIsCreating] = useState(false);
    const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        category: 'referrals',
        rank: 1,
        title: '',
        description: '',
        service_id: '' as string | number,
        service_quantity: '' as string | number,
        bonus_amount: '' as string | number,
        is_active: true,
    });

    const openCreate = () => {
        reset();
        setEditingPrize(null);
        setIsCreating(true);
    };

    const openEdit = (prize: Prize) => {
        setData({
            category: prize.category,
            rank: prize.rank,
            title: prize.title,
            description: prize.description ?? '',
            service_id: prize.service_id ?? '',
            service_quantity: prize.service_quantity ?? '',
            bonus_amount: prize.bonus_amount ?? '',
            is_active: prize.is_active,
        });
        setEditingPrize(prize);
        setIsCreating(false);
    };

    const closeForm = () => {
        setEditingPrize(null);
        setIsCreating(false);
        reset();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingPrize) {
            put(route('admin.leaderboard.prizes.update', editingPrize.id), { onSuccess: () => closeForm() });
        } else {
            post(route('admin.leaderboard.prizes.store'), { onSuccess: () => closeForm() });
        }
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-bold text-gray-900">Leaderboard Prizes</h2>}>
            <Head title="Leaderboard Prizes" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                    <div className="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">Monthly Prize Tiers</h3>
                            <p className="text-sm text-gray-500">Configure prizes for each leaderboard category and rank position.</p>
                        </div>
                        <button onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-brand-green px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-green/90 shadow-sm">
                            <Plus className="h-4 w-4" /> Add Prize
                        </button>
                    </div>

                    {/* Prize Form */}
                    {(isCreating || editingPrize) && (
                        <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                            <h3 className="mb-4 text-lg font-bold text-gray-900">{editingPrize ? 'Edit Prize' : 'Add New Prize'}</h3>
                            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Category</label>
                                    <select value={data.category} onChange={e => setData('category', e.target.value)} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none">
                                        {CATEGORIES.map(c => <option key={c.key} value={c.key}>{c.label}</option>)}
                                    </select>
                                    {errors.category && <p className="text-red-600 text-xs mt-1">{errors.category}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Rank Position</label>
                                    <select value={data.rank} onChange={e => setData('rank', Number(e.target.value))} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none">
                                        {[1,2,3,4,5,6,7,8,9,10].map(r => <option key={r} value={r}>{ORDINALS[r]} Place</option>)}
                                    </select>
                                    {errors.rank && <p className="text-red-600 text-xs mt-1">{errors.rank}</p>}
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Prize Title</label>
                                    <input type="text" value={data.title} onChange={e => setData('title', e.target.value)} placeholder="e.g. 10,000 Facebook Followers" className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none" required />
                                    {errors.title && <p className="text-red-600 text-xs mt-1">{errors.title}</p>}
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Description (optional)</label>
                                    <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={2} className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Wallet Bonus ($)</label>
                                    <input type="number" min="0" step="0.01" value={data.bonus_amount} onChange={e => setData('bonus_amount', e.target.value)} placeholder="0.00" className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none" />
                                    <p className="text-xs text-gray-400 mt-1">Auto-credited to winner's wallet on month close.</p>
                                </div>
                                <div className="flex items-end">
                                    <label className="inline-flex items-center gap-2 text-sm text-gray-700 pb-2">
                                        <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-brand-green focus:ring-brand-green" />
                                        Active
                                    </label>
                                </div>
                                <div className="sm:col-span-2 mt-2 flex gap-3">
                                    <button type="submit" disabled={processing} className="rounded-lg bg-brand-green px-5 py-2 text-sm font-semibold text-white transition hover:bg-brand-green/90 disabled:opacity-50 shadow-sm">
                                        {processing ? 'Saving...' : 'Save Prize'}
                                    </button>
                                    <button type="button" onClick={closeForm} className="rounded-lg border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 shadow-sm">Cancel</button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Prizes by Category */}
                    {CATEGORIES.map(cat => {
                        const categoryPrizes = prizes.filter(p => p.category === cat.key).sort((a, b) => a.rank - b.rank);
                        if (categoryPrizes.length === 0) return null;
                        return (
                            <div key={cat.key} className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                                <div className="bg-gray-50 border-b border-gray-200 px-6 py-3 flex items-center gap-2">
                                    <Trophy className="h-4 w-4 text-yellow-500" />
                                    <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wider">{cat.label} Prizes</h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-gray-50/50 border-b border-gray-100">
                                            <tr className="text-gray-500 font-medium text-xs uppercase tracking-wider">
                                                <th className="px-6 py-3 text-left">Rank</th>
                                                <th className="px-6 py-3 text-left">Title</th>
                                                <th className="px-6 py-3 text-left">Bonus</th>
                                                <th className="px-6 py-3 text-left">Status</th>
                                                <th className="px-6 py-3 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50">
                                            {categoryPrizes.map(prize => (
                                                <tr key={prize.id} className="hover:bg-gray-50 transition-colors">
                                                    <td className="px-6 py-4 font-bold text-gray-900">{ORDINALS[prize.rank] ?? `#${prize.rank}`}</td>
                                                    <td className="px-6 py-4">
                                                        <span className="font-semibold text-gray-900">{prize.title}</span>
                                                        {prize.description && <p className="text-xs text-gray-500 mt-0.5 line-clamp-1">{prize.description}</p>}
                                                    </td>
                                                    <td className="px-6 py-4 font-mono text-gray-900">${Number(prize.bonus_amount).toFixed(2)}</td>
                                                    <td className="px-6 py-4">
                                                        {prize.is_active ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">Active</span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 border border-gray-200">Inactive</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-right whitespace-nowrap">
                                                        <button onClick={() => openEdit(prize)} className="inline-flex items-center rounded-md p-2 text-brand-green hover:bg-brand-green/10 transition-colors">
                                                            <Edit2 className="h-4 w-4" />
                                                        </button>
                                                        <button onClick={() => setPendingDeleteId(prize.id)} className="inline-flex items-center rounded-md p-2 text-red-600 hover:bg-red-50 ms-1 transition-colors">
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        );
                    })}

                    {prizes.length === 0 && !isCreating && (
                        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/50 py-16 text-center">
                            <Gift className="h-12 w-12 text-gray-300 mb-3" />
                            <h3 className="text-lg font-bold text-gray-500">No Prizes Configured</h3>
                            <p className="mt-1 text-sm text-gray-400">Add prizes to incentivize your users on the leaderboard.</p>
                        </div>
                    )}
                </div>
            </div>

            {pendingDeleteId !== null && (
                <ConfirmModal
                    open
                    title="Delete Prize"
                    message="Are you sure you want to delete this prize? This will not affect past awards."
                    confirmLabel="Delete"
                    danger
                    onConfirm={() => { router.delete(route('admin.leaderboard.prizes.destroy', pendingDeleteId)); setPendingDeleteId(null); }}
                    onCancel={() => setPendingDeleteId(null)}
                />
            )}
        </AdminLayout>
    );
}
