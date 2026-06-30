import AdminLayout from '@/Layouts/AdminLayout'
import { useTranslation } from '@/lib/i18n'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { useState } from 'react'
import { FaCheck, FaTimes, FaLanguage } from 'react-icons/fa'

interface Suggestion {
    id: number
    locale: string
    key: string
    value: string
    original_value: string | null
    created_at: string
    user?: { id: number; name: string; email: string }
}
interface Paginated<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
}
interface Props {
    pending: Paginated<Suggestion>
    pendingCount: number
    overridesCount: number
}

const localeName: Record<string, string> = { en: 'english', sn: 'shona', nd: 'ndebele' }

function ReviewCard({ s, t }: { s: Suggestion; t: (k: string, r?: any) => string }) {
    const [rejecting, setRejecting] = useState(false)
    const rejectForm = useForm({ review_note: '' })

    function approve() {
        router.post(route('admin.translations.approve', s.id), {}, { preserveScroll: true })
    }
    function reject(e: React.FormEvent) {
        e.preventDefault()
        rejectForm.post(route('admin.translations.reject', s.id), { preserveScroll: true })
    }

    return (
        <div className="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <span className="font-mono text-[11px] text-zinc-400">{s.key}</span>
                <span className="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-600">
                    <FaLanguage /> {t(localeName[s.locale] ?? s.locale)}
                </span>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-xl bg-zinc-50 p-3">
                    <p className="mb-1 text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('current_value')}</p>
                    <p className="text-sm text-zinc-600">{s.original_value ?? '—'}</p>
                </div>
                <div className="rounded-xl bg-emerald-50 p-3">
                    <p className="mb-1 text-[10px] font-black uppercase tracking-widest text-emerald-600">{t('proposed')}</p>
                    <p className="text-sm font-medium text-zinc-900">{s.value}</p>
                </div>
            </div>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                <span className="text-xs text-zinc-400">
                    {t('suggested_by')} <span className="font-semibold text-zinc-600">{s.user?.name ?? '—'}</span>
                </span>
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setRejecting((v) => !v)}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 px-3 py-2 text-xs font-bold text-zinc-600 transition-colors hover:bg-zinc-50"
                    >
                        <FaTimes className="text-[10px]" /> {t('reject')}
                    </button>
                    <button
                        onClick={approve}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-brand-green px-4 py-2 text-xs font-bold text-white transition-all hover:bg-brand-green/90 active:scale-[0.98]"
                    >
                        <FaCheck className="text-[10px]" /> {t('approve')}
                    </button>
                </div>
            </div>

            {rejecting && (
                <form onSubmit={reject} className="mt-3 flex gap-2 border-t border-zinc-100 pt-3">
                    <input
                        type="text"
                        value={rejectForm.data.review_note}
                        onChange={(e) => rejectForm.setData('review_note', e.target.value)}
                        placeholder={t('reject_reason_placeholder')}
                        className="flex-1 rounded-xl border-2 border-zinc-100 bg-zinc-50 px-3 py-2 text-sm focus:border-red-400 focus:bg-white focus:outline-none"
                    />
                    <button type="submit" disabled={rejectForm.processing} className="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-50">
                        {t('reject')}
                    </button>
                </form>
            )}
        </div>
    )
}

export default function AdminTranslationsIndex({ pending, pendingCount, overridesCount }: Props) {
    const { t } = useTranslation()

    return (
        <AdminLayout>
            <Head title={t('translation_review')} />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-black text-zinc-900">{t('translation_review')}</h1>
                    <p className="mt-1 text-sm text-zinc-500">{t('translation_review_subtitle')}</p>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-2xl border border-zinc-200 bg-white p-4">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('pending_suggestions')}</p>
                        <p className="mt-1 text-3xl font-black text-amber-600">{pendingCount}</p>
                    </div>
                    <div className="rounded-2xl border border-zinc-200 bg-white p-4">
                        <p className="text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('active_overrides')}</p>
                        <p className="mt-1 text-3xl font-black text-emerald-600">{overridesCount}</p>
                    </div>
                </div>

                {pending.data.length === 0 ? (
                    <p className="rounded-2xl border-2 border-dashed border-zinc-200 bg-white py-16 text-center text-sm font-medium text-zinc-400">
                        {t('no_pending_suggestions')}
                    </p>
                ) : (
                    <div className="space-y-3">
                        {pending.data.map((s) => (
                            <ReviewCard key={s.id} s={s} t={t} />
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {pending.links.length > 3 && (
                    <div className="flex flex-wrap justify-center gap-1">
                        {pending.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-lg px-3 py-1.5 text-sm ${
                                    link.active ? 'bg-brand-green text-white' : link.url ? 'bg-white text-zinc-600 hover:bg-zinc-50' : 'text-zinc-300'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    )
}
