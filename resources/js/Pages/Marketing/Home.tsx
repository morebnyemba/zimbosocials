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
import {
  FiArrowRight,
  FiBarChart2,
  FiBriefcase,
  FiCheckCircle,
  FiClock,
  FiDollarSign,
  FiLock,
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
  min_qty: number
  max_qty: number
}

type Props = {
  featuredServices: Service[]
  categories: string[]
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

export default function Home() {
  const { props } = usePage<PageProps<Props>>()

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
      name: "Featured Social Growth Services",
      itemListElement: props.featuredServices.slice(0, 8).map((service, index) => ({
        "@type": "ListItem",
        position: index + 1,
        name: service.name,
      })),
    },
  ]

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
        <div className="mb-6">
          <h2 className="text-2xl font-bold text-zinc-950">Featured Services</h2>
          <p className="text-sm text-zinc-700">High demand services with proven delivery performance.</p>
        </div>

        <motion.div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" variants={sectionVariants}>
          {props.featuredServices.map((service, index) => (
            <motion.div key={service.id} variants={itemVariants} whileHover={{ y: -10 }}>
            <Card className={`border transition-transform duration-300 hover:shadow-xl ${index % 3 === 0 ? "border-emerald-300 bg-white" : index % 3 === 1 ? "border-amber-300 bg-amber-50/40" : "border-red-300 bg-red-50/40"}`}>
              <CardHeader>
                <div className="mb-2 inline-flex w-fit rounded-full bg-zinc-950 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-white">Popular</div>
                <CardTitle className="line-clamp-2 text-base text-zinc-950">{service.name}</CardTitle>
                <CardDescription className="capitalize text-zinc-600">{service.category}</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="mb-3 flex items-center justify-between text-xs text-zinc-600">
                  <span>Min: {Number(service.min_qty).toLocaleString()}</span>
                  <span>Max: {Number(service.max_qty).toLocaleString()}</span>
                </div>
                <Link href={route("login")} className="block">
                  <Button className="h-9 w-full bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 text-white transition duration-300 hover:scale-[1.02]">Order Now</Button>
                </Link>
              </CardContent>
            </Card>
            </motion.div>
          ))}
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
