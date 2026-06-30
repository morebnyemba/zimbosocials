import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import MarketingLayout from "@/Layouts/MarketingLayout"
import { motion } from "framer-motion"
import { Link } from "@inertiajs/react"
import { FiArrowRight, FiBriefcase, FiLayers, FiTarget, FiUsers } from "react-icons/fi"

const sectionViewport = { once: true, amount: 0.2 }

export default function AboutPage() {
  const aboutStructuredData: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "AboutPage",
    name: "About Zimbo Socials",
    url: "https://zimsocials.co.zw/about",
    description: "Learn about the mission of Zimbo Socials to support creators, brands, and marketers in Zimbabwe.",
  }

  return (
    <MarketingLayout
      title="About Zimbo Socials"
      description="Learn how Zimbo Socials helps creators, businesses, and marketers in Zimbabwe grow with reliable social media services."
      seoPath="/about"
      keywords={["about Zimbo Socials", "Zimbabwe social media platform", "creator growth Zimbabwe"]}
      structuredData={aboutStructuredData}
    >
      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-white via-emerald-50 to-amber-50">
        <div className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-zinc-950 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-zinc-950">
              <FiTarget className="h-3.5 w-3.5 text-emerald-600" />
              Our Story
            </p>
            <h1 className="text-4xl font-extrabold tracking-tight text-zinc-950 sm:text-5xl">Built for Zimbabwean creators, brands, marketers, and growth teams.</h1>
            <p className="mt-4 max-w-3xl text-sm text-zinc-700 sm:text-base">
              Zimbo Socials exists to make digital growth more accessible through transparent services, dependable support, and a platform that understands local campaign realities.
            </p>
          </motion.div>
        </div>
      </section>

      <section className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
          <Card className="border-zinc-950 bg-zinc-950 text-white shadow-xl">
            <CardHeader>
              <CardTitle className="text-white">Our Mission</CardTitle>
              <CardDescription className="text-zinc-300">Accessible growth, fair pricing, and trustworthy delivery.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm text-white/90">
              <p>
                We design social growth services for real business use: clearer pricing, safer fulfillment expectations, and support that understands what campaign operators actually need.
              </p>
              <p>
                Instead of treating growth like a one-click commodity, we focus on stable execution, transparent quantity bands, and better visibility across the full order flow.
              </p>
              <Button render={<Link href={route("register")} />} nativeButton={false} className="mt-2 gap-2 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white">
                Join the Platform
                <FiArrowRight className="h-4 w-4" />
              </Button>
            </CardContent>
          </Card>

          <div className="grid gap-4 sm:grid-cols-2">
            {[
              { title: "Creators", desc: "Grow visibility, reach, and engagement across fast-moving platforms.", icon: <FiUsers className="h-5 w-5 text-emerald-600" /> },
              { title: "Businesses", desc: "Run targeted awareness campaigns with clearer service options and reporting.", icon: <FiTarget className="h-5 w-5 text-red-600" /> },
              { title: "Marketers", desc: "Take contracts, execute campaigns, and track opportunities from one place.", icon: <FiBriefcase className="h-5 w-5 text-amber-500" /> },
              { title: "Agencies", desc: "Coordinate multiple client campaigns with more consistent service delivery.", icon: <FiLayers className="h-5 w-5 text-zinc-950" /> },
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

        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }} className="mt-12 grid gap-4 md:grid-cols-3">
          {[
            { title: "Transparent Rates", body: "Price clarity is central to our design. Users should understand what they pay for and what quantity ranges apply.", tone: "border-emerald-300 bg-emerald-50/40" },
            { title: "Dependable Support", body: "We emphasize responsive assistance because most campaign blockers happen after checkout, not before it.", tone: "border-amber-300 bg-amber-50/40" },
            { title: "Sustainable Growth", body: "We prefer delivery patterns and account practices that support longer-term platform health over short-term spikes.", tone: "border-red-300 bg-red-50/40" },
          ].map((item) => (
            <motion.div key={item.title} whileHover={{ y: -8 }} transition={{ duration: 0.2 }}>
              <Card className={`${item.tone} h-full border shadow-sm hover:shadow-xl`}>
                <CardHeader>
                  <CardTitle className="text-base text-zinc-950">{item.title}</CardTitle>
                  <CardDescription className="text-zinc-700">{item.body}</CardDescription>
                </CardHeader>
              </Card>
            </motion.div>
          ))}
        </motion.div>
      </section>
    </MarketingLayout>
  )
}
