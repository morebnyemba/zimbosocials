import AdminLayout from '@/Layouts/AdminLayout'
import ConfirmModal from '@/Components/ConfirmModal'
import { PageProps } from '@/types'
import { Head, router, useForm } from '@inertiajs/react'
import { useMemo, useState } from 'react'

interface RemoteTemplate {
    name: string
    status: string
    language: string
    category: string
    id: string
}

interface LocalTemplate {
    id: number
    name: string
    category: string
    body: string
    params: string[] | null
    header: string | null
    footer: string | null
    is_active: boolean
}

interface WhatsAppTemplatesProps extends PageProps {
    remoteTemplates: RemoteTemplate[]
    localTemplates: LocalTemplate[]
    error: string | null
    provider: string
    language: string
}

const statusChip = (status?: string) => {
    const s = status ?? 'NOT ON META'
    const cls =
        s === 'APPROVED' ? 'bg-brand-green/10 text-brand-green border-brand-green/20' :
        s === 'PENDING' ? 'bg-amber-100 text-amber-800 border-amber-200' :
        s === 'NOT ON META' ? 'bg-gray-100 text-gray-600 border-gray-300' :
        'bg-red-100 text-red-800 border-red-200'

    return <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wider uppercase border ${cls}`}>{s}</span>
}

/** Replace {{n}} with the param label (or a sample) for the live preview. */
const interpolate = (body: string, params: string[]) =>
    body.replace(/\{\{(\d+)\}\}/g, (_, n) => {
        const label = params[Number(n) - 1]
        return label ? `⟨${label}⟩` : `⟨param ${n}⟩`
    })

function TemplateForm({ template, onClose }: { template: LocalTemplate | null; onClose: () => void }) {
    const isEdit = template !== null
    const { data, setData, post, put, processing, errors, transform } = useForm({
        name: template?.name ?? '',
        category: template?.category ?? 'UTILITY',
        body: template?.body ?? '',
        header: template?.header ?? '',
        footer: template?.footer ?? '',
        params: (template?.params ?? []).join(', '),
        is_active: template?.is_active ?? true,
    })

    const paramList = useMemo(
        () => data.params.split(',').map(p => p.trim()).filter(Boolean),
        [data.params]
    )

    // The backend expects params as an ordered array.
    transform(d => ({ ...d, params: d.params.split(',').map((p: string) => p.trim()).filter(Boolean) }))

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose }
        isEdit
            ? put(route('admin.whatsapp.templates.update', template!.id), opts)
            : post(route('admin.whatsapp.templates.store'), opts)
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                <h3 className="text-lg font-bold text-gray-900">{isEdit ? `Edit “${template!.name}”` : 'New template'}</h3>
                <p className="mt-1 text-xs text-gray-500">
                    Changes apply immediately to the plain-text fallback. Template sends use the Meta-approved
                    version — push after editing (delete the old remote copy first) and wait for approval.
                </p>

                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-3">
                        {!isEdit && (
                            <div>
                                <label className="text-xs font-bold uppercase tracking-wider text-gray-500">Name (lowercase_underscores)</label>
                                <input value={data.name} onChange={e => setData('name', e.target.value)}
                                    placeholder="order_delivered"
                                    className="mt-1 w-full rounded-lg border-gray-300 text-sm font-mono" />
                                {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
                            </div>
                        )}
                        <div>
                            <label className="text-xs font-bold uppercase tracking-wider text-gray-500">Category</label>
                            <select value={data.category} onChange={e => setData('category', e.target.value)}
                                className="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                <option value="UTILITY">UTILITY — transactional</option>
                                <option value="MARKETING">MARKETING — promotional</option>
                                <option value="AUTHENTICATION">AUTHENTICATION — OTP</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs font-bold uppercase tracking-wider text-gray-500">Header (optional, 60 chars)</label>
                            <input value={data.header} onChange={e => setData('header', e.target.value)}
                                className="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label className="text-xs font-bold uppercase tracking-wider text-gray-500">Body — use {'{{1}}'}, {'{{2}}'} for variables</label>
                            <textarea value={data.body} onChange={e => setData('body', e.target.value)} rows={7}
                                className="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                            {errors.body && <p className="text-xs text-red-600 mt-1">{errors.body}</p>}
                        </div>
                        <div>
                            <label className="text-xs font-bold uppercase tracking-wider text-gray-500">Param labels (comma-separated, in order)</label>
                            <input value={data.params} onChange={e => setData('params', e.target.value)}
                                placeholder="user_name, amount, date"
                                className="mt-1 w-full rounded-lg border-gray-300 text-sm font-mono" />
                        </div>
                        <div>
                            <label className="text-xs font-bold uppercase tracking-wider text-gray-500">Footer (optional, 60 chars)</label>
                            <input value={data.footer} onChange={e => setData('footer', e.target.value)}
                                className="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300" />
                            Active (used for sending)
                        </label>
                    </div>

                    {/* Live WhatsApp-style preview */}
                    <div>
                        <label className="text-xs font-bold uppercase tracking-wider text-gray-500">Preview</label>
                        <div className="mt-1 rounded-xl bg-[#e5ddd5] p-4 min-h-[16rem]">
                            <div className="max-w-[95%] rounded-lg bg-white p-3 shadow text-sm text-gray-800 whitespace-pre-wrap">
                                {data.header && <p className="font-bold mb-1">{data.header}</p>}
                                <p>{interpolate(data.body || 'Type a body to preview…', paramList)}</p>
                                {data.footer && <p className="mt-2 text-xs text-gray-400">{data.footer}</p>}
                            </div>
                        </div>
                        <p className="mt-2 text-[11px] text-gray-400">⟨angle brackets⟩ show where variables are filled in at send time.</p>
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <button onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button onClick={submit} disabled={processing}
                        className="rounded-lg bg-brand-green px-5 py-2 text-sm font-medium text-white hover:bg-brand-green/90 disabled:opacity-50">
                        {processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create template'}
                    </button>
                </div>
            </div>
        </div>
    )
}

export default function WhatsAppTemplates({ remoteTemplates, localTemplates, error, provider, language }: WhatsAppTemplatesProps) {
    const [syncing, setSyncing] = useState(false)
    const [pendingDelete, setPendingDelete] = useState<string | null>(null)
    const [pendingLocalDelete, setPendingLocalDelete] = useState<LocalTemplate | null>(null)
    const [editing, setEditing] = useState<LocalTemplate | null>(null)
    const [creating, setCreating] = useState(false)

    const remoteByName = useMemo(
        () => Object.fromEntries(remoteTemplates.map(t => [t.name, t])),
        [remoteTemplates]
    )
    const remoteOnly = remoteTemplates.filter(rt => !localTemplates.find(lt => lt.name === rt.name))

    const handleSync = () => {
        setSyncing(true)
        router.post(route('admin.whatsapp.sync'), {}, { onFinish: () => setSyncing(false), preserveScroll: true })
    }

    return (
        <AdminLayout header={<h2 className="text-xl font-bold text-gray-900">WhatsApp Templates</h2>}>
            <Head title="WhatsApp Templates" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
                <div className="sm:flex sm:items-center sm:justify-between bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">WhatsApp Templates</h1>
                        <p className="mt-2 text-sm text-gray-500">
                            Edit templates here, then push to Meta for approval. Provider:{' '}
                            <span className="font-semibold capitalize text-brand-green">{provider}</span> · Language:{' '}
                            <span className="font-semibold uppercase">{language}</span>
                        </p>
                    </div>
                    <div className="mt-4 flex gap-3 sm:ml-16 sm:mt-0 sm:flex-none">
                        <button onClick={() => setCreating(true)}
                            className="inline-flex items-center justify-center rounded-lg border border-brand-green px-5 py-2.5 text-sm font-medium text-brand-green hover:bg-brand-green/5 transition-colors">
                            + New template
                        </button>
                        {provider === 'meta' && (
                            <button onClick={handleSync} disabled={syncing}
                                className="inline-flex items-center justify-center rounded-lg bg-brand-green px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-green/90 disabled:opacity-50 transition-colors">
                                {syncing ? 'Syncing…' : 'Push all missing to Meta'}
                            </button>
                        )}
                    </div>
                </div>

                {error && (
                    <div className="rounded-xl bg-red-50 p-4 border border-red-200 shadow-sm text-sm text-red-700">
                        <span className="font-bold">Couldn't reach Meta:</span> {error}
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-gray-500 font-medium text-xs uppercase tracking-wider">
                                    <th className="py-3 px-5">Template</th>
                                    <th className="py-3 px-5">Meta status</th>
                                    <th className="py-3 px-5">Category</th>
                                    <th className="py-3 px-5">Body</th>
                                    <th className="py-3 px-5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {localTemplates.map(t => {
                                    const remote = remoteByName[t.name]
                                    return (
                                        <tr key={t.id} className={`hover:bg-gray-50 transition-colors ${!t.is_active ? 'opacity-50' : ''}`}>
                                            <td className="py-3 px-5 font-medium text-gray-900 whitespace-nowrap">
                                                {t.name}
                                                {!t.is_active && <span className="ml-2 text-[10px] uppercase text-gray-400">inactive</span>}
                                            </td>
                                            <td className="py-3 px-5 whitespace-nowrap">{statusChip(remote?.status)}</td>
                                            <td className="py-3 px-5 text-gray-500 whitespace-nowrap">{t.category}</td>
                                            <td className="py-3 px-5 text-gray-500 max-w-md">
                                                <span className="line-clamp-2">{t.body}</span>
                                            </td>
                                            <td className="py-3 px-5 text-right font-medium whitespace-nowrap space-x-3">
                                                <button onClick={() => setEditing(t)} className="text-brand-green hover:underline">Edit</button>
                                                {provider === 'meta' && (!remote || ['REJECTED', 'PAUSED'].includes(remote.status)) && (
                                                    <button onClick={() => router.post(route('admin.whatsapp.templates.push', t.id), {}, { preserveScroll: true })}
                                                        className="text-amber-600 hover:underline">
                                                        {remote ? 'Resubmit' : 'Push to Meta'}
                                                    </button>
                                                )}
                                                {provider === 'meta' && remote && (
                                                    <button onClick={() => setPendingDelete(t.name)} className="text-red-500 hover:underline">Delete on Meta</button>
                                                )}
                                                <button onClick={() => setPendingLocalDelete(t)} className="text-red-600 hover:underline">Delete</button>
                                            </td>
                                        </tr>
                                    )
                                })}

                                {remoteOnly.map(rt => (
                                    <tr key={`remote-${rt.id}`} className="bg-amber-50/50">
                                        <td className="py-3 px-5 font-medium text-gray-900 whitespace-nowrap">{rt.name}</td>
                                        <td className="py-3 px-5 whitespace-nowrap">{statusChip(rt.status)}</td>
                                        <td className="py-3 px-5 text-gray-500 whitespace-nowrap">{rt.category}</td>
                                        <td className="py-3 px-5 text-xs italic text-amber-700">On Meta only — not managed here</td>
                                        <td className="py-3 px-5 text-right font-medium whitespace-nowrap">
                                            <button onClick={() => setPendingDelete(rt.name)} className="text-red-600 hover:underline">Delete on Meta</button>
                                        </td>
                                    </tr>
                                ))}

                                {localTemplates.length === 0 && remoteTemplates.length === 0 && (
                                    <tr><td colSpan={5} className="py-8 text-center text-gray-500">No templates yet — run <code>php artisan migrate</code> to seed the defaults.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {(creating || editing) && (
                <TemplateForm template={editing} onClose={() => { setCreating(false); setEditing(null) }} />
            )}

            {pendingDelete && (
                <ConfirmModal
                    open
                    title="Delete template on Meta"
                    message={`Delete '${pendingDelete}' from Meta? Template sends will fall back to plain text until it's re-pushed and approved.`}
                    confirmLabel="Delete on Meta"
                    danger
                    onConfirm={() => { router.delete(route('admin.whatsapp.delete', pendingDelete)); setPendingDelete(null) }}
                    onCancel={() => setPendingDelete(null)}
                />
            )}

            {pendingLocalDelete && (
                <ConfirmModal
                    open
                    title="Delete local template"
                    message={`Delete '${pendingLocalDelete.name}' from this panel? The Meta copy (if any) is untouched, but this app will no longer send it.`}
                    confirmLabel="Delete"
                    danger
                    onConfirm={() => { router.delete(route('admin.whatsapp.templates.destroy-local', pendingLocalDelete.id)); setPendingLocalDelete(null) }}
                    onCancel={() => setPendingLocalDelete(null)}
                />
            )}
        </AdminLayout>
    )
}
