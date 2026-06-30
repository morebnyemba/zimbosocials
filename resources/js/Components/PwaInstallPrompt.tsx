import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Download, X } from 'lucide-react';

/**
 * Captures the browser's `beforeinstallprompt` event and surfaces a branded,
 * dismissible "Install app" card so users can add Zimbo Socials to their home
 * screen. Pairs with the root service worker (public/sw.js).
 *
 * Behaviour:
 *  - Only renders when the browser deems the app installable.
 *  - Hidden when already running standalone (installed) or recently dismissed.
 *  - A dismissal is remembered for 14 days so we never nag.
 */

const DISMISS_KEY = 'zs-pwa-install-dismissed';
const DISMISS_DAYS = 14;

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

function recentlyDismissed(): boolean {
    try {
        const ts = Number(localStorage.getItem(DISMISS_KEY));
        if (!ts) return false;
        return Date.now() - ts < DISMISS_DAYS * 24 * 60 * 60 * 1000;
    } catch {
        return false;
    }
}

function isStandalone(): boolean {
    return (
        window.matchMedia?.('(display-mode: standalone)').matches ||
        // iOS Safari
        (window.navigator as unknown as { standalone?: boolean }).standalone === true
    );
}

export default function PwaInstallPrompt() {
    const [deferred, setDeferred] = useState<BeforeInstallPromptEvent | null>(
        null,
    );
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (isStandalone() || recentlyDismissed()) return;

        const onPrompt = (e: Event) => {
            e.preventDefault(); // stop Chrome's default mini-infobar
            setDeferred(e as BeforeInstallPromptEvent);
            // Small delay so it doesn't fight with first paint.
            setTimeout(() => setVisible(true), 2500);
        };

        const onInstalled = () => {
            setVisible(false);
            setDeferred(null);
        };

        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', onInstalled);
        return () => {
            window.removeEventListener('beforeinstallprompt', onPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    const dismiss = () => {
        setVisible(false);
        try {
            localStorage.setItem(DISMISS_KEY, String(Date.now()));
        } catch {
            /* ignore storage failures (private mode) */
        }
    };

    const install = async () => {
        if (!deferred) return;
        await deferred.prompt();
        await deferred.userChoice;
        setDeferred(null);
        setVisible(false);
    };

    return (
        <AnimatePresence>
            {visible && deferred && (
                <motion.div
                    initial={{ opacity: 0, y: 24, scale: 0.96 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: 24, scale: 0.96 }}
                    transition={{ type: 'spring', stiffness: 260, damping: 24 }}
                    role="dialog"
                    aria-label="Install Zimbo Socials app"
                    className="fixed inset-x-0 bottom-6 z-[9991] mx-auto w-[min(92vw,26rem)] rounded-2xl border border-zinc-200 bg-white p-4 shadow-2xl"
                >
                    <button
                        onClick={dismiss}
                        aria-label="Dismiss install prompt"
                        className="absolute right-3 top-3 text-zinc-400 transition-colors hover:text-zinc-600"
                    >
                        <X className="h-4 w-4" />
                    </button>

                    <div className="flex items-center gap-4">
                        <img
                            src="/pwa-192x192.png"
                            alt=""
                            className="h-14 w-14 flex-shrink-0 rounded-xl shadow-sm"
                        />
                        <div className="min-w-0">
                            <p className="text-sm font-black text-zinc-900">
                                Install Zimbo Socials
                            </p>
                            <p className="mt-0.5 text-xs font-medium leading-snug text-zinc-500">
                                Add the app to your home screen for faster access
                                and a full-screen experience.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 flex gap-2">
                        <button
                            onClick={install}
                            className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-green py-2.5 text-sm font-bold text-white transition-all hover:bg-brand-green/90 active:scale-[0.98]"
                        >
                            <Download className="h-4 w-4" /> Install
                        </button>
                        <button
                            onClick={dismiss}
                            className="rounded-xl bg-zinc-100 px-4 py-2.5 text-sm font-bold text-zinc-600 transition-all hover:bg-zinc-200 active:scale-[0.98]"
                        >
                            Not now
                        </button>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
