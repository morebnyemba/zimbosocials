import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import MarketingLayout from "@/Layouts/MarketingLayout"
import { motion } from "framer-motion"
import { Link } from "@inertiajs/react"
import { FiArrowRight, FiClock, FiCreditCard, FiHelpCircle, FiLock, FiUsers } from "react-icons/fi"

const faqs = [
  {
    q: "How long does delivery take?",
    a: "Most orders start within minutes. Full delivery depends on service type, quantity, and current fulfillment load.",
    icon: <FiClock className="h-5 w-5 text-emerald-600" />,
  },
  {
    q: "How do I deposit funds?",
    a: "Visit Contact and Payment Info, choose a payment method, and follow the listed instructions and reference steps.",
    icon: <FiCreditCard className="h-5 w-5 text-amber-500" />,
  },
  {
    q: "Can I become a marketer?",
    a: "Yes. Register, complete your account, and use the marketer dashboard to accept campaign contracts when eligible.",
    icon: <FiUsers className="h-5 w-5 text-red-600" />,
  },
  {
    q: "Do you require my password?",
    a: "No. We never ask for social media passwords. Orders should only require links, quantities, and account-safe inputs.",
    icon: <FiLock className="h-5 w-5 text-zinc-950" />,
  },
]

const sectionViewport = { once: true, amount: 0.2 }

export default function HelpPage() {
  return (
    <MarketingLayout title="Help Center - Zimbo Social">
      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-white via-amber-50 to-emerald-50">
        <div className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-zinc-950 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-zinc-950">
              <FiHelpCircle className="h-3.5 w-3.5 text-red-600" />
              Help Center
            </p>
            <h1 className="text-4xl font-extrabold tracking-tight text-zinc-950 sm:text-5xl">Find the answers that unblock growth faster.</h1>
            <p className="mt-4 max-w-2xl text-sm text-zinc-700 sm:text-base">Common answers about delivery, deposits, marketer onboarding, and account safety.</p>
          </motion.div>
        </div>
      </section>

      <section className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="grid gap-4 sm:grid-cols-2">
          {faqs.map((faq, index) => (
            <motion.div key={faq.q} whileHover={{ y: -8 }} transition={{ duration: 0.2 }}>
              <Card className={`${index % 3 === 0 ? "border-emerald-300 bg-emerald-50/40" : index % 3 === 1 ? "border-amber-300 bg-amber-50/40" : "border-red-300 bg-red-50/40"} h-full border shadow-sm hover:shadow-xl`}>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2 text-base text-zinc-950">{faq.icon}{faq.q}</CardTitle>
                  <CardDescription className="text-zinc-700">{faq.a}</CardDescription>
                </CardHeader>
              </Card>
            </motion.div>
          ))}
        </motion.div>

        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="mt-10 rounded-2xl border border-zinc-950 bg-zinc-950 p-6 text-white shadow-xl">
          <h2 className="text-2xl font-bold">Still need help?</h2>
          <p className="mt-2 max-w-2xl text-sm text-zinc-300">If your issue is payment-related or tied to a specific order, use the contact page and share the exact reference or order ID.</p>
          <div className="mt-5 flex flex-wrap gap-3">
            <Link href={route("marketing.contact")}>
              <Button className="gap-2 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white">
                Contact Support
                <FiArrowRight className="h-4 w-4" />
              </Button>
            </Link>
            <Link href={route("register")}>
              <Button variant="outline" className="border-white text-white hover:bg-white hover:text-zinc-950">Create Account</Button>
            </Link>
          </div>
        </motion.div>
      </section>
    </MarketingLayout>
  )
}
