import { AnimatePresence, motion } from 'framer-motion';
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
            className={`flex items-center gap-3 px-5 py-3.5 rounded-2xl border shadow-xl ${s.bg}`}
        >
            <s.Icon className={`w-4 h-4 shrink-0 ${s.iconColor}`} />
            <p className={`text-sm font-semibold ${s.text}`}>{message}</p>
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
}

export default function ToastContainer({ toast, onClose }: ToastContainerProps) {
    return (
        <div className="fixed top-5 right-5 z-[300] flex flex-col gap-2 pointer-events-none">
            <AnimatePresence>
                {toast && (
                    <div className="pointer-events-auto">
                        <Toast kind={toast.kind} message={toast.message} onClose={onClose} />
                    </div>
                )}
            </AnimatePresence>
        </div>
    );
}
