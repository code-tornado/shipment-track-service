import { useEffect, useState } from 'react';
import { api, fmtDate } from '../api';
import { Dates, DelayBadge, Empty, ErrorBox, Field, Loading, ShipmentLink, Stat, StatusBadge, Tabs } from '../components/ui';
import type { DelayedReport, OverviewReport, UpcomingReport } from '../types';

type Tab = 'upcoming' | 'overview' | 'delayed';

const today = () => new Date().toISOString().slice(0, 10);
const plusDays = (days: number) => new Date(Date.now() + days * 86400000).toISOString().slice(0, 10);

export default function ReportsPage() {
    const [tab, setTab] = useState<Tab>('upcoming');

    return (
        <div className="space-y-4">
            <h1 className="text-xl font-semibold">Reports</h1>
            <Tabs
                tabs={[
                    { value: 'upcoming', label: 'Upcoming deliveries' },
                    { value: 'overview', label: 'Status overview' },
                    { value: 'delayed', label: 'Delayed' },
                ]}
                value={tab}
                onChange={setTab}
            />
            {tab === 'upcoming' && <Upcoming />}
            {tab === 'overview' && <Overview />}
            {tab === 'delayed' && <Delayed />}
        </div>
    );
}

function Upcoming() {
    const [from, setFrom] = useState(today());
    const [to, setTo] = useState(plusDays(14));
    const [report, setReport] = useState<UpcomingReport | null>(null);
    const [error, setError] = useState<unknown>(null);

    useEffect(() => {
        setError(null);
        api<UpcomingReport>('/reports/upcoming-deliveries', { query: { from, to } }).then(setReport).catch(setError);
    }, [from, to]);

    return (
        <div className="space-y-4">
            <div className="card flex flex-wrap items-end gap-3">
                <Field label="From">
                    <input className="input" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                </Field>
                <Field label="To">
                    <input className="input" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                </Field>
                <div className="flex gap-2">
                    <button type="button" className="btn" onClick={() => (setFrom(today()), setTo(plusDays(7)))}>
                        Next 7 days
                    </button>
                    <button type="button" className="btn" onClick={() => (setFrom(today()), setTo(plusDays(30)))}>
                        Next 30 days
                    </button>
                </div>
            </div>
            <ErrorBox error={error} />
            {!report ? (
                <Loading />
            ) : (
                <>
                    <section className="card p-0">
                        <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">
                            Customer deliveries due {report.from} – {report.to} <span className="text-sm font-normal text-slate-500">({report.totals.deliveries})</span>
                        </h2>
                        {report.deliveries.length === 0 ? (
                            <Empty>No deliveries planned in this period.</Empty>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Delivery date</th>
                                        <th>Customer</th>
                                        <th>Tag</th>
                                        <th>Product</th>
                                        <th>Invoice</th>
                                        <th>Container</th>
                                        <th>Shipment</th>
                                        <th>Item status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.deliveries.map((i) => (
                                        <tr key={i.id}>
                                            <td className="whitespace-nowrap font-medium">{fmtDate(i.customer_delivery_date)}</td>
                                            <td>{i.customer?.name}</td>
                                            <td className="font-mono">{i.tag_no ?? '–'}</td>
                                            <td className="text-xs">{i.product?.description}</td>
                                            <td className="font-mono text-xs">{i.proforma_invoice_no}</td>
                                            <td className="font-mono text-xs">{i.container_no}</td>
                                            <td>{i.shipment && <ShipmentLink id={i.shipment.id} reference={i.shipment.reference} />}</td>
                                            <td>
                                                <StatusBadge status={i.status} label={i.status_label} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>
                    <section className="card p-0">
                        <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">
                            Arrivals at port expected in the same period <span className="text-sm font-normal text-slate-500">({report.totals.arrivals})</span>
                        </h2>
                        {report.arrivals.length === 0 ? (
                            <Empty>No shipments due to arrive in this period.</Empty>
                        ) : (
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>ETA</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                        <th>Destination</th>
                                        <th>Containers</th>
                                        <th>Customers</th>
                                        <th>Delay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.arrivals.map((s) => (
                                        <tr key={s.id}>
                                            <td className="whitespace-nowrap font-medium">{fmtDate(s.eta)}</td>
                                            <td>
                                                <ShipmentLink id={s.id} reference={s.reference} />
                                            </td>
                                            <td>
                                                <StatusBadge status={s.status} label={s.status_label} />
                                            </td>
                                            <td>{s.destination_port}</td>
                                            <td className="font-mono text-xs">{s.container_nos.join(', ')}</td>
                                            <td className="text-xs">{s.customers.join(', ')}</td>
                                            <td>
                                                <DelayBadge days={s.days_delayed} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>
                </>
            )}
        </div>
    );
}

function Overview() {
    const [report, setReport] = useState<OverviewReport | null>(null);
    const [error, setError] = useState<unknown>(null);

    useEffect(() => {
        api<OverviewReport>('/reports/status-overview').then(setReport).catch(setError);
    }, []);

    if (error) return <ErrorBox error={error} />;
    if (!report) return <Loading />;

    const h = report.highlights;

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
                <Stat label="Shipments" value={h.shipments_total} />
                <Stat label="In transit" value={h.shipments_in_transit} tone="text-blue-700" />
                <Stat label="Arriving ≤ 7 days" value={h.arriving_next_7_days} />
                <Stat label="Delayed shipments" value={h.shipments_delayed} tone={h.shipments_delayed ? 'text-red-700' : ''} />
                <Stat label="Items in storage" value={h.cargo_items_in_storage} tone="text-violet-700" />
                <Stat label="Deliveries ≤ 7 days" value={h.deliveries_next_7_days} />
                <Stat label="Late deliveries" value={h.deliveries_late} tone={h.deliveries_late ? 'text-red-700' : ''} />
            </div>

            <section className="card p-0">
                <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">By status (as of {report.as_of})</h2>
                <table className="table">
                    <thead>
                        <tr>
                            <th></th>
                            {report.statuses.map((s) => (
                                <th key={s.value}>{s.label}</th>
                            ))}
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(['shipments', 'containers', 'cargo_items'] as const).map((row) => {
                            const counts = report.by_status[row];
                            const total = Object.values(counts).reduce((a, b) => a + b, 0);
                            return (
                                <tr key={row}>
                                    <td className="font-medium capitalize">{row.replace('_', ' ')}</td>
                                    {report.statuses.map((s) => (
                                        <td key={s.value}>{counts[s.value]}</td>
                                    ))}
                                    <td className="font-semibold">{total}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </section>

            <section className="card p-0">
                <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">Cargo items by customer</h2>
                <table className="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            {report.statuses.map((s) => (
                                <th key={s.value}>{s.label}</th>
                            ))}
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.by_customer.map((c) => (
                            <tr key={c.customer}>
                                <td className="font-medium">{c.customer}</td>
                                {report.statuses.map((s) => (
                                    <td key={s.value}>{c[s.value]}</td>
                                ))}
                                <td className="font-semibold">{c.cargo_items}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </div>
    );
}

function Delayed() {
    const [report, setReport] = useState<DelayedReport | null>(null);
    const [error, setError] = useState<unknown>(null);

    useEffect(() => {
        api<DelayedReport>('/reports/delayed').then(setReport).catch(setError);
    }, []);

    if (error) return <ErrorBox error={error} />;
    if (!report) return <Loading />;

    return (
        <div className="space-y-4">
            <section className="card p-0">
                <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">
                    Delayed shipments <span className="text-sm font-normal text-slate-500">({report.totals.shipments}) — arrived after the planned ETA, or not arrived although the ETA has passed</span>
                </h2>
                {report.shipments.length === 0 ? (
                    <Empty>No delayed shipments.</Empty>
                ) : (
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Days late</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Destination</th>
                                <th>Dates</th>
                                <th>Containers</th>
                                <th>Customers</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.shipments.map((s) => (
                                <tr key={s.id}>
                                    <td>
                                        <DelayBadge days={s.days_delayed} />
                                    </td>
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
                                    <td className="font-mono text-xs">{s.container_nos.join(', ')}</td>
                                    <td className="text-xs">{s.customers.join(', ')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
            <section className="card p-0">
                <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">
                    Late customer deliveries <span className="text-sm font-normal text-slate-500">({report.totals.cargo_items}) — delivered after the agreed date, or still not delivered</span>
                </h2>
                {report.cargo_items.length === 0 ? (
                    <Empty>No late deliveries.</Empty>
                ) : (
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Days late</th>
                                <th>Agreed date</th>
                                <th>Delivered</th>
                                <th>Customer</th>
                                <th>Tag</th>
                                <th>Product</th>
                                <th>Shipment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.cargo_items.map((i) => (
                                <tr key={i.id}>
                                    <td>
                                        <DelayBadge days={i.days_delayed} />
                                    </td>
                                    <td className="whitespace-nowrap">{fmtDate(i.customer_delivery_date)}</td>
                                    <td className="whitespace-nowrap">{fmtDate(i.delivered_at)}</td>
                                    <td>{i.customer?.name}</td>
                                    <td className="font-mono">{i.tag_no ?? '–'}</td>
                                    <td className="text-xs">{i.product?.description}</td>
                                    <td>{i.shipment && <ShipmentLink id={i.shipment.id} reference={i.shipment.reference} />}</td>
                                    <td>
                                        <StatusBadge status={i.status} label={i.status_label} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
        </div>
    );
}
