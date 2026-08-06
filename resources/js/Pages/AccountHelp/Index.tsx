import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { FaExclamationCircle, FaTools, FaWallet } from 'react-icons/fa';

interface ServiceItem {
    key: string;
    label: string;
    price: number;
    blurb: string;
}

interface Props extends PageProps {
    items: ServiceItem[];
}

export default function AccountHelpIndex({ auth, items }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        item: items[0]?.key ?? '',
    });

    const chosen = items.find((i) => i.key === data.item);
    const userBalance = auth.user?.balance !== undefined ? Number(auth.user.balance) : 0;
    const total = chosen ? Number(chosen.price) : 0;
    const balanceAfter = userBalance - total;
    const isInsufficient = balanceAfter < 0;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!chosen) return;
        post(route('account-help.store'), { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout>
            <Head title="Account Help" />

            <div className="mx-auto max-w-7xl px-4 py-12 pb-28 lg:pb-12 sm:px-6 lg:px-8">
                <div className="mb-10 text-center max-w-2xl mx-auto">
                    <h1 className="text-4xl font-black tracking-tight text-zinc-900 mb-4 flex items-center justify-center gap-3">
                        <FaTools className="text-emerald-500" /> {t('account_help_title')}
                    </h1>
                    <p className="text-zinc-500 font-medium">{t('account_help_subtitle')}</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
                    <div className="lg:col-span-8 space-y-4">
                        {items.map((item) => {
                            const isSelected = data.item === item.key;
                            return (
                                <button
                                    key={item.key}
                                    type="button"
                                    onClick={() => setData('item', item.key)}
                                    className={`w-full text-left p-6 rounded-3xl border-2 transition-all duration-200 ${
                                        isSelected
                                            ? 'border-emerald-500 bg-emerald-50/50 shadow-md scale-[1.01]'
                                            : 'border-zinc-100 bg-white hover:border-zinc-200 hover:bg-zinc-50'
                                    }`}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3 mb-2">
                                        <span className="text-lg font-black text-zinc-900">{item.label}</span>
                                        <span className="text-2xl font-black text-emerald-600">${Number(item.price).toFixed(2)}</span>
                                    </div>
                                    <p className="text-sm font-medium text-zinc-600 leading-relaxed">{item.blurb}</p>
                                </button>
                            );
                        })}
                        {errors.item && <p className="text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle /> {errors.item}</p>}
                    </div>

                    <div className="lg:col-span-4">
                        <div className="sticky top-8 space-y-6">
                            <form onSubmit={submit} id="account-help-form">
                                <div className="bg-zinc-950 text-white rounded-[2rem] p-8 shadow-2xl relative overflow-hidden">
                                    <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-500/20 to-teal-500/20 rounded-full blur-[60px] pointer-events-none" />
                                    <div className="relative z-10 space-y-6">
                                        <div className="flex items-center gap-3">
                                            <div className="p-3 bg-white/10 rounded-xl">
                                                <FaTools className="text-xl text-emerald-400" />
                                            </div>
                                            <h3 className="text-xl font-black">{t('account_help_booking_summary')}</h3>
                                        </div>

                                        {chosen && (
                                            <div className="space-y-4 pt-4 border-t border-white/10 text-sm">
                                                <div className="flex justify-between items-center">
                                                    <span className="text-zinc-400 font-medium">{t('account_help_service_label')}</span>
                                                    <span className="font-bold">{chosen.label}</span>
                                                </div>
                                            </div>
                                        )}

                                        <div className="pt-4 border-t border-white/10">
                                            <div className="flex justify-between items-end">
                                                <span className="text-zinc-400 font-bold text-xs uppercase tracking-widest mb-1">{t('charge')}</span>
                                                <span className="text-4xl font-black text-emerald-400">${total.toFixed(2)}</span>
                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing || !chosen || isInsufficient}
                                            className="w-full py-4 px-6 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-black uppercase tracking-widest text-sm rounded-xl transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 disabled:cursor-not-allowed"
                                        >
                                            {processing ? t('processing_btn') : t('account_help_pay')}
                                        </button>
                                        <p className="text-[11px] text-zinc-400 text-center">{t('account_help_pay_note')}</p>
                                    </div>
                                </div>
                            </form>

                            <div className={`p-6 rounded-[2rem] border-2 transition-colors ${isInsufficient ? 'bg-red-50 border-red-200' : 'bg-white border-zinc-200'}`}>
                                <div className="flex items-center gap-3 mb-4">
                                    <FaWallet className={isInsufficient ? 'text-red-500' : 'text-zinc-400'} />
                                    <h4 className="text-xs font-black uppercase tracking-widest text-zinc-900">{t('wallet_status')}</h4>
                                </div>
                                <div className="space-y-3">
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-zinc-500 font-medium">{t('current_balance')}</span>
                                        <span className="font-bold">${userBalance.toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between items-center text-sm pt-2 border-t border-zinc-100">
                                        <span className="text-zinc-500 font-medium">{t('remaining_after')}</span>
                                        <span className={`font-black ${isInsufficient ? 'text-red-600' : 'text-emerald-600'}`}>
                                            ${balanceAfter.toFixed(2)}
                                        </span>
                                    </div>
                                </div>
                                {isInsufficient && (
                                    <div className="mt-4 p-3 bg-red-100 text-red-800 rounded-xl text-xs font-bold flex gap-2">
                                        <FaExclamationCircle className="shrink-0 mt-0.5" />
                                        <span>{t('account_help_insufficient')}</span>
                                    </div>
                                )}
                                {(errors as any).balance && (
                                    <div className="mt-4 p-3 bg-red-100 text-red-800 rounded-xl text-xs font-bold flex gap-2">
                                        <FaExclamationCircle className="shrink-0 mt-0.5" />
                                        <span>{(errors as any).balance}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="lg:hidden fixed bottom-0 inset-x-0 z-40 border-t border-zinc-200 bg-white/95 backdrop-blur px-4 py-3 flex items-center gap-4 shadow-[0_-8px_30px_-12px_rgba(0,0,0,0.25)]">
                <div className="flex-1 min-w-0">
                    <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('charge')}</p>
                    <p className="text-2xl font-black text-zinc-900 leading-none">${total.toFixed(2)}</p>
                </div>
                <button
                    type="submit"
                    form="account-help-form"
                    disabled={processing || !chosen || isInsufficient}
                    className="shrink-0 py-3.5 px-8 bg-emerald-500 hover:bg-emerald-400 text-zinc-950 font-black uppercase tracking-widest text-sm rounded-xl transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {processing ? t('processing_btn') : t('account_help_pay')}
                </button>
            </div>
        </AuthenticatedLayout>
    );
}
