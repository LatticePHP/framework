import type { Metadata } from 'next';
import './globals.css';
import { ClientLayout } from './client-layout';

export const metadata: Metadata = {
  title: 'LatticeCRM',
  description: 'CRM powered by LatticePHP Framework',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className="bg-slate-50 text-slate-900 antialiased">
        <ClientLayout>{children}</ClientLayout>
      </body>
    </html>
  );
}
