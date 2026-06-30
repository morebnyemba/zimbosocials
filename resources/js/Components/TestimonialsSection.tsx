import { motion } from "framer-motion"
import { Card, CardContent } from "@/Components/ui/card"
import { FiStar } from "react-icons/fi"

const testimonials = [
  {
    quote: "Zimbo Socials completely changed how we handle our brand presence. The speed of delivery is unmatched in Zimbabwe.",
    author: "Tendai M.",
    role: "Digital Marketer",
    rating: 5,
  },
  {
    quote: "I've tried other SMM panels before, but the local payment methods here make it so much easier. Highly recommend!",
    author: "Chiedza K.",
    role: "Content Creator",
    rating: 5,
  },
  {
    quote: "Customer support is brilliant. I had an issue with an order and they fixed it within minutes on WhatsApp.",
    author: "Tinashe N.",
    role: "E-commerce Store Owner",
    rating: 5,
  },
]

export default function TestimonialsSection() {
  const sectionVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.15 },
    },
  }

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.5 } },
  }

  return (
    <motion.section
      className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 lg:px-8"
      variants={sectionVariants}
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: "-100px" }}
    >
      <motion.div variants={itemVariants} className="text-center">
        <h2 className="text-3xl font-extrabold tracking-tight text-zinc-950 sm:text-4xl">
          Loved by Zimbabwean Creators
        </h2>
        <p className="mx-auto mt-4 max-w-2xl text-lg text-zinc-600">
          Don't just take our word for it. Here's what our community has to say about their experience with Zimbo Socials.
        </p>
      </motion.div>

      <div className="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
        {testimonials.map((testimonial, index) => (
          <motion.div key={index} variants={itemVariants} whileHover={{ y: -8 }}>
            <Card className="h-full border-zinc-200 bg-white shadow-md transition duration-300 hover:border-emerald-300 hover:shadow-xl">
              <CardContent className="flex h-full flex-col p-6">
                <div className="mb-4 flex items-center gap-1 text-amber-400">
                  {[...Array(testimonial.rating)].map((_, i) => (
                    <FiStar key={i} className="h-4 w-4 fill-current" />
                  ))}
                </div>
                <blockquote className="flex-1 text-zinc-700">
                  "{testimonial.quote}"
                </blockquote>
                <div className="mt-6 flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-zinc-800 to-zinc-950 text-white font-bold">
                    {testimonial.author.charAt(0)}
                  </div>
                  <div>
                    <div className="font-semibold text-zinc-950">{testimonial.author}</div>
                    <div className="text-xs text-zinc-500">{testimonial.role}</div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </motion.div>
        ))}
      </div>
    </motion.section>
  )
}
