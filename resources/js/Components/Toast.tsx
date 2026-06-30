import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef } from 'react';
import { FaCheckCircle, FaTimesCircle, FaInfoCircle, FaExclamationTriangle, FaTimes } from 'react-icons/fa';

export type ToastKind = 'success' | 'error' | 'info' | 'warn';

interface ToastProps {
    kind: ToastKind;
    message: string;
    onClose: () => void;
}

const styles: Record<ToastKind, { bg: string; text: string; Icon: any; iconColor: string }> = {
    success: { bg: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-800', Icon: FaCheckCircle,        iconColor: 'text-emerald-500' },
    error:   { bg: 'bg-red-50 border-red-200',         text: 'text-red-800',     Icon: FaTimesCircle,        iconColor: 'text-red-500' },
    info:    { bg: 'bg-blue-50 border-blue-200',        text: 'text-blue-800',    Icon: FaInfoCircle,         iconColor: 'text-blue-500' },
    warn:    { bg: 'bg-amber-50 border-amber-200',      text: 'text-amber-800',   Icon: FaExclamationTriangle, iconColor: 'text-amber-500' },
};

export function Toast({ kind, message, onClose }: ToastProps) {
    const s = styles[kind];
    return (
        <motion.div
            initial={{ opacity: 0, y: -16, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -16, scale: 0.96 }}
            className={`flex w-full items-start gap-3 rounded-2xl border px-4 py-3.5 shadow-xl sm:w-auto sm:max-w-md sm:items-center ${s.bg}`}
        >
            <s.Icon className={`mt-0.5 h-4 w-4 shrink-0 sm:mt-0 ${s.iconColor}`} />
            <p className={`min-w-0 break-words text-sm font-semibold ${s.text}`}>{message}</p>
            <button
                onClick={onClose}
                className={`ml-2 shrink-0 opacity-60 hover:opacity-100 transition-opacity ${s.text}`}
            >
                <FaTimes className="w-3.5 h-3.5" />
            </button>
        </motion.div>
    );
}

interface ToastContainerProps {
    toast: { kind: ToastKind; message: string } | null;
    onClose: () => void;
    /** Auto-dismiss delay in ms. Errors linger a little longer by default. */
    duration?: number;
}

export default function ToastContainer({ toast, onClose, duration }: ToastContainerProps) {
    // Keep the latest onClose without making it a timer dependency — otherwise an
    // inline `() => setToast(null)` from the parent would reset the timer on every
    // render and the toast would never auto-dismiss.
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;

    const delay = duration ?? (toast?.kind === 'error' ? 6000 : 4000);

    useEffect(() => {
        if (!toast) return;
        const id = setTimeout(() => onCloseRef.current(), delay);
        return () => clearTimeout(id);
    }, [toast?.kind, toast?.message, delay]);

    return (
        <div className="pointer-events-none fixed top-4 inset-x-4 z-[300] flex flex-col items-stretch gap-2 sm:inset-x-auto sm:right-5 sm:top-5 sm:items-end">
            <AnimatePresence>
                {toast && (
                    <div className="pointer-events-auto w-full sm:w-auto">
                        <Toast kind={toast.kind} message={toast.message} onClose={onClose} />
                    </div>
                )}
            </AnimatePresence>
        </div>
    );
}
