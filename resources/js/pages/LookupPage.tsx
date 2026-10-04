import { useState, type FormEvent } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api, fmtDate } from '../api';
import { Dates, DelayBadge, Empty, ErrorBox, Field, ShipmentLink, StatusBadge } from '../components/ui';
import type { LookupResult } from '../types';

const TYPES = [
    { value: 'all', label: 'Everything' },
    { value: 'container', label: 'Container no' },
    { value: 'invoice', label: 'Proforma invoice' },
    { value: 'tag', label: 'Tag number' },
    { value: 'product', label: 'Product' },
    { value: 'customer', label: 'Customer' },
];

export default function LookupPage() {
    const [params, setParams] = useSearchParams();
    const [q, setQ] = useState(params.get('q') ?? '');
    const [type, setType] = useState(params.get('type') ?? 'all');
    const [result, setResult] = useState<LookupResult | null>(null);
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);

    const run = async (query: string, kind: string) => {
        if (query.trim().length < 2) return;
        setBusy(true);
        setError(null);
        try {
            setResult(await api<LookupResult>('/lookup', { query: { q: query.trim(), type: kind } }));
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setParams({ q, type });
        void run(q, type);
    };

    useState(() => {
        if (q) void run(q, type);
    });

    return (
        <div className="space-y-4">
            <h1 className="text-xl font-semibold">Lookup</h1>
            <form onSubmit={submit} className="card flex flex-wrap items-end gap-3">
                <Field label="Search for" className="min-w-80 flex-1">
                    <input className="input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="HLBU8324720, 862600578, GNP-2608002, lice shield, Fisk AS…" autoFocus />
                </Field>
                <Field label="In">
                    <select className="input" value={type} onChange={(e) => setType(e.target.value)}>
                        {TYPES.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <button className="btn btn-primary" disabled={busy}>
                    {busy ? 'Searching…' : 'Search'}
                </button>
            </form>
            <ErrorBox error={error} />

            {result && (
                <>
                    <section className="card p-0">
                        <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">
                            Shipments <span className="text-sm font-normal text-slate-500">({result.shipments.length})</span>
                        </h2>
                        {result.shipments.length === 0 ? (
                            <Empty>No shipments match “{result.query}”.</Empty>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Status</th>
                                        <th>Destination</th>
                                        <th>Dates</th>
                                        <th>Delay</th>
                                        <th>Containers</th>
                                        <th>Customers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.shipments.map((s) => (
                                        <tr key={s.id}>
                                            <td>
                                                <ShipmentLink id={s.id} reference={s.reference} />
                                            </td>
                                            <td>
                                                <StatusBadge status={s.status} label={s.status_label} />
                                            </td>
                                            <td>{s.destination_port}</td>
                                            <td>
                                                <Dates etd={s.etd} eta={s.eta} ata={s.ata} />
                                            </td>
                                            <td>
                                                <DelayBadge days={s.days_delayed} />
                                            </td>
                                            <td className="font-mono text-xs">{s.container_nos.join(', ')}</td>
                                            <td className="text-xs">{s.customers.join(', ')}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>

                    {result.containers.length > 0 && (
                        <section className="card p-0">
                            <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">Containers ({result.containers.length})</h2>
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Container</th>
                                        <th>Shipment</th>
                                        <th>Status</th>
                                        <th>Storage</th>
                                        <th>Items</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.containers.map((c) => (
                                        <tr key={c.id}>
                                            <td className="font-mono">
                                                {c.container_no} <span className="text-xs text-slate-500">{c.container_type}</span>
                                            </td>
                                            <td>{c.shipment && <ShipmentLink id={c.shipment.id} reference={c.shipment.reference} />}</td>
                                            <td>{c.shipment && <StatusBadge status={c.shipment.status} label={c.shipment.status_label} />}</td>
                                            <td className="text-xs">
                                                {c.storage_facility ?? '–'}
                                                {c.storage_date ? ` (${fmtDate(c.storage_date)})` : ''}
                                            </td>
                                            <td className="text-xs">{c.cargo_items?.map((i) => i.tag_no ?? i.package_no).join(', ')}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    )}

                    {result.cargo_items.length > 0 && (
                        <section className="card p-0">
                            <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">Cargo items ({result.cargo_items.length})</h2>
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Tag</th>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Product</th>
                                        <th>Container</th>
                                        <th>Shipment</th>
                                        <th>Status</th>
                                        <th>Delivery</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.cargo_items.map((i) => (
                                        <tr key={i.id}>
                                            <td className="font-mono">{i.tag_no ?? <span className="text-slate-400">–</span>}</td>
                                            <td className="font-mono text-xs">{i.proforma_invoice_no}</td>
                                            <td>{i.customer?.name}</td>
                                            <td className="text-xs">{i.product?.description}</td>
                                            <td className="font-mono text-xs">{i.container_no}</td>
                                            <td>{i.shipment && <ShipmentLink id={i.shipment.id} reference={i.shipment.reference} />}</td>
                                            <td>
                                                <StatusBadge status={i.status} label={i.status_label} />
                                            </td>
                                            <td className="text-xs">
                                                {fmtDate(i.customer_delivery_date)}
                                                {i.delivered_at ? ` · delivered ${fmtDate(i.delivered_at)}` : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    )}
                </>
            )}
        </div>
    );
}
