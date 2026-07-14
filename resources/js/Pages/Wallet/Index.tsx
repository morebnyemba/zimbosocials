import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
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
    status?: string;
    method?: string;
    reference_code?: string;
}

interface ManualPaymentDetail {
    id: number;
    method_key: string;
    label: string;
    instructions?: string | null;
    account_number?: string | null;
    account_name?: string | null;
}

interface Props extends PageProps {
    transactions: { data: Transaction[] };
    totals: { deposited: number; contract_earnings: number; withdrawn: number; spent: number };
    manualPaymentDetails: ManualPaymentDetail[];
    availableMethods: Record<string, string>;
    gatewayMethods: string[];
    manualDepositBonusPercent: number;
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

// Status overrides the type-based theme above — a failed/rejected deposit must
// never render with the "money in" green just because its type is 'deposit'.
const statusThemeOverrides: Record<string, { icon: any; color: string; bg: string; badge: string; badgeText: string }> = {
    pending: { icon: FaClock, color: 'text-amber-600', bg: 'bg-amber-50', badge: 'bg-amber-100 text-amber-700', badgeText: 'Pending' },
    rejected: { icon: FaClock, color: 'text-red-600', bg: 'bg-red-50', badge: 'bg-red-100 text-red-700', badgeText: 'Failed' },
    failed: { icon: FaClock, color: 'text-red-600', bg: 'bg-red-50', badge: 'bg-red-100 text-red-700', badgeText: 'Failed' },
    cancelled: { icon: FaClock, color: 'text-red-600', bg: 'bg-red-50', badge: 'bg-red-100 text-red-700', badgeText: 'Cancelled' },
};

const methodIcons: Record<string, any> = {
    paynow: FaRegCreditCard,
    ecocash: FaMobileAlt,
    onemoney: FaMobileAlt,
    innbucks: FaMobileAlt,
    bank: FaUniversity,
    paypal: FaPaypal,
};

export default function WalletIndex({ auth, transactions, totals, manualPaymentDetails, availableMethods, gatewayMethods, manualDepositBonusPercent }: Props) {
    const { t } = useTranslation();
    const { formatUSD, currencyCode } = useCurrency();
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
    const [pollingTxId, setPollingTxId] = useState<number | null>(null);

    // InnBucks auth-code flow
    const [innbucksData, setInnbucksData] = useState<{
        authorizationCode: string;
        authorizationExpires: string;
        qrUrl: string;
        deepLink: string;
        txId: number;
    } | null>(null);

    // O'mari OTP flow
    const [omariOtp, setOmariOtp] = useState<{ txId: number; reference: string; otp: string; submitting: boolean } | null>(null);

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
    const bonusPercent = Number(manualDepositBonusPercent ?? 0);
    const depositAmount = parseFloat(depositForm.data.amount);
    const bonusPreview = !isPaynowMethod && bonusPercent > 0 && !isNaN(depositAmount) && depositAmount > 0
        ? Math.round(depositAmount * bonusPercent) / 100
        : null;

    const notify = (kind: ToastState['kind'], message: string) => {
        setToast({ kind, message });
        window.setTimeout(() => {
            setToast((current) => (current?.message === message ? null : current));
        }, 3500);
    };

    const submitDeposit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (isPaynowMethod) {
            // Paynow flow
            const isMobileProvider = depositForm.data.method !== 'paynow';
            const endpoint = isMobileProvider
                ? route('paynow.mobile', { provider: depositForm.data.method })
                : route('paynow.init');

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        // Accept JSON so Laravel returns 422 validation errors as JSON
                        // instead of a 302 redirect (which the fetch can't parse).
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': (document.head.querySelector('meta[name="csrf-token"]') as any)?.content || ''
                    },
                    body: JSON.stringify({
                        amount: depositForm.data.amount,
                        method: depositForm.data.method,
                        ...(isMobileProvider ? { phone: depositForm.data.phone } : {}),
                    })
                });
                if (!response.ok) {
                    // Surface the real server error instead of a generic message.
                    const err = await response.json().catch(() => ({}));
                    const firstError = err?.errors ? Object.values(err.errors)[0] as string[] : null;
                    notify('error', (firstError && firstError[0]) || err?.message || t('wallet_payment_gateway_error'));
                    return;
                }
                const result = await response.json();

                if (result.success && result.redirect_url) {
                    // Web redirect (Paynow website) — navigate away; return URL handles result
                    window.location.href = result.redirect_url;
                } else if (result.success && result.flow === 'innbucks_authcode') {
                    // InnBucks — show authorization code UI
                    setShowDeposit(false);
                    depositForm.reset();
                    setInnbucksData({
                        authorizationCode: result.authorization_code,
                        authorizationExpires: result.authorization_expires,
                        qrUrl: result.qr_url,
                        deepLink: result.deep_link,
                        txId: result.transaction_id,
                    });
                    startPolling(result.transaction_id);
                } else if (result.success && result.flow === 'omari_otp') {
                    // O'mari — show OTP input
                    setShowDeposit(false);
                    depositForm.reset();
                    notify('info', result.message || 'Enter the OTP sent to your phone.');
                    setOmariOtp({ txId: result.transaction_id, reference: result.otp_reference, otp: '', submitting: false });
                } else if (result.success && (result.flow === 'ussd_pin' || result.transaction_id)) {
                    // EcoCash / OneMoney / TeleCash — poll for completion
                    notify('info', result.message || 'Check your phone and enter your PIN.');
                    setShowDeposit(false);
                    depositForm.reset();
                    if (result.transaction_id) {
                        startPolling(result.transaction_id);
                    }
                } else {
                    notify('error', result.message || 'Failed to initiate payment.');
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

    const startPolling = (txId: number) => {
        setPollingTxId(txId);
        const INTERVAL = 5000;  // 5 seconds
        const MAX_ATTEMPTS = 24; // 2 minutes total
        let attempts = 0;

        const poll = async () => {
            attempts++;
            try {
                const res = await fetch(route('paynow.poll', { transaction: txId }), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': (document.head.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                    }
                });
                const data = await res.json();

                if (data.resolved) {
                    setPollingTxId(null);
                    if (data.status === 'completed') {
                        notify('success', 'Deposit confirmed! Your balance has been updated.');
                        // Reload page to reflect new balance
                        window.setTimeout(() => window.location.reload(), 1500);
                    } else if (data.status === 'rejected') {
                        notify('error', 'Payment was not successful. No funds were deducted.');
                    } else {
                        notify('info', 'Payment status: ' + data.status);
                    }
                    return;
                }
            } catch {
                // Network error — keep trying
            }

            if (attempts < MAX_ATTEMPTS) {
                window.setTimeout(poll, INTERVAL);
            } else {
                setPollingTxId(null);
                notify('info', 'Payment is taking longer than expected. Your balance will update once confirmed.');
            }
        };

        window.setTimeout(poll, INTERVAL);
    };

    const submitOmariOtpHandler = async () => {
        if (!omariOtp || !omariOtp.otp.trim()) return;
        setOmariOtp(prev => prev ? { ...prev, submitting: true } : null);
        try {
            const res = await fetch(route('paynow.omari.otp', { transaction: omariOtp.txId }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.head.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                },
                body: JSON.stringify({ otp: omariOtp.otp }),
            });
            const data = await res.json().catch(() => null);
            if (data?.success) {
                notify('info', data.message || 'OTP accepted. Waiting for payment…');
                const txId = omariOtp.txId;
                setOmariOtp(null);
                startPolling(txId);
            } else {
                const firstError = data?.errors ? Object.values(data.errors)[0] as string[] : null;
                notify('error', (firstError && firstError[0]) || data?.message || 'Invalid OTP. Please try again.');
                setOmariOtp(prev => prev ? { ...prev, submitting: false } : null);
            }
        } catch {
            notify('error', 'Could not submit OTP. Please try again.');
            setOmariOtp(prev => prev ? { ...prev, submitting: false } : null);
        }
    };

    const submitProof = (e: React.FormEvent) => {
        e.preventDefault();
        if (!proofForm.data.proof_file) {
            notify('error', t('wallet_failed_submit_proof'));
            return;
        }

        // Inertia handles CSRF (XSRF cookie), the redirect, and reloads the
        // transaction list so the submitted proof shows immediately. The old
        // raw fetch used a nonexistent csrf-token meta tag (→ 419) and grepped
        // the HTML for "success", so submissions silently failed.
        router.post(route('wallet.submit-proof'), {
            transaction_id: proofForm.data.transaction_id,
            proof_file: proofForm.data.proof_file,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                notify('success', t('wallet_proof_submitted'));
                proofForm.reset();
            },
            onError: () => notify('error', t('wallet_failed_submit_proof')),
        });
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

            {/* Mobile money polling indicator */}
            <AnimatePresence>
                {pollingTxId && (
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: 20 }}
                        className="fixed bottom-6 left-1/2 -translate-x-1/2 z-[120]"
                    >
                        <div className="flex items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-3 shadow-xl text-blue-700">
                            <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                            <p className="text-xs font-black uppercase tracking-widest">Waiting for payment confirmation…</p>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            {/* InnBucks authorization code modal */}
            <AnimatePresence>
                {innbucksData && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
                    >
                        <motion.div
                            initial={{ scale: 0.95, y: 10 }}
                            animate={{ scale: 1, y: 0 }}
                            exit={{ scale: 0.95, y: 10 }}
                            className="bg-white rounded-3xl p-8 shadow-2xl max-w-sm w-full space-y-6"
                        >
                            <div className="text-center space-y-1">
                                <p className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">InnBucks Payment</p>
                                <h2 className="text-2xl font-black text-zinc-900">Authorization Code</h2>
                                <p className="text-xs text-zinc-500">Open your InnBucks app and enter the code below, or tap the button to open the app directly.</p>
                            </div>

                            <div className="bg-zinc-900 rounded-2xl p-6 text-center space-y-2">
                                <p className="text-4xl font-black tracking-[0.2em] text-emerald-400 select-all">{innbucksData.authorizationCode}</p>
                                {innbucksData.authorizationExpires && (
                                    <p className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">Expires: {innbucksData.authorizationExpires}</p>
                                )}
                            </div>

                            {innbucksData.qrUrl && (
                                <div className="flex justify-center">
                                    <img src={innbucksData.qrUrl} alt="InnBucks QR Code" className="w-40 h-40 rounded-xl border border-zinc-100" />
                                </div>
                            )}

                            <div className="space-y-3">
                                <a
                                    href={innbucksData.deepLink}
                                    className="block w-full text-center py-4 rounded-2xl bg-emerald-500 text-white font-black text-xs uppercase tracking-widest shadow-xl hover:bg-emerald-600 transition-all"
                                >
                                    Open InnBucks App
                                </a>
                                <button
                                    onClick={() => setInnbucksData(null)}
                                    className="w-full text-center py-3 rounded-2xl border-2 border-zinc-100 text-zinc-500 font-black text-xs uppercase tracking-widest hover:border-zinc-300 transition-all"
                                >
                                    Close (payment is being tracked)
                                </button>
                            </div>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>

            {/* O'mari OTP modal */}
            <AnimatePresence>
                {omariOtp && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
                    >
                        <motion.div
                            initial={{ scale: 0.95, y: 10 }}
                            animate={{ scale: 1, y: 0 }}
                            exit={{ scale: 0.95, y: 10 }}
                            className="bg-white rounded-3xl p-8 shadow-2xl max-w-sm w-full space-y-6"
                        >
                            <div className="text-center space-y-1">
                                <p className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">O'mari Payment</p>
                                <h2 className="text-2xl font-black text-zinc-900">Enter OTP</h2>
                                <p className="text-xs text-zinc-500">An OTP has been sent to your phone. Enter it below to complete the payment.</p>
                            </div>

                            {omariOtp.reference && (
                                <div className="bg-zinc-50 rounded-2xl px-5 py-3 text-center">
                                    <p className="text-[10px] font-black text-zinc-400 uppercase tracking-widest">OTP Reference</p>
                                    <p className="text-sm font-black text-zinc-900 mt-1">{omariOtp.reference}</p>
                                </div>
                            )}

                            <div className="space-y-2">
                                <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-1">One-Time Password</label>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    maxLength={8}
                                    value={omariOtp.otp}
                                    onChange={e => setOmariOtp(prev => prev ? { ...prev, otp: e.target.value } : null)}
                                    placeholder="Enter OTP"
                                    className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-black text-zinc-900 text-center text-xl tracking-[0.3em] focus:outline-none focus:border-emerald-500 transition-all"
                                    autoFocus
                                />
                            </div>

                            <div className="space-y-3">
                                <button
                                    onClick={submitOmariOtpHandler}
                                    disabled={omariOtp.submitting || !omariOtp.otp.trim()}
                                    className="w-full py-4 rounded-2xl bg-emerald-500 text-white font-black text-xs uppercase tracking-widest shadow-xl hover:bg-emerald-600 transition-all disabled:opacity-50"
                                >
                                    {omariOtp.submitting ? 'Submitting…' : 'Confirm Payment'}
                                </button>
                                <button
                                    onClick={() => setOmariOtp(null)}
                                    disabled={omariOtp.submitting}
                                    className="w-full text-center py-3 rounded-2xl border-2 border-zinc-100 text-zinc-500 font-black text-xs uppercase tracking-widest hover:border-zinc-300 transition-all disabled:opacity-50"
                                >
                                    Cancel
                                </button>
                            </div>
                        </motion.div>
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
                                <span className="text-6xl font-black tracking-tighter">{formatUSD(Number(user.balance || 0))}</span>
                                <span className="text-emerald-400 text-sm font-black">{currencyCode}</span>
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
                    <StatCard label="Total Deposited" value={formatUSD(Number(totals.deposited))} />
                    <StatCard label="Manual Earnings" value={formatUSD(Number(totals.contract_earnings))} />
                    <StatCard label="Total Spent" value={formatUSD(Number(totals.spent))} />
                    <StatCard label="Withdrawn" value={formatUSD(Number(totals.withdrawn))} />
                </section>

                <AnimatePresence>
                    {showDeposit && (
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setShowDeposit(false)}
                            className="fixed inset-0 z-[115] flex items-start justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
                        >
                        <motion.div
                            initial={{ opacity: 0, scale: 0.96, y: 16 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.96, y: 16 }}
                            onClick={(e) => e.stopPropagation()}
                            className="my-8 w-full max-w-3xl rounded-[2.5rem] border border-zinc-200 bg-white p-7 shadow-2xl sm:p-10"
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
                                                const badge = isPaynow ? 'ONLINE' : (bonusPercent > 0 ? `MANUAL +${bonusPercent}%` : 'MANUAL');
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
                                            {depositForm.data.method !== 'paynow' ? (
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4">Mobile Number</label>
                                                    <input 
                                                        type="tel" required
                                                        value={depositForm.data.phone}
                                                        onChange={e => depositForm.setData('phone', e.target.value)}
                                                        placeholder="e.g. 0771234567"
                                                        className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-black text-zinc-900 focus:outline-none focus:border-emerald-500 transition-all"
                                                    />
                                                    {depositForm.data.method === 'innbucks' && (
                                                        <p className="text-[10px] text-zinc-500 ml-4">You'll receive an authorization code to enter in your InnBucks app.</p>
                                                    )}
                                                    {depositForm.data.method === 'omari' && (
                                                        <p className="text-[10px] text-zinc-500 ml-4">An OTP will be sent to your phone via SMS.</p>
                                                    )}
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
                                            {bonusPercent > 0 && (
                                                <div className="p-5 rounded-3xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-medium leading-relaxed">
                                                    <p className="font-black uppercase tracking-widest text-[9px] mb-1 flex items-center gap-2">
                                                        <FaCheckCircle /> {bonusPercent}% Bonus on Every Manual Deposit
                                                    </p>
                                                    {bonusPreview !== null
                                                        ? <>Deposit <span className="font-black">${depositAmount.toFixed(2)}</span> and get an extra <span className="font-black">${bonusPreview.toFixed(2)}</span> credited instantly when your payment is confirmed.</>
                                                        : <>Every manual deposit earns an instant {bonusPercent}% wallet bonus once your payment is confirmed.</>}
                                                </div>
                                            )}
                                            {chosenDetails && (
                                                <div className="p-6 rounded-3xl bg-zinc-900 text-zinc-300 text-xs font-medium leading-relaxed border border-white/5 space-y-4">
                                                    <p className="text-emerald-400 font-black uppercase tracking-widest text-[9px] flex items-center gap-2">
                                                        <FaInfoCircle /> Payment Details
                                                    </p>
                                                    {chosenDetails.account_name && (
                                                        <div>
                                                            <span className="text-zinc-400 text-[9px] uppercase tracking-widest">Account Name</span>
                                                            <p className="text-white font-black">{chosenDetails.account_name}</p>
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
                        </motion.div>
                        </motion.div>
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
                        // Only MANUAL pending deposits need proof. is_gateway is
                        // computed per-transaction on the server (Paynow poll-URL
                        // reference or an always-gateway method); fall back to the
                        // method set for safety.
                        const pendingManualDeposits = (transactions.data ?? []).filter(
                            t => t.type === 'deposit' && t.status === 'pending'
                                && !(t as any).is_gateway && !gatewaySet.has(t.method ?? '')
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
                            const statusOverride = t.status ? statusThemeOverrides[t.status] : undefined;
                            const theme = statusOverride ?? typeThemes[t.type] ?? { icon: FaClock, color: 'text-zinc-400', bg: 'bg-zinc-50' };
                            const Icon = theme.icon;
                            // Only unresolved/failed transactions get a badge — a completed one
                            // is self-evident from its color and doesn't need a label.
                            const isUnsettled = Boolean(statusOverride);
                            const amountColor = isUnsettled ? 'text-zinc-400' : (t.amount >= 0 ? 'text-emerald-600' : 'text-zinc-900');
                            return (
                                <div key={t.id} className="flex items-center gap-6 p-6 rounded-[2rem] border border-zinc-50 hover:bg-zinc-50 transition-all group">
                                    <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-xl shadow-sm ${theme.bg} ${theme.color}`}>
                                        <Icon />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="font-black text-zinc-900 truncate group-hover:text-zinc-600 transition-colors uppercase tracking-tight text-sm">{t.description || t.type}</p>
                                            {statusOverride && (
                                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-widest ${statusOverride.badge}`}>
                                                    {statusOverride.badgeText}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mt-1">{new Date(t.created_at).toLocaleDateString()} · {t.type.replace('_', ' ')}</p>
                                        {t.reference_code && (
                                            <p className="text-[10px] font-mono text-zinc-300 mt-0.5 select-all">{t.reference_code}</p>
                                        )}
                                    </div>
                                    <div className={`text-xl font-black ${amountColor} ${isUnsettled ? 'line-through' : ''}`}>
                                        {t.amount >= 0 ? '+' : '-'}{formatUSD(Math.abs(Number(t.amount)))}
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
