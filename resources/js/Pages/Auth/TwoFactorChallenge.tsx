import { Head, useForm, router } from '@inertiajs/react'
import { FormEventHandler } from 'react'

export default function TwoFactorChallenge() {
    const { data, setData, post, processing, errors } = useForm({ code: '' })

    const submit: FormEventHandler = (e) => {
        e.preventDefault()
        post(route('2fa.verify'))
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-zinc-50 px-4">
            <Head title="Verify Login" />

            <div className="w-full max-w-md rounded-[2rem] border border-zinc-200 bg-white p-8 shadow-xl">
                <div className="mb-6 text-center">
                    <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-900 text-2xl font-black text-white">Z</div>
                    <h1 className="text-xl font-black text-zinc-900 tracking-tight">Check your email</h1>
                    <p className="mt-2 text-sm font-medium text-zinc-500">
                        We sent a 6-digit code to your admin email address. Enter it below to finish signing in.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <input
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            maxLength={6}
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                            placeholder="123456"
                            className="w-full rounded-2xl border-2 border-zinc-200 bg-zinc-50 px-6 py-4 text-center font-mono text-2xl font-black tracking-[0.5em] text-zinc-900 focus:border-emerald-500 focus:outline-none"
                        />
                        {errors.code && <p className="mt-2 text-center text-xs font-bold text-red-600">{errors.code}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing || data.code.length !== 6}
                        className="w-full rounded-2xl bg-emerald-500 py-4 text-xs font-black uppercase tracking-widest text-white shadow-lg transition-all hover:bg-emerald-600 disabled:opacity-50"
                    >
                        {processing ? 'Verifying…' : 'Verify & Sign In'}
                    </button>
                </form>

                <div className="mt-6 flex items-center justify-between text-xs font-bold">
                    <button
                        type="button"
                        onClick={() => router.post(route('2fa.resend'))}
                        className="text-zinc-500 underline hover:text-zinc-900"
                    >
                        Resend code
                    </button>
                    <a href={route('login')} className="text-zinc-400 hover:text-zinc-700">Back to login</a>
                </div>
            </div>
        </div>
    )
}
