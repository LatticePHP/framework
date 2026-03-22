import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { NightwatchShell } from "./nightwatch-shell";

const inter = Inter({ subsets: ["latin"], variable: "--font-sans" });

export const metadata: Metadata = {
  title: "Nightwatch - Monitoring",
  description: "Unified monitoring dashboard for LatticePHP",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" className={inter.variable} suppressHydrationWarning>
      <body className="antialiased">
        <NightwatchShell>{children}</NightwatchShell>
      </body>
    </html>
  );
}
