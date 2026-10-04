import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../auth';

const links = [
    { to: '/shipments', label: 'Shipments' },
    { to: '/lookup', label: 'Lookup' },
    { to: '/reports', label: 'Reports' },
    { to: '/import', label: 'Import' },
];

export default function Layout() {
    const { user, logout } = useAuth();

    return (
        <div className="min-h-screen">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
                    <NavLink to="/shipments" className="text-base font-semibold text-slate-900">
                        Shipment Tracking
                    </NavLink>
                    <nav className="flex gap-1">
                        {links.map((l) => (
                            <NavLink
                                key={l.to}
                                to={l.to}
                                className={({ isActive }) =>
                                    `rounded-md px-3 py-1.5 text-sm font-medium ${isActive ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}`
                                }
                            >
                                {l.label}
                            </NavLink>
                        ))}
                    </nav>
                    <div className="ml-auto flex items-center gap-3 text-sm text-slate-600">
                        <span>
                            <span className="font-medium text-slate-900">{user?.company.name}</span> · {user?.name}
                        </span>
                        <a href="/api/docs" target="_blank" rel="noreferrer" className="text-blue-700 hover:underline">
                            API docs
                        </a>
                        <button type="button" className="btn" onClick={() => void logout()}>
                            Log out
                        </button>
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-7xl px-4 py-6">
                <Outlet />
            </main>
        </div>
    );
}
