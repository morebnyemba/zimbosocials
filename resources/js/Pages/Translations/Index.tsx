import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { PageProps } from '@/types'
import { useTranslation } from '@/lib/i18n'
import { Head, router, useForm } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import { FaSearch, FaLanguage, FaCheck, FaClock, FaTimes, FaPaperPlane } from 'react-icons/fa'

interface StringRow {
    key: string
    source: string
    current: string
    pending: string | null
}
interface Suggestion {
    id: number
    locale: string
    key: string
    value: string
    status: 'pending' | 'approved' | 'rejected'
    review_note: string | null
    created_at: string
}
interface Props extends PageProps {
    locales: string[]
    activeLocale: string
    strings: StringRow[]
    mySuggestions: Suggestion[]
}

const localeName: Record<string, string> = { en: 'english', sn: 'shona', nd: 'ndebele' }
const statusStyle: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
}

function Row({ row, locale, t }: { row: StringRow; locale: string; t: (k: string, r?: any) => string }) {
    const { data, setData, post, processing } = useForm({ locale, key: row.key, value: row.pending ?? row.current })
    const dirty = data.value.trim() !== (row.pending ?? row.current).trim() && data.value.trim().length > 0

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('translations.store'), { preserveScroll: true, preserveState: true })
    }

    return (
        <form onSubmit={submit} className="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div className="mb-2 flex items-center justify-between gap-2">
                <span className="font-mono text-[11px] text-zinc-400">{row.key}</span>
                {row.pending && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                        <FaClock className="text-[8px]" /> {t('in_review')}
                    </span>
                )}
            </div>
            <p className="mb-3 text-xs text-zinc-500">
                <span className="font-bold uppercase tracking-wider text-zinc-400">{t('english_reference')}:</span> {row.source}
            </p>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                <label className="flex-1">
                    <span className="mb-1 block text-[10px] font-black uppercase tracking-widest text-zinc-400">{t('your_suggestion')}</span>
                    <textarea
                        value={data.value}
                        onChange={(e) => setData('value', e.target.value)}
                        rows={1}
                        className="w-full resize-y rounded-xl border-2 border-zinc-100 bg-zinc-50 px-3 py-2 text-sm font-medium text-zinc-900 transition-all focus:border-brand-green focus:bg-white focus:outline-none"
                    />
                </label>
                <button
                    type="submit"
                    disabled={!dirty || processing}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-green px-4 py-2.5 text-sm font-bold text-white transition-all hover:bg-brand-green/90 active:scale-[0.98] disabled:opacity-40"
                >
                    <FaPaperPlane className="text-xs" /> {t('submit_suggestion')}
                </button>
            </div>
        </form>
    )
}

export default function TranslationsIndex({ locales, activeLocale, strings, mySuggestions }: Props) {
    const { t } = useTranslation()
    const [search, setSearch] = useState('')

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase()
        if (!q) return strings
        return strings.filter(
            (s) => s.key.toLowerCase().includes(q) || s.source.toLowerCase().includes(q) || s.current.toLowerCase().includes(q),
        )
    }, [search, strings])

    function switchLocale(loc: string) {
        router.get(route('translations.index'), { locale: loc }, { preserveScroll: true, preserveState: false })
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-black text-zinc-900">{t('translations_title')}</h2>}>
            <Head title={t('translations_title')} />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                {/* Intro */}
                <div className="flex items-start gap-4 rounded-3xl bg-zinc-950 p-6 text-white">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-emerald-400">
                        <FaLanguage className="text-2xl" />
                    </div>
                    <div>
                        <h1 className="text-lg font-black">{t('translations_title')}</h1>
                        <p className="mt-1 text-sm text-zinc-300">{t('translations_subtitle')}</p>
                    </div>
                </div>

                {/* Locale tabs */}
                <div className="flex flex-wrap gap-2">
                    {locales.map((loc) => (
                        <button
                            key={loc}
                            onClick={() => switchLocale(loc)}
                            className={`rounded-full px-4 py-2 text-sm font-bold transition-all ${
                                loc === activeLocale ? 'bg-brand-green text-white' : 'border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50'
                            }`}
                        >
                            {t(localeName[loc] ?? loc)}
                        </button>
                    ))}
                </div>

                {/* My suggestions */}
                {mySuggestions.length > 0 && (
                    <details className="rounded-2xl border border-zinc-200 bg-white p-4">
                        <summary className="cursor-pointer text-sm font-black text-zinc-900">{t('my_suggestions')} ({mySuggestions.length})</summary>
                        <ul className="mt-3 space-y-2">
                            {mySuggestions.map((s) => (
                                <li key={s.id} className="flex items-center justify-between gap-3 border-t border-zinc-50 pt-2 text-xs">
                                    <span className="min-w-0 flex-1 truncate">
                                        <span className="font-mono text-zinc-400">{s.key}</span> → <span className="text-zinc-700">{s.value}</span>
                                    </span>
                                    <span className={`shrink-0 rounded-full px-2 py-0.5 font-bold ${statusStyle[s.status]}`}>{t(`status_${s.status}`)}</span>
                                </li>
                            ))}
                        </ul>
                    </details>
                )}

                {/* Search */}
                <div className="relative">
                    <FaSearch className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-zinc-400" />
                    <input
                        type="text"
                        placeholder={t('search_strings')}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-2xl border-2 border-zinc-100 bg-zinc-50 py-3 pl-11 pr-4 text-sm font-medium text-zinc-900 transition-all focus:border-brand-green focus:bg-white focus:outline-none"
                    />
                </div>

                {/* String list */}
                {filtered.length === 0 ? (
                    <p className="rounded-2xl border-2 border-dashed border-zinc-200 bg-white py-16 text-center text-sm font-medium text-zinc-400">
                        {t('no_strings_match')}
                    </p>
                ) : (
                    <div className="space-y-3">
                        {filtered.map((row) => (
                            <Row key={row.key} row={row} locale={activeLocale} t={t} />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    )
}
