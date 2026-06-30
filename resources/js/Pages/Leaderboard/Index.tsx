import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState, useMemo } from 'react';
import { Trophy, Medal, Flame, TrendingUp, Clock, Star, ChevronDown, ChevronUp, Users, ShoppingCart, Wallet, Crown, Award, Zap } from 'lucide-react';

interface RankingEntry {
    user_id: number;
    user_name: string;
    score: number;
    rank: number;
}

interface PrizeInfo {
    rank: number;
    title: string;
    description: string | null;
    bonus_amount: number | null;
}

interface WinnerEntry {
    rank: number;
    user_name: string;
    score: number;
    prize_title: string | null;
    is_awarded: boolean;
}

interface MyRank {
    rank: number;
    score: number;
}

interface Props extends PageProps {
    rankings: Record<string, RankingEntry[]>;
    myRanks: Record<string, MyRank | null>;
    previousWinners: Record<string, WinnerEntry[]>;
    prizes: Record<string, PrizeInfo[]>;
    monthLabel: string;
    daysRemaining: number;
    previousMonthLabel: string;
}

const CATEGORIES = [
    { key: 'referrals', label: 'Top Referrers', icon: Users, color: 'emerald', description: 'Most successful referrals this month' },
    { key: 'orders', label: 'Top Orderers', icon: ShoppingCart, color: 'blue', description: 'Most orders placed this month' },
    { key: 'deposits', label: 'Top Depositors', icon: Wallet, color: 'amber', description: 'Highest deposit volume this month' },
] as const;

const MEDAL_STYLES: Record<number, { bg: string; border: string; text: string; glow: string; icon: string }> = {
    1: { bg: 'from-yellow-400/20 via-amber-300/10 to-yellow-500/20', border: 'border-yellow-400/60', text: 'text-yellow-500', glow: 'shadow-yellow-400/30', icon: '🥇' },
    2: { bg: 'from-gray-300/20 via-slate-200/10 to-gray-400/20', border: 'border-gray-300/60', text: 'text-gray-400', glow: 'shadow-gray-300/20', icon: '🥈' },
    3: { bg: 'from-orange-400/20 via-amber-600/10 to-orange-500/20', border: 'border-orange-400/60', text: 'text-orange-500', glow: 'shadow-orange-400/20', icon: '🥉' },
};

function PodiumCard({ entry, prize, categoryColor }: { entry: RankingEntry; prize?: PrizeInfo; categoryColor: string }) {
    const style = MEDAL_STYLES[entry.rank] ?? { bg: 'from-gray-100 to-gray-200', border: 'border-gray-200', text: 'text-gray-500', glow: '', icon: `#${entry.rank}` };
    const isFirst = entry.rank === 1;

    return (
        <div className={`relative flex flex-col items-center rounded-2xl border bg-gradient-to-b ${style.bg} ${style.border} p-5 shadow-lg ${style.glow} transition-all duration-300 hover:scale-105 ${isFirst ? 'order-1 md:-mt-6 z-10' : entry.rank === 2 ? 'order-0' : 'order-2'}`}>
            {isFirst && (
                <div className="absolute -top-4 flex items-center gap-1 rounded-full bg-gradient-to-r from-yellow-400 to-amber-500 px-3 py-1 text-xs font-black text-white shadow-lg">
                    <Crown className="h-3 w-3" /> CHAMPION
                </div>
            )}
            <span className="text-4xl mb-2">{style.icon}</span>
            <div className={`flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-gray-800 to-gray-900 text-xl font-black text-white shadow-inner ${isFirst ? 'ring-4 ring-yellow-400/50' : ''}`}>
                {entry.user_name.charAt(0).toUpperCase()}
            </div>
            <h4 className="mt-3 text-sm font-bold text-gray-900 text-center truncate max-w-[140px]">{entry.user_name}</h4>
            <p className={`mt-1 text-2xl font-black ${style.text}`}>{formatScore(entry.score)}</p>
            {prize && (
                <div className="mt-3 rounded-lg bg-white/70 px-3 py-2 text-center border border-gray-200/50">
                    <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">Prize</p>
                    <p className="text-xs font-bold text-gray-800 mt-0.5">{prize.title}</p>
                </div>
            )}
        </div>
    );
}

function formatScore(score: number): string {
    if (score >= 1000) {
        return `$${(score / 1000).toFixed(1)}k`;
    }
    if (score % 1 !== 0) {
        return `$${score.toFixed(2)}`;
    }
    return score.toLocaleString();
}

function RankBadge({ rank }: { rank: number }) {
    if (rank <= 3) {
        const colors = { 1: 'bg-yellow-400 text-yellow-900', 2: 'bg-gray-300 text-gray-700', 3: 'bg-orange-400 text-orange-900' };
        return <span className={`inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-black ${colors[rank as 1 | 2 | 3]}`}>{rank}</span>;
    }
    return <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">{rank}</span>;
}

export default function LeaderboardIndex({ rankings, myRanks, previousWinners, prizes, monthLabel, daysRemaining, previousMonthLabel }: Props) {
    const [activeCategory, setActiveCategory] = useState<string>('referrals');
    const [showPreviousWinners, setShowPreviousWinners] = useState(false);

    const activeCategoryConfig = CATEGORIES.find(c => c.key === activeCategory)!;
    const currentRankings = rankings[activeCategory] ?? [];
    const currentPrizes = prizes[activeCategory] ?? [];
    const myRank = myRanks[activeCategory];
    const previousCategoryWinners = previousWinners[activeCategory] ?? [];

    const podiumEntries = currentRankings.slice(0, 3);
    const tableEntries = currentRankings.slice(3);

    const getPrizeForRank = (rank: number) => currentPrizes.find(p => p.rank === rank);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-bold text-gray-900">Leaderboard</h2>}>
            <Head title="Leaderboard" />

            <div className="py-6">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-8">

                    {/* Hero Section */}
                    <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 p-8 text-white shadow-2xl">
                        <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9InAiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTTAgMGg2MHY2MEgweiIgZmlsbD0ibm9uZSIvPjxjaXJjbGUgY3g9IjMwIiBjeT0iMzAiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wNSkiLz48L3BhdHRlcm4+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjcCkiLz48L3N2Zz4=')] opacity-40" />
                        <div className="relative z-10 flex flex-col items-center text-center">
                            <div className="mb-4 flex items-center gap-2 rounded-full bg-yellow-500/20 px-4 py-2 text-sm font-bold text-yellow-400 border border-yellow-500/30">
                                <Trophy className="h-4 w-4" />
                                Monthly Champions
                            </div>
                            <h1 className="text-3xl md:text-4xl font-black tracking-tight">{monthLabel}</h1>
                            <p className="mt-2 max-w-lg text-gray-400">
                                Compete for real prizes! Refer friends, place orders, and fund your wallet to climb the ranks.
                            </p>
                            <div className="mt-5 flex items-center gap-2 rounded-xl bg-white/10 px-5 py-3 backdrop-blur-sm border border-white/10">
                                <Clock className="h-4 w-4 text-yellow-400" />
                                <span className="text-sm font-semibold">
                                    <span className="text-yellow-400">{daysRemaining}</span> days remaining this month
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Your Rank Cards */}
                    {(myRanks.referrals || myRanks.orders || myRanks.deposits) && (
                        <div className="grid gap-4 sm:grid-cols-3">
                            {CATEGORIES.map(cat => {
                                const rank = myRanks[cat.key];
                                if (!rank) return (
                                    <div key={cat.key} className="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 p-4 text-center">
                                        <cat.icon className="mx-auto h-5 w-5 text-gray-300" />
                                        <p className="mt-2 text-xs text-gray-400">Not ranked in {cat.label}</p>
                                    </div>
                                );
                                return (
                                    <button key={cat.key} onClick={() => setActiveCategory(cat.key)} className={`relative overflow-hidden rounded-2xl border p-4 text-left transition-all hover:shadow-md ${activeCategory === cat.key ? 'border-brand-green bg-brand-green/5 shadow-sm' : 'border-gray-200 bg-white'}`}>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <cat.icon className={`h-4 w-4 ${activeCategory === cat.key ? 'text-brand-green' : 'text-gray-400'}`} />
                                                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{cat.label}</span>
                                            </div>
                                            {rank.rank <= 3 && <Flame className="h-4 w-4 text-orange-500 animate-pulse" />}
                                        </div>
                                        <p className="mt-2 text-3xl font-black text-gray-900">#{rank.rank}</p>
                                        <p className="text-xs text-gray-500 mt-1">Score: {formatScore(rank.score)}</p>
                                    </button>
                                );
                            })}
                        </div>
                    )}

                    {/* Category Tabs */}
                    <div className="flex gap-2 overflow-x-auto pb-1">
                        {CATEGORIES.map(cat => (
                            <button
                                key={cat.key}
                                onClick={() => setActiveCategory(cat.key)}
                                className={`flex items-center gap-2 whitespace-nowrap rounded-xl px-5 py-3 text-sm font-bold transition-all ${activeCategory === cat.key ? 'bg-gray-900 text-white shadow-lg' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'}`}
                            >
                                <cat.icon className="h-4 w-4" />
                                {cat.label}
                            </button>
                        ))}
                    </div>

                    {/* Prize Showcase */}
                    {currentPrizes.length > 0 && (
                        <div className="rounded-2xl border border-gray-200 bg-gradient-to-r from-yellow-50/50 via-white to-amber-50/50 p-5">
                            <div className="flex items-center gap-2 mb-4">
                                <Award className="h-5 w-5 text-yellow-500" />
                                <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Prizes Up For Grabs</h3>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3 md:grid-cols-5">
                                {currentPrizes.map(prize => (
                                    <div key={prize.rank} className="flex flex-col items-center rounded-xl bg-white border border-gray-100 p-3 shadow-sm text-center">
                                        <span className="text-lg">{MEDAL_STYLES[prize.rank]?.icon ?? `#${prize.rank}`}</span>
                                        <p className="mt-1 text-xs font-bold text-gray-900 line-clamp-2">{prize.title}</p>
                                        {prize.bonus_amount != null && prize.bonus_amount > 0 && (
                                            <p className="mt-1 text-[10px] font-semibold text-emerald-600">+ ${prize.bonus_amount} bonus</p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Podium - Top 3 */}
                    {podiumEntries.length > 0 ? (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3 md:items-end">
                            {podiumEntries.map(entry => (
                                <PodiumCard key={entry.user_id} entry={entry} prize={getPrizeForRank(entry.rank)} categoryColor={activeCategoryConfig.color} />
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/50 py-16 text-center">
                            <Trophy className="h-12 w-12 text-gray-300 mb-3" />
                            <h3 className="text-lg font-bold text-gray-500">No Rankings Yet</h3>
                            <p className="mt-1 text-sm text-gray-400 max-w-xs">
                                Be the first to climb the {activeCategoryConfig.label} leaderboard this month!
                            </p>
                        </div>
                    )}

                    {/* Rest of Rankings Table */}
                    {tableEntries.length > 0 && (
                        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 bg-gray-50/80">
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Rank</th>
                                            <th className="px-6 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-gray-500">User</th>
                                            <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Score</th>
                                            <th className="px-6 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Prize</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {tableEntries.map(entry => {
                                            const prize = getPrizeForRank(entry.rank);
                                            const isMe = myRank?.rank === entry.rank;
                                            return (
                                                <tr key={entry.user_id} className={`transition-colors ${isMe ? 'bg-brand-green/5 font-semibold' : 'hover:bg-gray-50'}`}>
                                                    <td className="px-6 py-4"><RankBadge rank={entry.rank} /></td>
                                                    <td className="px-6 py-4 font-medium text-gray-900">
                                                        {entry.user_name}
                                                        {isMe && <span className="ml-2 text-[10px] font-black text-brand-green bg-brand-green/10 px-2 py-0.5 rounded-full">YOU</span>}
                                                    </td>
                                                    <td className="px-6 py-4 text-right font-mono font-bold text-gray-900">{formatScore(entry.score)}</td>
                                                    <td className="px-6 py-4 text-right text-xs text-gray-500">{prize?.title ?? '—'}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Previous Month Winners */}
                    {Object.keys(previousWinners).length > 0 && (
                        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                            <button
                                onClick={() => setShowPreviousWinners(!showPreviousWinners)}
                                className="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <Star className="h-5 w-5 text-yellow-500" />
                                    <div>
                                        <h3 className="text-sm font-bold text-gray-900">{previousMonthLabel} Champions</h3>
                                        <p className="text-xs text-gray-500">View last month's winners</p>
                                    </div>
                                </div>
                                {showPreviousWinners ? <ChevronUp className="h-4 w-4 text-gray-400" /> : <ChevronDown className="h-4 w-4 text-gray-400" />}
                            </button>
                            {showPreviousWinners && (
                                <div className="border-t border-gray-100 p-6 space-y-6">
                                    {CATEGORIES.map(cat => {
                                        const winners = previousWinners[cat.key] ?? [];
                                        if (winners.length === 0) return null;
                                        return (
                                            <div key={cat.key}>
                                                <div className="flex items-center gap-2 mb-3">
                                                    <cat.icon className="h-4 w-4 text-gray-400" />
                                                    <h4 className="text-xs font-bold uppercase tracking-wider text-gray-500">{cat.label}</h4>
                                                </div>
                                                <div className="space-y-2">
                                                    {winners.map(winner => (
                                                        <div key={winner.rank} className="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3">
                                                            <div className="flex items-center gap-3">
                                                                <RankBadge rank={winner.rank} />
                                                                <span className="text-sm font-semibold text-gray-900">{winner.user_name}</span>
                                                            </div>
                                                            <div className="text-right">
                                                                <span className="text-sm font-bold text-gray-900">{formatScore(winner.score)}</span>
                                                                {winner.prize_title && (
                                                                    <span className="ml-3 text-xs text-gray-500">{winner.prize_title}</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    )}

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
