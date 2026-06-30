import { FaWhatsapp, FaComments, FaExclamationTriangle } from 'react-icons/fa';
import { motion, AnimatePresence } from 'framer-motion';
import { useState, useEffect } from 'react';

export default function WhatsAppFloatingButton() {
    const [isHovered, setIsHovered] = useState(false);
    const [isVisible, setIsVisible] = useState(false);
    const [showModal, setShowModal] = useState(false);

    // Delay showing the button slightly so it doesn't pop in aggressively on load
    useEffect(() => {
        const timer = setTimeout(() => setIsVisible(true), 1500);
        return () => clearTimeout(timer);
    }, []);

    if (!isVisible) return null;

    const phoneNumber = '263710954504';
    const message = encodeURIComponent('Hi Admin, I have an emergency issue on Zimbo Socials that needs attention: ');
    const whatsappUrl = `https://wa.me/${phoneNumber}?text=${message}`;

    const openLiveChat = () => {
        setShowModal(false);
        if (typeof window !== 'undefined' && (window as any).Tawk_API) {
            (window as any).Tawk_API.maximize();
        }
    };

    return (
        <>
            <div className="fixed bottom-6 left-6 z-[9990]">
                <div 
                    className="relative flex items-center"
                    onMouseEnter={() => setIsHovered(true)}
                    onMouseLeave={() => setIsHovered(false)}
                >
                    <AnimatePresence>
                        {isHovered && !showModal && (
                            <motion.div
                                initial={{ opacity: 0, x: -10, scale: 0.95 }}
                                animate={{ opacity: 1, x: 0, scale: 1 }}
                                exit={{ opacity: 0, x: -10, scale: 0.95 }}
                                transition={{ duration: 0.2 }}
                                className="absolute left-[calc(100%+12px)] bg-zinc-900 text-white text-xs font-bold p-3 rounded-2xl shadow-xl border border-zinc-700/50 flex flex-col gap-1 w-48"
                            >
                                <span className="text-red-400">Emergencies Only</span>
                                <span className="text-zinc-300 font-medium text-[10px] leading-tight">
                                    For quick help, please use the Tawk.to live chat (bottom right) to speak with our agents.
                                </span>
                            </motion.div>
                        )}
                    </AnimatePresence>

                    <motion.button
                        onClick={() => setShowModal(true)}
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                        className="flex items-center justify-center w-14 h-14 bg-[#25D366] text-white rounded-full shadow-2xl hover:shadow-[#25D366]/40 transition-shadow duration-300 relative group overflow-hidden"
                    >
                        <motion.div
                            animate={{ rotate: [0, 10, -10, 10, 0] }}
                            transition={{ repeat: Infinity, duration: 2, repeatDelay: 3 }}
                        >
                            <FaWhatsapp className="w-8 h-8 relative z-10" />
                        </motion.div>
                        
                        {/* Ripple effect */}
                        <div className="absolute inset-0 rounded-full border-2 border-white/20 scale-150 opacity-0 group-hover:animate-ping" />
                    </motion.button>
                </div>
            </div>

            {/* Emergency Modal */}
            <AnimatePresence>
                {showModal && (
                    <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4">
                        <motion.div 
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setShowModal(false)}
                            className="absolute inset-0 bg-zinc-900/60 backdrop-blur-sm"
                        />
                        <motion.div 
                            initial={{ opacity: 0, scale: 0.9, y: 20 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.9, y: 20 }}
                            className="bg-white rounded-[2rem] shadow-2xl w-full max-w-sm relative z-10 overflow-hidden border border-zinc-200"
                        >
                            <div className="p-8 text-center space-y-4">
                                <div className="mx-auto w-16 h-16 bg-orange-100 text-orange-500 rounded-full flex items-center justify-center mb-6">
                                    <FaExclamationTriangle className="w-8 h-8" />
                                </div>
                                <h3 className="text-xl font-black text-zinc-900">Are you sure?</h3>
                                <p className="text-zinc-500 text-sm font-medium leading-relaxed">
                                    The WhatsApp line is strictly reserved for <strong className="text-zinc-900">emergencies</strong>. 
                                    For quick, general help, please speak to an agent on Live Chat.
                                </p>
                            </div>
                            <div className="bg-zinc-50 border-t border-zinc-100 p-6 flex flex-col gap-3">
                                <button 
                                    onClick={openLiveChat}
                                    className="w-full flex items-center justify-center gap-2 bg-brand-green text-white font-bold py-3.5 rounded-xl hover:bg-brand-green/90 transition-all active:scale-[0.98]"
                                >
                                    <FaComments /> Use Live Chat (Recommended)
                                </button>
                                <a 
                                    href={whatsappUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    onClick={() => setShowModal(false)}
                                    className="w-full flex items-center justify-center gap-2 bg-zinc-200 text-zinc-700 font-bold py-3.5 rounded-xl hover:bg-zinc-300 transition-all active:scale-[0.98]"
                                >
                                    <FaWhatsapp /> Proceed to WhatsApp
                                </a>
                            </div>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </>
    );
}
