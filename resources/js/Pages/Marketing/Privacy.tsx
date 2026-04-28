import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import MarketingLayout from "@/Layouts/MarketingLayout"
import { motion } from "framer-motion"
import { FiLock, FiMail, FiShield, FiUser } from "react-icons/fi"

const sections = [
  {
    title: "Information We Collect",
    body: "We may collect name, email, order history, account activity, and payment references required to operate and support the platform.",
    icon: <FiUser className="h-5 w-5 text-emerald-600" />,
    tone: "border-emerald-300 bg-emerald-50/40",
  },
  {
    title: "How We Use Information",
    body: "We use collected data to process orders, manage wallet funding, provide support, improve service quality, and detect abuse or fraud.",
    icon: <FiShield className="h-5 w-5 text-amber-500" />,
    tone: "border-amber-300 bg-amber-50/40",
  },
  {
    title: "Data Protection",
    body: "We apply reasonable organizational and technical safeguards to reduce risk and protect user account information and payment references.",
    icon: <FiLock className="h-5 w-5 text-red-600" />,
    tone: "border-red-300 bg-red-50/40",
  },
  {
    title: "Privacy Contact",
    body: "For privacy-related questions or requests, contact support@zimsocials.co.zw and include sufficient context for your request.",
    icon: <FiMail className="h-5 w-5 text-zinc-950" />,
    tone: "border-zinc-300 bg-white",
  },
]

const sectionViewport = { once: true, amount: 0.2 }

export default function PrivacyPage() {
  return (
    <MarketingLayout
      title="Privacy Policy - Zimbo Socials"
      description="Read how Zimbo Socials collects, uses, and protects personal and account data."
      seoPath="/privacy"
      keywords={["privacy policy", "data protection", "Zimbo Socials privacy"]}
    >
      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-zinc-950 via-emerald-700 to-zinc-900 text-white">
        <div className="mx-auto w-full max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]">
              <FiLock className="h-3.5 w-3.5" />
              Privacy Policy
            </p>
            <h1 className="text-4xl font-extrabold tracking-tight sm:text-5xl">How we collect, use, and protect your information.</h1>
            <p className="mt-4 max-w-2xl text-sm text-zinc-200 sm:text-base">A plain-language summary of the data we use to run the platform and support orders safely.</p>
          </motion.div>
        </div>
      </section>

      <section className="mx-auto w-full max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid gap-4 md:grid-cols-2">
          {sections.map((section) => (
            <motion.div key={section.title} initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.5 }} whileHover={{ y: -8 }}>
              <Card className={`${section.tone} h-full border shadow-sm hover:shadow-xl`}>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2 text-base text-zinc-950">{section.icon}{section.title}</CardTitle>
                  <CardDescription className="text-zinc-700">{section.body}</CardDescription>
                </CardHeader>
              </Card>
            </motion.div>
          ))}
        </div>
      </section>
    </MarketingLayout>
  )
}
