import { animate } from "framer-motion"
import { useEffect, useRef } from "react"

interface AnimatedCounterProps {
  value: number | string
  suffix?: string
  prefix?: string
}

export default function AnimatedCounter({ value, suffix = "", prefix = "" }: AnimatedCounterProps) {
  const nodeRef = useRef<HTMLSpanElement>(null)

  useEffect(() => {
    const node = nodeRef.current
    if (!node) return

    // If it's not a number (like "< 2 mins"), just display it statically
    if (typeof value === "string" && isNaN(Number(value))) {
      node.textContent = `${prefix}${value}${suffix}`
      return
    }

    const targetValue = Number(value)
    
    // Respect users who prefer reduced motion
    const prefersReducedMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches
    if (prefersReducedMotion) {
      node.textContent = `${prefix}${targetValue}${suffix}`
      return
    }

    const controls = animate(0, targetValue, {
      duration: 2,
      ease: "easeOut",
      onUpdate(currentValue) {
        node.textContent = `${prefix}${Math.round(currentValue)}${suffix}`
      },
    })

    return () => controls.stop()
  }, [value, suffix, prefix])

  return <span ref={nodeRef} />
}
