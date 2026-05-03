import AdminLayout from '@/Layouts/AdminLayout'
import ConfirmModal from '@/Components/ConfirmModal'
import { PageProps } from '@/types'
import { Head, router } from '@inertiajs/react'
import { useState } from 'react'

interface Template {
    name: string
    status: string
    language: string
    category: string
    id: string
}

interface WhatsAppTemplatesProps extends PageProps {
    remoteTemplates: Template[]
    localConfig: Record<string, any>
    error: string | null
    provider: string
}

export default function WhatsAppTemplates({ auth, remoteTemplates, localConfig, error, provider }: WhatsAppTemplatesProps) {
    const [syncing, setSyncing] = useState(false)
    const [pendingDelete, setPendingDelete] = useState<string | null>(null)

    const handleSync = () => {
        setSyncing(true)
        router.post(route('admin.whatsapp.sync'), {}, {
            onFinish: () => setSyncing(false),
            preserveScroll: true
        })
    }

    const handleDelete = (name: string) => {
        setPendingDelete(name)
    }

    const localKeys = Object.keys(localConfig)

    return (
        <AdminLayout header={<h2 className="text-xl font-bold text-gray-900">WhatsApp Templates</h2>}>
            <Head title="WhatsApp Templates" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
                <div className="sm:flex sm:items-center sm:justify-between bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 tracking-tight">WhatsApp Templates</h1>
                        <p className="mt-2 text-sm text-gray-500">
                            Manage message templates synced with Meta Business Account. 
                            Provider: <span className="font-semibold capitalize text-brand-green">{provider}</span>
                        </p>
                    </div>
                    {provider === 'meta' && (
                        <div className="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                            <button
                                onClick={handleSync}
                                disabled={syncing}
                                className="inline-flex items-center justify-center rounded-lg bg-brand-green px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-green/90 focus:outline-none disabled:opacity-50 transition-colors"
                            >
                                {syncing ? 'Syncing...' : 'Sync Templates with Meta'}
                            </button>
                        </div>
                    )}
                </div>

                {error && (
                    <div className="rounded-xl bg-red-50 p-4 border border-red-200 shadow-sm">
                        <div className="flex">
                            <div className="ml-3">
                                <h3 className="text-sm font-bold text-red-800">Error fetching templates</h3>
                                <div className="mt-2 text-sm text-red-700">
                                    <p>{error}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm text-left">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr className="text-gray-500 font-medium text-xs uppercase tracking-wider">
                                    <th scope="col" className="py-3 px-5">Template Name</th>
                                    <th scope="col" className="py-3 px-5">Status (Meta)</th>
                                    <th scope="col" className="py-3 px-5">Category</th>
                                    <th scope="col" className="py-3 px-5">Config Match</th>
                                    <th scope="col" className="py-3 px-5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {remoteTemplates.map((template) => {
                                    const isLocal = localKeys.includes(template.name)
                                    return (
                                        <tr key={template.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="whitespace-nowrap py-3 px-5 font-medium text-gray-900">
                                                {template.name}
                                            </td>
                                            <td className="whitespace-nowrap py-3 px-5">
                                                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wider uppercase border ${
                                                    template.status === 'APPROVED' ? 'bg-brand-green/10 text-brand-green border-brand-green/20' :
                                                    template.status === 'PENDING' ? 'bg-amber-100 text-amber-800 border-amber-200' :
                                                    'bg-red-100 text-red-800 border-red-200'
                                                }`}>
                                                    {template.status}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap py-3 px-5 text-gray-500">
                                                {template.category}
                                            </td>
                                            <td className="whitespace-nowrap py-3 px-5">
                                                {isLocal ? (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-green/20 bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green uppercase tracking-wider">✓ In Local Config</span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600 uppercase tracking-wider">✗ Missing locally</span>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap py-3 px-5 text-right font-medium">
                                                {provider === 'meta' && (
                                                    <button
                                                        onClick={() => handleDelete(template.name)}
                                                        className="text-red-600 hover:text-red-800 transition-colors"
                                                    >
                                                        Delete<span className="sr-only">, {template.name}</span>
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    )
                                })}
                                
                                {/* Show local templates that don't exist in Meta yet */}
                                {localKeys.filter(k => !remoteTemplates.find(rt => rt.name === k)).map((key) => (
                                    <tr key={`local-${key}`} className="bg-gray-50 border-l-4 border-l-amber-400">
                                        <td className="whitespace-nowrap py-3 px-5 font-medium text-gray-900">
                                            {key}
                                        </td>
                                        <td className="whitespace-nowrap py-3 px-5">
                                            <span className="inline-flex rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-semibold tracking-wider text-gray-600 uppercase border border-gray-300">
                                                NOT SYNCED
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap py-3 px-5 text-gray-500">
                                            {localConfig[key].category}
                                        </td>
                                        <td className="whitespace-nowrap py-3 px-5">
                                            <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-green/20 bg-brand-green/10 px-2.5 py-1 text-xs font-semibold text-brand-green uppercase tracking-wider">✓ In Local Config</span>
                                        </td>
                                        <td className="whitespace-nowrap py-3 px-5 text-right font-medium">
                                            <span className="text-gray-400 text-xs italic">Sync to create</span>
                                        </td>
                                    </tr>
                                ))}
                                {remoteTemplates.length === 0 && localKeys.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="py-8 text-center text-gray-500">
                                            No templates found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            {pendingDelete && (
                <ConfirmModal
                    open
                    title="Delete WhatsApp Template"
                    message={`Are you sure you want to delete template '${pendingDelete}' from Meta? This cannot be undone.`}
                    confirmLabel="Delete"
                    danger
                    onConfirm={() => { router.delete(route('admin.whatsapp.delete', pendingDelete)); setPendingDelete(null); }}
                    onCancel={() => setPendingDelete(null)}
                />
            )}
        </AdminLayout>
    )
}
