import { motion, AnimatePresence } from "framer-motion"
import { useState } from "react"
import { FiChevronDown } from "react-icons/fi"

const faqs = [
  {
    question: "How long does it take to start seeing results?",
    answer: "Most orders begin processing within 2-5 minutes of payment confirmation. Full delivery time depends on the quantity and specific service ordered.",
  },
  {
    question: "Which payment methods do you accept?",
    answer: "We accept local payment methods including EcoCash, InnBucks, and ZimSwitch/Visa/Mastercard through our secure Paynow integration.",
  },
  {
    question: "Is it safe for my social media accounts?",
    answer: "Yes. We use natural delivery pacing and high-quality profiles to ensure your account remains safe and compliant with platform guidelines.",
  },
  {
    question: "What happens if my followers drop?",
    answer: "Many of our services come with a refill guarantee. If you experience drops within the guarantee period, we will refill your order at no extra cost.",
  },
  {
    question: "Can I earn money as a marketer?",
    answer: "Absolutely! Our B2B platform allows creators to discover contracts and earn payouts by promoting brands to their audience.",
  },
]

export default function FaqSection() {
  const [openIndex, setOpenIndex] = useState<number | null>(0)

  const toggleFaq = (index: number) => {
    setOpenIndex(openIndex === index ? null : index)
  }

  return (
    <motion.section
      className="mx-auto w-full max-w-4xl px-4 py-16 sm:px-6 lg:px-8"
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-100px" }}
      transition={{ duration: 0.6 }}
    >
      <div className="mb-10 text-center">
        <h2 className="text-3xl font-extrabold text-zinc-950 sm:text-4xl">Frequently Asked Questions</h2>
        <p className="mt-4 text-lg text-zinc-600">Everything you need to know about Zimbo Socials.</p>
      </div>

      <div className="space-y-4">
        {faqs.map((faq, index) => {
          const isOpen = openIndex === index

          return (
            <motion.div
              key={index}
              className={`overflow-hidden rounded-2xl border transition-colors duration-300 ${isOpen ? "border-emerald-300 bg-emerald-50/50" : "border-zinc-200 bg-white hover:border-zinc-300"}`}
            >
              <button
                className="flex w-full items-center justify-between px-6 py-5 text-left focus:outline-none"
                onClick={() => toggleFaq(index)}
              >
                <span className="text-base font-semibold text-zinc-950">{faq.question}</span>
                <FiChevronDown
                  className={`h-5 w-5 flex-shrink-0 text-zinc-500 transition-transform duration-300 ${isOpen ? "rotate-180 text-emerald-600" : ""}`}
                />
              </button>
              
              <AnimatePresence initial={false}>
                {isOpen && (
                  <motion.div
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: "auto", opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    transition={{ duration: 0.3, ease: "easeInOut" }}
                  >
                    <div className="px-6 pb-5 pt-0 text-zinc-600">
                      {faq.answer}
                    </div>
                  </motion.div>
                )}
              </AnimatePresence>
            </motion.div>
          )
        })}
      </div>
    </motion.section>
  )
}
