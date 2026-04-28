import MarketingLayout from '@/Layouts/MarketingLayout';
import { Head } from '@inertiajs/react';

interface PortfolioItem { id: number; title: string; platform: string; url: string; thumbnail_url?: string; description?: string; metrics?: any; }
interface SocialLink { id: number; platform: string; url: string; username?: string; }
interface Props { marketer: { id: number; name: string; company_name?: string; bio?: string; profile_image_url?: string }; portfolio: PortfolioItem[]; socials: SocialLink[]; stats: { completed_contracts: number; social_accounts: number }; }

const platformColors: Record<string, string> = {
    instagram: 'from-pink-600 to-purple-600', tiktok: 'from-gray-800 to-gray-600', youtube: 'from-red-600 to-red-800',
    twitter: 'from-blue-500 to-blue-700', facebook: 'from-blue-600 to-blue-800', linkedin: 'from-blue-700 to-blue-900',
};

export default function Portfolio({ marketer, portfolio, socials, stats }: Props) {
    return (
        <MarketingLayout>
            <Head title={`${marketer.name} - Portfolio`} />
            <div className="max-w-4xl mx-auto px-4 sm:px-6 py-12 space-y-8">
                {/* Profile Header */}
                <div className="text-center space-y-4">
                    <div className="w-24 h-24 mx-auto rounded-full bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center text-white text-3xl font-bold">
                        {marketer.profile_image_url ? <img src={marketer.profile_image_url} alt={marketer.name} className="w-full h-full rounded-full object-cover" /> : marketer.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <h1 className="text-3xl font-bold text-white">{marketer.name}</h1>
                        {marketer.company_name && <p className="text-gray-400">{marketer.company_name}</p>}
                        {marketer.bio && <p className="text-gray-500 mt-2 max-w-lg mx-auto">{marketer.bio}</p>}
                    </div>
                    <div className="flex justify-center gap-4">
                        <div className="px-4 py-2 rounded-xl bg-gray-900/60 border border-white/5"><span className="text-white font-bold">{stats.completed_contracts}</span> <span className="text-gray-400 text-sm">contracts</span></div>
                        <div className="px-4 py-2 rounded-xl bg-gray-900/60 border border-white/5"><span className="text-white font-bold">{stats.social_accounts}</span> <span className="text-gray-400 text-sm">platforms</span></div>
                    </div>

                    {/* Social Links */}
                    {socials.length > 0 && (
                        <div className="flex justify-center gap-3 flex-wrap">
                            {socials.map(s => (
                                <a key={s.id} href={s.url} target="_blank" rel="noopener" className={`px-4 py-2 rounded-xl bg-gradient-to-r ${platformColors[s.platform.toLowerCase()] || 'from-gray-700 to-gray-800'} text-white text-sm hover:opacity-80 transition-opacity`}>
                                    {s.platform} {s.username && `@${s.username}`}
                                </a>
                            ))}
                        </div>
                    )}
                </div>

                {/* Portfolio Grid */}
                {portfolio.length > 0 && (
                    <div>
                        <h2 className="text-xl font-semibold text-white mb-4">Portfolio</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {portfolio.map(item => (
                                <a key={item.id} href={item.url} target="_blank" rel="noopener" className="rounded-2xl border border-white/5 bg-gray-900/60 p-5 hover:border-white/10 transition-colors group">
                                    <div className="flex items-center gap-2 mb-2">
                                        <span className={`px-2 py-0.5 text-xs rounded-full bg-gradient-to-r ${platformColors[item.platform.toLowerCase()] || 'from-gray-700 to-gray-800'} text-white`}>{item.platform}</span>
                                    </div>
                                    <h3 className="text-white font-medium group-hover:text-violet-400 transition-colors">{item.title}</h3>
                                    {item.description && <p className="text-sm text-gray-500 mt-1 line-clamp-2">{item.description}</p>}
                                </a>
                            ))}
                        </div>
                    </div>
                )}

                {portfolio.length === 0 && <p className="text-center text-gray-500 py-12">No portfolio items yet</p>}
            </div>
        </MarketingLayout>
    );
}
