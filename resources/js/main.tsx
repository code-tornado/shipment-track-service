import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom';
import { AuthProvider, useAuth } from './auth';
import Layout from './components/Layout';
import { Loading } from './components/ui';
import ImportPage from './pages/ImportPage';
import LoginPage from './pages/LoginPage';
import LookupPage from './pages/LookupPage';
import NewShipmentPage from './pages/NewShipmentPage';
import ReportsPage from './pages/ReportsPage';
import ShipmentPage from './pages/ShipmentPage';
import ShipmentsPage from './pages/ShipmentsPage';

function RequireAuth() {
    const { user, loading } = useAuth();
    if (loading) return <Loading what="Signing in" />;
    return user ? <Outlet /> : <Navigate to="/login" replace />;
}

function App() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route element={<RequireAuth />}>
                    <Route element={<Layout />}>
                        <Route index element={<Navigate to="/shipments" replace />} />
                        <Route path="/shipments" element={<ShipmentsPage />} />
                        <Route path="/shipments/new" element={<NewShipmentPage />} />
                        <Route path="/shipments/:id" element={<ShipmentPage />} />
                        <Route path="/lookup" element={<LookupPage />} />
                        <Route path="/reports" element={<ReportsPage />} />
                        <Route path="/import" element={<ImportPage />} />
                        <Route path="*" element={<Navigate to="/shipments" replace />} />
                    </Route>
                </Route>
            </Routes>
        </BrowserRouter>
    );
}

createRoot(document.getElementById('app')!).render(
    <StrictMode>
        <AuthProvider>
            <App />
        </AuthProvider>
    </StrictMode>,
);
