import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { LoomShell } from "./loom-shell";

const inter = Inter({ subsets: ["latin"], variable: "--font-sans" });

export const metadata: Metadata = {
  title: "Loom - Queue Dashboard",
  description: "Lattice Loom queue monitoring dashboard",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" className={inter.variable} suppressHydrationWarning>
      <body className="antialiased">
        <LoomShell>{children}</LoomShell>
      </body>
    </html>
  );
}
