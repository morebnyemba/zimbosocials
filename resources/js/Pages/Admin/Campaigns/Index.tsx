import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';

type Campaign = {
    id: number;
    name: string;
    status: string;
    recipients_total: number;
    sent_email: number;
    sent_whatsapp: number;
    sent_in_app: number;
    error_message?: string | null;
    created_at: string;
    completed_at?: string | null;
    creator?: { name: string; email: string };
};

type Props = {
    campaigns: {
        data: Campaign[];
    };
};

const roleOptions = ['all', 'user', 'marketer', 'reseller', 'admin'];
const accountTypeOptions = ['all', 'individual', 'business', 'marketer'];
const channelOptions = ['email', 'whatsapp', 'in_app'] as const;

export default function CampaignsIndex({ campaigns }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        subject_en: '',
        body_en: '',
        subject_sn: '',
        body_sn: '',
        subject_nd: '',
        body_nd: '',
        channels: ['email', 'in_app'] as string[],
        roles: ['all'] as string[],
        account_types: ['all'] as string[],
    });

    const toggleArray = (field: 'channels' | 'roles' | 'account_types', value: string) => {
        const current = data[field] as string[];
        setData(
            field,
            current.includes(value)
                ? current.filter((item) => item !== value)
                : [...current, value],
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.campaigns.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset('name', 'subject_en', 'body_en', 'subject_sn', 'body_sn', 'subject_nd', 'body_nd');
            },
        });
    };

    return (
        <AdminLayout>
            <Head title={t('campaigns_dashboard')} />

            <div className="space-y-6 py-4">
                <div>
                    <h1 className="text-2xl font-bold text-zinc-900">{t('campaigns_dashboard')}</h1>
                    <p className="text-sm text-zinc-500">{t('campaigns_dashboard_subtitle')}</p>
                </div>

                <form onSubmit={submit} className="grid gap-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">{t('campaign_name')}</label>
                            <input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full rounded-lg border border-zinc-300 px-3 py-2"
                                placeholder={t('campaign_name_placeholder')}
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </div>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">EN Subject</label>
                            <input value={data.subject_en} onChange={(e) => setData('subject_en', e.target.value)} className="w-full rounded-lg border border-zinc-300 px-3 py-2" />
                            {errors.subject_en && <p className="mt-1 text-xs text-red-600">{errors.subject_en}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">SN Subject</label>
                            <input value={data.subject_sn} onChange={(e) => setData('subject_sn', e.target.value)} className="w-full rounded-lg border border-zinc-300 px-3 py-2" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">ND Subject</label>
                            <input value={data.subject_nd} onChange={(e) => setData('subject_nd', e.target.value)} className="w-full rounded-lg border border-zinc-300 px-3 py-2" />
                        </div>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">EN Body</label>
                            <textarea value={data.body_en} onChange={(e) => setData('body_en', e.target.value)} rows={5} className="w-full rounded-lg border border-zinc-300 px-3 py-2" />
                            {errors.body_en && <p className="mt-1 text-xs text-red-600">{errors.body_en}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">SN Body</label>
                            <textarea value={data.body_sn} onChange={(e) => setData('body_sn', e.target.value)} rows={5} className="w-full rounded-lg border border-zinc-300 px-3 py-2" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">ND Body</label>
                            <textarea value={data.body_nd} onChange={(e) => setData('body_nd', e.target.value)} rows={5} className="w-full rounded-lg border border-zinc-300 px-3 py-2" />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{t('channels')}</legend>
                            <div className="space-y-2">
                                {channelOptions.map((channel) => (
                                    <label key={channel} className="flex items-center gap-2 text-sm text-zinc-700">
                                        <input type="checkbox" checked={data.channels.includes(channel)} onChange={() => toggleArray('channels', channel)} />
                                        {channel}
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{t('roles')}</legend>
                            <div className="space-y-2">
                                {roleOptions.map((role) => (
                                    <label key={role} className="flex items-center gap-2 text-sm text-zinc-700">
                                        <input type="checkbox" checked={data.roles.includes(role)} onChange={() => toggleArray('roles', role)} />
                                        {role}
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend className="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{t('account_types')}</legend>
                            <div className="space-y-2">
                                {accountTypeOptions.map((type) => (
                                    <label key={type} className="flex items-center gap-2 text-sm text-zinc-700">
                                        <input type="checkbox" checked={data.account_types.includes(type)} onChange={() => toggleArray('account_types', type)} />
                                        {type}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                    </div>

                    <div>
                        <button disabled={processing} className="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            {processing ? t('sending') : t('queue_broadcast')}
                        </button>
                    </div>
                </form>

                <section className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold text-zinc-900">{t('recent_campaigns')}</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-zinc-200 text-left text-zinc-500">
                                    <th className="py-2 pr-3">{t('name')}</th>
                                    <th className="py-2 pr-3">{t('status')}</th>
                                    <th className="py-2 pr-3">{t('recipients')}</th>
                                    <th className="py-2 pr-3">Email</th>
                                    <th className="py-2 pr-3">WhatsApp</th>
                                    <th className="py-2 pr-3">In-app</th>
                                    <th className="py-2 pr-3">{t('date')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {campaigns.data.map((campaign) => (
                                    <tr key={campaign.id} className="border-b border-zinc-100">
                                        <td className="py-2 pr-3 font-medium text-zinc-900">{campaign.name}</td>
                                        <td className="py-2 pr-3">
                                            <span className="rounded-full bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-700">{campaign.status}</span>
                                        </td>
                                        <td className="py-2 pr-3">{campaign.recipients_total}</td>
                                        <td className="py-2 pr-3">{campaign.sent_email}</td>
                                        <td className="py-2 pr-3">{campaign.sent_whatsapp}</td>
                                        <td className="py-2 pr-3">{campaign.sent_in_app}</td>
                                        <td className="py-2 pr-3 text-zinc-500">{new Date(campaign.created_at).toLocaleString()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}
