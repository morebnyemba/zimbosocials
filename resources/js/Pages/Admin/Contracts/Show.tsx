import AdminLayout from '@/Layouts/AdminLayout';
import ConfirmModal from '@/Components/ConfirmModal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { 
    Calendar, 
    DollarSign, 
    Users, 
    Briefcase, 
    ArrowLeft, 
    ExternalLink, 
    Trash2, 
    Clock, 
    User,
    CheckCircle2,
    XCircle,
    Info
} from 'lucide-react';

interface Application {
    id: number;
    pitch: string;
    status: string;
    created_at: string;
    marketer?: {
        id: number;
        name: string;
        email: string;
        social_links?: any[];
    };
    decider?: {
        name: string;
    };
    reviewed_at?: string;
}

interface Contract {
    id: number;
    title: string;
    description: string;
    platform: string;
    budget: string;
    slots: number;
    status: string;
    created_at: string;
    deadline_at?: string;
    business?: {
        id: number;
        name: string;
        email: string;
        company_name?: string;
    };
    applications: Application[];
}

interface Props {
    contract: Contract;
}

const statusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800 border-amber-200',
    approved: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    denied: 'bg-red-100 text-red-800 border-red-200',
    ignored: 'bg-zinc-100 text-zinc-800 border-zinc-200',
};

export default function ContractShow({ contract }: Props) {
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const deleteContract = () => {
        setShowDeleteConfirm(true);
    };

    return (
        <AdminLayout>
            <Head title={`Contract: ${contract.title}`} />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Link href={route('admin.contracts.index')} className="flex items-center gap-2 text-sm font-medium text-brand-green hover:text-brand-green/80">
                        <ArrowLeft className="h-4 w-4" /> Back to Contracts
                    </Link>
                    <button 
                        onClick={deleteContract}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition-colors text-sm font-bold border border-red-100"
                    >
                        <Trash2 className="h-4 w-4" /> Delete Contract
                    </button>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Contract Details */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm">
                            <div className="flex items-start justify-between">
                                <div className="space-y-1">
                                    <h1 className="text-2xl font-bold text-zinc-900 leading-tight">{contract.title}</h1>
                                    <div className="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                                        <span className="inline-flex items-center gap-1.5 font-bold text-brand-green uppercase tracking-widest">{contract.platform}</span>
                                        <span>·</span>
                                        <span className="inline-flex items-center gap-1.5"><Clock className="w-4 h-4" /> Posted {new Date(contract.created_at).toLocaleDateString()}</span>
                                        {contract.deadline_at && (
                                            <>
                                                <span>·</span>
                                                <span className="inline-flex items-center gap-1.5 text-amber-600 font-medium"><Calendar className="w-4 h-4" /> Deadline: {new Date(contract.deadline_at).toLocaleDateString()}</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                                <div className={`px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest border ${
                                    contract.status === 'open' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                    contract.status === 'filled' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                                    'bg-zinc-100 text-zinc-700 border-zinc-200'
                                }`}>
                                    {contract.status}
                                </div>
                            </div>

                            <div className="mt-8 prose prose-zinc max-w-none">
                                <h3 className="text-lg font-bold text-zinc-900 mb-3">Project Description</h3>
                                <div className="text-zinc-700 whitespace-pre-wrap leading-relaxed bg-zinc-50 p-6 rounded-xl border border-zinc-100">
                                    {contract.description}
                                </div>
                            </div>
                        </div>

                        {/* Applications */}
                        <div className="space-y-4">
                            <h2 className="text-xl font-bold text-zinc-900 flex items-center gap-2">
                                <Users className="h-5 w-5 text-zinc-400" /> Applications ({contract.applications.length})
                            </h2>
                            
                            <div className="space-y-4">
                                {contract.applications.length === 0 && (
                                    <div className="p-12 text-center bg-white rounded-2xl border border-zinc-200 border-dashed">
                                        <Users className="h-12 w-12 text-zinc-200 mx-auto mb-4" />
                                        <p className="text-zinc-500 font-medium">No marketers have applied to this contract yet.</p>
                                    </div>
                                )}
                                
                                {contract.applications.map(app => (
                                    <div key={app.id} className="rounded-2xl border border-zinc-200 bg-white overflow-hidden shadow-sm hover:border-zinc-300 transition-colors">
                                        <div className="p-6">
                                            <div className="flex items-start justify-between mb-6">
                                                <div className="flex items-center gap-4">
                                                    <div className="h-12 w-12 rounded-full bg-brand-green/10 flex items-center justify-center text-brand-green font-bold text-lg">
                                                        {app.marketer?.name.charAt(0).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <Link href={route('admin.marketers.show', { id: app.marketer?.id || 0 })} className="font-bold text-zinc-900 hover:text-brand-green">
                                                            {app.marketer?.name}
                                                        </Link>
                                                        <p className="text-xs text-zinc-500">{app.marketer?.email}</p>
                                                    </div>
                                                </div>
                                                <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest border ${statusColors[app.status]}`}>
                                                    {app.status}
                                                </span>
                                            </div>

                                            <div className="bg-zinc-50 p-5 rounded-xl border border-zinc-100 mb-4">
                                                <p className="text-xs font-bold text-zinc-400 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                                    <Info className="w-3 h-3" /> Marketer Pitch
                                                </p>
                                                <p className="text-sm text-zinc-700 whitespace-pre-wrap leading-relaxed">
                                                    {app.pitch || "No pitch provided."}
                                                </p>
                                            </div>

                                            {app.reviewed_at && (
                                                <div className="mt-4 flex items-center gap-2 text-xs text-zinc-500 bg-zinc-50 px-3 py-2 rounded-lg border border-zinc-100">
                                                    {app.status === 'approved' ? <CheckCircle2 className="w-3 h-3 text-emerald-500" /> : <XCircle className="w-3 h-3 text-red-500" />}
                                                    Reviewed by {app.decider?.name} on {new Date(app.reviewed_at).toLocaleDateString()}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Sidebar Stats */}
                    <div className="space-y-6">
                        <div className="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                            <h3 className="text-sm font-bold text-zinc-400 uppercase tracking-widest mb-6">Business Partner</h3>
                            <div className="flex items-center gap-4 mb-6">
                                <div className="h-12 w-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-xl border border-amber-100">
                                    {(contract.business?.company_name || contract.business?.name || 'B').charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <h4 className="font-bold text-zinc-900">{contract.business?.company_name || contract.business?.name}</h4>
                                    <p className="text-xs text-zinc-500 truncate max-w-[150px]">{contract.business?.email}</p>
                                </div>
                            </div>
                            <Link 
                                href={route('admin.users.show', contract.business?.id || 0)}
                                className="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-zinc-100 text-zinc-900 text-sm font-bold hover:bg-zinc-200 transition-colors"
                            >
                                <User className="w-4 h-4" /> View Business Profile
                            </Link>
                        </div>

                        <div className="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm space-y-4">
                            <h3 className="text-sm font-bold text-zinc-400 uppercase tracking-widest mb-2">Contract Financials</h3>
                            <div className="flex items-center justify-between">
                                <span className="text-zinc-500 text-sm">Budget</span>
                                <span className="text-zinc-900 font-bold font-mono text-lg">${Number(contract.budget).toFixed(2)}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-zinc-500 text-sm">Available Slots</span>
                                <span className="text-zinc-900 font-bold">{contract.slots}</span>
                            </div>
                            <div className="pt-4 border-t border-zinc-100">
                                <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-100">
                                    <p className="text-xs text-emerald-800 font-medium leading-relaxed">
                                        Platform holds the budget until the contract is marked as filled by the business.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <ConfirmModal
                open={showDeleteConfirm}
                title="Delete Contract"
                message="Are you sure you want to delete this contract? All applications will be permanently lost."
                confirmLabel="Delete"
                danger
                onConfirm={() => { setShowDeleteConfirm(false); router.delete(route('admin.contracts.destroy', contract.id)); }}
                onCancel={() => setShowDeleteConfirm(false)}
            />
        </AdminLayout>
    );
}
