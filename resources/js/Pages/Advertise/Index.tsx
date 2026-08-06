import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/react';
import { FaBullhorn, FaExclamationCircle, FaFilm, FaStar, FaWallet } from 'react-icons/fa';

interface Package {
    key: string;
    label: string;
    days: number;
    price: number;
    includes_video: boolean;
    blurb: string;
    recommended?: boolean;
}

interface Props extends PageProps {
    packages: Package[];
    recommended: string | null;
}

export default function AdvertiseIndex({ auth, packages, recommended }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        package: recommended ?? packages[0]?.key ?? '',
    });

    const chosen = packages.find((p) => p.key === data.package);
    const userBalance = auth.user?.balance !== undefined ? Number(auth.user.balance) : 0;
    const total = chosen ? Number(chosen.price) : 0;
    const balanceAfter = userBalance - total;
    const isInsufficient = balanceAfter < 0;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!chosen) return;
        post(route('advertise.store'), { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout>
            <Head title="Advertise" />

            <div className="mx-auto max-w-7xl px-4 py-12 pb-28 lg:pb-12 sm:px-6 lg:px-8">
                <div className="mb-10 text-center max-w-2xl mx-auto">
                    <h1 className="text-4xl font-black tracking-tight text-zinc-900 mb-4 flex items-center justify-center gap-3">
                        <FaBullhorn className="text-indigo-500" /> {t('advertise_title')}
                    </h1>
                    <p className="text-zinc-500 font-medium">{t('advertise_subtitle')}</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
                    <div className="lg:col-span-8 space-y-4">
                        {packages.map((pkg) => {
                            const isSelected = data.package === pkg.key;
                            return (
                                <button
                                    key={pkg.key}
                                    type="button"
                                    onClick={() => setData('package', pkg.key)}
                                    className={`w-full text-left p-6 rounded-3xl border-2 transition-all duration-200 ${
                                        isSelected
                                            ? 'border-indigo-500 bg-indigo-50/50 shadow-md scale-[1.01]'
                                            : 'border-zinc-100 bg-white hover:border-zinc-200 hover:bg-zinc-50'
                                    }`}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3 mb-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-lg font-black text-zinc-900">{pkg.label}</span>
                                            {pkg.includes_video && (
                                                <span className="px-2 py-0.5 rounded-md bg-purple-100 text-purple-700 text-[10px] font-black uppercase tracking-widest flex items-center gap-1">
                                                    <FaFilm size={10} /> {t('advertise_ai_video_tag')}
                                                </span>
                                            )}
                                            {pkg.key === recommended && (
                                                <span className="px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[10px] font-black uppercase tracking-widest flex items-center gap-1">
                                                    <FaStar size={10} /> {t('advertise_most_popular')}
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-2xl font-black text-indigo-600">${Number(pkg.price).toFixed(2)}</span>
                                    </div>
                                    <p className="text-sm font-medium text-zinc-600 leading-relaxed">{pkg.blurb}</p>
                                </button>
                            );
                        })}
                        {errors.package && <p className="text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle /> {errors.package}</p>}
                    </div>

                    <div className="lg:col-span-4">
                        <div className="sticky top-8 space-y-6">
                            <form onSubmit={submit} id="advertise-form">
                                <div className="bg-zinc-950 text-white rounded-[2rem] p-8 shadow-2xl relative overflow-hidden">
                                    <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-indigo-500/20 to-purple-500/20 rounded-full blur-[60px] pointer-events-none" />
                                    <div className="relative z-10 space-y-6">
                                        <div className="flex items-center gap-3">
                                            <div className="p-3 bg-white/10 rounded-xl">
                                                <FaBullhorn className="text-xl text-indigo-400" />
                                            </div>
                                            <h3 className="text-xl font-black">{t('advertise_booking_summary')}</h3>
                                        </div>

                                        {chosen && (
                                            <div className="space-y-4 pt-4 border-t border-white/10 text-sm">
                                                <div className="flex justify-between items-center">
                                                    <span className="text-zinc-400 font-medium">{t('advertise_package_label')}</span>
                                                    <span className="font-bold">{chosen.label}</span>
                                                </div>
                                                {chosen.includes_video && (
                                                    <div className="flex justify-between items-center">
                                                        <span className="text-zinc-400 font-medium">{t('advertise_includes')}</span>
                                                        <span className="font-bold text-purple-300">{t('advertise_includes_video')}</span>
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        <div className="pt-4 border-t border-white/10">
                                            <div className="flex justify-between items-end">
                                                <span className="text-zinc-400 font-bold text-xs uppercase tracking-widest mb-1">{t('charge')}</span>
                                                <span className="text-4xl font-black text-indigo-400">${total.toFixed(2)}</span>
                                            </div>
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing || !chosen || isInsufficient}
                                            className="w-full py-4 px-6 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-black uppercase tracking-widest text-sm rounded-xl transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 disabled:cursor-not-allowed"
                                        >
                                            {processing ? t('processing_btn') : t('advertise_pay_book')}
                                        </button>
                                        <p className="text-[11px] text-zinc-400 text-center">{t('advertise_pay_note')}</p>
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
                                        <span>{t('advertise_insufficient')}</span>
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
                    form="advertise-form"
                    disabled={processing || !chosen || isInsufficient}
                    className="shrink-0 py-3.5 px-8 bg-indigo-500 hover:bg-indigo-400 text-zinc-950 font-black uppercase tracking-widest text-sm rounded-xl transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {processing ? t('processing_btn') : t('advertise_pay_book')}
                </button>
            </div>
        </AuthenticatedLayout>
    );
}
