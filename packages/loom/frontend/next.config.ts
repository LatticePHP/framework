import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  async rewrites() {
    return [
      {
        source: "/api/loom/:path*",
        destination: `${process.env.LOOM_API_URL ?? "http://localhost:8000"}/api/loom/:path*`,
      },
    ];
  },
};

export default nextConfig;
