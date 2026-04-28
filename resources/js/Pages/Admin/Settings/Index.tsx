import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaCog, 
    FaEnvelope, 
    FaWhatsapp, 
    FaGlobe, 
    FaSave, 
    FaSync, 
    FaPlus, 
    FaTrash, 
    FaEdit, 
    FaShieldAlt,
    FaTerminal,
    FaServer,
    FaUsers,
} from 'react-icons/fa';

export default function SettingsIndex({ settings, providers, referralDefaults }: any) {
    const [activeTab, setActiveTab] = useState('general');
    
    // Flatten settings for useForm
    const getSetting = (group: string, key: string) => {
        return settings[group]?.find((s: any) => s.key === key)?.value || '';
    };

    const getReferralSetting = (key: string, fallback: string) => {
        return getSetting('referral', key) || fallback;
    };

    const { data, setData, post, processing } = useForm({
        settings: [
            // General
            { key: 'name', value: getSetting('app', 'name'), group: 'app' },
            { key: 'logo_url', value: getSetting('app', 'logo_url'), group: 'app' },
            // Mail
            { key: 'host', value: getSetting('mail', 'host'), group: 'mail' },
            { key: 'port', value: getSetting('mail', 'port'), group: 'mail' },
            { key: 'username', value: getSetting('mail', 'username'), group: 'mail' },
            { key: 'password', value: getSetting('mail', 'password'), group: 'mail' },
            { key: 'encryption', value: getSetting('mail', 'encryption'), group: 'mail' },
            { key: 'from_address', value: getSetting('mail', 'from_address'), group: 'mail' },
            { key: 'from_name', value: getSetting('mail', 'from_name'), group: 'mail' },
            // WhatsApp
            { key: 'provider', value: getSetting('whatsapp', 'provider'), group: 'whatsapp' },
            { key: 'api_token', value: getSetting('whatsapp', 'api_token'), group: 'whatsapp' },
            { key: 'phone_number_id', value: getSetting('whatsapp', 'phone_number_id'), group: 'whatsapp' },
            { key: 'waba_id', value: getSetting('whatsapp', 'waba_id'), group: 'whatsapp' },
            // Referral
            { key: 'first_deposit_reward', value: getReferralSetting('first_deposit_reward', referralDefaults?.first_deposit_reward || '1.00'), group: 'referral' },
            { key: 'order_commission_percent', value: getReferralSetting('order_commission_percent', referralDefaults?.order_commission_percent || '2.00'), group: 'referral' },
            { key: 'order_commission_min_total', value: getReferralSetting('order_commission_min_total', referralDefaults?.order_commission_min_total || '20.00'), group: 'referral' },
        ]
    });

    const updateSetting = (key: string, value: string) => {
        const index = data.settings.findIndex(s => s.key === key);
        if (index !== -1) {
            const newSettings = [...data.settings];
            newSettings[index].value = value;
            setData('settings', newSettings);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.settings.update'));
    };

    const tabs = [
        { id: 'general', label: 'Platform Settings', icon: FaCog },
        { id: 'mail', label: 'SMTP Infrastructure', icon: FaEnvelope },
        { id: 'whatsapp', label: 'WhatsApp Terminal', icon: FaWhatsapp },
        { id: 'referral', label: 'Referral Program', icon: FaUsers },
        { id: 'providers', label: 'API Supply Chain', icon: FaServer },
    ];

    return (
        <AdminLayout>
            <Head title="System Configuration" />

            <div className="p-8 max-w-6xl mx-auto space-y-10">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-4xl font-black text-zinc-900 tracking-tight">System Control Panel</h1>
                        <p className="text-zinc-500 font-bold uppercase tracking-widest text-[10px] mt-2">Manage infrastructure & external integrations</p>
                    </div>
                    {activeTab !== 'providers' && (
                        <button 
                            onClick={submit}
                            disabled={processing}
                            className="flex items-center gap-3 px-10 py-4 rounded-2xl bg-zinc-900 text-white text-sm font-black uppercase tracking-widest shadow-2xl hover:bg-zinc-800 transition-all active:scale-95 disabled:opacity-50"
                        >
                            <FaSave className="text-brand-green" /> Deploy Changes
                        </button>
                    )}
                </div>

                <div className="flex gap-4 p-2 bg-zinc-100 rounded-[2rem] border border-zinc-200 shadow-inner">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`flex-1 flex items-center justify-center gap-3 py-4 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest transition-all ${activeTab === tab.id ? 'bg-white text-zinc-900 shadow-xl shadow-zinc-200/50' : 'text-zinc-500 hover:text-zinc-900'}`}
                        >
                            <tab.icon className={activeTab === tab.id ? 'text-brand-green' : 'opacity-30'} /> {tab.label}
                        </button>
                    ))}
                </div>

                <AnimatePresence mode="wait">
                    <motion.div
                        key={activeTab}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        className="bg-white rounded-[3rem] border border-zinc-200 shadow-2xl shadow-zinc-200/40 overflow-hidden"
                    >
                        <div className="p-12">
                            {activeTab === 'general' && (
                                <div className="space-y-8 max-w-2xl">
                                    <div className="grid grid-cols-1 gap-8">
                                        <SettingInput label="Application Name" value={data.settings.find(s => s.key === 'name')?.value} onChange={(v: string) => updateSetting('name', v)} placeholder="ZimSocials Admin" />
                                        <SettingInput label="Logo URL" value={data.settings.find(s => s.key === 'logo_url')?.value} onChange={(v: string) => updateSetting('logo_url', v)} placeholder="https://..." />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'mail' && (
                                <div className="space-y-10">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <SettingInput label="SMTP Host" value={data.settings.find(s => s.key === 'host')?.value} onChange={(v: string) => updateSetting('host', v)} placeholder="smtp.gmail.com" />
                                        <SettingInput label="SMTP Port" value={data.settings.find(s => s.key === 'port')?.value} onChange={(v: string) => updateSetting('port', v)} placeholder="587" />
                                        <SettingInput label="SMTP Username" value={data.settings.find(s => s.key === 'username')?.value} onChange={(v: string) => updateSetting('username', v)} placeholder="user@gmail.com" />
                                        <SettingInput label="SMTP Password" value={data.settings.find(s => s.key === 'password')?.value} onChange={(v: string) => updateSetting('password', v)} placeholder="••••••••" type="password" />
                                        <SettingInput label="Encryption" value={data.settings.find(s => s.key === 'encryption')?.value} onChange={(v: string) => updateSetting('encryption', v)} placeholder="tls" />
                                        <div />
                                        <SettingInput label="From Address" value={data.settings.find(s => s.key === 'from_address')?.value} onChange={(v: string) => updateSetting('from_address', v)} placeholder="noreply@zimsocials.co.zw" />
                                        <SettingInput label="From Name" value={data.settings.find(s => s.key === 'from_name')?.value} onChange={(v: string) => updateSetting('from_name', v)} placeholder="ZimSocials Admin" />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'whatsapp' && (
                                <div className="space-y-10">
                                    <div className="p-8 rounded-[2rem] bg-emerald-50 border border-emerald-100 mb-8">
                                        <h3 className="text-emerald-800 font-black text-sm uppercase tracking-widest flex items-center gap-2 mb-2">
                                            <FaShieldAlt /> API Node Security
                                        </h3>
                                        <p className="text-emerald-600/70 text-xs font-medium leading-relaxed">
                                            Configure your Meta Business API or 3rd-party provider credentials here. This terminal handles all OTP and system notifications.
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <SettingInput label="Provider" value={data.settings.find(s => s.key === 'provider')?.value} onChange={(v: string) => updateSetting('provider', v)} placeholder="meta" />
                                        <SettingInput label="API Token" value={data.settings.find(s => s.key === 'api_token')?.value} onChange={(v: string) => updateSetting('api_token', v)} placeholder="EAAG..." type="password" />
                                        <SettingInput label="Phone Number ID" value={data.settings.find(s => s.key === 'phone_number_id')?.value} onChange={(v: string) => updateSetting('phone_number_id', v)} placeholder="102..." />
                                        <SettingInput label="WABA ID" value={data.settings.find(s => s.key === 'waba_id')?.value} onChange={(v: string) => updateSetting('waba_id', v)} placeholder="105..." />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'referral' && (
                                <div className="space-y-10 max-w-3xl">
                                    <div className="p-8 rounded-[2rem] bg-blue-50 border border-blue-100 mb-8">
                                        <h3 className="text-blue-800 font-black text-sm uppercase tracking-widest flex items-center gap-2 mb-2">
                                            <FaUsers /> Referral Engine
                                        </h3>
                                        <p className="text-blue-600/80 text-xs font-medium leading-relaxed">
                                            Configure referral rewards. Order commission applies from the referred user's second order, and only when order total is above minimum.
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <SettingInput
                                            label="First Deposit Reward (USD)"
                                            value={data.settings.find(s => s.key === 'first_deposit_reward')?.value}
                                            onChange={(v: string) => updateSetting('first_deposit_reward', v)}
                                            placeholder="1.00"
                                            type="number"
                                        />
                                        <SettingInput
                                            label="Order Commission (%)"
                                            value={data.settings.find(s => s.key === 'order_commission_percent')?.value}
                                            onChange={(v: string) => updateSetting('order_commission_percent', v)}
                                            placeholder="2.00"
                                            type="number"
                                        />
                                        <SettingInput
                                            label="Commission Minimum Order Total (USD)"
                                            value={data.settings.find(s => s.key === 'order_commission_min_total')?.value}
                                            onChange={(v: string) => updateSetting('order_commission_min_total', v)}
                                            placeholder="20.00"
                                            type="number"
                                        />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'providers' && (
                                <div className="space-y-8">
                                    <div className="flex items-center justify-between mb-8">
                                        <h3 className="text-lg font-black text-zinc-900 tracking-tight">Upstream API Providers</h3>
                                        <Link href={route('admin.upstream-providers.index')} className="flex items-center gap-2 px-6 py-3 rounded-xl bg-zinc-100 text-zinc-700 text-[10px] font-black uppercase tracking-widest hover:bg-zinc-200 transition-all">
                                            Manage Providers <FaGlobe className="text-brand-green" />
                                        </Link>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        {providers.length > 0 ? providers.map((p: any) => (
                                            <div key={p.id} className="p-8 rounded-[2rem] border border-zinc-100 bg-zinc-50 flex items-center justify-between group hover:bg-white hover:border-brand-green/20 transition-all">
                                                <div className="flex items-center gap-5">
                                                    <div className={`h-12 w-12 rounded-xl flex items-center justify-center text-xl shadow-sm ${p.is_active ? 'bg-emerald-100 text-emerald-600' : 'bg-zinc-200 text-zinc-400'}`}>
                                                        <FaServer />
                                                    </div>
                                                    <div>
                                                        <p className="font-black text-zinc-900">{p.name}</p>
                                                        <p className="text-[10px] font-black text-zinc-400 uppercase tracking-widest truncate max-w-[150px]">{p.url}</p>
                                                    </div>
                                                </div>
                                                <div className={`px-4 py-1.5 rounded-full text-[8px] font-black uppercase tracking-widest border ${p.is_active ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-600 border-red-100'}`}>
                                                    {p.is_active ? 'Active' : 'Offline'}
                                                </div>
                                            </div>
                                        )) : (
                                            <div className="col-span-2 p-12 text-center bg-zinc-50 rounded-[2rem] border-2 border-dashed border-zinc-200">
                                                <p className="text-zinc-400 font-bold text-sm">No upstream providers have been configured yet.</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </motion.div>
                </AnimatePresence>
            </div>
        </AdminLayout>
    );
}

function SettingInput({ label, value, onChange, placeholder, type = 'text' }: any) {
    return (
        <div className="space-y-2">
            <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-4 block">{label}</label>
            <input 
                type={type}
                value={value || ''}
                onChange={e => onChange(e.target.value)}
                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-2xl px-6 py-4 font-bold text-zinc-900 focus:outline-none focus:border-brand-green transition-all"
                placeholder={placeholder}
            />
        </div>
    );
}
