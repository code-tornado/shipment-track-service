import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, fmtDate } from '../api';
import { DelayBadge, Empty, ErrorBox, Field, Loading, ShipmentLink, StatusBadge } from '../components/ui';
import { STATUSES, type Paginated, type ShipmentSummary } from '../types';

const FILTERS = ['q', 'status', 'destination_port', 'customer', 'shipping_method', 'eta_from', 'eta_to', 'delayed', 'sort', 'page'] as const;

export default function ShipmentsPage() {
    const [params, setParams] = useSearchParams();
    const [result, setResult] = useState<Paginated<ShipmentSummary> | null>(null);
    const [error, setError] = useState<unknown>(null);
    const [loading, setLoading] = useState(true);

    const filters = Object.fromEntries(FILTERS.map((k) => [k, params.get(k) ?? ''])) as Record<(typeof FILTERS)[number], string>;

    const update = (changes: Partial<typeof filters>) => {
        const next = new URLSearchParams(params);
        for (const [k, v] of Object.entries(changes)) v ? next.set(k, v) : next.delete(k);
        if (!('page' in changes)) next.delete('page');
        setParams(next);
    };

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        api<Paginated<ShipmentSummary>>('/shipments', { query: { ...filters, per_page: 25 } })
            .then((r) => !cancelled && setResult(r))
            .catch((e) => !cancelled && setError(e))
            .finally(() => !cancelled && setLoading(false));
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [params.toString()]);

    const sort = filters.sort || '-etd';
    const toggleSort = (column: string) => update({ sort: sort === column ? `-${column}` : column });
    const sortMark = (column: string) => (sort === column ? ' ▲' : sort === `-${column}` ? ' ▼' : '');

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-semibold">Shipments</h1>
                <Link to="/shipments/new" className="btn btn-primary">
                    + New shipment
                </Link>
            </div>

            <div className="card grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
                <Field label="Search" className="col-span-2">
                    <input
                        className="input"
                        placeholder="reference, container, invoice, tag, customer, product…"
                        value={filters.q}
                        onChange={(e) => update({ q: e.target.value })}
                    />
                </Field>
                <Field label="Status">
                    <select className="input" value={filters.status} onChange={(e) => update({ status: e.target.value })}>
                        <option value="">All</option>
                        {STATUSES.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Destination">
                    <input className="input" value={filters.destination_port} onChange={(e) => update({ destination_port: e.target.value })} />
                </Field>
                <Field label="Customer">
                    <input className="input" value={filters.customer} onChange={(e) => update({ customer: e.target.value })} />
                </Field>
                <Field label="ETA from">
                    <input className="input" type="date" value={filters.eta_from} onChange={(e) => update({ eta_from: e.target.value })} />
                </Field>
                <Field label="ETA to">
                    <input className="input" type="date" value={filters.eta_to} onChange={(e) => update({ eta_to: e.target.value })} />
                </Field>
                <label className="col-span-2 flex items-center gap-2 text-sm md:col-span-1">
                    <input type="checkbox" checked={filters.delayed === '1'} onChange={(e) => update({ delayed: e.target.checked ? '1' : '' })} />
                    Delayed only
                </label>
                <div className="col-span-2 flex items-end md:col-span-1">
                    <button type="button" className="btn" onClick={() => setParams(new URLSearchParams())}>
                        Clear filters
                    </button>
                </div>
            </div>

            <ErrorBox error={error} />

            <div className="card overflow-x-auto p-0">
                <table className="table">
                    <thead>
                        <tr>
                            <th className="cursor-pointer" onClick={() => toggleSort('reference')}>
                                Reference{sortMark('reference')}
                            </th>
                            <th className="cursor-pointer" onClick={() => toggleSort('status')}>
                                Status{sortMark('status')}
                            </th>
                            <th>Route</th>
                            <th className="cursor-pointer" onClick={() => toggleSort('etd')}>
                                ETD{sortMark('etd')}
                            </th>
                            <th className="cursor-pointer" onClick={() => toggleSort('eta')}>
                                ETA{sortMark('eta')}
                            </th>
                            <th>Arrived</th>
                            <th>Delay</th>
                            <th>Containers</th>
                            <th>Items</th>
                            <th>Customers</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading && !result ? (
                            <tr>
                                <td colSpan={10}>
                                    <Loading />
                                </td>
                            </tr>
                        ) : result && result.data.length === 0 ? (
                            <tr>
                                <td colSpan={10}>
                                    <Empty>No shipments match these filters.</Empty>
                                </td>
                            </tr>
                        ) : (
                            result?.data.map((s) => (
                                <tr key={s.id} className="hover:bg-slate-50">
                                    <td>
                                        <ShipmentLink id={s.id} reference={s.reference} />
                                        <div className="text-xs text-slate-500">{s.shipping_line ?? s.shipping_method}</div>
                                    </td>
                                    <td>
                                        <StatusBadge status={s.status} label={s.status_label} />
                                    </td>
                                    <td className="text-xs">
                                        {s.origin_port ?? '?'} → <span className="font-medium">{s.destination_port}</span>
                                        <div className="text-slate-500">{s.shipping_method}</div>
                                    </td>
                                    <td className="whitespace-nowrap">{fmtDate(s.etd)}</td>
                                    <td className="whitespace-nowrap">{fmtDate(s.eta)}</td>
                                    <td className="whitespace-nowrap">{fmtDate(s.ata)}</td>
                                    <td>
                                        <DelayBadge days={s.days_delayed} />
                                    </td>
                                    <td className="font-mono text-xs">{s.container_nos.join(', ')}</td>
                                    <td>
                                        {s.delivered_items_count}/{s.cargo_items_count}
                                    </td>
                                    <td className="text-xs">{s.customers.join(', ')}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {result && result.meta.last_page > 1 && (
                <div className="flex items-center justify-between text-sm text-slate-600">
                    <span>
                        {result.meta.from}–{result.meta.to} of {result.meta.total}
                    </span>
                    <div className="flex gap-2">
                        <button className="btn" disabled={result.meta.current_page <= 1} onClick={() => update({ page: String(result.meta.current_page - 1) })}>
                            ‹ Previous
                        </button>
                        <button
                            className="btn"
                            disabled={result.meta.current_page >= result.meta.last_page}
                            onClick={() => update({ page: String(result.meta.current_page + 1) })}
                        >
                            Next ›
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
