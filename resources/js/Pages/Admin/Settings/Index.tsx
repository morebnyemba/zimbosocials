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
    FaDollarSign,
    FaCommentDots,
    FaCoins,
} from 'react-icons/fa';

interface CurrencyRate { code: string; symbol: string; rate: string; }

export default function SettingsIndex({ settings, providers, referralDefaults, monetizerDefaults, currencyRates }: any) {
    const [activeTab, setActiveTab] = useState('general');

    const initialCurrencies: CurrencyRate[] = Object.entries(currencyRates || {}).map(([code, entry]: [string, any]) => ({
        code,
        symbol: entry.symbol,
        rate: String(entry.rate),
    }));
    const [currencies, setCurrencies] = useState<CurrencyRate[]>(initialCurrencies);
    
    // Flatten settings for useForm
    const getSetting = (group: string, key: string) => {
        return settings[group]?.find((s: any) => s.key === key)?.value || '';
    };

    const getReferralSetting = (key: string, fallback: string) => {
        return getSetting('referral', key) || fallback;
    };

    const getMonetizerSetting = (key: string, fallback: string) => {
        return getSetting('monetizer', key) || fallback;
    };

    const { data, setData } = useForm({
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
            { key: 'webhook_verify_token', value: getSetting('whatsapp', 'webhook_verify_token'), group: 'whatsapp' },
            { key: 'app_secret', value: getSetting('whatsapp', 'app_secret'), group: 'whatsapp' },
            { key: 'assistant_enabled', value: getSetting('whatsapp', 'assistant_enabled') || '1', group: 'whatsapp' },
            { key: 'ai_max_services', value: getSetting('whatsapp', 'ai_max_services') || '0', group: 'whatsapp' },
            { key: 'api_key', value: getSetting('gemini', 'api_key'), group: 'gemini' },
            // Tawk
            { key: 'property_id', value: getSetting('tawk', 'property_id'), group: 'tawk' },
            { key: 'widget_id', value: getSetting('tawk', 'widget_id'), group: 'tawk' },
            // Referral
            { key: 'first_deposit_reward', value: getReferralSetting('first_deposit_reward', referralDefaults?.first_deposit_reward || '1.00'), group: 'referral' },
            { key: 'order_commission_percent', value: getReferralSetting('order_commission_percent', referralDefaults?.order_commission_percent || '2.00'), group: 'referral' },
            { key: 'order_commission_min_total', value: getReferralSetting('order_commission_min_total', referralDefaults?.order_commission_min_total || '20.00'), group: 'referral' },
            // Monetizer
            { key: 'monetizer_threshold_usd', value: getMonetizerSetting('monetizer_threshold_usd', monetizerDefaults?.threshold_usd || '100.00'), group: 'monetizer' },
            { key: 'monetizer_lookback_days', value: getMonetizerSetting('monetizer_lookback_days', monetizerDefaults?.lookback_days || '90'), group: 'monetizer' },
            // Security
            { key: 'admin_2fa_enabled', value: getSetting('security', 'admin_2fa_enabled') || '0', group: 'security' },
        ]
    });

    const [mailTest, setMailTest] = useState<{ status: 'idle' | 'sending' | 'ok' | 'error'; message: string }>({ status: 'idle', message: '' });

    const sendTestMail = async () => {
        const val = (key: string) => data.settings.find(s => s.key === key)?.value || '';
        setMailTest({ status: 'sending', message: '' });
        try {
            const res = await fetch(route('admin.settings.test-mail'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
                body: JSON.stringify({
                    host: val('host'),
                    port: Number(val('port')) || 0,
                    username: val('username'),
                    password: val('password'),
                    encryption: val('encryption'),
                    from_address: val('from_address'),
                    from_name: val('from_name'),
                }),
            });
            const json = await res.json();
            if (res.ok && json.ok) {
                setMailTest({ status: 'ok', message: json.message });
            } else {
                setMailTest({ status: 'error', message: json.message ?? Object.values(json.errors ?? {}).flat().join(' ') ?? 'Test failed.' });
            }
        } catch {
            setMailTest({ status: 'error', message: 'Request failed — check your connection and try again.' });
        }
    };

    const updateSetting = (key: string, value: string) => {
        const index = data.settings.findIndex(s => s.key === key);
        if (index !== -1) {
            const newSettings = [...data.settings];
            newSettings[index].value = value;
            setData('settings', newSettings);
        }
    };

    const addCurrency = () => setCurrencies([...currencies, { code: '', symbol: '', rate: '' }]);
    const removeCurrency = (index: number) => setCurrencies(currencies.filter((_, i) => i !== index));
    const updateCurrency = (index: number, field: keyof CurrencyRate, value: string) => {
        const next = [...currencies];
        next[index] = { ...next[index], [field]: field === 'code' ? value.toUpperCase() : value };
        setCurrencies(next);
    };

    const [saving, setSaving] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const ratesMap: Record<string, { symbol: string; rate: number }> = {};
        currencies.forEach(c => {
            if (c.code.trim() && Number(c.rate) > 0) {
                ratesMap[c.code.trim()] = { symbol: c.symbol.trim() || c.code.trim(), rate: Number(c.rate) };
            }
        });

        const settingsWithRates = [
            ...data.settings,
            { key: 'currency_rates', value: JSON.stringify(ratesMap), group: 'currency' },
        ];

        setSaving(true);
        router.post(route('admin.settings.update'), { settings: settingsWithRates }, {
            onFinish: () => setSaving(false),
        });
    };

    const tabs = [
        { id: 'general', label: 'Platform Settings', icon: FaCog },
        { id: 'mail', label: 'SMTP Infrastructure', icon: FaEnvelope },
        { id: 'whatsapp', label: 'WhatsApp Terminal', icon: FaWhatsapp },
        { id: 'referral', label: 'Referral Program', icon: FaUsers },
        { id: 'monetizer', label: 'Creator Monetizer', icon: FaDollarSign },
        { id: 'currency', label: 'Currencies', icon: FaCoins },
        { id: 'tawk', label: 'Live Chat (Tawk.to)', icon: FaCommentDots },
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
                            disabled={saving}
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

                                    <label className={`flex items-start gap-4 p-6 rounded-[1.5rem] border-2 cursor-pointer transition-all ${data.settings.find(s => s.key === 'admin_2fa_enabled')?.value === '1' ? 'border-emerald-300 bg-emerald-50' : 'border-zinc-100 bg-zinc-50'}`}>
                                        <input
                                            type="checkbox"
                                            checked={data.settings.find(s => s.key === 'admin_2fa_enabled')?.value === '1'}
                                            onChange={e => updateSetting('admin_2fa_enabled', e.target.checked ? '1' : '0')}
                                            className="mt-1 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <div>
                                            <p className="text-sm font-black text-zinc-900 flex items-center gap-2">
                                                <FaShieldAlt className="text-emerald-600" /> Admin 2FA (emailed login code)
                                            </p>
                                            <p className="text-xs font-medium text-zinc-500 mt-1 leading-relaxed">
                                                Requires admins to enter a 6-digit code emailed on every login. Only enable after
                                                verifying your SMTP settings with the "Send Test Email" button — if mail is broken,
                                                admins cannot log in (emergency escape hatch: <span className="font-mono">php artisan admin:2fa off</span> via SSH).
                                            </p>
                                        </div>
                                    </label>
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

                                    {/* Test SMTP with the values in the form, before saving */}
                                    <div className="p-8 rounded-[2rem] bg-zinc-50 border border-zinc-100 space-y-4">
                                        <div className="flex flex-wrap items-center justify-between gap-4">
                                            <div>
                                                <h3 className="text-sm font-black text-zinc-900 uppercase tracking-widest">Verify Connection</h3>
                                                <p className="text-xs font-medium text-zinc-500 mt-1 max-w-md">
                                                    Sends a test email to your own address using the values above (as typed — no need to save first).
                                                    Confirm this works before enabling admin 2FA.
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={sendTestMail}
                                                disabled={mailTest.status === 'sending'}
                                                className="flex items-center gap-2 px-8 py-4 rounded-2xl bg-emerald-600 text-white text-xs font-black uppercase tracking-widest shadow-xl hover:bg-emerald-700 transition-all active:scale-95 disabled:opacity-50"
                                            >
                                                <FaEnvelope /> {mailTest.status === 'sending' ? 'Sending…' : 'Send Test Email'}
                                            </button>
                                        </div>
                                        {mailTest.status === 'ok' && (
                                            <p className="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">✅ {mailTest.message}</p>
                                        )}
                                        {mailTest.status === 'error' && (
                                            <p className="text-xs font-bold text-red-700 bg-red-50 border border-red-200 rounded-xl px-4 py-3 break-words">❌ {mailTest.message}</p>
                                        )}
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

                                    <div className="p-8 rounded-[2rem] bg-emerald-50 border border-emerald-100 mb-8">
                                        <h3 className="text-emerald-800 font-black text-sm uppercase tracking-widest flex items-center gap-2 mb-2">
                                            <FaShieldAlt /> Conversational Assistant (Inbound)
                                        </h3>
                                        <p className="text-emerald-600/70 text-xs font-medium leading-relaxed">
                                            Powers the in-chat bot. The verify token and app secret must match your Meta webhook config
                                            (callback URL: <span className="font-mono">/webhooks/whatsapp</span>). The Gemini key turns on AI replies —
                                            without it the bot still works with menus and the knowledge base.
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <SettingInput label="Webhook Verify Token" value={data.settings.find(s => s.key === 'webhook_verify_token')?.value} onChange={(v: string) => updateSetting('webhook_verify_token', v)} placeholder="a random string you choose" />
                                        <SettingInput label="App Secret (X-Hub signature)" value={data.settings.find(s => s.key === 'app_secret')?.value} onChange={(v: string) => updateSetting('app_secret', v)} placeholder="Meta app secret" type="password" />
                                        <SettingInput label="Gemini API Key (AI)" value={data.settings.find(s => s.key === 'api_key')?.value} onChange={(v: string) => updateSetting('api_key', v)} placeholder="AIza..." type="password" />
                                        <SettingInput label="Assistant Enabled (1 / 0)" value={data.settings.find(s => s.key === 'assistant_enabled')?.value} onChange={(v: string) => updateSetting('assistant_enabled', v)} placeholder="1" />
                                        <SettingInput label="AI Max Services (0 = all)" value={data.settings.find(s => s.key === 'ai_max_services')?.value} onChange={(v: string) => updateSetting('ai_max_services', v)} placeholder="0" />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'tawk' && (
                                <div className="space-y-10 max-w-3xl">
                                    <div className="p-8 rounded-[2rem] bg-indigo-50 border border-indigo-100 mb-8">
                                        <h3 className="text-indigo-800 font-black text-sm uppercase tracking-widest flex items-center gap-2 mb-2">
                                            <FaCommentDots /> Tawk.to Live Chat
                                        </h3>
                                        <p className="text-indigo-600/80 text-xs font-medium leading-relaxed">
                                            Enable the live chat widget for customers. You can find these IDs in your Tawk.to dashboard under Administration &gt; Chat Widget &gt; Direct Chat Link.
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-1 gap-8">
                                        <SettingInput
                                            label="Property ID"
                                            value={data.settings.find(s => s.key === 'property_id')?.value}
                                            onChange={(v: string) => updateSetting('property_id', v)}
                                            placeholder="e.g. 64b3a1..."
                                        />
                                        <SettingInput
                                            label="Widget ID"
                                            value={data.settings.find(s => s.key === 'widget_id')?.value}
                                            onChange={(v: string) => updateSetting('widget_id', v)}
                                            placeholder="e.g. 1h5..."
                                        />
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

                            {activeTab === 'monetizer' && (
                                <div className="space-y-10 max-w-3xl">
                                    <div className="p-8 rounded-[2rem] bg-emerald-50 border border-emerald-100 mb-8">
                                        <h3 className="text-emerald-800 font-black text-sm uppercase tracking-widest flex items-center gap-2 mb-2">
                                            <FaDollarSign /> Creator Monetizer
                                        </h3>
                                        <p className="text-emerald-600/80 text-xs font-medium leading-relaxed">
                                            Set the spend/deposit threshold and lookback window required for creators to unlock the free monetizer panel.
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <SettingInput
                                            label="Threshold (USD)"
                                            value={data.settings.find((s: any) => s.key === 'monetizer_threshold_usd')?.value}
                                            onChange={(v: string) => updateSetting('monetizer_threshold_usd', v)}
                                            placeholder="100.00"
                                            type="number"
                                        />
                                        <SettingInput
                                            label="Lookback Window (days)"
                                            value={data.settings.find((s: any) => s.key === 'monetizer_lookback_days')?.value}
                                            onChange={(v: string) => updateSetting('monetizer_lookback_days', v)}
                                            placeholder="90"
                                            type="number"
                                        />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'currency' && (
                                <div className="space-y-8 max-w-3xl">
                                    <div className="p-8 rounded-[2rem] bg-amber-50 border border-amber-100 mb-8">
                                        <h3 className="text-amber-800 font-black text-sm uppercase tracking-widest flex items-center gap-2 mb-2">
                                            <FaCoins /> Display Currencies
                                        </h3>
                                        <p className="text-amber-700/80 text-xs font-medium leading-relaxed">
                                            USD is always the base currency — balances, transactions, and orders are stored and charged in USD only.
                                            Currencies added here just let users choose a display currency (Settings → Profile); amounts are converted
                                            using the rate below (units of that currency per 1 USD) for display purposes only.
                                        </p>
                                    </div>

                                    <div className="space-y-4">
                                        {currencies.length === 0 && (
                                            <p className="text-zinc-400 font-bold text-sm text-center py-8">No display currencies configured — users can only choose USD.</p>
                                        )}
                                        {currencies.map((c, i) => (
                                            <div key={i} className="grid grid-cols-[1fr_1fr_1fr_auto] gap-4 items-end p-6 rounded-[1.5rem] bg-zinc-50 border border-zinc-100">
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-1 block">Code</label>
                                                    <input
                                                        type="text"
                                                        value={c.code}
                                                        onChange={e => updateCurrency(i, 'code', e.target.value)}
                                                        placeholder="ZWL"
                                                        maxLength={3}
                                                        className="w-full bg-white border-2 border-zinc-200 rounded-xl px-4 py-3 font-bold text-zinc-900 uppercase focus:outline-none focus:border-brand-green"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-1 block">Symbol</label>
                                                    <input
                                                        type="text"
                                                        value={c.symbol}
                                                        onChange={e => updateCurrency(i, 'symbol', e.target.value)}
                                                        placeholder="ZWL$"
                                                        className="w-full bg-white border-2 border-zinc-200 rounded-xl px-4 py-3 font-bold text-zinc-900 focus:outline-none focus:border-brand-green"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black text-zinc-400 uppercase tracking-widest ml-1 block">Rate (per $1)</label>
                                                    <input
                                                        type="number"
                                                        step="0.0001"
                                                        value={c.rate}
                                                        onChange={e => updateCurrency(i, 'rate', e.target.value)}
                                                        placeholder="13000"
                                                        className="w-full bg-white border-2 border-zinc-200 rounded-xl px-4 py-3 font-bold text-zinc-900 focus:outline-none focus:border-brand-green"
                                                    />
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removeCurrency(i)}
                                                    className="p-3 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition-all"
                                                >
                                                    <FaTrash />
                                                </button>
                                            </div>
                                        ))}
                                    </div>

                                    <button
                                        type="button"
                                        onClick={addCurrency}
                                        className="flex items-center gap-2 px-6 py-3 rounded-xl bg-zinc-100 text-zinc-700 text-xs font-black uppercase tracking-widest hover:bg-zinc-200 transition-all"
                                    >
                                        <FaPlus /> Add Currency
                                    </button>
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
