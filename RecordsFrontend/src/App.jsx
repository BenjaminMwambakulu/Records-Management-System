import React, { lazy, Suspense } from 'react';
import { LogtoProvider, UserScope } from '@logto/react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Context/AuthContext';
import Layout from './Pages/Layout';
import { canAccessDashboard } from './lib/roles';
import { hasPermission } from './lib/permissions';

// Lazy-loaded page components
const Callback = lazy(() => import('./Pages/Callback'));
const LoginPage = lazy(() => import('./Pages/AuthPages/LoginPage'));
const Dashboard = lazy(() => import('./Pages/Dashboard/Dashboard'));
const MembersIndex = lazy(() => import('./Pages/MemberDirectory/MembersIndex'));
const DocumentsIndex = lazy(() => import('./Pages/DocumentDirectory/DocumentsIndex'));
const DocumentDetailPage = lazy(() => import('./Pages/DocumentDirectory/DocumentDetailPage'));
const EventsIndex = lazy(() => import('./Pages/EventsDirectory/EventsIndex'));
const EventDetailPage = lazy(() => import('./Pages/EventsDirectory/EventDetailPage'));
const AssetsIndex = lazy(() => import('./Pages/AssetDirectory/AssetsIndex'));
const LogsIndex = lazy(() => import('./Pages/LogsDirectory/LogsIndex'));
const FinancialRecordsIndex = lazy(() => import('./Pages/FinancialDirectory/FinancialRecordsIndex'));
const RolesIndex = lazy(() => import('./Pages/AdminDirectory/RolesIndex'));
const RoleDetailPage = lazy(() => import('./Pages/AdminDirectory/RoleDetailPage'));
const LandingPage = lazy(() => import('./Pages/Landing/LandingPage'));
const PublicEventDetailPage = lazy(() => import('./Pages/Landing/PublicEventDetailPage'));
const SettingsPage = lazy(() => import('./Pages/Settings/SettingsPage'));
const MyStudentsIndex = lazy(() => import('./Pages/MyStudents/MyStudentsIndex'));

const config = {
  endpoint: import.meta.env.VITE_LOGTO_ENDPOINT,
  appId: import.meta.env.VITE_LOGTO_APP_ID,
  scopes: [
    UserScope.Profile, // Adds 'picture', 'name', 'username', etc.
    UserScope.Email,   // Included for email claim
    UserScope.Roles,   // Keep if you need custom roles/permissions
  ],
  resources: import.meta.env.VITE_LOGTO_APP_RESOURCE
    ? [import.meta.env.VITE_LOGTO_APP_RESOURCE]
    : undefined,
  redirectUri: import.meta.env.VITE_REDIRECT_URI,
  postLogoutRedirectUri: import.meta.env.VITE_LOGTO_POST_LOGOUT_REDIRECT_URI,
};

function ProtectedRoute({ children }) {
  const { isAuthenticated, sessionRestored, user } = useAuth();

  if (!sessionRestored) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-csit-surface">
        <div className="flex flex-col items-center gap-4">
          <div className="loader" aria-hidden="true" />
          <p className="text-lg text-csit-text animate-pulse">
            Loading your workspace, tools, and resources
          </p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (!canAccessDashboard(user)) {
    return <Navigate to="/" replace />;
  }

  return children;
}

function PageLoader() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-csit-surface">
      <div className="flex flex-col items-center gap-4">
        <div className="loader" aria-hidden="true" />
        <p className="text-lg text-csit-text animate-pulse">Loading…</p>
      </div>
    </div>
  );
}

function YearRepRoute({ children }) {
  const { user } = useAuth();

  if (!hasPermission(user, 'members.year_rep.manage')) {
    return <Navigate to="/app" replace />;
  }

  return children;
}

function PublicRoute({ children }) {
  const { isAuthenticated, sessionRestored } = useAuth();

  if (!sessionRestored) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-csit-surface">
        <div className="flex flex-col items-center gap-4">
          <div className="loader" aria-hidden="true" />
          <p className="text-lg text-csit-text animate-pulse">
            Loading your workspace, tools, and resources
          </p>
        </div>
      </div>
    );
  }

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  return children;
}

export default function App() {
  return (
    <LogtoProvider config={config}>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/callback" element={<Suspense fallback={<PageLoader />}><Callback /></Suspense>} />
            <Route
              path="/login"
              element={
                <PublicRoute>
                  <Suspense fallback={<PageLoader />}><LoginPage /></Suspense>
                </PublicRoute>
              }
            />
            <Route path="/" element={<Suspense fallback={<PageLoader />}><LandingPage /></Suspense>} />
            <Route path="/events/:id" element={<Suspense fallback={<PageLoader />}><PublicEventDetailPage /></Suspense>} />
            <Route
              path="/app"
              element={
                <ProtectedRoute>
                  <Layout />
                </ProtectedRoute>
              }
            >
              <Route index element={<Suspense fallback={<PageLoader />}><Dashboard /></Suspense>} />
              <Route path="members" element={<Suspense fallback={<PageLoader />}><MembersIndex /></Suspense>} />
              <Route path="my-students" element={<YearRepRoute><Suspense fallback={<PageLoader />}><MyStudentsIndex /></Suspense></YearRepRoute>} />
              <Route path="events" element={<Suspense fallback={<PageLoader />}><EventsIndex /></Suspense>} />
              <Route path="events/:id" element={<Suspense fallback={<PageLoader />}><EventDetailPage /></Suspense>} />
              <Route path="assets" element={<Suspense fallback={<PageLoader />}><AssetsIndex /></Suspense>} />
              <Route path="logs" element={<Suspense fallback={<PageLoader />}><LogsIndex /></Suspense>} />
              <Route path="roles" element={<Suspense fallback={<PageLoader />}><RolesIndex /></Suspense>} />
              <Route path="roles/:id" element={<Suspense fallback={<PageLoader />}><RoleDetailPage /></Suspense>} />
              <Route path="financial-records" element={<Suspense fallback={<PageLoader />}><FinancialRecordsIndex /></Suspense>} />
              <Route path="documents" element={<Suspense fallback={<PageLoader />}><DocumentsIndex /></Suspense>} />
              <Route path="documents/:id" element={<Suspense fallback={<PageLoader />}><DocumentDetailPage /></Suspense>} />
              <Route path="settings" element={<Suspense fallback={<PageLoader />}><SettingsPage /></Suspense>} />
            </Route>
          </Routes>
        </AuthProvider>
      </BrowserRouter> x
    </LogtoProvider>
  );
}
