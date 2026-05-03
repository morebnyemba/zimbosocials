import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaBriefcase, 
    FaGlobe, 
    FaClock, 
    FaDollarSign, 
    FaCheckCircle, 
    FaTimesCircle, 
    FaRegHandshake,
    FaArrowLeft,
    FaExternalLinkAlt,
    FaUserCircle,
    FaChevronRight,
    FaPaperPlane,
    FaRocket,
    FaStar,
} from 'react-icons/fa';

interface MarketerReview {
    id: number;
    rating: number;
    comment?: string | null;
    created_at: string;
}

interface Marketer {
    id: number;
    name: string;
    email: string;
    social_links: Array<{ platform: string; url: string }>;
    avg_rating?: number | null;
    review_count?: number | null;
}

interface Proof {
    id: number;
    proof_url: string;
    notes?: string;
    status: string;
    created_at: string;
}

interface Application {
    id: number;
    pitch: string;
    status: string;
    created_at: string;
    marketer: Marketer;
    proofs: Proof[];
    review?: MarketerReview | null;
}

interface Contract {
    id: number;
    title: string;
    platform?: string;
    description: string;
    budget?: string;
    slots?: number;
    deadline_at?: string;
    status: string;
    applications: Application[];
}

interface Props {
    contract: Contract;
}

type PendingAction =
    | { type: 'application'; applicationId: number; decision: 'approved' | 'denied' }
    | { type: 'proof'; proofId: number; decision: 'approved' | 'rejected' };

export default function ContractShow({ contract }: Props) {
    const { t } = useTranslation();
    const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);

    // Review modal state
    const [reviewingApp, setReviewingApp] = useState<Application | null>(null);
    const [reviewRating, setReviewRating] = useState(5);
    const [reviewComment, setReviewComment] = useState('');
    const [reviewSubmitting, setReviewSubmitting] = useState(false);

    const submitReview = () => {
        if (!reviewingApp) return;
        setReviewSubmitting(true);
        router.post(
            route('contracts.review.store', [contract.id, reviewingApp.id]),
            { rating: reviewRating, comment: reviewComment || null },
            {
                preserveScroll: true,
                onFinish: () => {
                    setReviewSubmitting(false);
                    setReviewingApp(null);
                    setReviewRating(5);
                    setReviewComment('');
                },
            }
        );
    };

    const handleDecision = (applicationId: number, decision: 'approved' | 'denied') => {
        setPendingAction({ type: 'application', applicationId, decision });
    };

    const handleProofDecision = (proofId: number, decision: 'approved' | 'rejected') => {
        setPendingAction({ type: 'proof', proofId, decision });
    };

    const confirmPendingAction = () => {
        if (!pendingAction) return;

        if (pendingAction.type === 'application') {
            router.post(route('contracts.applications.decision', [contract.id, pendingAction.applicationId]), {
                decision: pendingAction.decision,
            }, {
                preserveScroll: true,
                onFinish: () => setPendingAction(null),
            });
            return;
        }

        router.post(route('proof.review', pendingAction.proofId), {
            decision: pendingAction.decision,
        }, {
            preserveScroll: true,
            onFinish: () => setPendingAction(null),
        });
    };

    const confirmationMessage = pendingAction
        ? pendingAction.type === 'application'
            ? pendingAction.decision === 'approved'
                ? t('confirm_hire_marketer')
                : t('confirm_deny_applicant')
            : pendingAction.decision === 'approved'
                ? t('confirm_release_funds')
                : t('confirm_reject_proof')
        : '';

    const pendingApplications = contract.applications.filter(a => a.status === 'pending');
    const activeApplications = contract.applications.filter(a => a.status === 'approved');
    const completedApplications = contract.applications.filter(a => a.status === 'completed');
    const deniedApplications = contract.applications.filter(a => a.status === 'denied' || a.status === 'ignored');

    return (
        <AuthenticatedLayout>
            <Head title={t('contract_terminal_title', { title: contract.title })} />

            <div className="max-w-7xl mx-auto px-6 lg:px-12 py-12">
                {/* Back Button */}
                <Link 
                    href={route('contracts.index')}
                    className="flex items-center gap-2 text-zinc-400 hover:text-zinc-900 transition-colors mb-8 group w-fit"
                >
                    <FaArrowLeft className="group-hover:-translate-x-1 transition-transform" />
                    <span className="text-[10px] font-black uppercase tracking-widest">{t('marketplace_archive')}</span>
                </Link>

                <div className="grid lg:grid-cols-[1.5fr,1fr] gap-12 items-start">
                    {/* Left Column: Contract Details & Applications */}
                    <div className="space-y-12">
                        <header className="space-y-6">
                            <div className="flex items-center gap-3">
                                <div className={`px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border ${
                                    contract.status === 'open' ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 
                                    contract.status === 'filled' ? 'bg-blue-500/10 text-blue-500 border-blue-500/20' :
                                    'bg-zinc-500/10 text-zinc-500 border-zinc-500/20'
                                }`}>
                                    {contract.status === 'open' ? t('contract_status_open') : contract.status === 'filled' ? t('contract_status_filled') : t('contract_status_closed')}
                                </div>
                                <span className="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">{t('mission_parameters')}</span>
                            </div>
                            <h1 className="text-4xl font-black text-zinc-900 tracking-tighter leading-tight">
                                {contract.title}
                            </h1>
                            <div className="flex flex-wrap gap-6 items-center">
                                <DetailBadge icon={FaGlobe} label={contract.platform || t('cross_platform')} />
                                <DetailBadge icon={FaDollarSign} label={`$${Number(contract.budget || 0).toFixed(2)}`} highlight />
                                <DetailBadge icon={FaClock} label={contract.deadline_at ? t('due_on', { date: new Date(contract.deadline_at).toLocaleDateString() }) : t('no_deadline')} />
                            </div>
                        </header>

                        <section className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                            <h3 className="text-xl font-black text-zinc-900 tracking-tight mb-6">{t('briefing_overview')}</h3>
                            <div className="prose prose-zinc max-w-none">
                                <p className="text-zinc-500 font-medium leading-relaxed whitespace-pre-wrap">
                                    {contract.description}
                                </p>
                            </div>
                        </section>

                        <section className="space-y-10">
                            <div className="flex items-center justify-between">
                                <h3 className="text-2xl font-black text-zinc-900 tracking-tight">{t('deployment_roster')}</h3>
                                <div className="px-4 py-1.5 rounded-full bg-zinc-100 text-zinc-500 text-[10px] font-black uppercase tracking-widest">
                                    {t('total_signals', { count: contract.applications.length })}
                                </div>
                            </div>

                            {/* Pending Applications */}
                            <div className="space-y-6">
                                <h4 className="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-4 flex items-center gap-2">
                                    <div className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                    {t('awaiting_review', { count: pendingApplications.length })}
                                </h4>
                                {pendingApplications.map(app => (
                                    <ApplicationCard 
                                        key={app.id} 
                                        application={app} 
                                        onDecision={handleDecision}
                                        isPending
                                    />
                                ))}
                                {pendingApplications.length === 0 && (
                                    <div className="p-12 text-center bg-zinc-50 rounded-[2.5rem] border border-dashed border-zinc-200">
                                        <p className="text-zinc-400 font-bold text-sm">{t('all_incoming_processed')}</p>
                                    </div>
                                )}
                            </div>

                            {/* Active Deployments (In Progress) */}
                            {activeApplications.length > 0 && (
                                <div className="space-y-6 pt-10 border-t border-zinc-100">
                                    <h4 className="text-[10px] font-black uppercase tracking-[0.2em] text-blue-500 mb-4 flex items-center gap-2">
                                        <FaRocket className="animate-bounce" />
                                        {t('in_progress_review', { count: activeApplications.length })}
                                    </h4>
                                    {activeApplications.map(app => (
                                        <ApplicationCard 
                                            key={app.id} 
                                            application={app} 
                                            onDecision={handleDecision}
                                            onProofDecision={handleProofDecision}
                                            isActive
                                        />
                                    ))}
                                </div>
                            )}

                            {/* Completed */}
                            {completedApplications.length > 0 && (
                                <div className="space-y-6 pt-10 border-t border-zinc-100">
                                    <h4 className="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-500 mb-4">
                                        {t('mission_accomplished', { count: completedApplications.length })}
                                    </h4>
                                    {completedApplications.map(app => (
                                        <ApplicationCard 
                                            key={app.id} 
                                            application={app} 
                                            onDecision={handleDecision}
                                            onReview={() => setReviewingApp(app)}
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>

                    {/* Right Column: Sidebar Stats & Actions */}
                    <aside className="sticky top-12 space-y-8">
                        <div className="bg-zinc-900 rounded-[3rem] p-10 text-white shadow-2xl overflow-hidden relative group">
                            <div className="absolute top-0 right-0 w-32 h-32 bg-emerald-500/20 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-emerald-500/30 transition-colors" />
                            <div className="relative z-10 space-y-8">
                                <div>
                                    <h4 className="text-[10px] font-black uppercase tracking-widest text-emerald-400 mb-2">{t('escrow_budget_per_slot')}</h4>
                                    <p className="text-4xl font-black tracking-tighter">${Number(contract.budget || 0).toFixed(2)}</p>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                                        <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">{t('total_slots')}</p>
                                        <p className="text-xl font-black">{contract.slots || 1}</p>
                                    </div>
                                    <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                                        <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">{t('hired')}</p>
                                        <p className="text-xl font-black">{activeApplications.length + completedApplications.length}</p>
                                    </div>
                                </div>
                                <div className="p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/20">
                                    <p className="text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                        <FaCheckCircle /> {t('secure_escrow_active')}
                                    </p>
                                    <p className="text-xs text-zinc-400 font-medium">{t('escrow_hint')}</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40 space-y-6">
                            <h4 className="text-lg font-black text-zinc-900 tracking-tight">{t('market_analytics')}</h4>
                            <div className="space-y-4">
                                <HealthRow label={t('conversion_rate')} value={`${Math.round(((activeApplications.length + completedApplications.length) / (contract.applications.length || 1)) * 100)}%`} color="text-zinc-900" />
                                <HealthRow label={t('applicant_quality')} value={t('high_tier')} color="text-emerald-500" />
                                <HealthRow label={t('fill_velocity')} value={t('standard')} color="text-zinc-500" />
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <AnimatePresence>
                {pendingAction && (
                    <div className="fixed inset-0 z-[100] flex items-center justify-center p-6">
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="absolute inset-0 bg-zinc-900/60 backdrop-blur-sm"
                            onClick={() => setPendingAction(null)}
                        />
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95, y: 12 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.95, y: 12 }}
                            className="relative w-full max-w-lg rounded-3xl bg-white p-8 shadow-2xl"
                        >
                            <h3 className="text-xl font-black tracking-tight text-zinc-900">{t('confirm')}</h3>
                            <p className="mt-3 text-sm font-medium text-zinc-500">{confirmationMessage}</p>
                            <div className="mt-8 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setPendingAction(null)}
                                    className="h-11 rounded-2xl border border-zinc-200 px-5 text-[10px] font-black uppercase tracking-widest text-zinc-500 transition-colors hover:bg-zinc-50"
                                >
                                    {t('cancel')}
                                </button>
                                <button
                                    type="button"
                                    onClick={confirmPendingAction}
                                    className="h-11 rounded-2xl bg-zinc-900 px-6 text-[10px] font-black uppercase tracking-widest text-white transition-colors hover:bg-zinc-700"
                                >
                                    {t('confirm')}
                                </button>
                            </div>
                        </motion.div>
                    </div>
                )}

                {reviewingApp && (
                    <div className="fixed inset-0 z-[100] flex items-center justify-center p-6">
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="absolute inset-0 bg-zinc-900/60 backdrop-blur-sm"
                            onClick={() => setReviewingApp(null)}
                        />
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95, y: 12 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.95, y: 12 }}
                            className="relative w-full max-w-lg rounded-3xl bg-white p-8 shadow-2xl"
                        >
                            <h3 className="text-xl font-black tracking-tight text-zinc-900">Rate {reviewingApp.marketer.name}</h3>
                            <p className="mt-1 text-xs font-medium text-zinc-400">Share your experience working with this marketer.</p>

                            {/* Star picker */}
                            <div className="mt-6 flex items-center gap-2">
                                {[1,2,3,4,5].map(star => (
                                    <button
                                        key={star}
                                        type="button"
                                        onClick={() => setReviewRating(star)}
                                        className="transition-transform hover:scale-110"
                                    >
                                        <FaStar className={`text-3xl ${star <= reviewRating ? 'text-amber-400' : 'text-zinc-200'}`} />
                                    </button>
                                ))}
                                <span className="ml-3 text-sm font-black text-zinc-500">{reviewRating}/5</span>
                            </div>

                            {/* Comment */}
                            <textarea
                                className="mt-6 w-full rounded-2xl border border-zinc-200 p-4 text-sm font-medium text-zinc-700 placeholder-zinc-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
                                rows={4}
                                placeholder="Optional comment about this marketer's work..."
                                value={reviewComment}
                                onChange={e => setReviewComment(e.target.value)}
                                maxLength={500}
                            />

                            <div className="mt-6 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setReviewingApp(null)}
                                    className="h-11 rounded-2xl border border-zinc-200 px-5 text-[10px] font-black uppercase tracking-widest text-zinc-500 hover:bg-zinc-50 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={submitReview}
                                    disabled={reviewSubmitting}
                                    className="h-11 rounded-2xl bg-zinc-900 px-6 text-[10px] font-black uppercase tracking-widest text-white hover:bg-zinc-700 transition-colors disabled:opacity-50"
                                >
                                    {reviewSubmitting ? 'Submitting…' : 'Submit Review'}
                                </button>
                            </div>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </AuthenticatedLayout>
    );
}

function StarDisplay({ rating, size = 'md' }: { rating: number; size?: 'sm' | 'md' }) {
    const sz = size === 'sm' ? 'text-xs' : 'text-sm';
    return (
        <div className="flex items-center gap-0.5">
            {[1,2,3,4,5].map(s => (
                <FaStar key={s} className={`${sz} ${s <= Math.round(rating) ? 'text-amber-400' : 'text-zinc-200'}`} />
            ))}
        </div>
    );
}

function DetailBadge({ icon: Icon, label, highlight }: { icon: any; label: string; highlight?: boolean }) {    return (
        <div className={`flex items-center gap-2 text-[10px] font-black uppercase tracking-widest ${highlight ? 'text-emerald-500' : 'text-zinc-400'}`}>
            <Icon className={highlight ? 'text-emerald-500' : 'text-zinc-200'} />
            {label}
        </div>
    );
}

function HealthRow({ label, value, color }: { label: string; value: string; color: string }) {
    return (
        <div className="flex items-center justify-between py-3 border-b border-zinc-50 last:border-0">
            <span className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{label}</span>
            <span className={`text-xs font-black ${color}`}>{value}</span>
        </div>
    );
}

function ApplicationCard({ 
    application, 
    onDecision, 
    onProofDecision,
    onReview,
    isPending, 
    isActive 
}: { 
    application: Application; 
    onDecision: (id: number, d: 'approved' | 'denied') => void; 
    onProofDecision?: (id: number, d: 'approved' | 'rejected') => void;
    onReview?: () => void;
    isPending?: boolean;
    isActive?: boolean;
}) {
    const { t } = useTranslation();
    const pendingProof = application.proofs.find(p => p.status === 'pending');
    const proofStatusLabel = (status: string) => status === 'approved'
        ? t('status_approved')
        : status === 'rejected'
            ? t('status_rejected')
            : t('status_pending');
    const applicationStatusLabel = application.status === 'completed'
        ? t('status_completed')
        : application.status === 'approved'
            ? t('in_progress')
            : application.status === 'denied'
                ? t('status_denied')
                : application.status === 'ignored'
                    ? t('status_ignored')
                    : t('status_pending');

    return (
        <motion.div 
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            className={`bg-white rounded-[2.5rem] p-8 border transition-all ${
                isPending ? 'border-zinc-200 shadow-xl shadow-zinc-200/20' : 
                isActive ? 'border-blue-200 bg-blue-50/10' : 
                'border-zinc-100 opacity-80'
            }`}
        >
            <div className="flex flex-col md:flex-row gap-8">
                {/* Marketer Info */}
                <div className="md:w-1/3 space-y-4">
                    <div className="flex items-center gap-4">
                        <div className="h-12 w-12 rounded-2xl bg-zinc-900 flex items-center justify-center text-white shadow-lg overflow-hidden">
                            <FaUserCircle className="text-3xl text-zinc-600" />
                        </div>
                        <div>
                            <p className="font-black text-zinc-900 tracking-tight">{application.marketer.name}</p>
                            <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">{application.marketer.email}</p>
                            {(application.marketer.avg_rating ?? 0) > 0 && (
                                <div className="mt-1 flex items-center gap-1">
                                    <StarDisplay rating={application.marketer.avg_rating ?? 0} size="sm" />
                                    <span className="text-[9px] font-black text-zinc-400">({application.marketer.review_count})</span>
                                </div>
                            )}
                        </div>
                    </div>
                    
                    <div className="flex flex-wrap gap-2">
                        {application.marketer.social_links.map((link, idx) => (
                            <a 
                                key={idx}
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="px-3 py-1 rounded-lg bg-white border border-zinc-200 text-[10px] font-black uppercase tracking-widest text-zinc-400 hover:text-emerald-500 hover:border-emerald-500/30 transition-all flex items-center gap-2"
                            >
                                <FaGlobe className="text-[8px]" /> {link.platform}
                            </a>
                        ))}
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 space-y-6">
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-[9px] font-black uppercase tracking-widest text-zinc-400">{t('mission_pitch')}</span>
                            <span className="text-[9px] font-bold text-zinc-300">{new Date(application.created_at).toLocaleDateString()}</span>
                        </div>
                        <p className="text-sm text-zinc-600 font-medium leading-relaxed italic">
                            "{application.pitch || t('no_pitch_provided')}"
                        </p>
                    </div>

                    {/* Proof Section */}
                    {application.proofs.length > 0 && (
                        <div className="pt-6 border-t border-zinc-100 space-y-4">
                            <span className="text-[9px] font-black uppercase tracking-widest text-emerald-500 flex items-center gap-2">
                                <FaCheckCircle /> {t('proof_of_work_submission')}
                            </span>
                            {application.proofs.map(proof => (
                                <div key={proof.id} className="p-4 rounded-2xl bg-zinc-50 border border-zinc-100 flex items-start justify-between gap-4">
                                    <div className="space-y-2 min-w-0">
                                        <a href={proof.proof_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-zinc-900 font-bold text-xs hover:text-emerald-600 transition-colors truncate">
                                            <FaExternalLinkAlt className="text-[10px]" /> {t('view_deliverable')}
                                        </a>
                                        {proof.notes && <p className="text-[10px] text-zinc-500 font-medium italic">{proof.notes}</p>}
                                    </div>
                                    <div className={`px-3 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest ${
                                        proof.status === 'approved' ? 'bg-emerald-100 text-emerald-600' : 
                                        proof.status === 'rejected' ? 'bg-red-100 text-red-600' : 
                                        'bg-zinc-200 text-zinc-500'
                                    }`}>
                                        {proofStatusLabel(proof.status)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Action Column */}
                <div className="flex md:flex-col items-center justify-center gap-3">
                    {isPending && (
                        <>
                            <button 
                                onClick={() => onDecision(application.id, 'approved')}
                                className="h-12 w-12 rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/30 flex items-center justify-center hover:scale-110 transition-transform"
                                title={t('approve_hire')}
                            >
                                <FaCheckCircle />
                            </button>
                            <button 
                                onClick={() => onDecision(application.id, 'denied')}
                                className="h-12 w-12 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center hover:bg-red-50 hover:text-red-500 transition-all"
                                title={t('reject')}
                            >
                                <FaTimesCircle />
                            </button>
                        </>
                    )}

                    {isActive && pendingProof && onProofDecision && (
                        <div className="flex md:flex-col gap-2">
                            <button 
                                onClick={() => onProofDecision(pendingProof.id, 'approved')}
                                className="px-6 py-3 rounded-2xl bg-emerald-500 text-white text-[9px] font-black uppercase tracking-widest shadow-lg shadow-emerald-500/20 hover:scale-105 transition-all flex items-center gap-2"
                            >
                                <FaCheckCircle /> {t('approve_proof')}
                            </button>
                            <button 
                                onClick={() => onProofDecision(pendingProof.id, 'rejected')}
                                className="px-6 py-3 rounded-2xl bg-zinc-100 text-zinc-500 text-[9px] font-black uppercase tracking-widest hover:bg-red-50 hover:text-red-500 transition-all"
                            >
                                <FaTimesCircle /> {t('reject')}
                            </button>
                        </div>
                    )}

                    {!isPending && !pendingProof && (
                        <div className="flex flex-col items-center gap-2">
                            <div className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest ${
                                application.status === 'completed' ? 'bg-emerald-50 text-emerald-500' : 
                                application.status === 'approved' ? 'bg-blue-50 text-blue-500' :
                                'bg-zinc-100 text-zinc-400'
                            }`}>
                                {applicationStatusLabel}
                            </div>
                            {application.status === 'completed' && onReview && (
                                application.review ? (
                                    <div className="flex flex-col items-center gap-1 mt-1">
                                        <StarDisplay rating={application.review.rating} size="sm" />
                                        <span className="text-[8px] font-black text-zinc-400 uppercase tracking-widest">Reviewed</span>
                                    </div>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={onReview}
                                        className="mt-1 px-4 py-2 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 text-[9px] font-black uppercase tracking-widest hover:bg-amber-100 transition-colors flex items-center gap-1"
                                    >
                                        <FaStar className="text-[8px]" /> Leave Review
                                    </button>
                                )
                            )}
                        </div>
                    )}
                </div>
            </div>
        </motion.div>
    );
}
