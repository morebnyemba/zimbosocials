import { Head } from "@inertiajs/react"

type JsonLd = Record<string, unknown>

type SeoHeadProps = {
  title: string
  description: string
  keywords?: string[]
  urlPath?: string
  image?: string
  type?: "website" | "article"
  noindex?: boolean
  structuredData?: JsonLd | JsonLd[]
}

const DEFAULT_IMAGE = "/images/zimbosocials.png"
const DEFAULT_ORIGIN = "https://zimsocials.co.zw"

function resolveOrigin() {
  if (typeof window === "undefined") return DEFAULT_ORIGIN
  return window.location.origin
}

function absoluteUrl(origin: string, value: string) {
  return new URL(value, origin).toString()
}

export default function SeoHead({
  title,
  description,
  keywords = [],
  urlPath,
  image = DEFAULT_IMAGE,
  type = "website",
  noindex = false,
  structuredData,
}: SeoHeadProps) {
  const origin = resolveOrigin()
  const currentPath = typeof window !== "undefined" ? window.location.pathname : "/"
  const canonicalUrl = absoluteUrl(origin, urlPath ?? currentPath)
  const imageUrl = absoluteUrl(origin, image)
  const robots = noindex
    ? "noindex, nofollow"
    : "index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1"

  const defaultWebPageSchema: JsonLd = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    name: title,
    description,
    url: canonicalUrl,
    inLanguage: "en-ZW",
    isPartOf: {
      "@type": "WebSite",
      name: "Zimbo Socials",
      url: origin,
    },
  }

  const jsonLdNodes = [
    defaultWebPageSchema,
    ...(Array.isArray(structuredData)
      ? structuredData
      : structuredData
        ? [structuredData]
        : []),
  ]

  return (
    <Head title={title}>
      <meta head-key="description" name="description" content={description} />
      <meta head-key="robots" name="robots" content={robots} />
      <meta head-key="og:title" property="og:title" content={title} />
      <meta head-key="og:description" property="og:description" content={description} />
      <meta head-key="og:type" property="og:type" content={type} />
      <meta head-key="og:url" property="og:url" content={canonicalUrl} />
      <meta head-key="og:image" property="og:image" content={imageUrl} />
      <meta head-key="og:site_name" property="og:site_name" content="Zimbo Socials" />
      <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
      <meta head-key="twitter:title" name="twitter:title" content={title} />
      <meta head-key="twitter:description" name="twitter:description" content={description} />
      <meta head-key="twitter:image" name="twitter:image" content={imageUrl} />
      <link head-key="canonical" rel="canonical" href={canonicalUrl} />
      {keywords.length > 0 && (
        <meta head-key="keywords" name="keywords" content={keywords.join(", ")} />
      )}
      {jsonLdNodes.map((node, index) => (
        <script
          key={`jsonld-${index}`}
          head-key={`jsonld-${index}`}
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(node) }}
        />
      ))}
    </Head>
  )
}
