import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { FormEventHandler } from 'react';
import { FiArrowRight, FiCheckCircle, FiShield } from 'react-icons/fi';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Log in" />

            <div className="flex min-h-screen items-start justify-center bg-gradient-to-br from-white via-amber-50 to-emerald-50 px-4 py-10">
                <div className="w-full max-w-md">
                    <div className="mb-6 text-center">
                        <Link href="/" className="inline-flex items-center justify-center">
                            <img src="/images/zimbosocials.png" alt="Zimbo Socials" className="h-12 w-auto" />
                        </Link>
                        <p className="mt-2 text-xs uppercase tracking-[0.18em] text-zinc-500">Welcome back</p>
                    </div>

                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.25 }}
                        className="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm"
                    >
                        <h1 className="text-2xl font-extrabold text-zinc-950">Sign in to your account</h1>
                        <p className="mt-1 text-sm text-zinc-600">Manage orders, wallet activity, and your growth campaigns.</p>

                        <div className="mt-4 grid gap-2 sm:grid-cols-2">
                            <p className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-800">
                                <FiCheckCircle className="h-3.5 w-3.5" /> Secure login
                            </p>
                            <p className="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-2.5 py-1.5 text-xs font-medium text-zinc-700">
                                <FiShield className="h-3.5 w-3.5" /> Protected account access
                            </p>
                        </div>

                        {status && (
                            <div className="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-5 space-y-4">
                            <div>
                                <InputLabel htmlFor="email" value="Email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="mt-1 block w-full"
                                    autoComplete="username"
                                    isFocused={true}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                <InputError message={errors.email} className="mt-2" />
                            </div>

                            <div>
                                <InputLabel htmlFor="password" value="Password" />
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                <InputError message={errors.password} className="mt-2" />
                            </div>

                            <label className="flex items-center">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked as false)}
                                />
                                <span className="ms-2 text-sm text-zinc-600">Remember me</span>
                            </label>

                            <div className="flex items-center justify-between">
                                {canResetPassword ? (
                                    <Link href={route('password.request')} className="text-sm text-zinc-600 underline hover:text-zinc-900">
                                        Forgot password?
                                    </Link>
                                ) : (
                                    <span className="text-sm text-zinc-400">Need help? Contact support.</span>
                                )}

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="ms-4 inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 px-4 py-2 text-sm font-bold text-white shadow transition hover:opacity-90 disabled:opacity-60"
                                >
                                    {processing ? 'Signing in...' : 'Log in'}
                                    <FiArrowRight className="h-4 w-4" />
                                </button>
                            </div>
                        </form>
                    </motion.div>

                    <p className="mt-4 text-center text-sm text-zinc-600">
                        No account?{' '}
                        <Link href={route('register')} className="font-semibold text-zinc-950 underline underline-offset-2 hover:text-emerald-700">
                            Create one
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
