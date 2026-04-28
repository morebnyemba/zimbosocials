import { Button } from "@/Components/ui/button"
import { Card, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import MarketingLayout from "@/Layouts/MarketingLayout"
import { PageProps } from "@/types"
import { motion } from "framer-motion"
import { Link, usePage } from "@inertiajs/react"
import { FiArrowRight, FiCheckCircle, FiTrendingUp, FiZap } from "react-icons/fi"
import { FaFacebookF, FaInstagram, FaTelegram, FaTiktok, FaWhatsapp, FaXTwitter, FaYoutube } from "react-icons/fa6"

type Service = {
  id: number
  name: string
  name_sn?: string | null
  category: string
}

type Props = {
  services: Record<string, Service[]>
}

const sectionViewport = { once: true, amount: 0.2 }

function platformIcon(category: string) {
  switch (category.toLowerCase()) {
    case "instagram":
      return <FaInstagram className="h-5 w-5" />
    case "youtube":
      return <FaYoutube className="h-5 w-5" />
    case "facebook":
      return <FaFacebookF className="h-5 w-5" />
    case "twitter":
      return <FaXTwitter className="h-5 w-5" />
    case "telegram":
      return <FaTelegram className="h-5 w-5" />
    case "tiktok":
      return <FaTiktok className="h-5 w-5" />
    case "whatsapp":
      return <FaWhatsapp className="h-5 w-5" />
    default:
      return <FiTrendingUp className="h-5 w-5" />
  }
}

function categoryLabel(category: string) {
  if (category === "twitter") return "X / Twitter"
  if (category === "whatsapp") return "WhatsApp"
  return category.charAt(0).toUpperCase() + category.slice(1)
}

function serviceDescription(name: string, category: string): string {
  const n = name.toLowerCase()
  if (n.includes("follower") || n.includes("subscriber")) return `Grow your ${categoryLabel(category)} audience with follower and subscriber delivery that strengthens social proof.`
  if (n.includes("like")) return `Increase engagement signals on your posts to improve algorithmic visibility and credibility.`
  if (n.includes("view")) return `Drive view counts and push your content further up recommendation feeds.`
  if (n.includes("comment")) return `Stimulate social conversation and activity around your content with comment signals.`
  if (n.includes("share")) return `Amplify content distribution with share activity that extends organic reach.`
  if (n.includes("watch") || n.includes("hour")) return `Build watch-time metrics that help content qualify for platform monetization thresholds.`
  if (n.includes("save") || n.includes("bookmark")) return `Boost post saves and bookmarks to improve long-tail content visibility.`
  if (n.includes("story")) return `Increase story views and interactions to maintain consistent audience touchpoints.`
  if (n.includes("reel")) return `Push reel reach and views to grow short-form video performance.`
  return `Targeted growth service for ${categoryLabel(category)} \u2014 boost presence and engagement signals.`
}

const categoryDescriptions: Record<string, { headline: string; body: string }> = {
  instagram: { headline: "Instagram Growth", body: "Followers, likes, views, story activity, and reel boosts \u2014 every metric that matters on Instagram." },
  youtube: { headline: "YouTube Performance", body: "Subscribers, views, watch hours, and likes that help channels grow and qualify for monetization." },
  facebook: { headline: "Facebook Reach", body: "Page likes, post reach, shares, and comment activity to build a stronger Facebook presence." },
  twitter: { headline: "X (Twitter) Momentum", body: "Followers, retweets, likes, and impressions that accelerate authority on the platform." },
  telegram: { headline: "Telegram Community", body: "Channel members, post views, and reactions \u2014 everything needed to build a credible Telegram community." },
  tiktok: { headline: "TikTok Virality", body: "Followers, video views, and likes designed to push TikTok content into discovery and the For You feed." },
  whatsapp: { headline: "WhatsApp Channel Growth", body: "Channel follower growth for brands and creators building recurring reach inside WhatsApp." },
}

const audienceDeliverables = [
  {
    audience: "Individuals",
    subtitle: "For creators and personal brands",
    tone: "border-emerald-300 bg-emerald-50/60",
    points: [
      "Follower and subscriber growth packages",
      "Post and video engagement boosts (likes, views, comments)",
      "Story and reel visibility support",
      "Safer, paced delivery aligned with account growth goals",
    ],
  },
  {
    audience: "Businesses",
    subtitle: "For brands, SMEs, and agencies",
    tone: "border-amber-300 bg-amber-50/60",
    points: [
      "Campaign-focused social growth across platforms",
      "Higher-volume options for product and promo launches",
      "Engagement support for brand credibility and conversions",
      "Scalable delivery for recurring marketing cycles",
    ],
  },
  {
    audience: "Marketers",
    subtitle: "For approved marketer accounts",
    tone: "border-red-300 bg-red-50/60",
    points: [
      "Access to business campaign contracts after admin approval",
      "Clear execution requirements for each accepted contract",
      "Tools to deliver campaign tasks across supported platforms",
      "Contract completion tracking for payout processing",
    ],
  },
]

export default function ServicesPage() {
  const { props } = usePage<PageProps<Props>>()

  const servicesStructuredData: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: "Zimbo Socials Service Catalog",
    itemListElement: Object.values(props.services)
      .flat()
      .slice(0, 30)
      .map((service, index) => ({
        "@type": "ListItem",
        position: index + 1,
        name: service.name,
      })),
  }

  return (
    <MarketingLayout
      title="Social Media Services Catalog - Zimbo Socials"
      description="Explore Zimbabwe-focused social media services across Instagram, YouTube, TikTok, Facebook, X, Telegram, and WhatsApp with clear quantity ranges."
      seoPath="/services"
      keywords={["SMM services Zimbabwe", "Instagram services", "YouTube subscribers", "WhatsApp channel followers", "social media packages"]}
      structuredData={servicesStructuredData}
    >
      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-zinc-950 via-red-600 to-emerald-600 text-white">
        <div className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
          <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={sectionViewport} transition={{ duration: 0.6 }}>
            <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]">
              <FiZap className="h-3.5 w-3.5" />
              Service Catalog
            </p>
            <h1 className="max-w-4xl text-4xl font-extrabold tracking-tight sm:text-5xl">What we deliver for individuals, businesses, and marketers.</h1>
            <p className="mt-4 max-w-2xl text-sm text-white/85 sm:text-base">
              Start with your user path, then explore platform services. Pricing and order limits become visible after free registration.
            </p>
            <div className="mt-8 grid gap-4 sm:grid-cols-3">
              {[
                { label: "Platforms Covered", value: Object.keys(props.services).length.toString() },
                { label: "Service Types", value: Object.values(props.services).reduce((t, g) => t + g.length, 0).toString() },
                { label: "Delivery Speed", value: "Minutes" },
              ].map((stat) => (
                <div key={stat.label} className="rounded-xl border border-white/20 bg-black/15 px-4 py-4 backdrop-blur">
                  <p className="text-xs uppercase tracking-[0.16em] text-white/70">{stat.label}</p>
                  <p className="mt-1 text-2xl font-bold">{stat.value}</p>
                </div>
              ))}
            </div>
          </motion.div>
        </div>
      </section>

      <section className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="space-y-10">
          <motion.section
            initial={{ opacity: 0, y: 28 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={sectionViewport}
            transition={{ duration: 0.6 }}
            className="space-y-5"
          >
            <div className="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
              <h2 className="text-2xl font-bold text-zinc-950">What We Deliver By Account Type</h2>
              <p className="mt-1 text-sm text-zinc-600">Choose the path that matches your goals. Your account type shapes your onboarding and platform experience.</p>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
              {audienceDeliverables.map((item) => (
                <Card key={item.audience} className={`${item.tone} h-full border shadow-sm`}>
                  <CardHeader>
                    <CardDescription className="text-xs uppercase tracking-[0.14em] text-zinc-600">{item.subtitle}</CardDescription>
                    <CardTitle className="text-lg text-zinc-950">{item.audience}</CardTitle>
                    <div className="space-y-2 pt-1 text-sm text-zinc-700">
                      {item.points.map((point) => (
                        <p key={point} className="flex items-start gap-2">
                          <FiCheckCircle className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                          <span>{point}</span>
                        </p>
                      ))}
                    </div>
                  </CardHeader>
                </Card>
              ))}
            </div>
          </motion.section>

          {Object.entries(props.services).map(([category, group], sectionIndex) => {
            const meta = categoryDescriptions[category.toLowerCase()] ?? { headline: categoryLabel(category) + " Services", body: "" }
            return (
            <motion.section
              key={category}
              initial={{ opacity: 0, y: 28 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={sectionViewport}
              transition={{ duration: 0.6, delay: sectionIndex * 0.04 }}
              className="space-y-5"
            >
              <div className="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <span className="mb-3 inline-flex items-center gap-2 rounded-full bg-zinc-950 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-white">
                    {platformIcon(category)}
                    {categoryLabel(category)}
                  </span>
                  <h2 className="text-2xl font-bold text-zinc-950">{categoryLabel(category)} Services</h2>
                  <h2 className="text-2xl font-bold text-zinc-950">{meta.headline}</h2>
                  {meta.body && <p className="mt-1 text-sm text-zinc-600">{meta.body}</p>}
                </div>
                <Link href={route("register")}>
                  <Button className="gap-2 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white">
                    Register to Order
                    <FiArrowRight className="h-4 w-4" />
                  </Button>
                </Link>
              </div>

              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {group.map((service, index) => (
                  <motion.div key={service.id} whileHover={{ y: -8 }} transition={{ duration: 0.2 }}>
                    <Card className={`${index % 3 === 0 ? "border-emerald-300 bg-emerald-50/50" : index % 3 === 1 ? "border-amber-300 bg-amber-50/50" : "border-red-300 bg-red-50/50"} h-full border shadow-sm transition-shadow hover:shadow-xl`}>
                      <CardHeader>
                        <CardDescription className="text-xs uppercase tracking-[0.14em] text-zinc-600">{categoryLabel(category)}</CardDescription>
                        <CardTitle className="line-clamp-2 text-base text-zinc-950">{service.name}</CardTitle>
                          <p className="mt-2 text-xs leading-relaxed text-zinc-600">{serviceDescription(service.name, category)}</p>
                          <div className="mt-3 flex flex-wrap gap-2">
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-800"><FiCheckCircle className="h-3 w-3" /> Fast Delivery</span>
                            <span className="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-1 text-[10px] font-semibold text-zinc-700"><FiZap className="h-3 w-3" /> Safe</span>
                          </div>
                      </CardHeader>
                    </Card>
                  </motion.div>
                ))}
              </div>
            </motion.section>
            )
          })}

          <div className="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-5 text-center text-sm text-zinc-600">
            Pricing, minimum and maximum quantities are visible after{" "}
            <Link href={route("register")} className="font-semibold text-zinc-950 underline underline-offset-2 hover:text-emerald-700">
              creating a free account
            </Link>.
          </div>
        </div>
      </section>
    </MarketingLayout>
  )
}
