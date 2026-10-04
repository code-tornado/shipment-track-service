import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';
import { ApiError, fmtDate } from '../api';
import type { Status } from '../types';

const STATUS_COLORS: Record<Status, string> = {
    planned: 'bg-slate-100 text-slate-700 ring-slate-300',
    in_transit: 'bg-blue-50 text-blue-700 ring-blue-200',
    arrived: 'bg-amber-50 text-amber-800 ring-amber-200',
    in_storage: 'bg-violet-50 text-violet-700 ring-violet-200',
    delivered: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
};

export function StatusBadge({ status, label }: { status: Status; label?: string }) {
    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${STATUS_COLORS[status] ?? ''}`}>
            {label ?? status}
        </span>
    );
}

export function DelayBadge({ days }: { days: number | null | undefined }) {
    if (days === null || days === undefined) return <span className="text-slate-400">–</span>;
    if (days <= 0) return <span className="text-emerald-700">on time</span>;
    return <span className="font-semibold text-red-700">+{days} d</span>;
}

export function ShipmentLink({ id, reference }: { id: number; reference: string }) {
    return (
        <Link to={`/shipments/${id}`} className="font-mono font-medium text-blue-700 hover:underline">
            {reference}
        </Link>
    );
}

export function Field({ label, children, className = '' }: { label: string; children: ReactNode; className?: string }) {
    return (
        <label className={`block ${className}`}>
            <span className="label">{label}</span>
            {children}
        </label>
    );
}

export function ErrorBox({ error }: { error: unknown }) {
    if (!error) return null;
    const messages = error instanceof ApiError ? error.messages : [String((error as Error).message ?? error)];
    return (
        <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            <ul className="list-disc pl-4">
                {messages.map((m, i) => (
                    <li key={i}>{m}</li>
                ))}
            </ul>
        </div>
    );
}

export function Notice({ children }: { children: ReactNode }) {
    return <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{children}</div>;
}

export function Loading({ what = 'Loading' }: { what?: string }) {
    return <p className="py-6 text-sm text-slate-500">{what}…</p>;
}

export function Empty({ children }: { children: ReactNode }) {
    return <p className="py-6 text-center text-sm text-slate-500">{children}</p>;
}

export function Dates({ etd, eta, ata }: { etd: string | null; eta: string | null; ata: string | null }) {
    return (
        <span className="whitespace-nowrap text-xs text-slate-600">
            ETD {fmtDate(etd)} · ETA {fmtDate(eta)}
            {ata ? ` · arrived ${fmtDate(ata)}` : ''}
        </span>
    );
}

export function Stat({ label, value, tone = '' }: { label: string; value: ReactNode; tone?: string }) {
    return (
        <div className="card">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold ${tone}`}>{value}</div>
        </div>
    );
}

export function Tabs<T extends string>({ tabs, value, onChange }: { tabs: { value: T; label: string }[]; value: T; onChange: (v: T) => void }) {
    return (
        <div className="flex gap-1 border-b border-slate-200">
            {tabs.map((t) => (
                <button
                    key={t.value}
                    type="button"
                    onClick={() => onChange(t.value)}
                    className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium ${
                        t.value === value ? 'border-blue-700 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800'
                    }`}
                >
                    {t.label}
                </button>
            ))}
        </div>
    );
}
