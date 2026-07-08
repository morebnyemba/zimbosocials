import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import ConfirmModal from '@/Components/ConfirmModal'
import { PageProps } from '@/types'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'

export default function SettingsIndex({ auth }: PageProps) {
    const user = auth.user
    const notifPrefs = (user as any).notification_prefs ?? { email: true, whatsapp: true }
    // Freshly generated key, flashed by the server exactly once. After any
    // other navigation only the masked last-4 form is available.
    const newApiKey = (usePage().props as any).flash?.new_api_key as string | undefined
    const apiKeyLast4 = (user as any).api_key_last4 as string | undefined
    const displayedKey = newApiKey ?? (apiKeyLast4 ? `zvk_live_••••••••••••${apiKeyLast4}` : null)

    const profileForm = useForm({
        name: user.name,
        email: user.email,
        phone: (user as any).phone ?? '',
        whatsapp_number: (user as any).whatsapp_number ?? '',
        company_name: (user as any).company_name ?? '',
        bio: (user as any).bio ?? '',
        locale: user.locale ?? 'en',
        currency: user.currency ?? 'USD',
        section: 'profile',
    })

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
        section: 'password',
    })

    const [emailNotif, setEmailNotif] = useState(notifPrefs.email)
    const [waNotif, setWaNotif] = useState(notifPrefs.whatsapp)
    const [copied, setCopied] = useState(false)
    const [showKeyConfirm, setShowKeyConfirm] = useState(false)

    function copyApiKey() {
        // Only the one-time revealed key is copyable — the masked form is useless.
        if (newApiKey) {
            navigator.clipboard.writeText(newApiKey)
            setCopied(true)
            setTimeout(() => setCopied(false), 2000)
        }
    }

    function regenerateKey() {
        setShowKeyConfirm(true)
    }

    function confirmRegenerateKey() {
        setShowKeyConfirm(false)
        router.post(route('settings.api-key'))
    }

    function saveNotifPrefs() {
        router.post(route('settings.update'), { section: 'notifications', email: emailNotif, whatsapp: waNotif }, { preserveScroll: true })
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-bold text-slate-900">Settings</h2>}>
            <Head title="Settings" />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
                {/* Profile */}
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="mb-4 font-semibold text-slate-900">Profile</h3>
                    <form onSubmit={(e) => { e.preventDefault(); profileForm.post(route('settings.update')) }} className="space-y-4">
                        {[
                            { label: 'Name', field: 'name' as const, type: 'text' },
                            { label: 'Email', field: 'email' as const, type: 'email' },
                            { label: 'Phone', field: 'phone' as const, type: 'tel' },
                            { label: 'WhatsApp Number', field: 'whatsapp_number' as const, type: 'tel' },
                            { label: 'Company Name', field: 'company_name' as const, type: 'text' },
                        ].map(({ label, field, type }) => (
                            <div key={field}>
                                <label className="mb-1.5 block text-sm font-medium text-slate-700">{label}</label>
                                <input type={type} value={profileForm.data[field]} onChange={(e) => profileForm.setData(field, e.target.value)}
                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-emerald-500" />
                                {profileForm.errors[field] && <p className="mt-1 text-xs text-red-600">{profileForm.errors[field]}</p>}
                            </div>
                        ))}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700">Bio</label>
                            <textarea value={profileForm.data.bio} onChange={(e) => profileForm.setData('bio', e.target.value)}
                                rows={3} className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-700">Language</label>
                                <select value={profileForm.data.locale} onChange={(e) => profileForm.setData('locale', e.target.value)}
                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none">
                                    <option value="en">English</option>
                                    <option value="sn">Shona</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-700">Currency</label>
                                <select value={profileForm.data.currency} onChange={(e) => profileForm.setData('currency', e.target.value)}
                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none">
                                    <option value="USD">USD</option>
                                    {Object.keys((usePage().props as any).currencyRates ?? {}).map((code) => (
                                        <option key={code} value={code}>{code}</option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-slate-400">Balances are always stored and charged in USD — this only changes how amounts are displayed to you.</p>
                            </div>
                        </div>
                        <button type="submit" disabled={profileForm.processing}
                            className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                            {profileForm.processing ? 'Saving…' : 'Save Profile'}
                        </button>
                    </form>
                </div>

                {/* Notification Preferences */}
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="mb-2 font-semibold text-slate-900">Notification Preferences</h3>
                    <p className="mb-4 text-sm text-slate-500">Choose how you want to receive notifications. In-app notifications are always enabled.</p>
                    <div className="space-y-3">
                        <label className="flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                            <div className="flex items-center gap-3">
                                <span className="text-xl">📧</span>
                                <div>
                                    <p className="text-sm font-medium text-slate-700">Email Notifications</p>
                                    <p className="text-xs text-slate-500">Deposits, withdrawals, refunds, and ticket replies</p>
                                </div>
                            </div>
                            <div className={`w-10 h-5 rounded-full transition-all cursor-pointer ${emailNotif ? 'bg-emerald-500' : 'bg-slate-300'}`}
                                onClick={() => setEmailNotif(!emailNotif)}>
                                <div className={`w-4 h-4 rounded-full bg-white transition-transform mt-0.5 ${emailNotif ? 'translate-x-5' : 'translate-x-0.5'}`} />
                            </div>
                        </label>
                        <label className="flex items-center justify-between p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                            <div className="flex items-center gap-3">
                                <span className="text-xl">💬</span>
                                <div>
                                    <p className="text-sm font-medium text-slate-700">WhatsApp Notifications</p>
                                    <p className="text-xs text-slate-500">
                                        Critical alerts: deposits, withdrawals, refunds, balance changes
                                        {!(user as any).whatsapp_number && <span className="text-amber-600 ml-1">(Set WhatsApp number above first)</span>}
                                    </p>
                                </div>
                            </div>
                            <div className={`w-10 h-5 rounded-full transition-all cursor-pointer ${waNotif ? 'bg-emerald-500' : 'bg-slate-300'}`}
                                onClick={() => setWaNotif(!waNotif)}>
                                <div className={`w-4 h-4 rounded-full bg-white transition-transform mt-0.5 ${waNotif ? 'translate-x-5' : 'translate-x-0.5'}`} />
                            </div>
                        </label>
                    </div>
                    <button onClick={saveNotifPrefs}
                        className="mt-4 rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Save Preferences
                    </button>
                </div>

                {/* Password */}
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="mb-4 font-semibold text-slate-900">Change Password</h3>
                    <form onSubmit={(e) => { e.preventDefault(); passwordForm.post(route('settings.update')) }} className="space-y-4">
                        {[
                            { label: 'Current Password', field: 'current_password' as const },
                            { label: 'New Password', field: 'password' as const },
                            { label: 'Confirm Password', field: 'password_confirmation' as const },
                        ].map(({ label, field }) => (
                            <div key={field}>
                                <label className="mb-1.5 block text-sm font-medium text-slate-700">{label}</label>
                                <input type="password" value={passwordForm.data[field]} onChange={(e) => passwordForm.setData(field, e.target.value)}
                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-emerald-500" />
                                {passwordForm.errors[field] && <p className="mt-1 text-xs text-red-600">{passwordForm.errors[field]}</p>}
                            </div>
                        ))}
                        <button type="submit" disabled={passwordForm.processing}
                            className="rounded-lg bg-slate-800 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-60">
                            {passwordForm.processing ? 'Updating…' : 'Update Password'}
                        </button>
                    </form>
                </div>

                {/* API Key */}
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="mb-2 font-semibold text-slate-900">API Key</h3>
                    <p className="mb-3 text-sm text-slate-500">
                        Use this key to access the API. For your security the full key is shown
                        <strong> only once</strong>, right after it is generated — copy it then.
                    </p>
                    {newApiKey && (
                        <div className="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                            Copy your new key now — it will not be shown again after you leave this page.
                        </div>
                    )}
                    <div className="flex items-center gap-2">
                        <code className="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 break-all">
                            {displayedKey ?? 'No API key yet — generate one below.'}
                        </code>
                        {newApiKey && (
                            <button onClick={copyApiKey}
                                className="shrink-0 rounded-md border border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                                {copied ? 'Copied!' : 'Copy'}
                            </button>
                        )}
                    </div>
                    <button onClick={regenerateKey}
                        className="mt-3 text-xs text-red-600 underline hover:text-red-800">
                        {apiKeyLast4 || newApiKey ? 'Regenerate key' : 'Generate key'}
                    </button>
                </div>
            </div>
            
            <ConfirmModal
                open={showKeyConfirm}
                title="Regenerate API Key"
                message="This will invalidate your current API key immediately. Any integrations using the old key will stop working."
                confirmLabel="Regenerate"
                danger
                onConfirm={confirmRegenerateKey}
                onCancel={() => setShowKeyConfirm(false)}
            />
        </AuthenticatedLayout>
    )
}

