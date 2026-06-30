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
import TestimonialsSection from "@/Components/TestimonialsSection"
import FaqSection from "@/Components/FaqSection"
import AnimatedCounter from "@/Components/AnimatedCounter"
import { PageProps } from "@/types"
import { motion, useReducedMotion } from "framer-motion"
import { Link, usePage } from "@inertiajs/react"
import { useEffect, useState, useMemo } from "react"
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
  FiStar,
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

import { fallbackServices, fallbackCategories } from "@/lib/constants"
import { Service } from "@/lib/types"


type HomeStats = {
  services: number
  categories: number
}

type Props = {
  activityServices: Service[]
  categories: string[]
  stats?: HomeStats
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

// Motion-reduced fallbacks: fade only, no transform/stagger (avoids vestibular discomfort).
const reducedSectionVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { duration: 0.2, staggerChildren: 0 } },
}
const reducedItemVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { duration: 0.2 } },
}

export default function Home() {
  const { props } = usePage<PageProps<Props>>()
  const reduceMotion = useReducedMotion()
  const sv = reduceMotion ? reducedSectionVariants : sectionVariants
  const iv = reduceMotion ? reducedItemVariants : itemVariants
  const servicePool = useMemo(() => props.activityServices.length > 0 ? props.activityServices : fallbackServices, [props.activityServices])
  const displayCategories = props.categories.length > 0 ? props.categories : fallbackCategories
  const stats = props.stats ?? { services: 0, categories: 0 }
  const servicesValue = stats.services > 0 ? stats.services : 500
  const platformsValue = stats.categories > 0 ? stats.categories : displayCategories.length
  const heroMetrics = [
    { label: "Services Available", value: servicesValue, suffix: "+" },
    { label: "Average Setup Time", value: "< 2 mins", suffix: "" },
    { label: "Platforms Supported", value: platformsValue, suffix: "" },
  ]

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
      itemListElement: servicePool.slice(0, 8).map((service: Service, index: number) => ({
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
        return <FaInstagram className="h-6 w-6" />
      case "youtube":
        return <FaYoutube className="h-6 w-6" />
      case "facebook":
        return <FaFacebookF className="h-6 w-6" />
      case "twitter":
        return <FaXTwitter className="h-6 w-6" />
      case "telegram":
        return <FaTelegram className="h-6 w-6" />
      case "tiktok":
        return <FaTiktok className="h-6 w-6" />
      case "whatsapp":
        return <FaWhatsapp className="h-6 w-6" />
      default:
        return <FiTrendingUp className="h-6 w-6" />
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
          animate={reduceMotion ? undefined : { y: [0, -10, 0], scale: [1, 1.04, 1] }}
          transition={{ duration: 8, repeat: Infinity, ease: "easeInOut" }}
          className="pointer-events-none absolute -top-24 left-8 h-72 w-72 rounded-full bg-emerald-300/40 blur-3xl"
        />
        <motion.div
          animate={reduceMotion ? undefined : { y: [0, 14, 0], x: [0, -12, 0] }}
          transition={{ duration: 10, repeat: Infinity, ease: "easeInOut" }}
          className="pointer-events-none absolute right-0 top-10 h-72 w-72 rounded-full bg-red-300/30 blur-3xl"
        />
        <motion.div
          animate={reduceMotion ? undefined : { y: [0, -8, 0], x: [0, 10, 0] }}
          transition={{ duration: 9, repeat: Infinity, ease: "easeInOut" }}
          className="pointer-events-none absolute bottom-0 left-1/3 h-52 w-52 rounded-full bg-amber-300/40 blur-3xl"
        />

        <div className="relative mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
          <div className="grid items-center gap-10 lg:grid-cols-[1.2fr_0.8fr]">
            <motion.div
              initial={reduceMotion ? { opacity: 0 } : { opacity: 0, x: -32 }}
              whileInView={{ opacity: 1, x: 0 }}
              viewport={sectionViewport}
              transition={{ duration: reduceMotion ? 0.2 : 0.7 }}
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
              initial={reduceMotion ? { opacity: 0 } : { opacity: 0, x: 32, rotate: 1.5 }}
              whileInView={{ opacity: 1, x: 0, rotate: 0 }}
              viewport={sectionViewport}
              transition={{ duration: reduceMotion ? 0.2 : 0.8 }}
              whileHover={reduceMotion ? undefined : { y: -6 }}
            >
            <Card className="border-zinc-950 bg-zinc-950 text-white shadow-2xl backdrop-blur">
              <CardHeader>
                <CardTitle className="text-white">Quick Start</CardTitle>
                <CardDescription className="text-zinc-300">Get your first order live in under 2 minutes.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="flex items-center gap-3">
                  <div className="flex -space-x-3">
                    {[1, 2, 3, 4].map((i) => (
                      <div key={i} className={`flex h-10 w-10 items-center justify-center rounded-full border-2 border-zinc-950 bg-gradient-to-br text-sm font-bold text-white shadow-sm ${i === 1 ? 'from-emerald-400 to-emerald-600' : i === 2 ? 'from-amber-400 to-amber-600' : i === 3 ? 'from-red-400 to-red-600' : 'from-blue-400 to-blue-600'}`}>
                        {['T', 'C', 'M', 'R'][i-1]}
                      </div>
                    ))}
                  </div>
                  <div>
                    <div className="flex items-center gap-1 text-amber-400">
                      {[1, 2, 3, 4, 5].map((i) => (
                        <FiStar key={i} className="h-4 w-4 fill-current" />
                      ))}
                    </div>
                    <p className="mt-0.5 text-sm font-medium text-zinc-300">Trusted by 5,000+ creators</p>
                  </div>
                </div>
                
                <div className="space-y-3">
                  <div className="flex items-center gap-3 text-sm font-medium text-zinc-300">
                    <FiCheckCircle className="h-5 w-5 text-emerald-500" /> Instant activation
                  </div>
                  <div className="flex items-center gap-3 text-sm font-medium text-zinc-300">
                    <FiCheckCircle className="h-5 w-5 text-amber-500" /> Automated delivery
                  </div>
                  <div className="flex items-center gap-3 text-sm font-medium text-zinc-300">
                    <FiCheckCircle className="h-5 w-5 text-red-500" /> Local payment methods
                  </div>
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
        variants={sv}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div className="mb-8 grid gap-4 sm:grid-cols-3" variants={sv}>
          {heroMetrics.map((metric) => (
            <motion.div key={metric.label} variants={iv} whileHover={{ y: -8 }}>
            <Card className="group overflow-hidden border-zinc-950 transition-transform duration-300 hover:shadow-xl">
              <div className="h-1 bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600" />
              <CardHeader>
                <CardDescription className="text-zinc-600">{metric.label}</CardDescription>
                <CardTitle className="text-2xl font-bold text-zinc-950 group-hover:text-emerald-700">
                  <AnimatedCounter value={metric.value} suffix={metric.suffix} />
                </CardTitle>
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

        <motion.div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" variants={sv}>
          {displayCategories.map((category, index) => (
            <motion.div key={category} variants={iv} whileHover={{ y: -6, scale: 1.01 }}>
              <Link
                href={`${route("marketing.services")}?category=${category}`}
                className="group relative flex h-full flex-col overflow-hidden rounded-3xl bg-white p-6 shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] transition-all duration-300 hover:shadow-2xl"
              >
                <div className={`absolute -inset-[1px] -z-10 rounded-3xl opacity-20 transition-opacity duration-300 group-hover:opacity-100 bg-gradient-to-br ${index % 3 === 0 ? "from-emerald-400 to-emerald-600" : index % 3 === 1 ? "from-amber-400 to-amber-600" : "from-red-400 to-red-600"}`} />
                <div className="absolute inset-[1px] -z-10 rounded-[23px] bg-white transition-colors duration-300 group-hover:bg-zinc-50/50" />
                <div className="flex items-center gap-4">
                  <div className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl shadow-sm transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3 ${index % 3 === 0 ? "bg-emerald-50 text-emerald-600" : index % 3 === 1 ? "bg-amber-50 text-amber-600" : "bg-red-50 text-red-600"}`}>
                    {categoryIcon(category)}
                  </div>
                  <div>
                    <h3 className="text-lg font-bold text-zinc-950 group-hover:text-transparent group-hover:bg-clip-text group-hover:bg-gradient-to-r group-hover:from-zinc-950 group-hover:to-zinc-600">{categoryLabel(category)}</h3>
                    <p className="mt-0.5 text-sm font-medium text-zinc-500">Premium Growth</p>
                  </div>
                </div>
                <div className="mt-8 flex flex-1 items-end justify-between">
                  <div className="flex -space-x-2">
                    <div className={`h-8 w-8 rounded-full border-2 border-white bg-gradient-to-br opacity-80 ${index % 3 === 0 ? "from-emerald-300 to-emerald-500" : index % 3 === 1 ? "from-amber-300 to-amber-500" : "from-red-300 to-red-500"}`} />
                    <div className="h-8 w-8 rounded-full border-2 border-white bg-zinc-200" />
                    <div className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-zinc-100 text-[10px] font-bold text-zinc-600">+</div>
                  </div>
                  <div className={`flex h-10 w-10 items-center justify-center rounded-full bg-zinc-50 text-zinc-400 transition-colors duration-300 group-hover:text-white ${index % 3 === 0 ? "group-hover:bg-emerald-600" : index % 3 === 1 ? "group-hover:bg-amber-500" : "group-hover:bg-red-600"}`}>
                    <FiArrowRight className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-0.5" />
                  </div>
                </div>
              </Link>
            </motion.div>
          ))}
        </motion.div>
      </motion.section>

      <motion.section
        className="bg-zinc-950 py-14 text-white"
        variants={sv}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-6 text-center">
          <h2 className="text-3xl font-bold">Why Trust Zimbo Socials?</h2>
          <p className="text-sm text-zinc-300">Built for Zimbabwe's creators, businesses, and growth professionals.</p>
        </div>
        <motion.div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" variants={sv}>
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
            <motion.div key={item.title} variants={iv} whileHover={{ y: -8 }}>
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

      {/* We removed the Live Broadcasting section as the live ticker is now in the header */}

      <motion.section
        className="bg-gradient-to-r from-emerald-600 via-amber-400 to-red-600 py-14"
        variants={sv}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mb-6 text-center">
          <h2 className="text-3xl font-bold text-white">Getting Started</h2>
          <p className="text-sm text-white/90">Three simple steps to begin growing your social presence.</p>
        </div>
        <motion.div className="grid gap-4 md:grid-cols-3" variants={sv}>
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
            <motion.div key={step.n} variants={iv} whileHover={{ y: -8, scale: 1.01 }}>
            <Card className="border-white/20 bg-white/10 text-center backdrop-blur-md transition-all duration-300 hover:bg-white/20 hover:shadow-xl">
              <CardHeader>
                <div className={`mx-auto mb-1 inline-flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br ${step.grad} text-lg font-bold text-white shadow-lg`}>
                  {step.n}
                </div>
                <CardTitle className="text-base text-white">{step.title}</CardTitle>
                <CardDescription className="text-white/80">{step.desc}</CardDescription>
              </CardHeader>
            </Card>
            </motion.div>
          ))}
        </motion.div>
        </div>
      </motion.section>

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8"
        variants={sv}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <div className="mb-6 text-center">
          <h2 className="text-2xl font-bold text-zinc-950">Earn as a Marketer</h2>
          <p className="text-sm text-zinc-700">Monetize your audience with campaign opportunities and tracked payouts.</p>
        </div>
        <motion.div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" variants={sv}>
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
            <motion.div key={item.title} variants={iv} whileHover={{ y: -8 }}>
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
        variants={sv}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]" variants={sv}>
          <motion.div variants={iv} whileHover={{ y: -8 }}>
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

          <motion.div variants={iv} whileHover={{ y: -8 }}>
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
        variants={sv}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div className="grid gap-4 md:grid-cols-3" variants={sv}>
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
            <motion.div key={item.title} variants={iv} whileHover={{ y: -8 }}>
            <Card className="group border-zinc-950 bg-white transition duration-300 hover:bg-zinc-950 hover:text-white hover:shadow-xl">
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

      <TestimonialsSection />
      
      <FaqSection />

      <motion.section
        className="mx-auto w-full max-w-7xl px-4 pb-16 sm:px-6 lg:px-8"
        variants={sv}
        initial="hidden"
        whileInView="visible"
        viewport={sectionViewport}
      >
        <motion.div variants={iv} whileHover={{ y: -6 }}>
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
