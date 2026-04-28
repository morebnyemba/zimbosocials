import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
    FaPaperPlane
} from 'react-icons/fa';

interface Marketer {
    id: number;
    name: string;
    email: string;
    social_links: Array<{ platform: string; url: string }>;
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

export default function ContractShow({ contract }: Props) {
    const handleDecision = (applicationId: number, decision: 'approved' | 'denied') => {
        const msg = decision === 'approved' 
            ? "Are you sure? This will hire the marketer and move the budget to escrow." 
            : "Are you sure you want to deny this applicant?";
            
        if (confirm(msg)) {
            router.post(route('contracts.applications.decision', [contract.id, applicationId]), {
                decision: decision === 'approved' ? 'approved' : 'denied'
            }, { preserveScroll: true });
        }
    };

    const handleProofDecision = (proofId: number, decision: 'approved' | 'rejected') => {
        const msg = decision === 'approved'
            ? "Confirming this proof will release the funds from escrow to the marketer's wallet. Proceed?"
            : "Reject this proof? The marketer will need to submit a new link.";

        if (confirm(msg)) {
            router.post(route('proof.review', proofId), {
                decision
            }, { preserveScroll: true });
        }
    };

    const pendingApplications = contract.applications.filter(a => a.status === 'pending');
    const activeApplications = contract.applications.filter(a => a.status === 'approved');
    const completedApplications = contract.applications.filter(a => a.status === 'completed');
    const deniedApplications = contract.applications.filter(a => a.status === 'denied' || a.status === 'ignored');

    return (
        <AuthenticatedLayout>
            <Head title={`Contract Terminal: ${contract.title}`} />

            <div className="max-w-7xl mx-auto px-6 lg:px-12 py-12">
                {/* Back Button */}
                <Link 
                    href={route('contracts.index')}
                    className="flex items-center gap-2 text-zinc-400 hover:text-zinc-900 transition-colors mb-8 group w-fit"
                >
                    <FaArrowLeft className="group-hover:-translate-x-1 transition-transform" />
                    <span className="text-[10px] font-black uppercase tracking-widest">Marketplace Archive</span>
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
                                    {contract.status}
                                </div>
                                <span className="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Mission Parameters</span>
                            </div>
                            <h1 className="text-4xl font-black text-zinc-900 tracking-tighter leading-tight">
                                {contract.title}
                            </h1>
                            <div className="flex flex-wrap gap-6 items-center">
                                <DetailBadge icon={FaGlobe} label={contract.platform || 'Cross-Platform'} />
                                <DetailBadge icon={FaDollarSign} label={`$${Number(contract.budget || 0).toFixed(2)}`} highlight />
                                <DetailBadge icon={FaClock} label={contract.deadline_at ? `Due ${new Date(contract.deadline_at).toLocaleDateString()}` : 'No Deadline'} />
                            </div>
                        </header>

                        <section className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                            <h3 className="text-xl font-black text-zinc-900 tracking-tight mb-6">Briefing Overview</h3>
                            <div className="prose prose-zinc max-w-none">
                                <p className="text-zinc-500 font-medium leading-relaxed whitespace-pre-wrap">
                                    {contract.description}
                                </p>
                            </div>
                        </section>

                        <section className="space-y-10">
                            <div className="flex items-center justify-between">
                                <h3 className="text-2xl font-black text-zinc-900 tracking-tight">Deployment Roster</h3>
                                <div className="px-4 py-1.5 rounded-full bg-zinc-100 text-zinc-500 text-[10px] font-black uppercase tracking-widest">
                                    {contract.applications.length} Total Signals
                                </div>
                            </div>

                            {/* Pending Applications */}
                            <div className="space-y-6">
                                <h4 className="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-4 flex items-center gap-2">
                                    <div className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                    Awaiting Review ({pendingApplications.length})
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
                                        <p className="text-zinc-400 font-bold text-sm">All incoming signals have been processed.</p>
                                    </div>
                                )}
                            </div>

                            {/* Active Deployments (In Progress) */}
                            {activeApplications.length > 0 && (
                                <div className="space-y-6 pt-10 border-t border-zinc-100">
                                    <h4 className="text-[10px] font-black uppercase tracking-[0.2em] text-blue-500 mb-4 flex items-center gap-2">
                                        <FaRocket className="animate-bounce" />
                                        In Progress / Review ({activeApplications.length})
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
                                        Mission Accomplished ({completedApplications.length})
                                    </h4>
                                    {completedApplications.map(app => (
                                        <ApplicationCard 
                                            key={app.id} 
                                            application={app} 
                                            onDecision={handleDecision}
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
                                    <h4 className="text-[10px] font-black uppercase tracking-widest text-emerald-400 mb-2">Escrow Budget (Per Slot)</h4>
                                    <p className="text-4xl font-black tracking-tighter">${Number(contract.budget || 0).toFixed(2)}</p>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                                        <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">Total Slots</p>
                                        <p className="text-xl font-black">{contract.slots || 1}</p>
                                    </div>
                                    <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                                        <p className="text-[9px] font-black uppercase tracking-widest text-zinc-500 mb-1">Hired</p>
                                        <p className="text-xl font-black">{activeApplications.length + completedApplications.length}</p>
                                    </div>
                                </div>
                                <div className="p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/20">
                                    <p className="text-[10px] font-black text-emerald-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                        <FaCheckCircle /> Secure Escrow Active
                                    </p>
                                    <p className="text-xs text-zinc-400 font-medium">Funds are deducted upon hire but held safely until you verify the work.</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40 space-y-6">
                            <h4 className="text-lg font-black text-zinc-900 tracking-tight">Market Analytics</h4>
                            <div className="space-y-4">
                                <HealthRow label="Conversion Rate" value={`${Math.round(((activeApplications.length + completedApplications.length) / (contract.applications.length || 1)) * 100)}%`} color="text-zinc-900" />
                                <HealthRow label="Applicant Quality" value="High-Tier" color="text-emerald-500" />
                                <HealthRow label="Fill Velocity" value="Standard" color="text-zinc-500" />
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function DetailBadge({ icon: Icon, label, highlight }: { icon: any; label: string; highlight?: boolean }) {
    return (
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
    isPending, 
    isActive 
}: { 
    application: Application; 
    onDecision: (id: number, d: 'approved' | 'denied') => void; 
    onProofDecision?: (id: number, d: 'approved' | 'rejected') => void;
    isPending?: boolean;
    isActive?: boolean;
}) {
    const pendingProof = application.proofs.find(p => p.status === 'pending');

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
                            <span className="text-[9px] font-black uppercase tracking-widest text-zinc-400">Mission Pitch</span>
                            <span className="text-[9px] font-bold text-zinc-300">{new Date(application.created_at).toLocaleDateString()}</span>
                        </div>
                        <p className="text-sm text-zinc-600 font-medium leading-relaxed italic">
                            "{application.pitch || 'No pitch provided.'}"
                        </p>
                    </div>

                    {/* Proof Section */}
                    {application.proofs.length > 0 && (
                        <div className="pt-6 border-t border-zinc-100 space-y-4">
                            <span className="text-[9px] font-black uppercase tracking-widest text-emerald-500 flex items-center gap-2">
                                <FaCheckCircle /> Proof of Work Submission
                            </span>
                            {application.proofs.map(proof => (
                                <div key={proof.id} className="p-4 rounded-2xl bg-zinc-50 border border-zinc-100 flex items-start justify-between gap-4">
                                    <div className="space-y-2 min-w-0">
                                        <a href={proof.proof_url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 text-zinc-900 font-bold text-xs hover:text-emerald-600 transition-colors truncate">
                                            <FaExternalLinkAlt className="text-[10px]" /> View Deliverable
                                        </a>
                                        {proof.notes && <p className="text-[10px] text-zinc-500 font-medium italic">{proof.notes}</p>}
                                    </div>
                                    <div className={`px-3 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest ${
                                        proof.status === 'approved' ? 'bg-emerald-100 text-emerald-600' : 
                                        proof.status === 'rejected' ? 'bg-red-100 text-red-600' : 
                                        'bg-zinc-200 text-zinc-500'
                                    }`}>
                                        {proof.status}
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
                                title="Approve & Hire"
                            >
                                <FaCheckCircle />
                            </button>
                            <button 
                                onClick={() => onDecision(application.id, 'denied')}
                                className="h-12 w-12 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center hover:bg-red-50 hover:text-red-500 transition-all"
                                title="Reject"
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
                                <FaCheckCircle /> Approve Proof
                            </button>
                            <button 
                                onClick={() => onProofDecision(pendingProof.id, 'rejected')}
                                className="px-6 py-3 rounded-2xl bg-zinc-100 text-zinc-500 text-[9px] font-black uppercase tracking-widest hover:bg-red-50 hover:text-red-500 transition-all"
                            >
                                <FaTimesCircle /> Reject
                            </button>
                        </div>
                    )}

                    {!isPending && !pendingProof && (
                        <div className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest ${
                            application.status === 'completed' ? 'bg-emerald-50 text-emerald-500' : 
                            application.status === 'approved' ? 'bg-blue-50 text-blue-500' :
                            'bg-zinc-100 text-zinc-400'
                        }`}>
                            {application.status === 'approved' ? 'In Progress' : application.status}
                        </div>
                    )}
                </div>
            </div>
        </motion.div>
    );
}
