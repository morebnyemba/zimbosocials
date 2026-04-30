import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaWallet, 
    FaHistory, 
    FaPlus, 
    FaMinus, 
    FaRegCreditCard, 
    FaMobileAlt, 
    FaUniversity, 
    FaPaypal,
    FaCheckCircle,
    FaClock,
    FaArrowUp,
    FaArrowDown,
    FaInfoCircle,
    FaExclamationCircle
} from 'react-icons/fa';

interface Transaction {
    id: number;
    type: string;
    amount: number;
    description: string;
    created_at: string;
}

interface ManualPaymentDetail {
    id: number;
    method_key: string;
    label: string;
    instructions?: string | null;
    account_number?: string | null;
    account_holder?: string | null;
    bank_name?: string | null;
}

interface Props extends PageProps {
    transactions: { data: Transaction[] };
    totals: { deposited: number; contract_earnings: number; withdrawn: number; spent: number };
    manualPaymentDetails: ManualPaymentDetail[];
    availableMethods: Record<string, string>;
    gatewayMethods: string[];
}

interface ToastState {
    kind: 'success' | 'error' | 'info';
    message: string;
}

const typeThemes: Record<string, { icon: any; color: string; bg: string }> = {
    deposit: { icon: FaArrowDown, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    contract_earning: { icon: FaArrowDown, color: 'text-emerald-600', bg: 'bg-emerald-50' },
    debit: { icon: FaArrowUp, color: 'text-red-600', bg: 'bg-red-50' },
    withdrawal: { icon: FaArrowUp, color: 'text-amber-600', bg: 'bg-amber-50' },
    contract_payout: { icon: FaArrowUp, color: 'text-rose-600', bg: 'bg-rose-50' },
    refund: { icon: FaArrowDown, color: 'text-blue-600', bg: 'bg-blue-50' },
};

const methodIcons: Record<string, any> = {
    paynow: FaRegCreditCard,
    ecocash: FaMobileAlt,
    onemoney: FaMobileAlt,
    innbucks: FaMobileAlt,
    bank: FaUniversity,
    paypal: FaPaypal,
};

export default function WalletIndex({ auth, transactions, totals, manualPaymentDetails, availableMethods, gatewayMethods }: Props) {
    const { t } = useTranslation();
    const user = auth.user;
    const isMarketer = user.role === 'marketer' || user.role === 'reseller';
    const methods = Object.entries(availableMethods ?? {});
    const gatewaySet = new Set(gatewayMethods ?? []);
    const manualMethodLabels = methods
        .filter(([key]) => !gatewaySet.has(key))
        .map(([, label]) => label);
    const manualMethodsDisplay = manualMethodLabels.length > 0
        ? `(${manualMethodLabels.join(', ')})`
        : '(No manual methods configured)';
    
    const [showDeposit, setShowDeposit] = useState(false);
    const [showWithdraw, setShowWithdraw] = useState(false);
    const [toast, setToast] = useState<ToastState | null>(null);

    const depositForm = useForm({
        amount: '',
        method: methods[0]?.[0] ?? 'paynow',
        reference: '',
        phone: '',
    });

    const proofForm = useForm({
        transaction_id: '',
        proof_file: null as File | null,
    });

    const withdrawalForm = useForm({
        amount: '',
        method: 'ecocash',
        reference: '',
    });

    const chosenDetails = manualPaymentDetails.find((m) => m.method_key === depositForm.data.method);
    const isPaynowMethod = gatewaySet.has(depositForm.data.method);

    const notify = (kind: ToastState['kind'], message: string) => {
        setToast({ kind, message });
        window.setTimeout(() => {
            setToast((current) => (current?.message === message ? null : current));
        }, 3500);
    };

    const submitDeposit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (isPaynowMethod) {
            // Paynow flow - redirect to payment gateway
            try {
                const response = await fetch(route('paynow.init'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.head.querySelector('meta[name="csrf-token"]') as any)?.content || ''
                    },
                    body: JSON.stringify({
                        amount: depositForm.data.amount,
                        method: depositForm.data.method,
                        phone: depositForm.data.phone,
                    })
                });
                const result = await response.json();
                
                if (result.success && result.redirect_url) {
                    window.location.href = result.redirect_url;
                } else if (result.success && result.poll_url) {
                    notify('info', result.message || t('wallet_check_phone_pin'));
                    setShowDeposit(false);
                } else {
                    notify('error', result.message || t('wallet_failed_initiate_payment'));
                }
            } catch (err) {
                notify('error', t('wallet_payment_gateway_error'));
            }
        } else {
            // Manual flow - create pending transaction and show POP submission
            depositForm.post(route('wallet.add'), {
                onSuccess: () => { 
                    depositForm.reset();
                    setShowDeposit(false);
                    notify('success', t('wallet_deposit_request_created'));
                },
            });
        }
    };

    const submitProof = (e: React.FormEvent) => {
        e.preventDefault();
        const formData = new FormData();
        formData.append('transaction_id', proofForm.data.transaction_id);
        if (proofForm.data.proof_file) {
            formData.append('proof_file', proofForm.data.proof_file);
        }

        fetch(route('wallet.submit-proof'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.head.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            },
            body: formData
        })
        .then(r => r.text().then(text => {
            // Parse HTML response for success/error
            if (text.includes('success')) {
                notify('success', t('wallet_proof_submitted'));
                proofForm.reset();
            } else {
                notify('error', t('wallet_failed_submit_proof'));
            }
        }))
        .catch(() => notify('error', t('wallet_error_submit_proof')));
    };

    const submitWithdrawal = (e: React.FormEvent) => {
        e.preventDefault();
        withdrawalForm.post(route('wallet.withdraw'), {
            onSuccess: () => { withdrawalForm.reset(); setShowWithdraw(false); },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Wallet & Finance" />

            <AnimatePresence>
                {toast && (
                    <motion.div
                        initial={{ opacity: 0, y: -10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        className="fixed right-6 top-6 z-[120]"
                    >
                        <div className={`min-w-[260px] max-w-sm rounded-2xl border px-5 py-4 shadow-2xl ${
                            toast.kind === 'success'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : toast.kind === 'error'
                                    ? 'border-red-200 bg-red-50 text-red-700'
                                    : 'border-blue-200 bg-blue-50 text-blue-700'
                        }`}>
                            <p className="text-xs font-black uppercase tracking-widest">{toast.message}</p>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            <div className="space-y-12">
                {/* Header & Main Balance */}
                <section className="relative overflow-hidden rounded-[3rem] bg-zinc-900 text-white shadow-2xl shadow-zinc-900/40">
                    <div className="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-emerald-500/10 to-transparent pointer-events-none" />
                    
                    <div className="relative px-12 py-16 flex flex-col md:flex-row justify-between items-center gap-12">
                        <div className="space-y-4 text-center md:text-left">
                            <span className="px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] font-black uppercase tracking-widest">
                                Financial Overview
                            </span>
                            <h1 className="text-4xl lg:text-6xl font-black tracking-tighter">Your <span className="text-zinc-500">Balance</span></h1>
                            <div className="flex items-baseline gap-3 justify-center md:justify-start">
                                <span className="text-6xl font-black tracking-tighter">${Number(user.balance || 0).toFixed(2)}</span>
                                <span className="text-emerald-400 text-sm font-black">USD</span>
                            </div>
                        </div>

                        <div className="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                            <button 
                                onClick={() => { setShowDeposit(true); setShowWithdraw(false); }}
                                className="px-10 py-5 rounded-2xl bg-emerald-500 text-zinc-900 font-black text-sm uppercase tracking-widest shadow-xl shadow-emerald-500/20 hover:scale-105 transition-all flex items-center justify-center gap-3"
                            >
                                <FaPlus /> Add Funds
                            </button>
                            {isMarketer && (
                                <button 
                                    onClick={() => { setShowWithdraw(true); setShowDeposit(false); }}
                                    className="px-10 py-5 rounded-2xl bg-white/5 border border-white/10 text-white font-black text-sm uppercase tracking-widest hover:bg-white/10 transition-all flex items-center justify-center gap-3"
                                >
                                    <FaMinus /> Cash Out
                                </button>
                            )}
                        </div>
                    </div>
                </section>

                {/* Sub-Stats Grid */}
                <section className="grid grid-cols-2 lg:grid-cols-4 gap-6">
                    <StatCard label="Total Deposited" value={`$${Number(totals.deposited).toFixed(2)}`} />
                    <StatCard label="Manual Earnings" value={`$${Number(totals.contract_earnings).toFixed(2)}`} />
                    <StatCard label="Total Spent" value={`$${Number(totals.spent).toFixed(2)}`} />
                    <StatCard label="Withdrawn" value={`$${Number(totals.withdrawn).toFixed(2)}`} />
                </section>

                <AnimatePresence mode="wait">
                    {showDeposit && (
                        <motion.section 
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40"
                        >
                            <div className="flex items-center justify-between mb-8">
                                <h3 className="text-2xl font-black text-zinc-900">
                                    {isPaynowMethod ? 'Online Payment' : `Manual Deposit ${manualMethodsDisplay}`}
                                </h3>
                                <button onClick={() => setShowDeposit(false)} className="text-zinc-300 hover:text-zinc-500 transition-colors">Close</button>
                            </div>

                            <form onSubmit={submitDeposit} className="grid md:grid-cols-2 gap-8">
                                <div className="space-y-6">
                                    <div className="space-y-2">
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Payment Method</label>
                                        <div className="grid grid-cols-2 gap-3">
                                            {methods.map(([key, label]) => {
                                                const Icon = methodIcons[key] || FaRegCreditCard;
                                                const isSelected = depositForm.data.method === key;
                                                const isPaynow = gatewaySet.has(key);
                                                const badge = isPaynow ? 'ONLINE' : 'MANUAL';
                                                return (
                                                    <button 
                                                        key={key}
                                                        type="button"
                                                        onClick={() => depositForm.setData('method', key)}
                                                        className={`p-4 rounded-2xl border-2 flex flex-col items-start gap-3 font-bold transition-all ${isSelected ? 'bg-zinc-900 border-zinc-900 text-white' : 'bg-zinc-50 border-zinc-100 text-zinc-500 hover:border-emerald-200'}`}
                                                    >
                                                        <div className="flex items-center justify-between w-full">
                                                            <Icon className={isSelected ? 'text-emerald-400' : ''} />
                                                            <span className={`text-[7px] font-black px-2 py-1 rounded-full ${isPaynow ? 'bg-emerald-500/20 text-emerald-600' : 'bg-amber-500/20 text-amber-600'}`}>
                                                                {badge}
                                                            </span>
                                                        </div>
                                                        <span className="text-xs truncate">{label}</span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        {manualMethodLabels.length > 0 && (
                                            <p className="text-[10px] font-black text-amber-600 uppercase tracking-widest ml-1">
                                                Manual methods {manualMethodsDisplay}
                                            </p>
                                        )}
                                    </div>
                                    
                                    <div className="space-y-2">
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Amount to Deposit (USD)</label>
                                        <input 
                                            type="number" step="0.01" min="1" required
                                            value={depositForm.data.amount}
                                            onChange={e => depositForm.setData('amount', e.target.value)}
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-black text-zinc-900 focus:outline-none focus:border-emerald-500 transition-all"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-6">
                                    {isPaynowMethod ? (
                                        <>
                                            {['ecocash', 'onemoney'].includes(depositForm.data.method) ? (
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Mobile Number</label>
                                                    <input 
                                                        type="tel" required
                                                        value={depositForm.data.phone}
                                                        onChange={e => depositForm.setData('phone', e.target.value)}
                                                        placeholder="e.g. 0771234567"
                                                        className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-black text-zinc-900 focus:outline-none focus:border-emerald-500 transition-all"
                                                    />
                                                </div>
                                            ) : (
                                                <div className="p-6 rounded-3xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-medium leading-relaxed">
                                                    <p className="font-black uppercase tracking-widest text-[9px] mb-2 flex items-center gap-2">
                                                        <FaCheckCircle /> Quick & Secure
                                                    </p>
                                                    You'll be redirected to Paynow for secure online payment. No additional details needed.
                                                </div>
                                            )}
                                            <button 
                                                disabled={depositForm.processing}
                                                className="w-full py-5 rounded-2xl bg-emerald-500 text-white font-black text-xs uppercase tracking-widest shadow-xl hover:bg-emerald-600 transition-all disabled:opacity-50"
                                            >
                                                Proceed to Payment
                                            </button>
                                        </>
                                    ) : (
                                        <>
                                            {chosenDetails && (
                                                <div className="p-6 rounded-3xl bg-zinc-900 text-zinc-300 text-xs font-medium leading-relaxed border border-white/5 space-y-4">
                                                    <p className="text-emerald-400 font-black uppercase tracking-widest text-[9px] flex items-center gap-2">
                                                        <FaInfoCircle /> Bank Details
                                                    </p>
                                                    {chosenDetails.bank_name && (
                                                        <div>
                                                            <span className="text-zinc-400 text-[9px] uppercase tracking-widest">Bank</span>
                                                            <p className="text-white font-black">{chosenDetails.bank_name}</p>
                                                        </div>
                                                    )}
                                                    {chosenDetails.account_holder && (
                                                        <div>
                                                            <span className="text-zinc-400 text-[9px] uppercase tracking-widest">Account Holder</span>
                                                            <p className="text-white font-black">{chosenDetails.account_holder}</p>
                                                        </div>
                                                    )}
                                                    {chosenDetails.account_number && (
                                                        <div>
                                                            <span className="text-zinc-400 text-[9px] uppercase tracking-widest">Account Number</span>
                                                            <p className="text-white font-black break-all">{chosenDetails.account_number}</p>
                                                        </div>
                                                    )}
                                                    {chosenDetails.instructions && (
                                                        <div>
                                                            <span className="text-zinc-400 text-[9px] uppercase tracking-widest">Instructions</span>
                                                            <p className="text-white text-xs mt-1">{chosenDetails.instructions}</p>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                            <button 
                                                disabled={depositForm.processing}
                                                className="w-full py-5 rounded-2xl bg-zinc-900 text-white font-black text-xs uppercase tracking-widest shadow-xl hover:bg-zinc-800 transition-all disabled:opacity-50"
                                            >
                                                Create Deposit Request
                                            </button>
                                            <p className="text-[10px] text-zinc-500 text-center font-medium">
                                                After transfer, upload your proof of payment to complete the deposit.
                                            </p>
                                        </>
                                    )}
                                </div>
                            </form>
                        </motion.section>
                    )}

                    {showWithdraw && (
                        <motion.section 
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40"
                        >
                            <div className="flex items-center justify-between mb-8">
                                <h3 className="text-2xl font-black text-zinc-900">Withdrawal Terminal</h3>
                                <button onClick={() => setShowWithdraw(false)} className="text-zinc-300 hover:text-zinc-500 transition-colors">Close</button>
                            </div>

                            <form onSubmit={submitWithdrawal} className="grid md:grid-cols-2 gap-8">
                                <div className="space-y-6">
                                    <div className="space-y-2">
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Method</label>
                                        <select 
                                            value={withdrawalForm.data.method}
                                            onChange={e => withdrawalForm.setData('method', e.target.value)}
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-black text-zinc-900 focus:outline-none focus:border-emerald-500 appearance-none"
                                        >
                                            <option value="ecocash">EcoCash</option>
                                            <option value="innbucks">InnBucks</option>
                                            <option value="bank">Bank Transfer</option>
                                            <option value="paypal">PayPal</option>
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Amount to Extract (USD)</label>
                                        <input 
                                            type="number" step="0.01" min="1" required
                                            value={withdrawalForm.data.amount}
                                            onChange={e => withdrawalForm.setData('amount', e.target.value)}
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-black text-zinc-900 focus:outline-none focus:border-emerald-500 transition-all"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-6">
                                    <div className="space-y-2">
                                        <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Destination Account</label>
                                        <input 
                                            type="text" required
                                            value={withdrawalForm.data.reference}
                                            onChange={e => withdrawalForm.setData('reference', e.target.value)}
                                            placeholder="Wallet number or Bank details"
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-black text-zinc-900 focus:outline-none focus:border-emerald-500 transition-all"
                                        />
                                    </div>
                                    <button 
                                        disabled={withdrawalForm.processing}
                                        className="w-full py-5 rounded-2xl bg-amber-600 text-white font-black text-xs uppercase tracking-widest shadow-xl hover:bg-amber-700 transition-all disabled:opacity-50"
                                    >
                                        Request Withdrawal
                                    </button>
                                </div>
                            </form>
                        </motion.section>
                    )}
                </AnimatePresence>

                {/* Pending Manual Deposits - POP Submission */}
                {transactions.data && (
                    (() => {
                        const pendingManualDeposits = (transactions.data ?? []).filter(
                            t => t.type === 'deposit' && t.status === 'pending' && !gatewaySet.has(t.method)
                        );
                        
                        return pendingManualDeposits.length > 0 ? (
                            <section className="bg-amber-50 rounded-[3rem] p-10 border-2 border-amber-200 shadow-xl shadow-amber-100/40">
                                <div className="flex items-center justify-between mb-8">
                                    <div className="flex items-center gap-3">
                                        <FaExclamationCircle className="text-amber-600 text-2xl" />
                                        <div>
                                            <h3 className="text-2xl font-black text-zinc-900">Pending Proof of Payment</h3>
                                            <p className="text-[10px] font-black text-amber-600 uppercase tracking-widest mt-1">Complete your manual deposit by uploading proof</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-6">
                                    {pendingManualDeposits.map((transaction) => (
                                        <div key={transaction.id} className="bg-white rounded-2xl p-6 border border-amber-100">
                                            <div className="flex items-center justify-between mb-6">
                                                <div>
                                                    <p className="text-sm font-black text-zinc-900">
                                                        Deposit of <span className="text-emerald-600">${Number(transaction.amount).toFixed(2)}</span> via {transaction.method}
                                                    </p>
                                                    <p className="text-[10px] text-zinc-400 uppercase tracking-widest mt-1">
                                                        Created {new Date(transaction.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                                <span className="px-4 py-2 rounded-full bg-amber-100 text-amber-700 text-[10px] font-black uppercase tracking-widest">
                                                    Awaiting Proof
                                                </span>
                                            </div>

                                            <form onSubmit={(e) => {
                                                e.preventDefault();
                                                proofForm.setData({
                                                    transaction_id: String(transaction.id),
                                                    proof_file: proofForm.data.proof_file
                                                });
                                                submitProof(e);
                                            }} className="space-y-4">
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">
                                                        Upload Proof of Payment (Receipt/Screenshot)
                                                    </label>
                                                    <input 
                                                        type="file" 
                                                        accept="image/*"
                                                        required
                                                        onChange={(e) => proofForm.setData('proof_file', e.target.files?.[0] || null)}
                                                        className="w-full bg-zinc-50 border-2 border-dashed border-amber-300 rounded-2xl px-6 py-4 text-zinc-600 focus:outline-none focus:border-emerald-500 transition-all"
                                                    />
                                                    <p className="text-[9px] text-zinc-400">PNG, JPG or GIF (max 5MB). Clearly show transaction details.</p>
                                                </div>
                                                <button 
                                                    type="submit"
                                                    className="w-full py-4 rounded-2xl bg-amber-600 text-white font-black text-xs uppercase tracking-widest shadow-xl hover:bg-amber-700 transition-all"
                                                >
                                                    Submit Proof of Payment
                                                </button>
                                            </form>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ) : null;
                    })()
                )}

                {/* Transaction Table */}
                <section className="bg-white rounded-[3rem] p-10 border border-zinc-200 shadow-xl shadow-zinc-200/40">
                    <div className="flex items-center justify-between mb-10">
                        <div>
                            <h3 className="text-2xl font-black text-zinc-900 tracking-tight">Financial History</h3>
                            <p className="text-zinc-500 text-[10px] font-black uppercase tracking-widest mt-1">Audit log of all wallet movements</p>
                        </div>
                        <FaHistory className="text-zinc-200 text-2xl" />
                    </div>

                    <div className="space-y-4">
                        {(transactions.data ?? []).length === 0 && (
                            <p className="text-center py-20 text-zinc-400 font-bold italic">No financial activity recorded yet.</p>
                        )}
                        {(transactions.data ?? []).map((t) => {
                            const theme = typeThemes[t.type] || { icon: FaClock, color: 'text-zinc-400', bg: 'bg-zinc-50' };
                            const Icon = theme.icon;
                            return (
                                <div key={t.id} className="flex items-center gap-6 p-6 rounded-[2rem] border border-zinc-50 hover:bg-zinc-50 transition-all group">
                                    <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-xl shadow-sm ${theme.bg} ${theme.color}`}>
                                        <Icon />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-black text-zinc-900 truncate group-hover:text-zinc-600 transition-colors uppercase tracking-tight text-sm">{t.description || t.type}</p>
                                        <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-1">{new Date(t.created_at).toLocaleDateString()} · {t.type.replace('_', ' ')}</p>
                                    </div>
                                    <div className={`text-xl font-black ${t.amount >= 0 ? 'text-emerald-600' : 'text-zinc-900'}`}>
                                        {t.amount >= 0 ? '+' : ''}{Number(t.amount).toFixed(2)}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="bg-white rounded-[2rem] p-8 border border-zinc-200 shadow-xl shadow-zinc-200/40 group hover:-translate-y-1 transition-all">
            <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400 mb-2">{label}</p>
            <p className="text-2xl font-black text-zinc-900 tracking-tighter">{value}</p>
        </div>
    );
}
