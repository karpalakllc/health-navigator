import type { NextConfig } from "next";

function contentSecurityPolicy(): string {
  let apiOrigin = "";

  try {
    if (process.env.NEXT_PUBLIC_API_URL) {
      apiOrigin = new URL(process.env.NEXT_PUBLIC_API_URL).origin;
    }
  } catch {
    apiOrigin = "";
  }

  const connectSrc = ["'self'", apiOrigin].filter(Boolean).join(" ");

  return [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: https://api.dicebear.com",
    "font-src 'self'",
    "frame-src https://www.openstreetmap.org",
    `connect-src ${connectSrc}`,
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ].join("; ");
}

const securityHeaders = [
  { key: "X-Frame-Options", value: "DENY" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=()",
  },
  {
    key: "Content-Security-Policy",
    value: contentSecurityPolicy(),
  },
];

const nextConfig: NextConfig = {
  async headers() {
    return [
      {
        source: "/:path*",
        headers: securityHeaders,
      },
    ];
  },
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "api.dicebear.com",
        pathname: "/**",
      },
    ],
  },
};

export default nextConfig;
