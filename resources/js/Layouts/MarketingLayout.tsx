import {
  NavigationMenu,
  NavigationMenuContent,
  NavigationMenuItem,
  NavigationMenuLink,
  NavigationMenuList,
  NavigationMenuTrigger,
  navigationMenuTriggerStyle,
} from "@/Components/ui/navigation-menu"
import { cn } from "@/lib/utils"
import { Head, Link } from "@inertiajs/react"
import { FiArrowRight, FiLogIn, FiMenu, FiZap, FiX } from "react-icons/fi"
import { PropsWithChildren, useState } from "react"

type MarketingLayoutProps = PropsWithChildren<{
  title?: string
  canAuth?: boolean
}>

export default function MarketingLayout({
  children,
  title = "Zimbo Social",
  canAuth = true,
}: MarketingLayoutProps) {
  const [mobileOpen, setMobileOpen] = useState(false)

  const primaryLinks = [
    { href: route("marketing.home"), name: "marketing.home", label: "Home" },
    { href: route("marketing.services"), name: "marketing.services", label: "Services" },
    { href: route("marketing.contact"), name: "marketing.contact", label: "Contact" },
    { href: route("marketing.about"), name: "marketing.about", label: "About" },
    { href: route("marketing.help"), name: "marketing.help", label: "Help" },
  ]

  const secondaryLinks = [
    { href: route("marketing.privacy"), name: "marketing.privacy", label: "Privacy" },
    { href: route("marketing.terms"), name: "marketing.terms", label: "Terms" },
  ]

  return (
    <>
      <Head title={title} />
      <div className="min-h-screen bg-white text-zinc-950">
        <div className="border-b border-amber-300 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600">
          <div className="mx-auto flex w-full max-w-7xl items-center justify-center gap-2 px-4 py-2 text-center text-xs font-semibold text-white sm:px-6 lg:px-8">
            <FiZap className="h-3.5 w-3.5 animate-pulse" />
            Faster fulfillment windows now available for select services.
          </div>
        </div>
        <header className="sticky top-0 z-50 border-b border-zinc-200 bg-white/95 backdrop-blur">
          <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <Link href="/" className="inline-flex items-center" aria-label="Zimbo Social Home">
              <img src="/images/zimbosocials.png" alt="Zimbo Social" className="h-9 w-auto transition-transform duration-200 hover:scale-[1.02] sm:h-10" />
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
                    <NavigationMenuTrigger className={cn("text-zinc-700 hover:bg-amber-50 hover:text-emerald-700", route().current("marketing.privacy") || route().current("marketing.terms") ? "border-emerald-200 bg-emerald-50 font-semibold text-emerald-700" : "")}>Legal</NavigationMenuTrigger>
                    <NavigationMenuContent>
                      <ul className="grid min-w-[200px] gap-1 p-2">
                        {secondaryLinks.map((link) => (
                          <li key={link.name}>
                            <NavigationMenuLink asChild>
                              <Link
                                href={link.href}
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
                          </li>
                        ))}
                      </ul>
                    </NavigationMenuContent>
                  </NavigationMenuItem>
                </NavigationMenuList>
              </NavigationMenu>
            </div>

            {canAuth && (
              <div className="hidden items-center gap-2 md:flex">
                <Link
                  href={route("login")}
                  className="inline-flex items-center gap-2 rounded-lg border border-zinc-900 px-3 py-2 text-sm font-semibold text-zinc-900 transition hover:-translate-y-0.5 hover:bg-zinc-950 hover:text-white hover:shadow-sm"
                >
                  <FiLogIn className="h-4 w-4" />
                  Login
                </Link>
                <Link
                  href={route("register")}
                  className="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                >
                  <FiArrowRight className="h-4 w-4" />
                  Get Started
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
                <p className="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Legal</p>
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
                    Login
                  </Link>
                  <Link
                    href={route("register")}
                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 px-3 py-2 text-center text-sm font-semibold text-white transition"
                    onClick={() => setMobileOpen(false)}
                  >
                    <FiArrowRight className="h-4 w-4" />
                    Get Started
                  </Link>
                </div>
              )}
            </div>
          </div>
        </header>

        <main>{children}</main>

        <footer className="mt-20 border-t border-slate-200 bg-white">
          <div className="mx-auto grid w-full max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
            <div>
              <img src="/images/zimbosocials.png" alt="Zimbo Social" className="mb-3 h-10 w-auto" />
              <p className="text-sm text-slate-600">
                Zimbabwe's trusted SMM growth platform for creators, brands, and marketers.
              </p>
            </div>

            <div>
              <h3 className="mb-3 text-sm font-semibold text-slate-900">Platform</h3>
              <ul className="space-y-2 text-sm text-slate-600">
                <li><Link href="/our-services" className="hover:text-emerald-700">Browse Services</Link></li>
                <li><Link href="/contact" className="hover:text-emerald-700">Contact</Link></li>
              </ul>
            </div>

            <div>
              <h3 className="mb-3 text-sm font-semibold text-slate-900">Company</h3>
              <ul className="space-y-2 text-sm text-slate-600">
                <li><Link href="/about" className="hover:text-emerald-700">About</Link></li>
                <li><Link href="/privacy-policy" className="hover:text-emerald-700">Privacy</Link></li>
                <li><Link href="/terms-of-service" className="hover:text-emerald-700">Terms</Link></li>
              </ul>
            </div>

            <div>
              <h3 className="mb-3 text-sm font-semibold text-slate-900">Support</h3>
              <ul className="space-y-2 text-sm text-slate-600">
                <li><Link href="/help-center" className="hover:text-emerald-700">Help Center</Link></li>
                <li><Link href="/login" className="hover:text-emerald-700">Client Login</Link></li>
              </ul>
            </div>
          </div>

          <div className="border-t border-slate-100 py-4 text-center text-xs text-slate-500">
            © {new Date().getFullYear()} Zimbo Social. All rights reserved.
          </div>
        </footer>
      </div>
    </>
  )
}
