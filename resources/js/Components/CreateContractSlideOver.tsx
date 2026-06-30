import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
    FaTimes, 
    FaInstagram, 
    FaYoutube, 
    FaTiktok, 
    FaFacebook, 
    FaTwitter, 
    FaGlobe, 
    FaRocket,
    FaMoneyBillWave,
    FaUsers,
    FaCalendarAlt
} from 'react-icons/fa';

interface CreateContractSlideOverProps {
    isOpen: boolean;
    onClose: () => void;
    t: (key: string) => string;
}

const platforms = [
    { id: 'instagram', name: 'Instagram', icon: FaInstagram, color: 'text-pink-500', bg: 'bg-pink-50', border: 'border-pink-200' },
    { id: 'youtube', name: 'YouTube', icon: FaYoutube, color: 'text-red-500', bg: 'bg-red-50', border: 'border-red-200' },
    { id: 'tiktok', name: 'TikTok', icon: FaTiktok, color: 'text-black', bg: 'bg-zinc-100', border: 'border-zinc-300' },
    { id: 'x', name: 'X (Twitter)', icon: FaTwitter, color: 'text-blue-400', bg: 'bg-blue-50', border: 'border-blue-200' },
    { id: 'facebook', name: 'Facebook', icon: FaFacebook, color: 'text-blue-600', bg: 'bg-blue-50', border: 'border-blue-200' },
    { id: 'custom', name: 'Custom Platform', icon: FaGlobe, color: 'text-emerald-500', bg: 'bg-emerald-50', border: 'border-emerald-200' },
];

export default function CreateContractSlideOver({ isOpen, onClose, t }: CreateContractSlideOverProps) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        platform: '',
        description: '',
        budget: '',
        slots: '1',
        deadline_at: '',
    });

    const [customPlatform, setCustomPlatform] = useState('');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        transform((currentData) => ({
            ...currentData,
            platform: currentData.platform === 'custom' ? customPlatform : currentData.platform
        }));
        
        post(route('contracts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setCustomPlatform('');
                onClose();
            },
        });
    };

    return (
        <AnimatePresence>
            {isOpen && (
                <>
                    {/* Backdrop */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={onClose}
                        className="fixed inset-0 z-40 bg-zinc-950/40 backdrop-blur-sm"
                    />

                    {/* Slide-over Panel */}
                    <motion.div
                        initial={{ x: '100%' }}
                        animate={{ x: 0 }}
                        exit={{ x: '100%' }}
                        transition={{ type: 'spring', bounce: 0, duration: 0.4 }}
                        className="fixed inset-y-0 right-0 z-50 w-full max-w-2xl bg-white shadow-2xl overflow-y-auto border-l border-zinc-200"
                    >
                        <form onSubmit={handleSubmit} className="flex flex-col min-h-full">
                            {/* Header */}
                            <div className="px-8 py-10 bg-zinc-950 text-white relative overflow-hidden shrink-0">
                                <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-emerald-500/20 to-amber-500/20 rounded-full blur-[80px]" />
                                
                                <div className="flex items-start justify-between relative z-10">
                                    <div>
                                        <div className="flex items-center gap-3 mb-4">
                                            <div className="h-10 w-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/20 text-emerald-400">
                                                <FaRocket className="text-xl" />
                                            </div>
                                            <span className="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/10">Contract Terminal</span>
                                        </div>
                                        <h2 className="text-4xl font-black tracking-tight mb-2">Deploy Mission</h2>
                                        <p className="text-zinc-400 text-sm font-medium max-w-md leading-relaxed">
                                            Draft a highly specific mission brief to attract the best talent on the platform. Escrow funding is required.
                                        </p>
                                    </div>
                                    <button 
                                        type="button" 
                                        onClick={onClose}
                                        className="p-3 bg-white/10 hover:bg-white/20 rounded-xl transition-colors"
                                    >
                                        <FaTimes />
                                    </button>
                                </div>
                            </div>

                            {/* Body */}
                            <div className="p-8 space-y-10 flex-1">
                                {/* Section 1: Mission Details */}
                                <section>
                                    <h3 className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                                        <span className="h-px w-8 bg-zinc-200" /> Mission Details
                                    </h3>
                                    
                                    <div className="space-y-6">
                                        <div>
                                            <label className="block text-xs font-black text-zinc-900 uppercase tracking-widest mb-2">Mission Title</label>
                                            <input 
                                                type="text" 
                                                value={data.title}
                                                onChange={e => setData('title', e.target.value)}
                                                placeholder="e.g. Create a 30s TikTok reviewing our new product"
                                                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-[1rem] px-5 py-4 font-bold text-zinc-900 focus:outline-none focus:border-emerald-500 focus:bg-white transition-all"
                                                required
                                            />
                                            {errors.title && <p className="text-red-500 text-xs mt-2 font-bold">{errors.title}</p>}
                                        </div>

                                        <div>
                                            <label className="block text-xs font-black text-zinc-900 uppercase tracking-widest mb-4">Target Platform</label>
                                            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                                                {platforms.map(p => {
                                                    const Icon = p.icon;
                                                    const isSelected = data.platform === p.id;
                                                    return (
                                                        <button
                                                            key={p.id}
                                                            type="button"
                                                            onClick={() => setData('platform', p.id)}
                                                            className={`flex flex-col items-center gap-3 p-4 rounded-[1.2rem] border-2 transition-all ${
                                                                isSelected 
                                                                    ? `${p.border} ${p.bg} shadow-md` 
                                                                    : 'border-zinc-100 bg-zinc-50 hover:border-zinc-200'
                                                            }`}
                                                        >
                                                            <Icon className={`text-3xl ${isSelected ? p.color : 'text-zinc-400'}`} />
                                                            <span className={`text-[10px] font-black uppercase tracking-widest ${isSelected ? 'text-zinc-900' : 'text-zinc-500'}`}>
                                                                {p.name}
                                                            </span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                            {data.platform === 'custom' && (
                                                <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} className="mt-4">
                                                    <input 
                                                        type="text" 
                                                        value={customPlatform}
                                                        onChange={e => setCustomPlatform(e.target.value)}
                                                        placeholder="Specify the platform..."
                                                        className="w-full bg-white border-2 border-emerald-200 rounded-[1rem] px-5 py-4 font-bold text-zinc-900 focus:outline-none focus:border-emerald-500"
                                                        required
                                                    />
                                                </motion.div>
                                            )}
                                            {errors.platform && <p className="text-red-500 text-xs mt-2 font-bold">{errors.platform}</p>}
                                        </div>
                                    </div>
                                </section>

                                {/* Section 2: Mission Briefing */}
                                <section>
                                    <h3 className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                                        <span className="h-px w-8 bg-zinc-200" /> Briefing
                                    </h3>
                                    
                                    <div>
                                        <textarea 
                                            rows={6}
                                            value={data.description}
                                            onChange={e => setData('description', e.target.value)}
                                            placeholder="Provide a detailed breakdown of the deliverables, target audience, brand guidelines, and any specific requirements the talent must follow."
                                            className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-[1rem] px-5 py-5 font-bold text-zinc-900 focus:outline-none focus:border-emerald-500 focus:bg-white transition-all resize-none leading-relaxed"
                                            required
                                        />
                                        <p className="text-xs text-zinc-500 mt-2 font-medium">Be as descriptive as possible to avoid revisions later.</p>
                                        {errors.description && <p className="text-red-500 text-xs mt-2 font-bold">{errors.description}</p>}
                                    </div>
                                </section>

                                {/* Section 3: Financials & Logistics */}
                                <section>
                                    <h3 className="text-[10px] font-black text-zinc-400 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                                        <span className="h-px w-8 bg-zinc-200" /> Logistics
                                    </h3>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-xs font-black text-zinc-900 uppercase tracking-widest mb-2 flex items-center gap-2">
                                                <FaMoneyBillWave className="text-emerald-500" /> Budget Per Talent
                                            </label>
                                            <div className="relative">
                                                <div className="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                                                    <span className="text-zinc-400 font-bold">$</span>
                                                </div>
                                                <input 
                                                    type="number" 
                                                    step="0.01"
                                                    value={data.budget}
                                                    onChange={e => setData('budget', e.target.value)}
                                                    placeholder="50.00"
                                                    className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-[1rem] pl-10 pr-5 py-4 font-black text-zinc-900 text-xl focus:outline-none focus:border-emerald-500 focus:bg-white transition-all"
                                                    required
                                                />
                                            </div>
                                            {errors.budget && <p className="text-red-500 text-xs mt-2 font-bold">{errors.budget}</p>}
                                        </div>

                                        <div>
                                            <label className="block text-xs font-black text-zinc-900 uppercase tracking-widest mb-2 flex items-center gap-2">
                                                <FaUsers className="text-indigo-500" /> Talent Slots
                                            </label>
                                            <div className="relative">
                                                <input 
                                                    type="number" 
                                                    min="1"
                                                    value={data.slots}
                                                    onChange={e => setData('slots', e.target.value)}
                                                    className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-[1rem] px-5 py-4 font-black text-zinc-900 text-xl focus:outline-none focus:border-emerald-500 focus:bg-white transition-all"
                                                    required
                                                />
                                                <div className="absolute inset-y-0 right-0 pr-5 flex items-center pointer-events-none">
                                                    <span className="text-zinc-400 font-bold text-xs uppercase tracking-widest">Marketers</span>
                                                </div>
                                            </div>
                                            {errors.slots && <p className="text-red-500 text-xs mt-2 font-bold">{errors.slots}</p>}
                                        </div>

                                        <div className="md:col-span-2">
                                            <label className="block text-xs font-black text-zinc-900 uppercase tracking-widest mb-2 flex items-center gap-2">
                                                <FaCalendarAlt className="text-amber-500" /> Delivery Deadline (Optional)
                                            </label>
                                            <input 
                                                type="date" 
                                                value={data.deadline_at}
                                                onChange={e => setData('deadline_at', e.target.value)}
                                                className="w-full bg-zinc-50 border-2 border-zinc-100 rounded-[1rem] px-5 py-4 font-bold text-zinc-900 focus:outline-none focus:border-emerald-500 focus:bg-white transition-all"
                                            />
                                            {errors.deadline_at && <p className="text-red-500 text-xs mt-2 font-bold">{errors.deadline_at}</p>}
                                        </div>
                                    </div>

                                    {/* Cost Summary Preview */}
                                    {data.budget && data.slots && (
                                        <div className="mt-8 p-6 rounded-[1.5rem] bg-emerald-50 border-2 border-emerald-100">
                                            <div className="flex justify-between items-center mb-2">
                                                <span className="text-emerald-800 text-xs font-bold">Subtotal ({data.slots} × ${data.budget})</span>
                                                <span className="text-emerald-900 font-black">${(Number(data.budget) * Number(data.slots)).toFixed(2)}</span>
                                            </div>
                                            <div className="flex justify-between items-center mb-4 pb-4 border-b border-emerald-200/50">
                                                <span className="text-emerald-800 text-xs font-bold">Platform Fee (10%)</span>
                                                <span className="text-emerald-900 font-black">${(Number(data.budget) * Number(data.slots) * 0.1).toFixed(2)}</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="text-emerald-900 text-sm font-black uppercase tracking-widest">Total Escrow</span>
                                                <span className="text-emerald-600 text-2xl font-black">${(Number(data.budget) * Number(data.slots) * 1.1).toFixed(2)}</span>
                                            </div>
                                        </div>
                                    )}
                                </section>
                            </div>

                            {/* Footer */}
                            <div className="px-8 py-6 bg-white border-t border-zinc-100 flex items-center justify-end gap-4 shrink-0">
                                <button 
                                    type="button" 
                                    onClick={onClose}
                                    className="px-8 py-4 rounded-[1rem] text-zinc-500 font-black text-xs uppercase tracking-widest hover:bg-zinc-50 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit" 
                                    disabled={processing}
                                    className="px-10 py-4 rounded-[1rem] bg-zinc-900 text-white font-black text-xs uppercase tracking-widest shadow-xl shadow-zinc-900/20 hover:bg-zinc-800 hover:scale-105 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-3"
                                >
                                    {processing ? 'Deploying...' : 'Deploy Contract'}
                                </button>
                            </div>
                        </form>
                    </motion.div>
                </>
            )}
        </AnimatePresence>
    );
}
