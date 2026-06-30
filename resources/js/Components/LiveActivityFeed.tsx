import { motion } from "framer-motion"
import {
  FaFacebookF,
  FaInstagram,
  FaTelegram,
  FaTiktok,
  FaWhatsapp,
  FaXTwitter,
  FaYoutube,
} from "react-icons/fa6"
import { FiRadio, FiTrendingUp } from "react-icons/fi"

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

type Props = {
  activities: LiveActivity[]
}

function categoryIcon(category: string) {
  switch (category.toLowerCase()) {
    case "instagram":
      return <FaInstagram className="h-3.5 w-3.5 text-pink-500" />
    case "youtube":
      return <FaYoutube className="h-3.5 w-3.5 text-red-500" />
    case "facebook":
      return <FaFacebookF className="h-3.5 w-3.5 text-blue-500" />
    case "twitter":
      return <FaXTwitter className="h-3.5 w-3.5 text-zinc-300" />
    case "telegram":
      return <FaTelegram className="h-3.5 w-3.5 text-sky-400" />
    case "tiktok":
      return <FaTiktok className="h-3.5 w-3.5 text-teal-400" />
    case "whatsapp":
      return <FaWhatsapp className="h-3.5 w-3.5 text-green-400" />
    default:
      return <FiTrendingUp className="h-3.5 w-3.5 text-emerald-400" />
  }
}

export default function LiveActivityFeed({ activities }: Props) {
  if (activities.length === 0) return null

  // Duplicate for seamless infinite marquee scroll
  const duplicatedActivities = [...activities, ...activities]

  return (
    <div className="relative flex overflow-hidden border-b border-zinc-800 bg-zinc-950 py-2.5 text-xs text-zinc-100">
      {/* Fixed left label */}
      <div className="absolute left-0 z-10 flex h-full items-center bg-gradient-to-r from-zinc-950 via-zinc-950 to-transparent pl-4 pr-12">
        <span className="flex items-center gap-2 font-bold uppercase tracking-wider text-emerald-400">
          <FiRadio className="h-3.5 w-3.5 animate-pulse" /> Live Orders
        </span>
      </div>

      {/* 
        To prevent marquee "tripping", the motion div must contain two EXACTLY identical halves.
        No container padding/margins that would throw off the -50% translation. 
      */}
      <motion.div
        className="flex shrink-0 w-max"
        animate={{ x: ["0%", "-50%"] }}
        transition={{ repeat: Infinity, ease: "linear", duration: 35 }}
      >
        {duplicatedActivities.map((activity, index) => (
          <div key={`${activity.id}-${index}`} className="flex items-center gap-2 whitespace-nowrap pl-10">
            {categoryIcon(activity.category)}
            <span className="font-semibold text-white">{activity.buyer}</span>
            <span className="text-zinc-400">from {activity.town}</span>
            <span className="text-zinc-300">{activity.action}</span>
            <span className="font-bold text-emerald-400">
              {activity.quantity.toLocaleString()} {activity.service}
            </span>
            <span className="ml-1 text-[10px] text-zinc-500">{activity.timeAgo}</span>
          </div>
        ))}
      </motion.div>
    </div>
  )
}
