import {
  NavigationMenu,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
  navigationMenuTriggerStyle,
} from "@/Components/ui/navigation-menu"
import SeoHead from "@/Components/SeoHead"
import { cn } from "@/lib/utils"
import { Link, router, usePage } from "@inertiajs/react"
import { FiArrowRight, FiLogIn, FiMenu, FiZap, FiX, FiMail } from "react-icons/fi"
import { FaGlobe, FaChevronDown } from "react-icons/fa"
import { FaFacebookF, FaInstagram, FaWhatsapp, FaXTwitter } from "react-icons/fa6"
import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { PropsWithChildren, useState, useRef, useEffect } from "react"
import { useTranslation } from "@/lib/i18n"
import { AnimatePresence, motion } from "framer-motion"
import LiveActivityFeed from "@/Components/LiveActivityFeed"
import { useLiveActivity } from "@/lib/useLiveActivity"
import { Service } from "@/lib/types"

type MarketingLayoutProps = PropsWithChildren<{
  title?: string
  description?: string
  keywords?: string[]
  seoPath?: string
  noindex?: boolean
  seoImage?: string
  seoType?: "website" | "article"
  structuredData?: Record<string, unknown> | Array<Record<string, unknown>>
  canAuth?: boolean
}>

function LangSwitcher() {
  const { locale, t } = useTranslation()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const current = locale ?? 'sn'

  const langs = [
    { code: 'sn', short: 'SN', label: t('shona') },
    { code: 'nd', short: 'ND', label: t('ndebele') },
    { code: 'en', short: 'EN', label: t('english') },
  ]

  const currentLang = langs.find((l) => l.code === current) ?? langs[0]

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const switchLang = (code: string) => {
    setOpen(false)
    router.post(route('locale.switch'), { locale: code }, { preserveScroll: true })
  }

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex items-center gap-1 rounded-md border border-zinc-200 bg-white px-1.5 py-1 text-xs shadow-sm hover:border-zinc-300 focus:outline-none"
        aria-label="Switch language"
      >
        <FaGlobe className="text-[10px] text-zinc-400" />
        <span className="text-[9px] font-black uppercase tracking-wide text-zinc-700">{currentLang.short}</span>
        <FaChevronDown className={`text-[8px] text-zinc-400 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0, y: -4 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -4 }}
            transition={{ duration: 0.12 }}
            className="absolute right-0 top-full mt-1 z-50 min-w-[110px] rounded-md border border-zinc-200 bg-white py-1 shadow-md"
          >
            {langs.map((lang) => (
              <button
                key={lang.code}
                onClick={() => switchLang(lang.code)}
                className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs hover:bg-zinc-50 ${lang.code === current ? 'font-bold text-zinc-900' : 'text-zinc-600'}`}
              >
                <span className="w-5 text-[9px] font-black uppercase tracking-wide text-zinc-400">{lang.short}</span>
                {lang.label}
              </button>
            ))}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export default function MarketingLayout({
  children,
  title = "Zimbo Socials",
  description = "Zimbo Socials helps creators, businesses, and marketers in Zimbabwe grow social media reach with transparent pricing, fast delivery, and responsive support.",
  keywords = ["Zimbabwe SMM", "social media marketing Zimbabwe", "Instagram followers Zimbabwe", "TikTok growth", "YouTube subscribers", "WhatsApp channel followers"],
  seoPath,
  noindex = false,
  seoImage,
  seoType = "website",
  structuredData,
  canAuth = true,
}: MarketingLayoutProps) {
  const [mobileOpen, setMobileOpen] = useState(false)
  const [legalOpen, setLegalOpen] = useState(false)
  const legalDropdownRef = useRef<HTMLDivElement>(null)
  const { t } = useTranslation()
  const pageProps = usePage().props as any
  const liveActivities = useLiveActivity(pageProps.activityServices || [])

  useEffect(() => {
    const onPointerDown = (event: MouseEvent) => {
      if (legalDropdownRef.current && !legalDropdownRef.current.contains(event.target as Node)) {
        setLegalOpen(false)
      }
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setLegalOpen(false)
      }
    }

    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [])

  const baseStructuredData: Array<Record<string, unknown>> = [
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      name: "Zimbo Socials",
      url: "https://zimsocials.co.zw",
      logo: "https://zimsocials.co.zw/images/zimbosocials.png",
      contactPoint: {
        "@type": "ContactPoint",
        contactType: "customer support",
        email: "support@zimsocials.co.zw",
        availableLanguage: ["en", "sn", "nd"],
      },
    },
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      name: "Zimbo Socials",
      url: "https://zimsocials.co.zw",
      inLanguage: ["en-ZW", "sn-ZW", "nd-ZW"],
    },
  ]

  const mergedStructuredData = [
    ...baseStructuredData,
    ...(Array.isArray(structuredData)
      ? structuredData
      : structuredData
        ? [structuredData]
        : []),
  ]

  const primaryLinks = [
    { href: route("marketing.home"), name: "marketing.home", label: t('home') },
    { href: route("marketing.services"), name: "marketing.services", label: t('services') },
    { href: route("marketing.contact"), name: "marketing.contact", label: t('contact') },
    { href: route("marketing.about"), name: "marketing.about", label: t('about') },
    { href: route("marketing.help"), name: "marketing.help", label: t('help') },
  ]

  const secondaryLinks = [
    { href: route("marketing.privacy"), name: "marketing.privacy", label: t('privacy') },
    { href: route("marketing.terms"), name: "marketing.terms", label: t('terms') },
  ]

  return (
    <>
      <SeoHead
        title={title}
        description={description}
        keywords={keywords}
        urlPath={seoPath}
        image={seoImage}
        type={seoType}
        noindex={noindex}
        structuredData={mergedStructuredData}
      />
      <div className="min-h-screen bg-white text-zinc-950">
        <LiveActivityFeed activities={liveActivities} />
        <header className="sticky top-0 z-50 border-b border-zinc-200 bg-white/95 backdrop-blur">
          <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <Link href="/" className="inline-flex items-center" aria-label="Zimbo Socials Home">
              <img src="/images/zimbosocials.png" alt="Zimbo Socials" className="h-9 w-auto transition-transform duration-200 hover:scale-[1.02] sm:h-10" />
            </Link>

            <div className="hidden md:flex md:items-center md:gap-2">
              <NavigationMenu viewport={false}>
                <NavigationMenuList>
                  {primaryLinks.map((link) => (
                    <NavigationMenuItem key={link.name}>
                      <Link
                        href={link.href}
                        className={cn(
                          navigationMenuTriggerStyle(),
                          "text-zinc-700 hover:bg-amber-50 hover:text-emerald-700",
                          route().current(link.name) && "border-emerald-200 bg-emerald-50 font-semibold text-emerald-700"
                        )}
                      >
                        {link.label}
                      </Link>
                    </NavigationMenuItem>
                  ))}
                  <NavigationMenuItem>
                    <div ref={legalDropdownRef} className="relative">
                      <button
                        type="button"
                        onClick={() => setLegalOpen((prev) => !prev)}
                        aria-expanded={legalOpen}
                        aria-haspopup="menu"
                        className={cn(
                          navigationMenuTriggerStyle(),
                          "text-zinc-700 hover:bg-amber-50 hover:text-emerald-700",
                          (route().current("marketing.privacy") || route().current("marketing.terms")) && "border-emerald-200 bg-emerald-50 font-semibold text-emerald-700"
                        )}
                      >
                        {t('legal')}
                        <FaChevronDown className={cn("ml-1 h-3 w-3 text-zinc-400 transition-transform", legalOpen && "rotate-180")} />
                      </button>

                      <AnimatePresence>
                        {legalOpen && (
                          <motion.div
                            initial={{ opacity: 0, y: 6 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: 6 }}
                            transition={{ duration: 0.12 }}
                            className="absolute right-0 top-full z-[70] mt-2 min-w-[200px] rounded-xl border border-zinc-200 bg-white p-2 shadow-xl"
                            role="menu"
                          >
                            {secondaryLinks.map((link) => (
                              <NavigationMenuLink key={link.name} asChild>
                                <Link
                                  href={link.href}
                                  role="menuitem"
                                  onClick={() => setLegalOpen(false)}
                                  className={cn(
                                    "block rounded-md px-3 py-2 text-sm font-medium text-slate-700 transition",
                                    route().current(link.name)
                                      ? "bg-emerald-50 text-emerald-700"
                                      : "hover:bg-emerald-50 hover:text-emerald-700"
                                  )}
                                >
                                  {link.label}
                                </Link>
                              </NavigationMenuLink>
                            ))}
                          </motion.div>
                        )}
                      </AnimatePresence>
                    </div>
                  </NavigationMenuItem>
                </NavigationMenuList>
              </NavigationMenu>
            </div>

            {canAuth && (
              <div className="hidden items-center gap-2 md:flex">
                <LangSwitcher />
                <Link
                  href={route("login")}
                  className="inline-flex items-center gap-2 rounded-lg border border-zinc-900 px-3 py-2 text-sm font-semibold text-zinc-900 transition hover:-translate-y-0.5 hover:bg-zinc-950 hover:text-white hover:shadow-sm"
                >
                  <FiLogIn className="h-4 w-4" />
                  {t('login')}
                </Link>
                <Link
                  href={route("register")}
                  className="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                >
                  <FiArrowRight className="h-4 w-4" />
                  {t('get_started')}
                </Link>
              </div>
            )}

            <button
              onClick={() => setMobileOpen((prev) => !prev)}
              className="inline-flex items-center justify-center rounded-md border border-zinc-200 p-2 text-zinc-700 transition hover:bg-zinc-950 hover:text-white md:hidden"
              aria-label="Toggle menu"
            >
              {mobileOpen ? <FiX className="h-5 w-5" /> : <FiMenu className="h-5 w-5" />}
            </button>
          </div>

          <div className={cn("border-t border-zinc-200 bg-white md:hidden", mobileOpen ? "block" : "hidden")}>
            <div className="space-y-1 px-4 py-3">
              {primaryLinks.map((link) => (
                <Link
                  key={link.name}
                  href={link.href}
                  className={cn(
                    "block rounded-md px-3 py-2 text-sm font-medium transition",
                    route().current(link.name)
                      ? "bg-emerald-50 text-emerald-700"
                      : "text-slate-700 hover:bg-slate-100"
                  )}
                  onClick={() => setMobileOpen(false)}
                >
                  {link.label}
                </Link>
              ))}

              <div className="pt-2">
                <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{t('legal')}</p>
                {secondaryLinks.map((link) => (
                  <Link
                    key={link.name}
                    href={link.href}
                    className={cn(
                      "block rounded-md px-3 py-2 text-sm font-medium transition",
                      route().current(link.name)
                        ? "bg-emerald-50 text-emerald-700"
                        : "text-slate-700 hover:bg-slate-100"
                    )}
                    onClick={() => setMobileOpen(false)}
                  >
                    {link.label}
                  </Link>
                ))}
              </div>

              {canAuth && (
                <div className="grid grid-cols-2 gap-2 pt-3">
                  <Link
                    href={route("login")}
                    className="inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-900 px-3 py-2 text-center text-sm font-semibold text-zinc-900 transition hover:bg-zinc-950 hover:text-white"
                    onClick={() => setMobileOpen(false)}
                  >
                    <FiLogIn className="h-4 w-4" />
                    {t('login')}
                  </Link>
                  <Link
                    href={route("register")}
                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 px-3 py-2 text-center text-sm font-semibold text-white transition"
                    onClick={() => setMobileOpen(false)}
                  >
                    <FiArrowRight className="h-4 w-4" />
                    {t('get_started')}
                  </Link>
                </div>
              )}
              <div className="flex items-center justify-center pt-3 pb-1">
                <LangSwitcher />
              </div>
            </div>
          </div>
        </header>

        <main>{children}</main>

        <footer className="mt-20 border-t border-slate-200 bg-white">
          <div className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div className="grid gap-10 md:grid-cols-2 lg:grid-cols-5">
              <div className="lg:col-span-2">
                <img src="/images/zimbosocials.png" alt="Zimbo Socials" className="mb-4 h-10 w-auto" />
                <p className="mb-6 max-w-xs text-sm text-slate-600">
                  {t('footer_tagline')}
                </p>
                <div className="flex items-center gap-4 text-slate-400">
                  <a href="#" className="hover:text-emerald-600 transition"><FaFacebookF className="h-5 w-5" /></a>
                  <a href="#" className="hover:text-red-500 transition"><FaInstagram className="h-5 w-5" /></a>
                  <a href="#" className="hover:text-emerald-500 transition"><FaWhatsapp className="h-5 w-5" /></a>
                  <a href="#" className="hover:text-slate-900 transition"><FaXTwitter className="h-5 w-5" /></a>
                </div>
              </div>

              <div>
                <h3 className="mb-4 text-sm font-bold text-slate-900 uppercase tracking-wider">Platform</h3>
                <ul className="space-y-3 text-sm text-slate-600">
                  <li><Link href="/our-services" className="hover:text-emerald-600 transition">{t('browse_services')}</Link></li>
                  <li><Link href="/contact" className="hover:text-emerald-600 transition">{t('contact')}</Link></li>
                  <li><Link href="/about" className="hover:text-emerald-600 transition">{t('about')}</Link></li>
                  <li><Link href={route("marketing.referral-program")} className="hover:text-emerald-600 transition">{t('referral_program')}</Link></li>
                </ul>
              </div>

              <div>
                <h3 className="mb-4 text-sm font-bold text-slate-900 uppercase tracking-wider">Legal</h3>
                <ul className="space-y-3 text-sm text-slate-600">
                  <li><Link href="/privacy-policy" className="hover:text-emerald-600 transition">{t('privacy')}</Link></li>
                  <li><Link href="/terms-of-service" className="hover:text-emerald-600 transition">{t('terms')}</Link></li>
                  <li><Link href="/help-center" className="hover:text-emerald-600 transition">{t('help_center')}</Link></li>
                </ul>
              </div>

              <div className="lg:col-span-1">
                <h3 className="mb-4 text-sm font-bold text-slate-900 uppercase tracking-wider">Stay Updated</h3>
                <p className="mb-3 text-sm text-slate-600">Get the latest platform updates and marketing tips.</p>
                <div className="flex gap-2">
                  <Input placeholder="Email address" className="bg-slate-50 text-sm" />
                  <Button size="icon" className="bg-zinc-900 shrink-0 text-white hover:bg-emerald-600 transition">
                    <FiArrowRight className="h-4 w-4" />
                  </Button>
                </div>
                <div className="mt-8">
                  <h4 className="mb-3 text-xs font-semibold text-slate-400 uppercase tracking-wider">Accepted Payments</h4>
                  <div className="flex flex-wrap gap-2">
                    <span className="inline-flex rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600">PAYNOW</span>
                    <span className="inline-flex rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600">ECOCASH</span>
                    <span className="inline-flex rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600">INNBUCKS</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="border-t border-slate-100 bg-slate-50 py-6 text-center text-xs text-slate-500">
            <p>© {new Date().getFullYear()} Zimbo Socials. All rights reserved.</p>
          </div>
        </footer>
      </div>
    </>
  )
}
