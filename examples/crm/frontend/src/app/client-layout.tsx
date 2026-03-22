'use client';

import { usePathname } from 'next/navigation';
import { Sidebar } from '@/components/layout/sidebar';
import { Header } from '@/components/layout/header';
import { ToastProvider } from '@/components/ui/toast';

export function ClientLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const isLoginPage = pathname === '/login';

  if (isLoginPage) {
    return <ToastProvider>{children}</ToastProvider>;
  }

  return (
    <ToastProvider>
      <div className="flex min-h-screen">
        <Sidebar />
        <div className="flex flex-1 flex-col pl-64">
          <Header />
          <main className="flex-1 p-6">{children}</main>
        </div>
      </div>
    </ToastProvider>
  );
}
