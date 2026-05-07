import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel'
import TextInput from '@/Components/TextInput'
import { Head, Link, useForm, usePage } from '@inertiajs/react'
import { PageProps } from '@/types'
import { AnimatePresence, motion } from 'framer-motion'
import type { FormEventHandler, ReactNode } from 'react'
import { useState } from 'react'
import { FiArrowLeft, FiArrowRight, FiBriefcase, FiCheckCircle, FiUser, FiUsers, FiGift } from 'react-icons/fi'

type AccountPath = 'marketer' | 'individual' | 'business'

type RegisterPageProps = {
    referralCode?: string | null
    referrerName?: string | null
}

type PathDef = {
    key: AccountPath
    label: string
    tagline: string
    description: string
    icon: ReactNode
    cardClass: string
    badgeClass: string
}

const paths: PathDef[] = [
    {
        key: 'individual',
        label: 'Individual',
        tagline: 'Grow your personal brand',
        description: 'For creators, influencers, and individuals looking to grow their own social media accounts and presence.',
        icon: <FiUser className="h-6 w-6" />,
        cardClass: 'border-emerald-300 bg-emerald-50 hover:bg-emerald-100/70',
        badgeClass: 'bg-emerald-600 text-white',
    },
    {
        key: 'business',
        label: 'Business',
        tagline: 'Scale your brand reach',
        description: 'For companies, agencies, and brands running campaigns across multiple platforms and client accounts.',
        icon: <FiBriefcase className="h-6 w-6" />,
        cardClass: 'border-amber-300 bg-amber-50 hover:bg-amber-100/70',
        badgeClass: 'bg-amber-500 text-white',
    },
    {
        key: 'marketer',
        label: 'Marketer',
        tagline: 'Earn from campaign contracts',
        description: 'For digital marketers who want to accept and execute brand campaign contracts through the platform.',
        icon: <FiUsers className="h-6 w-6" />,
        cardClass: 'border-red-300 bg-red-50 hover:bg-red-100/70',
        badgeClass: 'bg-red-600 text-white',
    },
]

export default function Register() {
    const { referralCode, referrerName } = usePage<PageProps<RegisterPageProps>>().props
    const [step, setStep] = useState<1 | 2>(1)
    const [selectedPath, setSelectedPath] = useState<AccountPath | null>(null)

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        whatsapp_number: '',
        password: '',
        password_confirmation: '',
        role: 'user',
        account_type: '',
        company_name: '',
        locale: 'sn',
        referral_code: referralCode ?? '',
    })

    const choosePath = (p: AccountPath) => {
        setSelectedPath(p)
        setData((prev) => ({
            ...prev,
            account_type: p,
            role: p === 'marketer' ? 'marketer' : 'user',
        }))
        setStep(2)
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault()
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        })
    }

    const chosen = paths.find((p) => p.key === selectedPath)

    return (
        <>
            <Head title="Register" />

            <div className="flex min-h-screen items-start justify-center bg-gradient-to-br from-white via-amber-50 to-emerald-50 px-4 py-10">
                <div className="w-full max-w-2xl">
                    <div className="mb-8 text-center">
                        <Link href="/" className="inline-flex items-center justify-center">
                            <img src="/images/zimbosocials.png" alt="Zimbo Socials" className="h-12 w-auto" />
                        </Link>
                        <p className="mt-2 text-xs uppercase tracking-[0.18em] text-zinc-500">Create your account</p>
                    </div>

                    <AnimatePresence mode="wait">
                        {step === 1 && (
                            <motion.div key="step1" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -20 }} transition={{ duration: 0.25 }}>
                                <div className="mb-6 text-center">
                                    <h1 className="text-2xl font-extrabold text-zinc-950">How will you use Zimbo Socials?</h1>
                                    <p className="mt-2 text-sm text-zinc-600">Choose the path that best describes your goals.</p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    {paths.map((p) => (
                                        <button
                                            key={p.key}
                                            onClick={() => choosePath(p.key)}
                                            className={`group flex flex-col items-start gap-3 rounded-2xl border-2 p-5 text-left transition-all ${p.cardClass} focus:outline-none focus:ring-2 focus:ring-zinc-950`}
                                        >
                                            <span className={`inline-flex items-center justify-center rounded-xl p-2 ${p.badgeClass}`}>
                                                {p.icon}
                                            </span>
                                            <div>
                                                <p className="font-bold text-zinc-950">{p.label}</p>
                                                <p className="mt-0.5 text-xs font-medium text-zinc-600">{p.tagline}</p>
                                            </div>
                                            <p className="text-xs leading-relaxed text-zinc-600">{p.description}</p>
                                            <span className="mt-auto inline-flex items-center gap-1 text-xs font-semibold text-zinc-950 opacity-0 transition-opacity group-hover:opacity-100">
                                                Choose this <FiArrowRight className="h-3.5 w-3.5" />
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                <p className="mt-6 text-center text-sm text-zinc-600">
                                    Already have an account?{' '}
                                    <Link href={route('login')} className="font-semibold text-zinc-950 underline underline-offset-2 hover:text-emerald-700">Sign in</Link>
                                </p>
                            </motion.div>
                        )}

                        {step === 2 && chosen && (
                            <motion.div key="step2" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -20 }} transition={{ duration: 0.25 }}>
                                <div className="mb-5 flex items-center justify-between">
                                    <button onClick={() => setStep(1)} className="inline-flex items-center gap-1.5 text-sm text-zinc-600 hover:text-zinc-950">
                                        <FiArrowLeft className="h-4 w-4" /> Back
                                    </button>
                                    <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${chosen.badgeClass}`}>
                                        {chosen.icon} {chosen.label}
                                    </span>
                                </div>

                                <div className="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                                    <h2 className="text-xl font-bold text-zinc-950">{chosen.tagline}</h2>
                                    <p className="mt-1 text-sm text-zinc-600">{chosen.description}</p>

                                    <form onSubmit={submit} className="mt-6 space-y-4">
                                        {selectedPath === 'business' && (
                                            <div>
                                                <InputLabel htmlFor="company_name" value="Company / Brand Name" />
                                                <TextInput
                                                    id="company_name"
                                                    name="company_name"
                                                    value={data.company_name}
                                                    className="mt-1 block w-full"
                                                    onChange={(e) => setData('company_name', e.target.value)}
                                                    placeholder="Your company or brand name"
                                                />
                                                <InputError message={errors.company_name} className="mt-2" />
                                            </div>
                                        )}

                                        <div className={selectedPath === 'business' ? 'grid gap-4 sm:grid-cols-2' : ''}>
                                            <div>
                                                <InputLabel htmlFor="name" value={selectedPath === 'business' ? 'Contact Name' : 'Full Name'} />
                                                <TextInput
                                                    id="name"
                                                    name="name"
                                                    value={data.name}
                                                    className="mt-1 block w-full"
                                                    autoComplete="name"
                                                    isFocused={true}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    required
                                                />
                                                <InputError message={errors.name} className="mt-2" />
                                            </div>
                                            <div className={selectedPath !== 'business' ? 'mt-0' : ''}>
                                                <InputLabel htmlFor="email" value="Email Address" />
                                                <TextInput
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    value={data.email}
                                                    className="mt-1 block w-full"
                                                    autoComplete="username"
                                                    onChange={(e) => setData('email', e.target.value)}
                                                    required
                                                />
                                                <InputError message={errors.email} className="mt-2" />
                                            </div>
                                        </div>

                                        {/* WhatsApp Number — required for all paths */}
                                        <div>
                                            <InputLabel htmlFor="whatsapp_number" value="WhatsApp Number" />
                                            <TextInput
                                                id="whatsapp_number"
                                                type="tel"
                                                name="whatsapp_number"
                                                value={data.whatsapp_number}
                                                className="mt-1 block w-full"
                                                onChange={(e) => setData('whatsapp_number', e.target.value)}
                                                placeholder="e.g. 263771234567"
                                                required
                                            />
                                            <p className="mt-1 text-xs text-zinc-500">Include country code, no + sign (e.g. 263 for Zimbabwe)</p>
                                            <InputError message={errors.whatsapp_number} className="mt-2" />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="locale" value="Preferred Language" />
                                            <select
                                                id="locale"
                                                name="locale"
                                                value={data.locale}
                                                onChange={(e) => setData('locale', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-zinc-900 focus:ring-zinc-900"
                                                required
                                            >
                                                <option value="sn">Shona</option>
                                                <option value="nd">IsiNdebele</option>
                                                <option value="en">English</option>
                                            </select>
                                            <InputError message={errors.locale} className="mt-2" />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="referral_code" value="Referral Code (Optional)" />
                                            {referralCode ? (
                                                <>
                                                    <div className="mt-1 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2">
                                                        <FiGift className="h-4 w-4 shrink-0 text-emerald-600" />
                                                        <span className="font-mono text-sm font-bold tracking-wider text-emerald-800">{referralCode}</span>
                                                        {referrerName && (
                                                            <span className="ml-auto text-xs text-emerald-700">Referred by <strong>{referrerName}</strong></span>
                                                        )}
                                                    </div>
                                                    <input type="hidden" name="referral_code" value={referralCode} />
                                                </>
                                            ) : (
                                                <>
                                                    <TextInput
                                                        id="referral_code"
                                                        name="referral_code"
                                                        value={data.referral_code}
                                                        className="mt-1 block w-full"
                                                        onChange={(e) => setData('referral_code', e.target.value.toUpperCase())}
                                                        placeholder="e.g. ZIMABC12345"
                                                    />
                                                    <InputError message={errors.referral_code} className="mt-2" />
                                                </>
                                            )}
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <InputLabel htmlFor="password" value="Password" />
                                                <TextInput
                                                    id="password"
                                                    type="password"
                                                    name="password"
                                                    value={data.password}
                                                    className="mt-1 block w-full"
                                                    autoComplete="new-password"
                                                    onChange={(e) => setData('password', e.target.value)}
                                                    required
                                                />
                                                <InputError message={errors.password} className="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel htmlFor="password_confirmation" value="Confirm Password" />
                                                <TextInput
                                                    id="password_confirmation"
                                                    type="password"
                                                    name="password_confirmation"
                                                    value={data.password_confirmation}
                                                    className="mt-1 block w-full"
                                                    autoComplete="new-password"
                                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                                    required
                                                />
                                                <InputError message={errors.password_confirmation} className="mt-2" />
                                            </div>
                                        </div>

                                        {selectedPath === 'marketer' && (
                                            <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800">
                                                <FiCheckCircle className="mb-0.5 mr-1 inline h-3.5 w-3.5" />
                                                Your account will be registered as a marketer. Campaign contract access is subject to admin review and activation.
                                            </div>
                                        )}

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full rounded-xl bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 py-2.5 text-sm font-bold text-white shadow transition hover:opacity-90 disabled:opacity-60"
                                        >
                                            {processing ? 'Creating account…' : `Create ${chosen.label} Account`}
                                        </button>

                                        <p className="text-center text-sm text-zinc-600">
                                            Already registered?{' '}
                                            <Link href={route('login')} className="font-semibold text-zinc-950 underline underline-offset-2 hover:text-emerald-700">Sign in</Link>
                                        </p>
                                    </form>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </>
    )
}
