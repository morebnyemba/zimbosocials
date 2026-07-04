import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import MarketingLayout from "@/Layouts/MarketingLayout"
import { motion } from "framer-motion"
import { Link } from "@inertiajs/react"
import { FiArrowRight, FiGift, FiTrendingUp, FiUsers, FiAward, FiShare2, FiDollarSign, FiClock } from "react-icons/fi"
import { FaTrophy, FaMedal } from "react-icons/fa"

const sectionViewport = { once: true, amount: 0.2 }

type Rates = {
  first_deposit_reward: number
  welcome_bonus_percent: number
  order_commission_percent: number
  order_commission_min_total: number
  min_qualifying_deposit: number
  lifetime_months: number
}

type Props = { rates: Rates }

const fmt = (n: number) => (Number.isInteger(n) ? String(n) : n.toFixed(2))

export default function ReferralProgramPage({ rates }: Props) {
  const structuredData: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    name: "Referral Program & Leaderboard",
    url: "https://zimsocials.co.zw/referral-program",
    description: "Earn rewards for every friend you refer to Zimbo Socials, and compete on the monthly leaderboard for referrals, orders, and deposits.",
  }

  return (
    <MarketingLayout
      title="Referral Program & Leaderboard - Zimbo Socials"
      description={`Earn $${fmt(rates.first_deposit_reward)} for every friend who deposits, plus ${fmt(rates.order_commission_percent)}% of their orders. Climb the monthly leaderboard for referrals, orders, and deposits.`}
      seoPath="/referral-program"
      keywords={["Zimbo Socials referral program", "refer and earn Zimbabwe", "social media growth leaderboard", "affiliate rewards Zimbabwe"]}
      structuredData={structuredData}
    >
      {/* Hero */}
      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-white via-emerald-50 to-amber-50">
        <div className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-zinc-950 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-zinc-950">
              <FiGift className="h-3.5 w-3.5 text-emerald-600" />
              Refer &amp; Earn
            </p>
            <h1 className="text-4xl font-extrabold tracking-tight text-zinc-950 sm:text-5xl">
              Share Zimbo Socials, <span className="text-emerald-600">earn real money</span>, and climb the leaderboard.
            </h1>
            <p className="mt-4 max-w-3xl text-sm text-zinc-700 sm:text-base">
              Every account gets a personal referral link. When someone signs up and deposits, you both get rewarded — and every order they place after that keeps paying you, for as long as your referral stays active.
            </p>
            <div className="mt-6 flex flex-wrap items-center gap-3">
              <Button render={<Link href={route("register")} />} nativeButton={false} className="gap-2 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white">
                Create Free Account
                <FiArrowRight className="h-4 w-4" />
              </Button>
              <Button render={<Link href={route("login")} />} nativeButton={false} variant="outline" className="border-zinc-950 text-zinc-950">
                I already have an account
              </Button>
            </div>
          </motion.div>
        </div>
      </section>

      {/* How referrals work */}
      <section className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="mb-8 text-center">
          <h2 className="text-2xl font-bold text-zinc-950 sm:text-3xl">How the referral program works</h2>
          <p className="mt-2 text-sm text-zinc-700">Three simple stages — and the rewards below update automatically if we ever adjust the rates.</p>
        </motion.div>

        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="grid gap-5 md:grid-cols-3">
          <Card className="border-emerald-200 bg-emerald-50/40 shadow-sm">
            <CardHeader>
              <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white"><FiShare2 className="h-5 w-5" /></div>
              <CardTitle className="text-base text-zinc-950">1. Share your link</CardTitle>
              <CardDescription className="text-zinc-700">
                Every account has a unique referral link from day one — no application, no waiting. Share it anywhere: WhatsApp, Instagram, Facebook, or direct.
              </CardDescription>
            </CardHeader>
          </Card>

          <Card className="border-amber-200 bg-amber-50/40 shadow-sm">
            <CardHeader>
              <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-white"><FiGift className="h-5 w-5" /></div>
              <CardTitle className="text-base text-zinc-950">2. They join &amp; deposit</CardTitle>
              <CardDescription className="text-zinc-700">
                When someone signs up with your link and makes a first deposit of <strong>${fmt(rates.min_qualifying_deposit)}</strong> or more, you earn <strong>${fmt(rates.first_deposit_reward)}</strong> instantly, and they get <strong>{fmt(rates.welcome_bonus_percent)}% extra</strong> credited to that same deposit.
              </CardDescription>
            </CardHeader>
          </Card>

          <Card className="border-red-200 bg-red-50/40 shadow-sm">
            <CardHeader>
              <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-red-600 text-white"><FiTrendingUp className="h-5 w-5" /></div>
              <CardTitle className="text-base text-zinc-950">3. Keep earning</CardTitle>
              <CardDescription className="text-zinc-700">
                From their second order onward (${fmt(rates.order_commission_min_total)}+ orders), you earn <strong>{fmt(rates.order_commission_percent)}%</strong> of every order they place — an ongoing commission, not a one-time bonus.
              </CardDescription>
            </CardHeader>
          </Card>
        </motion.div>

        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="mt-6 flex items-start gap-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
          <FiClock className="mt-0.5 h-5 w-5 shrink-0 text-zinc-500" />
          <p className="text-sm text-zinc-700">
            Each referral earns ongoing commissions for up to <strong>{rates.lifetime_months} months</strong> from the day they join — plenty of time for a real relationship to pay off. Full details, live status, and your personal link are on your Referrals dashboard once you're logged in.
          </p>
        </motion.div>
      </section>

      {/* Leaderboard */}
      <section className="bg-zinc-950 py-14 text-white">
        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="mb-8 text-center">
            <p className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-300">
              <FaTrophy className="h-3.5 w-3.5" />
              Monthly Leaderboard
            </p>
            <h2 className="text-2xl font-bold sm:text-3xl">Compete every month, get recognized for real results.</h2>
            <p className="mt-2 text-sm text-zinc-300">Rankings reset at the start of each month across three categories — everyone starts fresh.</p>
          </motion.div>

          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="grid gap-4 sm:grid-cols-3">
            <Card className="border-zinc-700 bg-zinc-900 text-white shadow-lg">
              <CardHeader>
                <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white"><FiUsers className="h-5 w-5" /></div>
                <CardTitle className="text-base text-white">Top Referrers</CardTitle>
                <CardDescription className="text-zinc-300">Ranked by how many people you bring who go on to deposit.</CardDescription>
              </CardHeader>
            </Card>
            <Card className="border-zinc-700 bg-zinc-900 text-white shadow-lg">
              <CardHeader>
                <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-white"><FiTrendingUp className="h-5 w-5" /></div>
                <CardTitle className="text-base text-white">Top Order Volume</CardTitle>
                <CardDescription className="text-zinc-300">Ranked by campaigns placed in the current month.</CardDescription>
              </CardHeader>
            </Card>
            <Card className="border-zinc-700 bg-zinc-900 text-white shadow-lg">
              <CardHeader>
                <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-red-600 text-white"><FiDollarSign className="h-5 w-5" /></div>
                <CardTitle className="text-base text-white">Top Depositors</CardTitle>
                <CardDescription className="text-zinc-300">Ranked by total wallet deposits in the current month.</CardDescription>
              </CardHeader>
            </Card>
          </motion.div>

          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="mt-6 flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 p-5">
            <FaMedal className="mt-0.5 h-5 w-5 shrink-0 text-amber-300" />
            <p className="text-sm text-zinc-300">
              Top finishers in each category win prizes when the month closes. Usernames are shown on the public leaderboard — never your real name or contact details — so you can compete with total privacy. Log in any time to see live standings and your own rank.
            </p>
          </motion.div>
        </div>
      </section>

      {/* Final CTA */}
      <section className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
          <Card className="border-zinc-950 bg-gradient-to-r from-zinc-950 via-red-600 to-emerald-600 text-white shadow-2xl">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-2xl font-bold text-white"><FiAward className="h-6 w-6" /> Ready to start earning?</CardTitle>
              <CardDescription className="text-white/90">
                Create your free account, grab your referral link, and start climbing the leaderboard today.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap gap-3">
                <Button render={<Link href={route("register")} />} nativeButton={false} className="bg-white text-zinc-950 hover:bg-amber-100">
                  Create Free Account
                </Button>
                <Button render={<Link href={route("marketing.services")} />} nativeButton={false} variant="outline" className="border-white text-white hover:bg-white hover:text-zinc-950">
                  Browse Services
                </Button>
              </div>
            </CardContent>
          </Card>
        </motion.div>
      </section>
    </MarketingLayout>
  )
}
