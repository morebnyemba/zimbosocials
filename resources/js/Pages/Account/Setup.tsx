import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { useTranslation } from '@/lib/i18n';
import { Head, useForm, Link } from '@inertiajs/react';
import { FaExclamationCircle, FaLock, FaShieldAlt, FaAt, FaUserCircle } from 'react-icons/fa';

interface Props extends PageProps {
    current: { username: string; email: string };
    is_placeholder_email: boolean;
}

export default function AccountSetup({ current, is_placeholder_email }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        username: current.username ?? '',
        email: is_placeholder_email ? '' : current.email ?? '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('account.setup.update'), { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout>
            <Head title={t('account_setup_title')} />

            <div className="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="mb-10 text-center">
                    <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-500/10 text-indigo-500 text-2xl">
                        <FaShieldAlt />
                    </div>
                    <h1 className="text-3xl font-black tracking-tight text-zinc-900 mb-3">{t('account_setup_title')}</h1>
                    <p className="text-zinc-500 font-medium">{t('account_setup_subtitle')}</p>
                </div>

                <form onSubmit={submit} className="bg-white rounded-[2rem] border-2 border-zinc-100 p-8 space-y-6 shadow-sm">
                    <div>
                        <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest flex items-center gap-2">
                            <FaUserCircle className="text-zinc-400" /> {t('account_setup_username')}
                        </label>
                        <input
                            type="text"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-4 font-bold text-zinc-900 focus:border-indigo-500 focus:bg-white focus:outline-none transition-all"
                            autoComplete="username"
                            required
                        />
                        {errors.username && <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle /> {errors.username}</p>}
                    </div>

                    <div>
                        <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest flex items-center gap-2">
                            <FaAt className="text-zinc-400" /> {t('account_setup_email')}
                        </label>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder={is_placeholder_email ? t('account_setup_email_placeholder') : undefined}
                            className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-4 font-medium text-zinc-900 focus:border-indigo-500 focus:bg-white focus:outline-none transition-all"
                            autoComplete="email"
                            required
                        />
                        {is_placeholder_email && !errors.email && (
                            <p className="mt-2 text-xs font-bold text-amber-600 flex items-center gap-1">
                                <FaExclamationCircle /> {t('account_setup_placeholder_note')}
                            </p>
                        )}
                        {errors.email && <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle /> {errors.email}</p>}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest flex items-center gap-2">
                                <FaLock className="text-zinc-400" /> {t('account_setup_password')}
                            </label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-4 font-bold text-zinc-900 focus:border-indigo-500 focus:bg-white focus:outline-none transition-all"
                                autoComplete="new-password"
                                required
                            />
                            {errors.password && <p className="mt-2 text-xs font-bold text-red-500 flex items-center gap-1"><FaExclamationCircle /> {errors.password}</p>}
                        </div>
                        <div>
                            <label className="mb-2 block text-xs font-black text-zinc-900 uppercase tracking-widest">{t('account_setup_password_confirm')}</label>
                            <input
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 px-4 py-4 font-bold text-zinc-900 focus:border-indigo-500 focus:bg-white focus:outline-none transition-all"
                                autoComplete="new-password"
                                required
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-4 pt-4">
                        <Link
                            href={route('account.setup.skip')}
                            method="post"
                            as="button"
                            className="text-xs font-black uppercase tracking-widest text-zinc-400 hover:text-zinc-600 transition-colors"
                        >
                            {t('account_setup_skip')}
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="py-3.5 px-8 bg-indigo-500 hover:bg-indigo-400 text-white font-black uppercase tracking-widest text-sm rounded-xl transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100"
                        >
                            {processing ? t('processing_btn') : t('account_setup_save')}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
