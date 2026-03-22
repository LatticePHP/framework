import { useEffect } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { Spinner } from '@nextui-org/react';
import { useModeStore } from '@/stores/mode';
import { useStatus } from '@/api/entries';
import Layout from '@/components/Layout';

// Dev pages
import RequestsPage from '@/pages/dev/RequestsPage';
import QueriesPage from '@/pages/dev/QueriesPage';
import ExceptionsPage from '@/pages/dev/ExceptionsPage';
import EventsPage from '@/pages/dev/EventsPage';
import CachePage from '@/pages/dev/CachePage';
import JobsPage from '@/pages/dev/JobsPage';
import MailPage from '@/pages/dev/MailPage';
import LogsPage from '@/pages/dev/LogsPage';

// Prod pages
import OverviewPage from '@/pages/prod/OverviewPage';
import SlowRequestsPage from '@/pages/prod/SlowRequestsPage';
import SlowQueriesPage from '@/pages/prod/SlowQueriesPage';
import ProdExceptionsPage from '@/pages/prod/ExceptionsPage';

function ModeDetector({ children }: { children: React.ReactNode }) {
  const { setMode, setEnabled, setLoading, loading } = useModeStore();
  const { data, isLoading, error } = useStatus();

  useEffect(() => {
    if (data) {
      setMode(data.mode);
      setEnabled(data.enabled);
      setLoading(false);
    } else if (error) {
      // Default to dev mode if API is unavailable
      setLoading(false);
    }
  }, [data, error, setMode, setEnabled, setLoading]);

  if (isLoading || loading) {
    return (
      <div className="flex items-center justify-center h-screen bg-background">
        <div className="text-center">
          <Spinner size="lg" color="primary" />
          <p className="mt-4 text-default-400">Initializing Nightwatch...</p>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}

function ModeRedirect() {
  const mode = useModeStore((s) => s.mode);
  return <Navigate to={mode === 'prod' ? '/prod/overview' : '/dev/requests'} replace />;
}

export default function App() {
  return (
    <ModeDetector>
      <Routes>
        <Route element={<Layout />}>
          {/* Redirect root to appropriate mode */}
          <Route index element={<ModeRedirect />} />

          {/* Dev routes */}
          <Route path="dev/requests" element={<RequestsPage />} />
          <Route path="dev/queries" element={<QueriesPage />} />
          <Route path="dev/exceptions" element={<ExceptionsPage />} />
          <Route path="dev/events" element={<EventsPage />} />
          <Route path="dev/cache" element={<CachePage />} />
          <Route path="dev/jobs" element={<JobsPage />} />
          <Route path="dev/mail" element={<MailPage />} />
          <Route path="dev/logs" element={<LogsPage />} />

          {/* Prod routes */}
          <Route path="prod/overview" element={<OverviewPage />} />
          <Route path="prod/slow-requests" element={<SlowRequestsPage />} />
          <Route path="prod/slow-queries" element={<SlowQueriesPage />} />
          <Route path="prod/exceptions" element={<ProdExceptionsPage />} />

          {/* Catch-all */}
          <Route path="*" element={<ModeRedirect />} />
        </Route>
      </Routes>
    </ModeDetector>
  );
}
