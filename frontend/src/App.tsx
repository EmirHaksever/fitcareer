import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from '@/context/AuthContext';
import { AppShell } from '@/components/layout/AppShell';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { GuestRoute, ProtectedRoute } from '@/components/routing/ProtectedRoute';
import { LandingPage } from '@/pages/public/LandingPage';
import { LoginPage } from '@/pages/auth/LoginPage';
import { RegisterPage } from '@/pages/auth/RegisterPage';
import { ForgotPasswordPage } from '@/pages/auth/ForgotPasswordPage';
import { ResetPasswordPage } from '@/pages/auth/ResetPasswordPage';
import { DashboardPage } from '@/pages/candidate/DashboardPage';
import { JobDetailPage } from '@/pages/candidate/JobDetailPage';
import { JobsPage } from '@/pages/candidate/JobsPage';
import { ProfilePage } from '@/pages/candidate/ProfilePage';
import { ApplicationsPage } from '@/pages/candidate/ApplicationsPage';
import { ApplicationDetailPage } from '@/pages/candidate/ApplicationDetailPage';
import { CompanyApplicationDetailPage } from '@/pages/company/CompanyApplicationDetailPage';
import { CompanyApplicationsPage } from '@/pages/company/CompanyApplicationsPage';
import { CompanyDashboardPage } from '@/pages/company/CompanyDashboardPage';
import { CompanyJobCreatePage } from '@/pages/company/CompanyJobCreatePage';
import { CompanyJobEditPage } from '@/pages/company/CompanyJobEditPage';
import { CompanyJobsPage } from '@/pages/company/CompanyJobsPage';
import { CompanySettingsPage } from '@/pages/company/CompanySettingsPage';
import { SavedJobsPage } from '@/pages/candidate/SavedJobsPage';
import { FitAnalysisPage } from '@/pages/candidate/FitAnalysisPage';
import { NotificationsPage } from '@/pages/candidate/NotificationsPage';
import { SettingsPage } from '@/pages/candidate/SettingsPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/" element={<LandingPage />} />

            <Route element={<GuestRoute />}>
              <Route element={<AuthLayout />}>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                <Route path="/reset-password" element={<ResetPasswordPage />} />
              </Route>
            </Route>

            <Route element={<ProtectedRoute allowedRoles={['candidate']} />}>
              <Route element={<AppShell />}>
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/jobs" element={<JobsPage />} />
                <Route path="/applications" element={<ApplicationsPage />} />
                <Route path="/applications/:id" element={<ApplicationDetailPage />} />
                <Route path="/profile" element={<ProfilePage />} />
                <Route path="/fit-analysis" element={<FitAnalysisPage />} />
                <Route path="/saved" element={<SavedJobsPage />} />
                <Route path="/notifications" element={<NotificationsPage />} />
                <Route path="/settings" element={<SettingsPage />} />
              </Route>
            </Route>

            <Route element={<ProtectedRoute allowedRoles={['candidate', 'company']} />}>
              <Route element={<AppShell />}>
                <Route path="/jobs/:slug" element={<JobDetailPage />} />
              </Route>
            </Route>

            <Route element={<ProtectedRoute allowedRoles={['company']} />}>
              <Route element={<AppShell />}>
                <Route path="/company/dashboard" element={<CompanyDashboardPage />} />
                <Route path="/company/jobs" element={<CompanyJobsPage />} />
                <Route path="/company/jobs/new" element={<CompanyJobCreatePage />} />
                <Route path="/company/jobs/:id/edit" element={<CompanyJobEditPage />} />
                <Route path="/company/applications" element={<CompanyApplicationsPage />} />
                <Route path="/company/applications/:id" element={<CompanyApplicationDetailPage />} />
                <Route path="/company/settings" element={<CompanySettingsPage />} />
              </Route>
            </Route>

            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  );
}
