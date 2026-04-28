import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import MarketingLayout from "@/Layouts/MarketingLayout"
import { motion } from "framer-motion"
import { Link } from "@inertiajs/react"
import { FiClock, FiMail, FiMessageCircle, FiShield, FiUser, FiZap } from "react-icons/fi"

const sectionViewport = { once: true, amount: 0.2 }

export default function ContactPage() {
  return (
    <MarketingLayout title="Zimbo Social - Contact">
      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-amber-50 via-white to-red-50">
        <div className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-zinc-950 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-zinc-950">
              <FiMessageCircle className="h-3.5 w-3.5 text-red-600" />
              Contact Us
            </p>
            <h1 className="text-4xl font-extrabold tracking-tight text-zinc-950 sm:text-5xl">Real support when you need it, every step of the way.</h1>
            <p className="mt-4 max-w-2xl text-sm text-zinc-700 sm:text-base">
              Need help with orders, your account, or getting started? Our team is available every day to help you move faster.
            </p>
          </motion.div>
        </div>
      </section>

      <section className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
          <Card className="border-zinc-950 bg-zinc-950 text-white shadow-xl">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-white"><FiMail className="h-5 w-5 text-emerald-400" /> Support Desk</CardTitle>
              <CardDescription className="text-zinc-300">Get direct assistance for deposits, orders, and delivery updates.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div className="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <p className="inline-flex items-center gap-2 font-semibold text-white"><FiMail className="h-4 w-4 text-amber-300" /> support@zimsocials.co.zw</p>
              </div>
              <div className="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <p className="inline-flex items-center gap-2 font-semibold text-white"><FiClock className="h-4 w-4 text-red-400" /> Mon-Sun, 08:00 - 22:00 CAT</p>
              </div>
              <div className="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <p className="inline-flex items-center gap-2 font-semibold text-white"><FiShield className="h-4 w-4 text-emerald-400" /> Account passwords are never required</p>
              </div>
              <Link href={route("register")}>
                <Button className="mt-2 w-full bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white">Create Account</Button>
              </Link>
            </CardContent>
          </Card>

          <div className="grid gap-4 sm:grid-cols-2">
            {[
              { title: "Order Support", desc: "Questions about delivery timelines, order status, or service types? We respond same-day.", icon: <FiZap className="h-5 w-5 text-emerald-600" /> },
              { title: "Response Time", desc: "Most queries are answered within a few hours during operating hours.", icon: <FiClock className="h-5 w-5 text-amber-500" /> },
              { title: "Human Support", desc: "Real people handling payment questions, order issues, and account recommendations.", icon: <FiUser className="h-5 w-5 text-red-600" /> },
              { title: "Safe & Confidential", desc: "We never ask for passwords. All support interactions are private and secure.", icon: <FiShield className="h-5 w-5 text-zinc-950" /> },
            ].map((item) => (
              <motion.div key={item.title} whileHover={{ y: -8 }} transition={{ duration: 0.2 }}>
                <Card className="h-full border-zinc-200 shadow-sm hover:shadow-xl">
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base text-zinc-950">{item.icon}{item.title}</CardTitle>
                    <CardDescription className="text-zinc-700">{item.desc}</CardDescription>
                  </CardHeader>
                </Card>
              </motion.div>
            ))}
          </div>
        </motion.div>
      </section>
    </MarketingLayout>
  )
}
