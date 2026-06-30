export type Service = {
  id: number
  name: string
  name_sn?: string | null
  category: string
}

export type LiveActivity = {
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
