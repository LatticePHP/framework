import type { Metadata } from "next";
import "./globals.css";
import { LoomShell } from "./loom-shell";

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
    <html lang="en" suppressHydrationWarning>
      <body>
        <LoomShell>{children}</LoomShell>
      </body>
    </html>
  );
}
