import { Service } from "./types"

export const shonaNames = [
  "Tendai", "Rudo", "Nyasha", "Tafadzwa", "Rutendo", "Tadiwa", "Munashe", 
  "Anesu", "Tanaka", "Kudakwashe", "Ropafadzo", "Simbarashe", "Chiedza", 
  "Farai", "Shingirai", "Vimbai", "Tinashe", "Mufaro", "Tawananyasha", 
  "Tatenda", "Tonderai", "Tariro", "Tsitsi", "Tapiwa", "Blessing", "Panashe", 
  "Takudzwa", "Nomsa", "Memory", "Ashley", "Rumbidzai", "Kundai", "Loveness", 
  "Virginia", "Fadzai", "Priscilla", "Taurai", "Melody", "Shamiso", "Takunda", 
  "Wadzi", "Yamurai", "Munyaradzi", "Chenai", "Rangarirai", "Dzimbanhete", 
  "Tafara", "Tashinga", "Vongai", "Nyaradzo", "Ruramai", "Tendekai", 
  "Makanaka", "Tinotenda", "Kudzai", "Marvellous", "Ratidzo", "Yeukai",
]

export const zimbabweTowns = [
  "Harare", "Chitungwiza", "Mutare", "Gweru", "Masvingo", "Kadoma", 
  "Marondera", "Bindura", "Chegutu", "Kwekwe", "Norton", "Rusape", 
  "Karoi", "Chipinge", "Zvishavane", "Redcliff", "Shurugwi", "Mvurwi", 
  "Gokwe", "Hwange",
]

export const timePool = [
  "just now", "8 sec ago", "14 sec ago", "22 sec ago", "41 sec ago", 
  "1 min ago", "2 min ago", "3 min ago", "5 min ago"
]

export const activityWindowSize = 5

export const categoryWeights: Record<string, number> = {
  instagram: 1.25,
  tiktok: 1.22,
  youtube: 1.16,
  facebook: 1.08,
  telegram: 1.04,
  twitter: 0.96,
  whatsapp: 0.92,
}

export const fallbackServices: Service[] = [
  { id: 1, name: "Instagram Followers", name_sn: "Instagram Followers", category: "instagram" },
  { id: 2, name: "TikTok Views", name_sn: "TikTok Views", category: "tiktok" },
  { id: 3, name: "YouTube Likes", name_sn: "YouTube Likes", category: "youtube" },
  { id: 4, name: "Facebook Page Likes", name_sn: "Facebook Page Likes", category: "facebook" },
  { id: 5, name: "Telegram Members", name_sn: "Telegram Members", category: "telegram" },
]

export const fallbackCategories = ["instagram", "tiktok", "youtube", "facebook", "twitter", "telegram", "whatsapp"]
