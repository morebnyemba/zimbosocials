import MarketingLayout from "@/Layouts/MarketingLayout"
import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card"
import { PageProps } from "@/types"
import { motion } from "framer-motion"
import { Link, usePage } from "@inertiajs/react"
import { useEffect, useState } from "react"
import {
  FiArrowRight,
  FiBarChart2,
  FiBell,
  FiBriefcase,
  FiCheckCircle,
  FiClock,
  FiDollarSign,
  FiEye,
  FiLock,
  FiRadio,
  FiShield,
  FiTrendingUp,
  FiUsers,
  FiZap,
} from "react-icons/fi"
import {
  FaFacebookF,
  FaInstagram,
  FaTelegram,
  FaTiktok,
  FaWhatsapp,
  FaXTwitter,
  FaYoutube,
} from "react-icons/fa6"

type Service = {
  id: number
  name: string
  name_sn?: string | null
  category: string
}

type Props = {
  activityServices: Service[]
  categories: string[]
}

type LiveActivity = {
  id: string
  serviceId: number
  buyer: string
  town: string
  service: string
  category: string
  quantity: number
  timeAgo: string
  action: string
}

const sectionViewport = { once: true, amount: 0.2 }

const sectionVariants = {
  hidden: { opacity: 0, y: 36 },
  visible: {
    opacity: 1,
    y: 0,
    transition: {
      duration: 0.7,
      staggerChildren: 0.1,
      delayChildren: 0.08,
    },
  },
}

const itemVariants = {
  hidden: { opacity: 0, y: 24, scale: 0.98 },
  visible: {
    opacity: 1,
    y: 0,
    scale: 1,
    transition: { duration: 0.55 },
  },
}

const shonaNames = [
  "Tendai",
  "Rudo",
  "Nyasha",
  "Tafadzwa",
  "Rutendo",
  "Tadiwa",
  "Munashe",
  "Anesu",
  "Tanaka",
  "Kudakwashe",
  "Ropafadzo",
  "Simbarashe",
  "Chiedza",
  "Farai",
  "Shingirai",
  "Vimbai",
  "Tinashe",
  "Mufaro",
  "Tawananyasha",
  "Tatenda",
  "Tonderai",
  "Tariro",
  "Tsitsi",
  "Tapiwa",
  "Blessing",
  "Panashe",
  "Takudzwa",
  "Nomsa",
  "Memory",
  "Ashley",
  "Rumbidzai",
  "Kundai",
  "Loveness",
  "Virginia",
  "Fadzai",
  "Priscilla",
  "Taurai",
  "Melody",
  "Shamiso",
  "Takunda",
  "Wadzi",
  "Yamurai",
  "Munyaradzi",
  "Chenai",
  "Rangarirai",
  "Dzimbanhete",
  "Tafara",
  "Tashinga",
  "Vongai",
  "Nyaradzo",
  "Ruramai",
  "Tendekai",
  "Makanaka",
  "Tinotenda",
  "Kudzai",
  "Marvellous",
  "Ratidzo",
  "Yeukai",
]

const zimbabweTowns = [
  "Harare",
  "Chitungwiza",
  "Mutare",
  "Gweru",
  "Masvingo",
  "Kadoma",
  "Marondera",
  "Bindura",
  "Chegutu",
  "Kwekwe",
  "Norton",
  "Rusape",
  "Karoi",
  "Chipinge",
  "Zvishavane",
  "Redcliff",
  "Shurugwi",
  "Mvurwi",
  "Gokwe",
  "Hwange",
]

const timePool = ["just now", "8 sec ago", "14 sec ago", "22 sec ago", "41 sec ago", "1 min ago", "2 min ago", "3 min ago", "5 min ago"]
const activityWindowSize = 5
const categoryWeights: Record<string, number> = {
  instagram: 1.25,
  tiktok: 1.22,
  youtube: 1.16,
  facebook: 1.08,
  telegram: 1.04,
  twitter: 0.96,
  whatsapp: 0.92,
}
const fallbackServices: Service[] = [
  { id: 1, name: "Instagram Followers", name_sn: "Instagram Followers", category: "instagram" },
  { id: 2, name: "TikTok Views", name_sn: "TikTok Views", category: "tiktok" },
  { id: 3, name: "YouTube Likes", name_sn: "YouTube Likes", category: "youtube" },
  { id: 4, name: "Facebook Page Likes", name_sn: "Facebook Page Likes", category: "facebook" },
  { id: 5, name: "Telegram Members", name_sn: "Telegram Members", category: "telegram" },
]

type WeightedItem<T> = {
  item: T
  weight: number
}

type ServiceProfile = {
  weight: number
  quantityOptions: number[]
  actionPhrases: string[]
}

function pickRandom<T>(items: T[]): T {
  return items[Math.floor(Math.random() * items.length)]
}

function pickWithCooldown<T>(items: T[], recent: T[]): T {
  const freshItems = items.filter((item) => !recent.includes(item))
  return pickRandom(freshItems.length > 0 ? freshItems : items)
}

function pickWeighted<T>(items: Array<WeightedItem<T>>): T {
  const totalWeight = items.reduce((sum, entry) => sum + entry.weight, 0)
  let cursor = Math.random() * totalWeight

  for (const entry of items) {
    cursor -= entry.weight
    if (cursor <= 0) {
      return entry.item
    }
  }

  return items[items.length - 1].item
}

function pickWeightedQuantity(quantityOptions: number[]): number {
  const middleIndex = (quantityOptions.length - 1) / 2

  return pickWeighted(quantityOptions.map((quantity, index) => ({
    item: quantity,
    weight: Math.max(1, quantityOptions.length - Math.abs(index - middleIndex) * 1.4),
  })))
}

function getServiceProfile(service: Service): ServiceProfile {
  const name = `${service.name} ${service.name_sn ?? ""}`.toLowerCase()
  const categoryWeight = categoryWeights[service.category.toLowerCase()] ?? 1
  let weight = 1.2 * categoryWeight
  let quantityOptions = [100, 250, 500, 1000, 1500]
  let actionPhrases = ["just ordered", "is boosting", "queued up"]

  if (name.includes("view") || name.includes("watch")) {
    weight += 4.8
    quantityOptions = [500, 1000, 2000, 5000, 10000, 15000]
    actionPhrases = ["is pushing", "just boosted", "started a run for"]
  } else if (name.includes("follower") || name.includes("subscriber")) {
    weight += 4.2
    quantityOptions = [50, 100, 250, 500, 1000, 2000]
    actionPhrases = ["is growing with", "just ordered", "is building momentum with"]
  } else if (name.includes("like") || name.includes("heart")) {
    weight += 3.7
    quantityOptions = [100, 250, 500, 1000, 1500, 2500]
    actionPhrases = ["is topping up", "just boosted", "queued up"]
  } else if (name.includes("comment")) {
    weight += 2.6
    quantityOptions = [10, 20, 30, 50, 75, 100, 150]
    actionPhrases = ["is sparking chatter with", "just ordered", "is warming up"]
  } else if (name.includes("share") || name.includes("retweet")) {
    weight += 2.9
    quantityOptions = [25, 50, 100, 200, 300, 500]
    actionPhrases = ["is widening reach with", "queued up", "just pushed"]
  } else if (name.includes("member") || name.includes("join")) {
    weight += 3.1
    quantityOptions = [50, 100, 250, 500, 1000, 1500]
    actionPhrases = ["is filling up", "just ordered", "is growing"]
  }

  if (name.includes("real") || name.includes("premium")) {
    weight += 1.1
  }

  if (name.includes("instant") || name.includes("fast")) {
    weight += 0.6
  }

  return {
    weight,
    quantityOptions,
    actionPhrases,
  }
}

function createLiveActivity(pool: Service[], previousActivities: LiveActivity[]): LiveActivity {
  const services = pool.length > 0 ? pool : fallbackServices
  const recentServiceIds = previousActivities.slice(0, 3).map((activity) => activity.serviceId)
  const recentBuyers = previousActivities.slice(0, 4).map((activity) => activity.buyer)
  const recentTowns = previousActivities.slice(0, 3).map((activity) => activity.town)

  const weightedServices = services.map((service) => {
    const profile = getServiceProfile(service)
    const repetitionPenalty = recentServiceIds.includes(service.id) ? 0.14 : 1

    return {
      item: service,
      weight: Math.max(profile.weight * repetitionPenalty, 0.08),
    }
  })

  const service = pickWeighted(weightedServices)
  const profile = getServiceProfile(service)
  const buyer = pickWithCooldown(shonaNames, recentBuyers)
  const town = pickWithCooldown(zimbabweTowns, recentTowns)
  const quantity = pickWeightedQuantity(profile.quantityOptions)
  const action = pickRandom(profile.actionPhrases)
  const timeAgo = pickRandom(timePool)

  return {
    id: `${service.id}-${buyer}-${town}-${Date.now()}`,
    serviceId: service.id,
    buyer,
    town,
    service: service.name_sn || service.name,
    category: service.category,
    quantity,
    timeAgo,
    action,
  }
}

function createInitialLiveActivities(pool: Service[], count: number): LiveActivity[] {
  let activities: LiveActivity[] = []

  for (let index = 0; index < count; index += 1) {
    activities = [createLiveActivity(pool, activities), ...activities]
  }

  return activities
}

export default function Home() {
  const { props } = usePage<PageProps<Props>>()
  const servicePool = props.activityServices.length > 0 ? props.activityServices : fallbackServices
  const [liveActivities, setLiveActivities] = useState<LiveActivity[]>(() => (
    createInitialLiveActivities(servicePool, activityWindowSize)
  ))

  const homeStructuredData: Array<Record<string, unknown>> = [
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      name: "Zimbo Socials",
      url: "https://zimsocials.co.zw",
      potentialAction: {
        "@type": "ViewAction",
        target: "https://zimsocials.co.zw/services",
      },
    },
    {
      "@context": "https://schema.org",
      "@type": "ItemList",
      name: "Popular Social Growth Services",
      itemListElement: servicePool.slice(0, 8).map((service, index) => ({
        "@type": "ListItem",
        position: index + 1,
        name: service.name,
      })),
    },
  ]

  useEffect(() => {
    setLiveActivities(createInitialLiveActivities(servicePool, activityWindowSize))

    const intervalId = window.setInterval(() => {
      setLiveActivities((current) => [
        createLiveActivity(servicePool, current),
        ...current,
      ].slice(0, activityWindowSize))
    }, 1800)

    return () => window.clearInterval(intervalId)
  }, [servicePool])

  const categoryLabel = (category: string) => {
    if (category === "twitter") return "X / Twitter"
    if (category === "whatsapp") return "WhatsApp"
    return category.charAt(0).toUpperCase() + category.slice(1)
  }

  const categoryIcon = (category: string) => {
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

  return (
    <MarketingLayout
      title="Zimbo Socials - Zimbabwe's #1 SMM Growth Platform"
      description="Zimbabwe's trusted SMM platform for Instagram, TikTok, YouTube, Facebook, X, Telegram, and WhatsApp channel growth with transparent pricing and fast support."
      seoPath="/"
      keywords={["Zimbabwe SMM", "social media growth", "Instagram followers", "TikTok views", "WhatsApp channel followers"]}
      structuredData={homeStructuredData}
    >

      <section className="relative overflow-hidden border-b border-zinc-950 bg-gradient-to-br from-white via-amber-50 to-emerald-50">
        <motion.div
          animate={{ y: [0, -10, 0], scale: [1, 1.04, 1] }}
          transition={{ duration: 8, repeat: Infinity, ease: "easeInOut" }}
          className="pointer-events-none absolute -top-24 left-8 h-72 w-72 rounded-full bg-emerald-300/40 blur-3xl"
        />
        <motion.div
          animate={{ y: [0, 14, 0], x: [0, -12, 0] }}
          transition={{ duration: 10, repeat: Infinity, ease: "easeInOut" }}
          className="pointer-events-none absolute right-0 top-10 h-72 w-72 rounded-full bg-red-300/30 blur-3xl"
        />
        <motion.div
          animate={{ y: [0, -8, 0], x: [0, 10, 0] }}
          transition={{ duration: 9, repeat: Infinity, ease: "easeInOut" }}
          className="pointer-events-none absolute bottom-0 left-1/3 h-52 w-52 rounded-full bg-amber-300/40 blur-3xl"
        />

        <div className="relative mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
          <div className="grid items-center gap-10 lg:grid-cols-[1.2fr_0.8fr]">
            <motion.div
              initial={{ opacity: 0, x: -32 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={sectionViewport}
              transition={{ duration: 0.7 }}
            >
              <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-zinc-950 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-zinc-950 shadow-sm">
                <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-white">
                  <FiZap className="h-3 w-3" />
                </span>
                Zimbabwe's Trusted SMM Platform
              </p>

              <h1 className="text-4xl font-extrabold tracking-tight text-zinc-950 sm:text-5xl lg:text-6xl">
                Grow louder with <span className="text-emerald-600">real momentum</span>,
                <span className="text-red-600"> visible trust</span>, and
                <span className="text-amber-500"> measurable reach</span>.
              </h1>
              <p className="mt-5 max-w-2xl text-base text-zinc-700 sm:text-lg">
                Launch campaigns in minutes for Instagram, YouTube, TikTok, Facebook, X, and Telegram.
                Transparent pricing, responsive support, and reliable order tracking.
              </p>

              <div className="mt-8 flex flex-wrap items-center gap-3">
                <Link href={route("marketing.services")}>
                  <Button className="h-10 gap-2 bg-zinc-950 px-5 text-white transition duration-300 hover:-translate-y-1 hover:bg-zinc-800 hover:shadow-xl">
                    Explore Services
                    <FiArrowRight className="h-4 w-4" />
                  </Button>
                </Link>
                <Link href={route("register")}>
                  <Button variant="outline" className="h-10 border-red-600 px-5 text-red-600 transition duration-300 hover:-translate-y-1 hover:bg-red-600 hover:text-white">
                    Create Account
                  </Button>
                </Link>
              </div>

              <div className="mt-6 flex flex-wrap gap-4 text-sm text-zinc-700">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 shadow-sm"><FiShield className="h-4 w-4 text-emerald-600" /> Secure checkout</span>
                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 shadow-sm"><FiZap className="h-4 w-4 text-amber-500" /> Fast delivery</span>
                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 shadow-sm"><FiClock className="h-4 w-4 text-red-600" /> 24/7 support</span>
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, x: 32, rotate: 1.5 }}
              whileInView={{ opacity: 1, x: 0, rotate: 0 }}
              viewport={sectionViewport}
              transition={{ duration: 0.8 }}
              whileHover={{ y: -6 }}
            >
            <Card className="border-zinc-950 bg-zinc-950 text-white shadow-2xl backdrop-blur">
              <CardHeader>
                <CardTitle className="text-white">Quick Start</CardTitle>
                <CardDescription className="text-zinc-300">Get your first order live in under 2 minutes.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div>
                  <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-300">Platform / Service</p>
                  <Input placeholder="e.g. Instagram Followers" readOnly className="border-emerald-500/40 bg-white text-zinc-950" />
                </div>
                <div>
                  <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-300">Link</p>
                  <Input placeholder="https://instagram.com/yourprofile" readOnly className="border-amber-400/50 bg-white text-zinc-950" />
                </div>
                <div>
                  <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-300">Quantity</p>
                  <Input placeholder="1000" readOnly className="border-red-500/50 bg-white text-zinc-950" />
                </div>
              </CardContent>
              <CardFooter className="justify-between gap-2">
                <Link href={route("login")} className="w-full">
                  <Button variant="outline" className="w-full border-white text-white hover:bg-white hover:text-zinc-950">Login</Button>
                </Link>
                <Link href={route("register")} className="w-full">
                  <Button className="w-full bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white transition duration-300 hover:scale-[1.02]">Start Now</Button>
                </Link>
              </CardFooter>
            </Card>
            </motion.div>
          </div>
        </div>
      </section>

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div className="mb-8 grid gap-4 sm:grid-cols-3" variants={sectionVariants}>
          {[
            { label: "Services Catalog", value: "500+" },
            { label: "Average Setup Time", value: "< 2 mins" },
            { label: "Active Marketers", value: "10k+" },
          ].map((metric) => (
            <motion.div key={metric.label} variants={itemVariants} whileHover={{ y: -8 }}>
            <Card className="group overflow-hidden border-zinc-950 transition-transform duration-300 hover:shadow-xl">
              <div className="h-1 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600" />
              <CardHeader>
                <CardDescription className="text-zinc-600">{metric.label}</CardDescription>
                <CardTitle className="text-2xl font-bold text-zinc-950 group-hover:text-emerald-700">{metric.value}</CardTitle>
              </CardHeader>
            </Card>
            </motion.div>
          ))}
        </motion.div>

        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 className="text-2xl font-bold text-zinc-950">Popular Platforms</h2>
            <p className="text-sm text-zinc-700">Pick a channel and launch your growth strategy.</p>
          </div>
          <Link href={route("marketing.services")} className="text-sm font-semibold text-red-600 hover:text-zinc-950">
            View all services
          </Link>
        </div>

        <motion.div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" variants={sectionVariants}>
          {props.categories.map((category, index) => (
            <motion.div key={category} variants={itemVariants} whileHover={{ y: -10, scale: 1.02 }}>
            <Link
              key={category}
              href={`${route("marketing.services")}?category=${category}`}
              className={`rounded-xl border px-4 py-4 text-center text-sm font-semibold capitalize shadow-sm transition duration-300 hover:-translate-y-2 hover:shadow-xl ${index % 3 === 0 ? "border-emerald-300 bg-emerald-50 text-zinc-950" : index % 3 === 1 ? "border-amber-300 bg-amber-50 text-zinc-950" : "border-red-300 bg-red-50 text-zinc-950"}`}
            >
              <span className="mb-2 inline-flex items-center justify-center rounded-full bg-zinc-950 p-3 text-white transition duration-300 group-hover:scale-110">{categoryIcon(category)}</span>
              <span className="block">{categoryLabel(category)}</span>
              <span className="mt-1 block text-xs font-medium text-zinc-600">Followers, Likes & Views</span>
            </Link>
            </motion.div>
          ))}
        </motion.div>
      </motion.section>

      <motion.section
        className="bg-zinc-950 py-14 text-white"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-6 text-center">
          <h2 className="text-3xl font-bold">Why Trust Zimbo Socials?</h2>
          <p className="text-sm text-zinc-300">Built for Zimbabwe's creators, businesses, and growth professionals.</p>
        </div>
        <motion.div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" variants={sectionVariants}>
          {[
            {
              title: "Fast Delivery",
              desc: "Most services begin within minutes so your campaigns gain momentum quickly.",
              icon: <FiZap className="h-5 w-5 text-emerald-400" />,
            },
            {
              title: "Secure & Private",
              desc: "We never ask for passwords and your account safety remains protected.",
              icon: <FiLock className="h-5 w-5 text-red-400" />,
            },
            {
              title: "Transparent Pricing",
              desc: "Competitive rates with no hidden fees and clear quantity limits.",
              icon: <FiDollarSign className="h-5 w-5 text-amber-300" />,
            },
            {
              title: "Local Support",
              desc: "Zimbabwe-focused support to help with deposits, orders, and account setup.",
              icon: <FiUsers className="h-5 w-5 text-white" />,
            },
          ].map((item) => (
            <motion.div key={item.title} variants={itemVariants} whileHover={{ y: -8 }}>
            <Card className="border-zinc-700 bg-zinc-900 text-white transition-transform duration-300 hover:border-amber-300 hover:shadow-2xl">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">{item.icon}{item.title}</CardTitle>
                <CardDescription className="text-zinc-300">{item.desc}</CardDescription>
              </CardHeader>
            </Card>
            </motion.div>
          ))}
        </motion.div>
        </div>
      </motion.section>

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-red-700">
              <span className="inline-flex h-2.5 w-2.5 rounded-full bg-red-500 animate-pulse" />
              Live Broadcasting
            </div>
            <h2 className="text-2xl font-bold text-zinc-950">Real-time buyer activity that stays lightweight.</h2>
            <p className="mt-1 max-w-2xl text-sm text-zinc-700">
              Instead of loading a growing services block, the homepage now keeps a fixed layout and rotates recent purchase-style activity using Zimbabwean names and a small service pool.
            </p>
          </div>
          <div className="grid grid-cols-2 gap-3 sm:w-auto">
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
              <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Layout</p>
              <p className="mt-1 text-lg font-extrabold text-zinc-950">Fixed</p>
            </div>
            <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
              <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-amber-700">Broadcast</p>
              <p className="mt-1 text-lg font-extrabold text-zinc-950">Live</p>
            </div>
          </div>
        </div>

        <motion.div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]" variants={sectionVariants}>
          <motion.div variants={itemVariants} whileHover={{ y: -6 }}>
            <Card className="overflow-hidden border-zinc-950 shadow-xl">
              <div className="border-b border-zinc-200 bg-gradient-to-r from-zinc-950 via-red-600 to-emerald-600 px-5 py-4 text-white">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.22em] text-white/80">
                      <FiRadio className="h-3.5 w-3.5" />
                      Activity Stream
                    </p>
                    <h3 className="mt-1 text-xl font-bold">People are ordering right now</h3>
                  </div>
                  <div className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold backdrop-blur">
                    <FiEye className="h-4 w-4" />
                    Watching 24/7
                  </div>
                </div>
              </div>
              <CardContent className="space-y-4 p-5">
                <div className="rounded-2xl border border-red-100 bg-red-50/70 p-4">
                  <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-red-700">Latest Broadcast</p>
                  <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-800">
                    <span className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 font-semibold shadow-sm">
                      <FiBell className="h-4 w-4 text-red-500" />
                      {liveActivities[0]?.buyer} from {liveActivities[0]?.town}
                    </span>
                    <span>{liveActivities[0]?.action}</span>
                    <span className="rounded-full bg-zinc-950 px-3 py-1 font-semibold text-white">
                      {liveActivities[0]?.service}
                    </span>
                    <span className="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">
                      {liveActivities[0]?.quantity.toLocaleString()} qty
                    </span>
                    <span className="text-zinc-500">{liveActivities[0]?.timeAgo}</span>
                  </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  {liveActivities.slice(1, 5).map((activity, index) => (
                    <motion.div
                      key={activity.id}
                      variants={itemVariants}
                      whileHover={{ y: -4 }}
                      className={`rounded-2xl border p-4 ${index % 3 === 0 ? "border-emerald-200 bg-emerald-50/70" : index % 3 === 1 ? "border-amber-200 bg-amber-50/70" : "border-red-200 bg-red-50/70"}`}
                    >
                      <p className="text-xs font-bold uppercase tracking-[0.18em] text-zinc-500">{activity.timeAgo}</p>
                      <h4 className="mt-2 text-base font-bold text-zinc-950">{activity.buyer} • {activity.town}</h4>
                      <p className="mt-1 text-sm text-zinc-700">{activity.action} <span className="font-semibold text-zinc-950">{activity.service}</span></p>
                      <div className="mt-3 flex items-center justify-between text-xs">
                        <span className="rounded-full bg-white px-2.5 py-1 font-semibold text-zinc-700 shadow-sm">{categoryLabel(activity.category)}</span>
                        <span className="font-bold text-zinc-950">{activity.quantity.toLocaleString()} qty</span>
                      </div>
                    </motion.div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </motion.div>

          <motion.div variants={itemVariants} whileHover={{ y: -6 }}>
            <Card className="border-zinc-950 bg-zinc-950 text-white shadow-2xl">
              <CardHeader>
                <CardTitle className="text-2xl">Why this works better</CardTitle>
                <CardDescription className="text-zinc-300">
                  The homepage stays visually consistent even if your catalog grows far beyond 1,000 services.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {[
                  "We only send a small random sample of services to the homepage instead of growing the whole layout.",
                  "The activity feed rotates automatically, so visitors feel motion and demand without seeing a long service wall.",
                  "Shona buyer names make the stream feel more local and familiar for your audience.",
                  "Your full catalog still lives on the dedicated services page where depth belongs.",
                ].map((point) => (
                  <div key={point} className="rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/90">
                    <p className="inline-flex items-start gap-2">
                      <FiCheckCircle className="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" />
                      <span>{point}</span>
                    </p>
                  </div>
                ))}
              </CardContent>
              <CardFooter className="flex flex-wrap gap-3">
                <Link href={route("marketing.services")}>
                  <Button className="bg-white text-zinc-950 transition duration-300 hover:-translate-y-1 hover:bg-amber-100">
                    Browse Full Catalog
                  </Button>
                </Link>
                <Link href={route("register")}>
                  <Button variant="outline" className="border-white text-white hover:bg-white hover:text-zinc-950">
                    Start Growing
                  </Button>
                </Link>
              </CardFooter>
            </Card>
          </motion.div>
        </motion.div>
      </motion.section>

      <motion.section
        className="bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 py-14"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-6 text-center">
          <h2 className="text-3xl font-bold text-white">Getting Started</h2>
          <p className="text-sm text-white/90">Three simple steps to begin growing your social presence.</p>
        </div>
        <motion.div className="grid gap-4 md:grid-cols-3" variants={sectionVariants}>
          {[
            {
              n: "1",
              title: "Register Your Account",
              desc: "Create your free account in seconds with basic details.",
              grad: "from-emerald-600 to-amber-500",
            },
            {
              n: "2",
              title: "Select Services",
              desc: "Choose your platform, service, and quantity based on your goals.",
              grad: "from-red-600 to-amber-500",
            },
            {
              n: "3",
              title: "Track & Grow",
              desc: "Monitor progress in real time and scale campaigns confidently.",
              grad: "from-emerald-600 to-red-600",
            },
          ].map((step) => (
            <motion.div key={step.n} variants={itemVariants} whileHover={{ y: -8, scale: 1.01 }}>
            <Card className="border-white/40 bg-white text-center transition-transform duration-300 hover:shadow-xl">
              <CardHeader>
                <div className={`mx-auto mb-1 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br ${step.grad} text-lg font-bold text-white`}>
                  {step.n}
                </div>
                <CardTitle className="text-base">{step.title}</CardTitle>
                <CardDescription className="text-zinc-700">{step.desc}</CardDescription>
              </CardHeader>
            </Card>
            </motion.div>
          ))}
        </motion.div>
        </div>
      </motion.section>

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <div className="mb-6 text-center">
          <h2 className="text-2xl font-bold text-zinc-950">Earn as a Marketer</h2>
          <p className="text-sm text-zinc-700">Monetize your audience with campaign opportunities and tracked payouts.</p>
        </div>
        <motion.div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" variants={sectionVariants}>
          {[
            {
              title: "Discover Contracts",
              desc: "Access campaigns that match your page audience and engagement profile.",
              icon: <FiBriefcase className="h-5 w-5 text-emerald-600" />,
            },
            {
              title: "Guaranteed Payments",
              desc: "Transparent payout flow with clear approval stages for each campaign.",
              icon: <FiCheckCircle className="h-5 w-5 text-red-600" />,
            },
            {
              title: "Performance Tracking",
              desc: "Measure campaign output and earnings directly from your dashboard.",
              icon: <FiBarChart2 className="h-5 w-5 text-amber-500" />,
            },
            {
              title: "Dedicated Support",
              desc: "Work with a team that helps you execute campaigns smoothly.",
              icon: <FiUsers className="h-5 w-5 text-zinc-900" />,
            },
          ].map((item) => (
            <motion.div key={item.title} variants={itemVariants} whileHover={{ y: -8 }}>
            <Card className="border-zinc-950 transition-transform duration-300 hover:shadow-xl">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">{item.icon}{item.title}</CardTitle>
                <CardDescription className="text-zinc-700">{item.desc}</CardDescription>
              </CardHeader>
            </Card>
            </motion.div>
          ))}
        </motion.div>
        <div className="mt-5 text-center">
          <Link href={route("register")}>
            <Button className="h-10 bg-zinc-950 px-5 text-white transition duration-300 hover:-translate-y-1 hover:bg-red-600">Become a Marketer</Button>
          </Link>
        </div>
      </motion.section>

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]" variants={sectionVariants}>
          <motion.div variants={itemVariants} whileHover={{ y: -8 }}>
          <Card className="border-zinc-950 bg-zinc-950 text-white shadow-2xl">
            <CardHeader>
              <CardTitle className="text-2xl">Launch Campaigns on Premium Pages</CardTitle>
              <CardDescription className="text-zinc-300">
                Reach qualified audiences through high-engagement Zimbabwe creator networks with clear reporting.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm text-white">
              <p className="inline-flex items-center gap-2"><FiCheckCircle className="h-4 w-4 text-emerald-400" /> Access verified high-traffic pages</p>
              <p className="inline-flex items-center gap-2"><FiCheckCircle className="h-4 w-4 text-red-400" /> Partner with established content creators</p>
              <p className="inline-flex items-center gap-2"><FiCheckCircle className="h-4 w-4 text-amber-300" /> Target campaigns by platform and audience</p>
              <p className="inline-flex items-center gap-2"><FiCheckCircle className="h-4 w-4 text-white" /> Track delivery and ROI transparently</p>
            </CardContent>
            <CardFooter>
              <Link href={route("register")}>
                <Button className="gap-2 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white transition duration-300 hover:scale-[1.02]">Start B2B Campaign <FiArrowRight className="h-4 w-4" /></Button>
              </Link>
            </CardFooter>
          </Card>
          </motion.div>

          <motion.div variants={itemVariants} whileHover={{ y: -8 }}>
          <Card className="border-red-300 bg-gradient-to-br from-red-50 via-white to-amber-50">
            <CardHeader>
              <CardTitle className="text-base text-zinc-950">Creator Network Reach</CardTitle>
              <CardDescription className="text-zinc-700">Premium page access across major platforms.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              {[
                { name: "Facebook Pages", value: "500K+ combined followers", icon: <FaFacebookF className="h-4 w-4 text-emerald-600" /> },
                { name: "Instagram Accounts", value: "400K+ verified followers", icon: <FaInstagram className="h-4 w-4 text-red-600" /> },
                { name: "TikTok Creators", value: "600K+ active followers", icon: <FaTiktok className="h-4 w-4 text-zinc-950" /> },
                { name: "YouTube Channels", value: "300K+ subscriber network", icon: <FaYoutube className="h-4 w-4 text-amber-500" /> },
              ].map((n) => (
                <div key={n.name} className="rounded-md border border-zinc-200 bg-white px-3 py-2.5 transition duration-300 hover:-translate-y-1 hover:border-red-300 hover:shadow-md">
                  <p className="inline-flex items-center gap-2 text-sm font-semibold text-zinc-950">{n.icon}{n.name}</p>
                  <p className="text-xs text-zinc-600">{n.value}</p>
                </div>
              ))}
            </CardContent>
          </Card>
          </motion.div>
        </motion.div>
      </motion.section>

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div className="grid gap-4 md:grid-cols-3" variants={sectionVariants}>
          {[
            {
              icon: <FiUsers className="h-5 w-5 text-emerald-600" />,
              title: "Built for teams",
              desc: "Create consistent results across multiple client accounts with streamlined order flows.",
            },
            {
              icon: <FiShield className="h-5 w-5 text-red-600" />,
              title: "Safer account growth",
              desc: "Balanced delivery pacing helps campaigns look natural and sustainable over time.",
            },
            {
              icon: <FiZap className="h-5 w-5 text-amber-500" />,
              title: "Execution speed",
              desc: "Launch and track campaigns quickly with clear status updates and account-level reporting.",
            },
          ].map((item) => (
            <motion.div key={item.title} variants={itemVariants} whileHover={{ y: -8 }}>
            <Card className="border-zinc-950 bg-white transition duration-300 hover:bg-zinc-950 hover:text-white hover:shadow-xl">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  {item.icon}
                  {item.title}
                </CardTitle>
                <CardDescription className="text-zinc-700 group-hover:text-zinc-200">{item.desc}</CardDescription>
              </CardHeader>
            </Card>
            </motion.div>
          ))}
        </motion.div>
      </motion.section>

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 pb-16 sm:px-6 lg:px-8"
        variants={sectionVariants}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div variants={itemVariants} whileHover={{ y: -6 }}>
        <Card className="border-zinc-950 bg-gradient-to-r from-zinc-950 via-red-600 to-emerald-600 text-white shadow-2xl">
          <CardHeader>
            <CardTitle className="text-2xl font-bold text-white">Ready to scale your socials?</CardTitle>
            <CardDescription>
              Join creators and brands already growing with Zimbo Socials.
            </CardDescription>
          </CardHeader>
          <CardFooter className="flex flex-wrap gap-3">
            <Link href={route("register")}>
              <Button className="h-10 bg-white px-5 text-zinc-950 transition duration-300 hover:-translate-y-1 hover:bg-amber-100">Create Free Account</Button>
            </Link>
            <Link href={route("marketing.services")}>
              <Button variant="outline" className="h-10 border-white px-5 text-white hover:bg-white hover:text-zinc-950">Browse Services</Button>
            </Link>
          </CardFooter>
        </Card>
        </motion.div>
      </motion.section>
    </MarketingLayout>
  )
}
