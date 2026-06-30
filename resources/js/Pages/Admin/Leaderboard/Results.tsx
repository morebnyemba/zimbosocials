import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import { Trophy, CheckCircle2, Clock, Award } from 'lucide-react';

interface ResultEntry {
    id: number;
    category: string;
    rank: number;
    score: number;
    user_name: string;
    user_email: string | null;
    prize_title: string | null;
    is_awarded: boolean;
    awarded_at: string | null;
    notes: string | null;
}

interface AvailableMonth {
    year: number;
    month: number;
}

const CATEGORIES = [
    { key: 'referrals', label: 'Referrals' },
    { key: 'orders', label: 'Orders' },
    { key: 'deposits', label: 'Deposits' },
];

const ORDINALS = ['', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
const MONTH_NAMES = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

export default function LeaderboardResults({ results, year, month, availableMonths }: PageProps<{ results: ResultEntry[]; year: number; month: number; availableMonths: AvailableMonth[] }>) {
    const [awardingId, setAwardingId] = useState<number | null>(null);
    const [awardNotes, setAwardNotes] = useState('');

    const monthLabel = `${MONTH_NAMES[month] ?? month} ${year}`;

    const handleAward = (snapshotId: number) => {
        router.post(route('admin.leaderboard.award', snapshotId), { notes: awardNotes || 'Manually awarded by admin.' }, {
            preserveScroll: true,
            onSuccess: () => { setAwardingId(null); setAwardNotes(''); },
        });
    };

    const navigateToMonth = (y: number, m: number) => {
        router.get(route('admin.leaderboard.results', { year: y, month: m }));
    };

    return (
        <AdminLayout header={<h2 className="text-xl font-bold text-gray-900">Leaderboard Results</h2>}>
            <Head title={`Leaderboard Results — ${monthLabel}`} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                    <div className="flex flex-wrap justify-between items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">{monthLabel} Results</h3>
                            <p className="text-sm text-gray-500">Review and award prizes to monthly leaderboard winners.</p>
                        </div>
                        {availableMonths.length > 0 && (
                            <select
                                value={`${year}-${month}`}
                                onChange={e => {
                                    const [y, m] = e.target.value.split('-').map(Number);
                                    navigateToMonth(y, m);
                                }}
                                className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-green focus:ring-brand-green/20 outline-none"
                            >
                                {availableMonths.map(m => (
                                    <option key={`${m.year}-${m.month}`} value={`${m.year}-${m.month}`}>
                                        {MONTH_NAMES[m.month] ?? m.month} {m.year}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>

                    {results.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/50 py-16 text-center">
                            <Trophy className="h-12 w-12 text-gray-300 mb-3" />
                            <h3 className="text-lg font-bold text-gray-500">No Results</h3>
                            <p className="mt-1 text-sm text-gray-400">
                                No leaderboard snapshots exist for this month. The command runs automatically on the 1st.
                            </p>
                        </div>
                    ) : (
                        CATEGORIES.map(cat => {
                            const catResults = results.filter(r => r.category === cat.key);
                            if (catResults.length === 0) return null;
                            return (
                                <div key={cat.key} className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                                    <div className="bg-gray-50 border-b border-gray-200 px-6 py-3 flex items-center gap-2">
                                        <Award className="h-4 w-4 text-yellow-500" />
                                        <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wider">{cat.label}</h3>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-sm">
                                            <thead className="bg-gray-50/50 border-b border-gray-100">
                                                <tr className="text-gray-500 font-medium text-xs uppercase tracking-wider">
                                                    <th className="px-6 py-3 text-left">Rank</th>
                                                    <th className="px-6 py-3 text-left">User</th>
                                                    <th className="px-6 py-3 text-right">Score</th>
                                                    <th className="px-6 py-3 text-left">Prize</th>
                                                    <th className="px-6 py-3 text-left">Status</th>
                                                    <th className="px-6 py-3 text-right">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-50">
                                                {catResults.map(entry => (
                                                    <tr key={entry.id} className="hover:bg-gray-50 transition-colors">
                                                        <td className="px-6 py-4 font-bold text-gray-900">{ORDINALS[entry.rank] ?? `#${entry.rank}`}</td>
                                                        <td className="px-6 py-4">
                                                            <span className="font-semibold text-gray-900">{entry.user_name}</span>
                                                            {entry.user_email && <p className="text-xs text-gray-500">{entry.user_email}</p>}
                                                        </td>
                                                        <td className="px-6 py-4 text-right font-mono font-bold text-gray-900">{Number(entry.score).toLocaleString()}</td>
                                                        <td className="px-6 py-4 text-sm text-gray-700">{entry.prize_title ?? '—'}</td>
                                                        <td className="px-6 py-4">
                                                            {entry.is_awarded ? (
                                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                                                    <CheckCircle2 className="h-3 w-3" /> Awarded
                                                                </span>
                                                            ) : entry.prize_title ? (
                                                                <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 border border-amber-200">
                                                                    <Clock className="h-3 w-3" /> Pending
                                                                </span>
                                                            ) : (
                                                                <span className="text-xs text-gray-400">No prize</span>
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-4 text-right">
                                                            {entry.prize_title && !entry.is_awarded && (
                                                                awardingId === entry.id ? (
                                                                    <div className="flex items-center gap-2 justify-end">
                                                                        <input
                                                                            type="text"
                                                                            value={awardNotes}
                                                                            onChange={e => setAwardNotes(e.target.value)}
                                                                            placeholder="Notes (optional)"
                                                                            className="w-40 rounded-md border border-gray-300 px-2 py-1.5 text-xs focus:border-brand-green focus:ring-brand-green/20 outline-none"
                                                                        />
                                                                        <button onClick={() => handleAward(entry.id)} className="rounded-md bg-brand-green px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-green/90">
                                                                            Confirm
                                                                        </button>
                                                                        <button onClick={() => { setAwardingId(null); setAwardNotes(''); }} className="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                                                            Cancel
                                                                        </button>
                                                                    </div>
                                                                ) : (
                                                                    <button onClick={() => setAwardingId(entry.id)} className="rounded-md bg-brand-green/10 px-3 py-1.5 text-xs font-semibold text-brand-green hover:bg-brand-green/20 transition-colors">
                                                                        Mark Awarded
                                                                    </button>
                                                                )
                                                            )}
                                                            {entry.is_awarded && entry.notes && (
                                                                <p className="text-xs text-gray-400 mt-1">{entry.notes}</p>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
