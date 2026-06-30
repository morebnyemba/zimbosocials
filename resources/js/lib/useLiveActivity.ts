import { useState, useEffect, useRef } from "react"
import { Service, LiveActivity } from "./types"
import {
  shonaNames,
  zimbabweTowns,
  timePool,
  categoryWeights,
  fallbackServices,
  activityWindowSize,
} from "./constants"

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

export function useLiveActivity(servicePool: Service[]) {
  // Hold the latest pool in a ref so the ticker reads current data without the
  // interval being a dependency. Passing `props.activityServices || []` from a
  // layout yields a new array every render/navigation; depending on it here
  // would tear down and recreate the interval (and re-seed the feed) constantly,
  // which is what made the ticker speed up after switching pages.
  const poolRef = useRef(servicePool)
  poolRef.current = servicePool

  const [liveActivities, setLiveActivities] = useState<LiveActivity[]>(() =>
    createInitialLiveActivities(servicePool, activityWindowSize)
  )

  useEffect(() => {
    const prefersReducedMotion = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches
    if (prefersReducedMotion) {
      return
    }

    // Exactly one interval per mounted instance, cleared on unmount.
    const intervalId = window.setInterval(() => {
      setLiveActivities((current) => [
        createLiveActivity(poolRef.current, current),
        ...current,
      ].slice(0, activityWindowSize))
    }, 2800)

    return () => window.clearInterval(intervalId)
  }, [])

  return liveActivities
}
