import { useState, useEffect, useMemo } from 'react';
import { NextUIProvider } from '@nextui-org/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Layout } from '@/components/Layout';
import { WorkflowListPage } from '@/pages/WorkflowListPage';
import { WorkflowDetailPage } from '@/pages/WorkflowDetailPage';
import { StatsPage } from '@/pages/StatsPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 2000,
      retry: 2,
      refetchOnWindowFocus: true,
    },
  },
});

type Route =
  | { page: 'dashboard' }
  | { page: 'workflows' }
  | { page: 'workflow-detail'; id: string }
  | { page: 'stats' };

function parseHash(hash: string): Route {
  const path = hash.replace(/^#\/?/, '/');

  const detailMatch = path.match(/^\/workflows\/(.+?)(?:\/)?$/);
  if (detailMatch?.[1]) {
    return { page: 'workflow-detail', id: detailMatch[1] };
  }

  if (path.startsWith('/workflows')) {
    return { page: 'workflows' };
  }

  if (path.startsWith('/stats')) {
    return { page: 'stats' };
  }

  return { page: 'dashboard' };
}

function Router() {
  const [hash, setHash] = useState(window.location.hash);

  useEffect(() => {
    const handler = () => setHash(window.location.hash);
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);

  const route = useMemo(() => parseHash(hash), [hash]);

  switch (route.page) {
    case 'workflow-detail':
      return <WorkflowDetailPage workflowId={route.id} />;
    case 'workflows':
      return <WorkflowListPage />;
    case 'stats':
      return <StatsPage />;
    case 'dashboard':
    default:
      return <WorkflowListPage />;
  }
}

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <NextUIProvider>
        <Layout>
          <Router />
        </Layout>
      </NextUIProvider>
    </QueryClientProvider>
  );
}
