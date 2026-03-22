import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import DashboardPage from './pages/DashboardPage';
import RecentJobsPage from './pages/RecentJobsPage';
import FailedJobsPage from './pages/FailedJobsPage';
import JobDetailPage from './pages/JobDetailPage';
import WorkersPage from './pages/WorkersPage';

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<DashboardPage />} />
        <Route path="jobs/recent" element={<RecentJobsPage />} />
        <Route path="jobs/failed" element={<FailedJobsPage />} />
        <Route path="jobs/:id" element={<JobDetailPage />} />
        <Route path="workers" element={<WorkersPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}
