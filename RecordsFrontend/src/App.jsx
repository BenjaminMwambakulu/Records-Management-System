import React from 'react';
import { LogtoProvider, UserScope } from '@logto/react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './Context/AuthContext';
import Callback from './Pages/Callback';
import LoginPage from './Pages/AuthPages/LoginPage';
import Dashboard from './Pages/Dashboard/Dashboard';
import MembersIndex from './Pages/MemberDirectory/MembersIndex';
import DocumentsIndex from './Pages/DocumentDirectory/DocumentsIndex';
import DocumentDetailPage from './Pages/DocumentDirectory/DocumentDetailPage';
import EventsIndex from './Pages/EventsDirectory/EventsIndex';
import EventDetailPage from './Pages/EventsDirectory/EventDetailPage';
import AssetsIndex from './Pages/AssetDirectory/AssetsIndex';
import LogsIndex from './Pages/LogsDirectory/LogsIndex';
import FinancialRecordsIndex from './Pages/FinancialDirectory/FinancialRecordsIndex';
import RolesIndex from "./Pages/AdminDirectory/RolesIndex";
import RoleDetailPage from "./Pages/AdminDirectory/RoleDetailPage";
import LandingPage from './Pages/Landing/LandingPage';
import PublicEventDetailPage from './Pages/Landing/PublicEventDetailPage';
import SettingsPage from './Pages/Settings/SettingsPage';
import MyStudentsIndex from './Pages/MyStudents/MyStudentsIndex';
import Layout from './Pages/Layout';
import { canAccessDashboard } from './lib/roles';

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
            <Route path="/callback" element={<Callback />} />
            <Route
              path="/login"
              element={
                <PublicRoute>
                  <LoginPage />
                </PublicRoute>
              }
            />
            <Route path="/" element={<LandingPage />} />
            <Route path="/events/:id" element={<PublicEventDetailPage />} />
            <Route
              path="/app"
              element={
                <ProtectedRoute>
                  <Layout />
                </ProtectedRoute>
              }
            >
              <Route index element={<Dashboard />} />
              <Route path="members" element={<MembersIndex />} />
              <Route path="my-students" element={<MyStudentsIndex />} />
              <Route path="events" element={<EventsIndex />} />
              <Route path="events/:id" element={<EventDetailPage />} />
              <Route path="assets" element={<AssetsIndex />} />
              <Route path="logs" element={<LogsIndex />} />
              <Route path="roles" element={<RolesIndex />} />
              <Route path="roles/:id" element={<RoleDetailPage />} />
              <Route path="financial-records" element={<FinancialRecordsIndex />} />
              <Route path="documents" element={<DocumentsIndex />} />
              <Route path="documents/:id" element={<DocumentDetailPage />} />
              <Route path="settings" element={<SettingsPage />} />
            </Route>
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </LogtoProvider>
  );
}
