import { Card, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import MarketingLayout from "@/Layouts/MarketingLayout"
import { motion } from "framer-motion"
import { FiAlertTriangle, FiCheckCircle, FiFileText, FiLock, FiRefreshCcw, FiShield, FiUser, FiXCircle } from "react-icons/fi"
import { FaGavel } from "react-icons/fa6"

const terms = [
  {
    clause: "1",
    heading: "Definitions and Scope",
    title: "Account Responsibilities",
    body: "You must provide accurate account information, keep credentials secure, and remain responsible for all activity performed through your account.",
    icon: <FiUser className="h-5 w-5 text-emerald-600" />,
    tone: "border-emerald-300 bg-emerald-50/40",
  },
  {
    clause: "2",
    heading: "Lawful Use",
    title: "Lawful and Constitutional Use",
    body: "Use of this platform must comply with the laws of Zimbabwe and constitutional rights, including lawful conduct, dignity, privacy, and non-abuse of digital systems.",
    icon: <FiShield className="h-5 w-5 text-red-600" />,
    tone: "border-red-300 bg-red-50/40",
  },
  {
    clause: "3",
    heading: "Acceptable Use Restrictions",
    title: "Prohibited Conduct",
    body: "You may not use Zimbo Socials for fraud, misinformation campaigns, impersonation, unlawful political manipulation, hate speech, harassment, or any activity that violates platform rules or Zimbabwean law.",
    icon: <FiXCircle className="h-5 w-5 text-amber-500" />,
    tone: "border-amber-300 bg-amber-50/40",
  },
  {
    clause: "4",
    heading: "Commercial Terms",
    title: "Payments, Wallets, and Refunds",
    body: "Funds added to your wallet are applied to services on the platform. Refund decisions are discretionary, based on service state, delivery progress, and abuse-screening outcomes.",
    icon: <FiCheckCircle className="h-5 w-5 text-zinc-950" />,
    tone: "border-zinc-300 bg-white",
  },
  {
    clause: "5",
    heading: "Enforcement Rights",
    title: "Suspension and Termination Rights",
    body: "We may suspend, limit, or terminate accounts and reject or cancel orders where risk, non-compliance, policy breaches, or legal concerns are identified.",
    icon: <FiAlertTriangle className="h-5 w-5 text-red-600" />,
    tone: "border-red-300 bg-red-50/40",
  },
  {
    clause: "6",
    heading: "Liability Framework",
    title: "Limitation of Liability",
    body: "Services are provided on an as-available basis. To the maximum extent permitted by Zimbabwean law, Zimbo Socials is not liable for indirect, incidental, reputational, or consequential losses.",
    icon: <FiLock className="h-5 w-5 text-zinc-950" />,
    tone: "border-zinc-300 bg-white",
  },
  {
    clause: "7",
    heading: "User Indemnity",
    title: "Indemnity",
    body: "You agree to indemnify and hold Zimbo Socials, its team, and partners harmless against claims, damages, penalties, and legal costs resulting from your unlawful use or breach of these terms.",
    icon: <FiShield className="h-5 w-5 text-emerald-600" />,
    tone: "border-emerald-300 bg-emerald-50/40",
  },
  {
    clause: "8",
    heading: "Jurisdiction",
    title: "Governing Law and Disputes",
    body: "These terms are governed by the laws of Zimbabwe. Unless otherwise required by law, disputes fall under the jurisdiction of competent courts in Zimbabwe.",
    icon: <FaGavel className="h-5 w-5 text-amber-600" />,
    tone: "border-amber-300 bg-amber-50/40",
  },
  {
    clause: "9",
    heading: "Amendments",
    title: "Policy Updates",
    body: "We may update these terms to reflect legal, regulatory, operational, or security changes. Continued use after updates constitutes acceptance of the revised terms.",
    icon: <FiRefreshCcw className="h-5 w-5 text-emerald-600" />,
    tone: "border-emerald-300 bg-emerald-50/40",
  },
]

const sectionViewport = { once: true, amount: 0.2 }

export default function TermsPage() {
  return (
    <MarketingLayout
      title="Terms of Service - Zimbo Socials"
      description="Review Zimbo Socials terms of service, user obligations, and legal framework under Zimbabwean law."
      seoPath="/terms"
      keywords={["terms of service", "Zimbo Socials terms", "Zimbabwe digital services law"]}
    >
      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-red-600 via-zinc-950 to-amber-500 text-white">
        <div className="mx-auto w-full max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]">
              <FiFileText className="h-3.5 w-3.5" />
              Terms of Service
            </p>
            <h1 className="text-4xl font-extrabold tracking-tight sm:text-5xl">Platform rules for responsible use of Zimbo Socials.</h1>
            <p className="mt-4 max-w-2xl text-sm text-zinc-200 sm:text-base">These terms define legal use, risk allocation, and user obligations under Zimbabwean law and constitutional principles.</p>
            <p className="mt-3 max-w-2xl text-xs text-zinc-300">This page is a simplified summary of platform terms. For formal legal reliance, users should keep records of the latest published version.</p>
          </motion.div>
        </div>
      </section>

      <section className="mx-auto w-full max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {terms.map((term) => (
            <motion.div key={term.clause} initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.5 }} whileHover={{ y: -8 }}>
              <Card className={`${term.tone} h-full border shadow-sm hover:shadow-xl`}>
                <CardHeader>
                  <p className="mb-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Clause {term.clause}: {term.heading}</p>
                  <CardTitle className="flex items-center gap-2 text-base text-zinc-950">{term.icon}{term.title}</CardTitle>
                  <CardDescription className="text-zinc-700">{term.body}</CardDescription>
                </CardHeader>
              </Card>
            </motion.div>
          ))}
        </div>
      </section>
    </MarketingLayout>
  )
}
