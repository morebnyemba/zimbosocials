import {
    FaInstagram, FaYoutube, FaTiktok, FaFacebook, FaTwitter,
    FaTelegram, FaWhatsapp, FaSpotify, FaLinkedin, FaDiscord,
    FaSnapchatGhost, FaTwitch, FaSoundcloud, FaPinterest, FaRedditAlien,
    FaGlobe, FaAt,
} from 'react-icons/fa';
import { IconType } from 'react-icons';

export interface PlatformMeta {
    label: string;
    icon: IconType;
    color: string;
    bg: string;
    border: string;
}

/**
 * Icon/color per canonical platform slug (Service::PLATFORMS on the backend —
 * keep the two in sync). Keyed by an exact slug rather than matched against
 * free-text category, so every service carries the same icon everywhere it
 * appears regardless of how its category is worded.
 */
const PLATFORM_META: Record<string, PlatformMeta> = {
    instagram: { label: 'Instagram', icon: FaInstagram, color: 'text-pink-500', bg: 'bg-pink-500/10', border: 'border-pink-200' },
    youtube: { label: 'YouTube', icon: FaYoutube, color: 'text-red-500', bg: 'bg-red-500/10', border: 'border-red-200' },
    tiktok: { label: 'TikTok', icon: FaTiktok, color: 'text-zinc-900', bg: 'bg-zinc-900/10', border: 'border-zinc-300' },
    facebook: { label: 'Facebook', icon: FaFacebook, color: 'text-blue-600', bg: 'bg-blue-600/10', border: 'border-blue-200' },
    twitter: { label: 'Twitter / X', icon: FaTwitter, color: 'text-blue-400', bg: 'bg-blue-400/10', border: 'border-blue-200' },
    telegram: { label: 'Telegram', icon: FaTelegram, color: 'text-sky-500', bg: 'bg-sky-500/10', border: 'border-sky-200' },
    whatsapp: { label: 'WhatsApp', icon: FaWhatsapp, color: 'text-emerald-500', bg: 'bg-emerald-500/10', border: 'border-emerald-200' },
    spotify: { label: 'Spotify', icon: FaSpotify, color: 'text-green-500', bg: 'bg-green-500/10', border: 'border-green-200' },
    linkedin: { label: 'LinkedIn', icon: FaLinkedin, color: 'text-blue-700', bg: 'bg-blue-700/10', border: 'border-blue-300' },
    discord: { label: 'Discord', icon: FaDiscord, color: 'text-indigo-500', bg: 'bg-indigo-500/10', border: 'border-indigo-200' },
    threads: { label: 'Threads', icon: FaAt, color: 'text-zinc-900', bg: 'bg-zinc-900/10', border: 'border-zinc-300' },
    snapchat: { label: 'Snapchat', icon: FaSnapchatGhost, color: 'text-yellow-500', bg: 'bg-yellow-400/10', border: 'border-yellow-200' },
    twitch: { label: 'Twitch', icon: FaTwitch, color: 'text-purple-500', bg: 'bg-purple-500/10', border: 'border-purple-200' },
    soundcloud: { label: 'SoundCloud', icon: FaSoundcloud, color: 'text-orange-500', bg: 'bg-orange-500/10', border: 'border-orange-200' },
    pinterest: { label: 'Pinterest', icon: FaPinterest, color: 'text-red-600', bg: 'bg-red-600/10', border: 'border-red-200' },
    reddit: { label: 'Reddit', icon: FaRedditAlien, color: 'text-orange-600', bg: 'bg-orange-600/10', border: 'border-orange-200' },
};

const FALLBACK: PlatformMeta = { label: 'Other', icon: FaGlobe, color: 'text-emerald-500', bg: 'bg-emerald-500/10', border: 'border-emerald-200' };

/** Look up icon/color for a platform slug. Unknown or missing → a neutral fallback. */
export function getPlatformMeta(platform?: string | null): PlatformMeta {
    if (!platform) return FALLBACK;

    return PLATFORM_META[platform] ?? FALLBACK;
}

/**
 * The platform most of a group's services actually belong to — for picking
 * a single representative icon on a category tab/tile that groups several
 * services together (each service still shows its OWN platform icon
 * wherever it's listed individually).
 */
export function dominantPlatform<T extends { platform?: string | null }>(items: T[]): string | undefined {
    const counts: Record<string, number> = {};
    for (const item of items) {
        if (!item.platform) continue;
        counts[item.platform] = (counts[item.platform] ?? 0) + 1;
    }
    const [top] = Object.entries(counts).sort((a, b) => b[1] - a[1]);

    return top?.[0];
}
