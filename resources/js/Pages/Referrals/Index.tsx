import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';
import { useState, useEffect } from 'react';
import { FaCheckCircle, FaCopy, FaFacebookF, FaGift, FaInstagram, FaLink, FaMoneyBillWave, FaUsers, FaWhatsapp, FaTrophy, FaMedal, FaStar, FaCrown } from 'react-icons/fa';
import { Link } from '@inertiajs/react';
import confetti from 'canvas-confetti';

interface ReferralItem {
    id: number;
    name: string;
    email: string;
    joined_at: string | null;
    first_deposit_rewarded: boolean;
    completed_deposits: number;
    orders_count: number;
}

interface RewardItem {
    id: number;
    amount: number;
    method: string;
    reference?: string | null;
    notes?: string | null;
    created_at: string | null;
}

interface GlobalRewardItem {
    id: number;
    user_name: string;
    amount: number;
    method: string;
    time_ago: string;
}

interface Props {
    summary: {
        total_referrals: number;
        successful_first_deposits: number;
        total_rewards_earned: number;
        order_commissions_earned: number;
    };
    referralCode: string;
    referralLink: string;
    referrals: ReferralItem[];
    rewardHistory: RewardItem[];
    myRank: { rank: number; score: number } | null;
    globalRecentRewards: GlobalRewardItem[];
}

export default function ReferralsIndex({ summary, referralCode, referralLink, referrals, rewardHistory, myRank, globalRecentRewards }: Props) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const [shareStatus, setShareStatus] = useState<string | null>(null);

    const shareText = t('referral_share_message', { link: referralLink });
    const whatsappShareUrl = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
    const facebookShareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralLink)}`;

    const copyReferralLink = async () => {
        await navigator.clipboard.writeText(referralLink);
        setCopied(true);
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 },
            colors: ['#10b981', '#f59e0b', '#3b82f6'],
        });
        setTimeout(() => setCopied(false), 1800);
    };

    const copyShareMessage = async () => {
        try {
            await navigator.clipboard.writeText(shareText);
            setShareStatus(t('share_instagram_ready'));
        } catch {
            setShareStatus(t('share_copy_failed'));
        }
    };

    const shareToInstagram = async () => {
        try {
            await navigator.clipboard.writeText(shareText);
            if (navigator.share) {
                await navigator.share({
                    text: shareText,
                    url: referralLink,
                });
                setShareStatus(t('share_instagram_ready'));
                return;
            }
            window.open('https://www.instagram.com/', '_blank', 'noopener,noreferrer');
            setShareStatus(t('share_instagram_ready'));
        } catch {
            setShareStatus(t('share_copy_failed'));
        }
    };

    const getTierInfo = (total: number) => {
        if (total >= 20) return { name: 'Gold Ambassador', icon: FaCrown, color: 'text-yellow-400', bg: 'bg-yellow-400/20', border: 'border-yellow-400/30', next: null, progress: 100 };
        if (total >= 5) return { name: 'Silver Influencer', icon: FaStar, color: 'text-slate-300', bg: 'bg-slate-300/20', border: 'border-slate-300/30', next: 20, progress: (total / 20) * 100 };
        return { name: 'Bronze Promoter', icon: FaMedal, color: 'text-orange-400', bg: 'bg-orange-400/20', border: 'border-orange-400/30', next: 5, progress: (total / 5) * 100 };
    };
    
    const tier = getTierInfo(summary.total_referrals);

    return (
        <AuthenticatedLayout>
            <Head title={t('referrals_title')} />

            {/* Marquee Ticker */}
            {globalRecentRewards?.length > 0 && (
                <div className="relative flex overflow-x-hidden bg-emerald-950 text-emerald-100 py-2 border-b border-emerald-900/50">
                    <div className="animate-marquee whitespace-nowrap flex gap-8 px-4 items-center text-sm font-medium">
                        {globalRecentRewards.map((reward, i) => (
                            <span key={`${reward.id}-${i}`} className="inline-flex items-center gap-2">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                {reward.user_name} earned ${reward.amount.toFixed(2)} from a referral! <span className="text-emerald-500/70 text-xs">{reward.time_ago}</span>
                            </span>
                        ))}
                    </div>
                    {/* Duplicate for seamless looping */}
                    <div className="absolute top-0 py-2 animate-marquee2 whitespace-nowrap flex gap-8 px-4 items-center text-sm font-medium">
                        {globalRecentRewards.map((reward, i) => (
                            <span key={`dup-${reward.id}-${i}`} className="inline-flex items-center gap-2">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                {reward.user_name} earned ${reward.amount.toFixed(2)} from a referral! <span className="text-emerald-500/70 text-xs">{reward.time_ago}</span>
                            </span>
                        ))}
                    </div>
                </div>
            )}

            <div className="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.2),_transparent_30%),linear-gradient(135deg,#052e16_0%,#0f172a_45%,#14532d_100%)] p-6 text-white shadow-xl shadow-slate-900/10 sm:p-8">
                    <div className="grid gap-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                        <div className="min-w-0 space-y-4">
                            <div className="flex flex-wrap gap-2">
                                <span className="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-100">
                                    {t('referrals_program_badge')}
                                </span>
                                {myRank && (
                                    <Link href={route('leaderboard.index')} className="inline-flex items-center gap-1.5 rounded-full border border-yellow-400/30 bg-yellow-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.1em] text-yellow-400 hover:bg-yellow-400/20 transition-colors">
                                        <FaTrophy className="h-3 w-3" />
                                        Leaderboard Rank: #{myRank.rank}
                                    </Link>
                                )}
                                <span className={`inline-flex items-center gap-1.5 rounded-full border ${tier.border} ${tier.bg} px-3 py-1 text-xs font-semibold uppercase tracking-[0.1em] ${tier.color}`}>
                                    <tier.icon className="h-3 w-3" />
                                    {tier.name}
                                </span>
                            </div>
                            
                            {/* Progress to Next Tier */}
                            {tier.next !== null && (
                                <div className="mt-4 mb-2 max-w-sm">
                                    <div className="flex justify-between text-xs font-medium text-slate-300 mb-1.5">
                                        <span>Progress to next tier</span>
                                        <span>{summary.total_referrals} / {tier.next} referrals</span>
                                    </div>
                                    <div className="h-2 w-full bg-slate-950/40 rounded-full overflow-hidden border border-white/5">
                                        <div 
                                            className="h-full bg-gradient-to-r from-emerald-500 to-emerald-300 rounded-full transition-all duration-1000 ease-out"
                                            style={{ width: `${tier.progress}%` }}
                                        />
                                    </div>
                                </div>
                            )}

                            <div>
                                <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{t('referrals_hero_title')}</h1>
                                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-200 sm:text-base">
                                    {t('referrals_hero_subtitle')}
                                </p>
                            </div>
                            <div className="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur">
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">{t('referrals_code_label')}</p>
                                <p className="mt-2 break-all text-3xl font-semibold tracking-tight">{referralCode}</p>
                                <div className="mt-5 flex flex-col gap-3 sm:flex-row">
                                    <div className="min-w-0 flex-1 rounded-2xl border border-white/10 bg-slate-950/20 px-4 py-3 text-sm text-slate-200">
                                        <span className="block text-xs uppercase tracking-[0.2em] text-slate-400">{t('referrals_link_label')}</span>
                                        <span className="mt-1 block break-all font-medium">{referralLink}</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={copyReferralLink}
                                        className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-slate-900 transition hover:bg-emerald-50"
                                    >
                                        {copied ? <FaCheckCircle className="text-emerald-600" /> : <FaCopy />}
                                        {copied ? t('copied') : t('copy_link')}
                                    </button>
                                </div>
                            </div>

                            <div className="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">{t('share_referral')}</p>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-200">{t('share_referral_hint')}</p>
                                    </div>
                                </div>

                                <div className="mt-5 flex flex-wrap gap-3">
                                    <a
                                        href={whatsappShareUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-2 rounded-2xl bg-[#25D366] px-4 py-3 text-sm font-semibold text-white transition hover:brightness-105"
                                    >
                                        <FaWhatsapp />
                                        {t('share_whatsapp')}
                                    </a>
                                    <a
                                        href={facebookShareUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-2 rounded-2xl bg-[#1877F2] px-4 py-3 text-sm font-semibold text-white transition hover:brightness-105"
                                    >
                                        <FaFacebookF />
                                        {t('share_facebook')}
                                    </a>
                                    <button
                                        type="button"
                                        onClick={shareToInstagram}
                                        className="inline-flex items-center gap-2 rounded-2xl bg-[linear-gradient(135deg,#f58529,#dd2a7b,#8134af,#515bd4)] px-4 py-3 text-sm font-semibold text-white transition hover:brightness-105"
                                    >
                                        <FaInstagram />
                                        {t('share_instagram')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={copyShareMessage}
                                        className="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/15"
                                    >
                                        <FaCopy />
                                        {t('share_copy_message')}
                                    </button>
                                </div>

                                <p className="mt-4 text-xs leading-5 text-slate-300">{t('share_instagram_hint')}</p>
                                {shareStatus && <p className="mt-2 text-xs font-medium text-emerald-200">{shareStatus}</p>}

                                <div className="mt-4 rounded-2xl border border-white/10 bg-slate-950/20 p-4 text-sm text-slate-200">
                                    <span className="block text-xs uppercase tracking-[0.2em] text-slate-400">{t('share_preview_label')}</span>
                                    <p className="mt-2 whitespace-pre-wrap break-words">{shareText}</p>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                            <SummaryCard icon={FaUsers} label={t('referrals_total')} value={summary.total_referrals} />
                            <SummaryCard icon={FaGift} label={t('referrals_first_deposits')} value={summary.successful_first_deposits} />
                            <SummaryCard icon={FaMoneyBillWave} label={t('referrals_total_rewards')} value={`$${Number(summary.total_rewards_earned).toFixed(2)}`} />
                            <SummaryCard icon={FaLink} label={t('referrals_order_commissions')} value={`$${Number(summary.order_commissions_earned).toFixed(2)}`} />
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                    <div className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/70">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">{t('referred_users')}</h2>
                                <p className="text-sm text-slate-500">{t('referred_users_subtitle')}</p>
                            </div>
                            <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                {t('users_count', { count: referrals.length })}
                            </span>
                        </div>

                        <div className="mt-5 space-y-3">
                            {referrals.length === 0 && (
                                <p className="text-sm text-slate-500">{t('no_referrals_yet')}</p>
                            )}

                            {referrals.map((referral) => (
                                <div key={referral.id} className="rounded-2xl border border-slate-200 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-slate-900">{referral.name}</p>
                                            <p className="truncate text-xs text-slate-500">{referral.email}</p>
                                        </div>
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${referral.first_deposit_rewarded ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                            {referral.first_deposit_rewarded ? t('first_deposit_rewarded') : t('awaiting_first_deposit')}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-4 text-xs text-slate-500">
                                        <span>{t('joined_label')}: {referral.joined_at ? new Date(referral.joined_at).toLocaleDateString() : t('not_available')}</span>
                                        <span>{t('deposits_label')}: {referral.completed_deposits}</span>
                                        <span>{t('orders_label')}: {referral.orders_count}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/70">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">{t('reward_history')}</h2>
                            <p className="mt-1 text-sm text-slate-500">{t('reward_history_subtitle')}</p>
                        </div>

                        <div className="mt-5 space-y-3">
                            {rewardHistory.length === 0 && (
                                <p className="text-sm text-slate-500">{t('no_referral_rewards_yet')}</p>
                            )}

                            {rewardHistory.map((reward) => (
                                <div key={reward.id} className="rounded-2xl border border-slate-200 p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900">
                                                {reward.method === 'referral' ? t('first_deposit_reward') : t('order_commission')}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">{reward.reference || reward.notes || t('referral_reward_fallback')}</p>
                                        </div>
                                        <span className="text-sm font-semibold text-emerald-700">+${Number(reward.amount).toFixed(2)}</span>
                                    </div>
                                    <p className="mt-2 text-xs text-slate-400">
                                        {reward.created_at ? new Date(reward.created_at).toLocaleString() : t('not_available')}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function SummaryCard({ icon: Icon, label, value }: { icon: any; label: string; value: string | number }) {
    return (
        <div className="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">{label}</p>
                    <p className="mt-3 text-3xl font-semibold tracking-tight text-white">{value}</p>
                </div>
                <div className="rounded-2xl bg-white/10 p-3 text-emerald-200">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}
