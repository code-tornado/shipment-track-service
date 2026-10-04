import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useParams } from 'react-router-dom';
import { api, fmtDate, fmtDateTime } from '../api';
import { DelayBadge, ErrorBox, Field, Loading, Notice, StatusBadge } from '../components/ui';
import { STATUSES, type CargoItem, type Container, type HistoryEntry, type Shipment } from '../types';

export default function ShipmentPage() {
    const { id } = useParams();
    const [shipment, setShipment] = useState<Shipment | null>(null);
    const [history, setHistory] = useState<HistoryEntry[]>([]);
    const [error, setError] = useState<unknown>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const reload = useCallback(async () => {
        const [s, h] = await Promise.all([api<{ data: Shipment }>(`/shipments/${id}`), api<{ data: HistoryEntry[] }>(`/shipments/${id}/history`)]);
        setShipment(s.data);
        setHistory(h.data);
    }, [id]);

    useEffect(() => {
        reload().catch(setError);
    }, [reload]);

    const done = (message: string) => {
        setNotice(message);
        setTimeout(() => setNotice(null), 4000);
        return reload();
    };

    if (error && !shipment) return <ErrorBox error={error} />;
    if (!shipment) return <Loading what="Loading shipment" />;

    return (
        <div className="space-y-5">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="font-mono text-2xl font-semibold">{shipment.reference}</h1>
                        <StatusBadge status={shipment.status} label={shipment.status_label} />
                        {shipment.is_delayed && (
                            <span className="rounded bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-red-200">
                                delayed {shipment.days_delayed} d
                            </span>
                        )}
                    </div>
                    <p className="mt-1 text-sm text-slate-600">
                        {shipment.shipping_method} · {shipment.shipping_line ?? 'no line'} · {shipment.origin_port ?? '?'} → {shipment.destination_port}
                        {shipment.incoterm ? ` · ${shipment.incoterm}` : ''}
                    </p>
                    {shipment.notes && <p className="mt-1 text-sm italic text-slate-500">{shipment.notes}</p>}
                </div>
                <dl className="grid grid-cols-3 gap-x-6 gap-y-1 text-sm">
                    <dt className="text-slate-500">ETD</dt>
                    <dd className="col-span-2 font-medium">{fmtDate(shipment.etd)}</dd>
                    <dt className="text-slate-500">ETA</dt>
                    <dd className="col-span-2 font-medium">{fmtDate(shipment.eta)}</dd>
                    <dt className="text-slate-500">Arrived</dt>
                    <dd className="col-span-2 font-medium">
                        {fmtDate(shipment.ata)} <DelayBadge days={shipment.days_delayed} />
                    </dd>
                </dl>
            </header>

            {notice && <Notice>{notice}</Notice>}

            <div className="grid gap-4 lg:grid-cols-2">
                <StatusForm shipment={shipment} onDone={done} />
                <DatesForm shipment={shipment} onDone={done} />
            </div>

            <section className="space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">
                        Containers &amp; cargo{' '}
                        <span className="text-sm font-normal text-slate-500">
                            ({shipment.delivered_items_count}/{shipment.cargo_items_count} items delivered)
                        </span>
                    </h2>
                </div>
                {shipment.containers.map((c) => (
                    <ContainerCard key={c.id} container={c} onDone={done} />
                ))}
                <AddContainerForm shipment={shipment} onDone={done} />
            </section>

            <History entries={history} />
        </div>
    );
}

function StatusForm({ shipment, onDone }: { shipment: Shipment; onDone: (m: string) => Promise<void> }) {
    const [status, setStatus] = useState(shipment.allowed_transitions[0] ?? '');
    const [note, setNote] = useState('');
    const [occurredAt, setOccurredAt] = useState('');
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => setStatus(shipment.allowed_transitions[0] ?? ''), [shipment]);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            await api(`/shipments/${shipment.id}/status`, { method: 'POST', body: { status, note: note || null, occurred_at: occurredAt || null } });
            setNote('');
            setOccurredAt('');
            await onDone(`Status changed to ${STATUSES.find((s) => s.value === status)?.label ?? status}.`);
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={submit} className="card space-y-3">
            <h2 className="font-semibold">Change status</h2>
            {shipment.allowed_transitions.length === 0 ? (
                <p className="text-sm text-slate-500">This shipment is delivered; the flow is complete.</p>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="New status">
                            <select className="input" value={status} onChange={(e) => setStatus(e.target.value as typeof status)}>
                                {shipment.allowed_transitions.map((s) => (
                                    <option key={s} value={s}>
                                        {STATUSES.find((x) => x.value === s)?.label ?? s}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Happened on (optional)">
                            <input className="input" type="date" value={occurredAt} onChange={(e) => setOccurredAt(e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Note">
                        <input className="input" value={note} onChange={(e) => setNote(e.target.value)} placeholder="e.g. Discharged at Bergen, customs cleared" />
                    </Field>
                    <p className="text-xs text-slate-500">
                        Flow: planned → in transit → arrived → in storage → delivered. "Arrived" records the arrival date, "delivered" marks every remaining item delivered.
                    </p>
                    <ErrorBox error={error} />
                    <button className="btn btn-primary" disabled={busy || !status}>
                        {busy ? 'Saving…' : 'Change status'}
                    </button>
                </>
            )}
        </form>
    );
}

function DatesForm({ shipment, onDone }: { shipment: Shipment; onDone: (m: string) => Promise<void> }) {
    const [etd, setEtd] = useState(shipment.etd);
    const [eta, setEta] = useState(shipment.eta);
    const [ata, setAta] = useState(shipment.ata ?? '');
    const [cdd, setCdd] = useState('');
    const [reason, setReason] = useState('');
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        setEtd(shipment.etd);
        setEta(shipment.eta);
        setAta(shipment.ata ?? '');
    }, [shipment]);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        const body: Record<string, string | null> = { reason };
        if (etd !== shipment.etd) body.etd = etd;
        if (eta !== shipment.eta) body.eta = eta;
        if ((ata || null) !== shipment.ata) body.ata = ata || null;
        if (cdd) body.customer_delivery_date = cdd;
        try {
            await api(`/shipments/${shipment.id}/dates`, { method: 'PATCH', body });
            setReason('');
            setCdd('');
            await onDone('Dates updated; the change is in the history below.');
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={submit} className="card space-y-3">
            <h2 className="font-semibold">Change dates</h2>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Field label="ETD">
                    <input className="input" type="date" value={etd} onChange={(e) => setEtd(e.target.value)} required />
                </Field>
                <Field label="ETA">
                    <input className="input" type="date" value={eta} onChange={(e) => setEta(e.target.value)} required />
                </Field>
                <Field label="Actual arrival">
                    <input className="input" type="date" value={ata} onChange={(e) => setAta(e.target.value)} />
                </Field>
                <Field label="Customer delivery (all undelivered items)">
                    <input className="input" type="date" value={cdd} onChange={(e) => setCdd(e.target.value)} />
                </Field>
            </div>
            <Field label="Reason (required)">
                <input className="input" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Vessel rerouted via Rotterdam" required minLength={3} />
            </Field>
            <ErrorBox error={error} />
            <button className="btn btn-primary" disabled={busy}>
                {busy ? 'Saving…' : 'Save dates'}
            </button>
        </form>
    );
}

function ContainerCard({ container, onDone }: { container: Container; onDone: (m: string) => Promise<void> }) {
    const [editing, setEditing] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);

    return (
        <div className="card space-y-3 p-0">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-slate-100 px-4 py-3 text-sm">
                <span className="font-mono text-base font-semibold">{container.container_no}</span>
                {container.container_type && <span className="text-slate-500">{container.container_type}</span>}
                {container.seal_no && <span className="text-slate-500">seal {container.seal_no}</span>}
                {container.customs_cleared && <span className="rounded bg-emerald-50 px-1.5 text-xs text-emerald-700">customs cleared</span>}
                {container.storage_facility && (
                    <span className="text-slate-500">
                        storage: {container.storage_facility}
                        {container.storage_date ? ` since ${fmtDate(container.storage_date)}` : ''}
                    </span>
                )}
                <button type="button" className="btn ml-auto" onClick={() => setAdding(!adding)}>
                    {adding ? 'Cancel' : '+ Add cargo item'}
                </button>
            </div>
            {adding && <AddCargoItemForm container={container} onDone={(m) => onDone(m).then(() => setAdding(false))} />}
            <div className="overflow-x-auto">
                <table className="table">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Invoice / order</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Delivery date</th>
                            <th>Delivered</th>
                            <th>Delay</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(container.cargo_items ?? []).length === 0 && (
                            <tr>
                                <td colSpan={10} className="text-center text-slate-500">
                                    No cargo items yet.
                                </td>
                            </tr>
                        )}
                        {container.cargo_items?.map((item) => (
                            <ItemRow key={item.id} item={item} editing={editing === item.id} onEdit={() => setEditing(editing === item.id ? null : item.id)} onDone={(m) => onDone(m).then(() => setEditing(null))} />
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function ItemRow({ item, editing, onEdit, onDone }: { item: CargoItem; editing: boolean; onEdit: () => void; onDone: (m: string) => Promise<void> }) {
    const [cdd, setCdd] = useState(item.customer_delivery_date ?? '');
    const [deliveredAt, setDeliveredAt] = useState(item.delivered_at ?? '');
    const [reason, setReason] = useState('');
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        const body: Record<string, string | null> = { reason };
        if ((cdd || null) !== item.customer_delivery_date) body.customer_delivery_date = cdd || null;
        if ((deliveredAt || null) !== item.delivered_at) body.delivered_at = deliveredAt || null;
        try {
            await api(`/cargo-items/${item.id}/dates`, { method: 'PATCH', body });
            await onDone(`Dates of ${item.tag_no ?? item.package_no ?? 'item'} updated.`);
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <tr className={item.is_stock ? 'bg-amber-50/40' : ''}>
                <td className="font-mono">
                    {item.tag_no ?? <span className="text-slate-400">no tag</span>}
                    {item.package_no && <div className="text-xs text-slate-500">{item.package_no}</div>}
                    {item.is_stock && <div className="text-xs text-amber-700">stock</div>}
                </td>
                <td className="font-mono text-xs">
                    {item.proforma_invoice_no}
                    {item.exporter_ref && <div className="text-slate-500">SO {item.exporter_ref}</div>}
                    {item.customer_po && <div className="text-slate-500">PO {item.customer_po}</div>}
                </td>
                <td>{item.customer?.name}</td>
                <td className="text-xs">
                    <div>{item.product?.description}</div>
                    <div className="text-slate-500">
                        {item.product?.net_type_label}
                        {item.product?.material_code ? ` · ${item.product.material_code}` : ''}
                    </div>
                </td>
                <td>
                    {item.quantity} {item.unit}
                </td>
                <td>
                    <StatusBadge status={item.status} label={item.status_label} />
                </td>
                <td className="whitespace-nowrap">{fmtDate(item.customer_delivery_date)}</td>
                <td className="whitespace-nowrap">{fmtDate(item.delivered_at)}</td>
                <td>
                    <DelayBadge days={item.days_delayed} />
                </td>
                <td>
                    <button type="button" className="btn" onClick={onEdit}>
                        {editing ? 'Cancel' : 'Dates'}
                    </button>
                </td>
            </tr>
            {editing && (
                <tr className="bg-slate-50">
                    <td colSpan={10}>
                        <form onSubmit={submit} className="grid grid-cols-2 gap-3 md:grid-cols-5">
                            <Field label="Customer delivery date">
                                <input className="input" type="date" value={cdd} onChange={(e) => setCdd(e.target.value)} />
                            </Field>
                            <Field label="Delivered at">
                                <input className="input" type="date" value={deliveredAt} onChange={(e) => setDeliveredAt(e.target.value)} />
                            </Field>
                            <Field label="Reason (required)" className="col-span-2">
                                <input className="input" value={reason} onChange={(e) => setReason(e.target.value)} required minLength={3} />
                            </Field>
                            <div className="flex items-end">
                                <button className="btn btn-primary" disabled={busy}>
                                    {busy ? 'Saving…' : 'Save'}
                                </button>
                            </div>
                            <div className="col-span-2 md:col-span-5">
                                <ErrorBox error={error} />
                            </div>
                        </form>
                    </td>
                </tr>
            )}
        </>
    );
}

function AddCargoItemForm({ container, onDone }: { container: Container; onDone: (m: string) => Promise<void> }) {
    const [form, setForm] = useState({
        proforma_invoice_no: '',
        exporter_ref: '',
        customer_po: '',
        tag_no: '',
        package_no: '',
        quantity: '1',
        customer_name: '',
        material_code: '',
        description: '',
        net_type: 'net_pen',
        customer_delivery_date: '',
    });
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);
    const set = (key: keyof typeof form) => (e: { target: { value: string } }) => setForm({ ...form, [key]: e.target.value });

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            await api(`/containers/${container.id}/cargo-items`, {
                method: 'POST',
                body: {
                    proforma_invoice_no: form.proforma_invoice_no,
                    exporter_ref: form.exporter_ref || null,
                    customer_po: form.customer_po || null,
                    tag_no: form.tag_no || null,
                    package_no: form.package_no || null,
                    quantity: Number(form.quantity) || 1,
                    customer_name: form.customer_name,
                    product: { material_code: form.material_code || null, description: form.description, net_type: form.net_type },
                    customer_delivery_date: form.customer_delivery_date || null,
                },
            });
            await onDone(`Cargo item added to ${container.container_no}.`);
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 border-b border-slate-100 px-4 pb-4 md:grid-cols-4">
            <Field label="Proforma invoice no">
                <input className="input" value={form.proforma_invoice_no} onChange={set('proforma_invoice_no')} required />
            </Field>
            <Field label="Exporter's ref (sales order)">
                <input className="input" value={form.exporter_ref} onChange={set('exporter_ref')} />
            </Field>
            <Field label="Customer PO">
                <input className="input" value={form.customer_po} onChange={set('customer_po')} />
            </Field>
            <Field label="Tag no">
                <input className="input" value={form.tag_no} onChange={set('tag_no')} placeholder="GNP-2608002" />
            </Field>
            <Field label="Package no">
                <input className="input" value={form.package_no} onChange={set('package_no')} placeholder="WENA193722" />
            </Field>
            <Field label="Quantity">
                <input className="input" type="number" min={1} value={form.quantity} onChange={set('quantity')} />
            </Field>
            <Field label="Customer">
                <input className="input" value={form.customer_name} onChange={set('customer_name')} required placeholder="Fisk AS West" />
            </Field>
            <Field label="Customer delivery date">
                <input className="input" type="date" value={form.customer_delivery_date} onChange={set('customer_delivery_date')} />
            </Field>
            <Field label="Material code">
                <input className="input" value={form.material_code} onChange={set('material_code')} />
            </Field>
            <Field label="Product description" className="col-span-2">
                <input className="input" value={form.description} onChange={set('description')} required placeholder="NP-164mCx1.3+18+16m/KNXPlus/540p-29HM/O1" />
            </Field>
            <Field label="Net type">
                <select className="input" value={form.net_type} onChange={set('net_type')}>
                    <option value="net_pen">Net pen (NP)</option>
                    <option value="dead_fish_collector">Dead fish collector (DF)</option>
                    <option value="lice_shield">Lice shield</option>
                    <option value="repair_panel">Repair panel</option>
                    <option value="velcro_straps">Velcro straps</option>
                    <option value="rope">Rope</option>
                    <option value="bird_net">Bird net</option>
                    <option value="other">Other</option>
                </select>
            </Field>
            <div className="col-span-2 md:col-span-4">
                <ErrorBox error={error} />
            </div>
            <div>
                <button className="btn btn-primary" disabled={busy}>
                    {busy ? 'Saving…' : 'Add item'}
                </button>
            </div>
        </form>
    );
}

function AddContainerForm({ shipment, onDone }: { shipment: Shipment; onDone: (m: string) => Promise<void> }) {
    const [open, setOpen] = useState(false);
    const [containerNo, setContainerNo] = useState('');
    const [type, setType] = useState('45UT');
    const [seal, setSeal] = useState('');
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);

    if (!open)
        return (
            <button type="button" className="btn" onClick={() => setOpen(true)}>
                + Add container
            </button>
        );

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            await api(`/shipments/${shipment.id}/containers`, { method: 'POST', body: { container_no: containerNo, container_type: type || null, seal_no: seal || null } });
            setContainerNo('');
            setSeal('');
            setOpen(false);
            await onDone('Container added.');
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={submit} className="card grid grid-cols-2 gap-3 md:grid-cols-4">
            <Field label={shipment.shipping_method === 'air' ? 'Air waybill no' : 'Container no (ISO 6346)'}>
                <input className="input font-mono" value={containerNo} onChange={(e) => setContainerNo(e.target.value)} required />
            </Field>
            <Field label="Type">
                <input className="input" value={type} onChange={(e) => setType(e.target.value)} />
            </Field>
            <Field label="Seal no">
                <input className="input" value={seal} onChange={(e) => setSeal(e.target.value)} />
            </Field>
            <div className="flex items-end gap-2">
                <button className="btn btn-primary" disabled={busy}>
                    Add
                </button>
                <button type="button" className="btn" onClick={() => setOpen(false)}>
                    Cancel
                </button>
            </div>
            <div className="col-span-2 md:col-span-4">
                <ErrorBox error={error} />
            </div>
        </form>
    );
}

function History({ entries }: { entries: HistoryEntry[] }) {
    return (
        <section className="card">
            <h2 className="mb-3 font-semibold">History</h2>
            {entries.length === 0 && <p className="text-sm text-slate-500">Nothing yet.</p>}
            <ol className="space-y-2">
                {entries.map((e) => (
                    <li key={`${e.type}-${e.id}`} className="flex gap-3 text-sm">
                        <span className="w-40 shrink-0 text-xs text-slate-500">{fmtDateTime(e.occurred_at)}</span>
                        <span className="flex-1">
                            {e.type === 'status' ? (
                                <>
                                    <span className="font-medium">Status</span> {e.from_label ? `${e.from_label} → ` : ''}
                                    <span className="font-medium">{e.to_label}</span>
                                    {e.note && <span className="text-slate-600"> — {e.note}</span>}
                                </>
                            ) : (
                                <>
                                    <span className="font-medium">{e.field_label}</span>
                                    {e.subject_type === 'cargo_item' && <span className="font-mono text-xs text-slate-600"> ({e.subject_label ?? `item #${e.subject_id}`})</span>}{' '}
                                    {e.old ?? '–'} → <span className="font-medium">{e.new ?? '–'}</span>
                                    {e.days_shifted !== null && e.days_shifted !== 0 && (
                                        <span className={e.days_shifted > 0 ? 'text-red-700' : 'text-emerald-700'}>
                                            {' '}
                                            ({e.days_shifted > 0 ? '+' : ''}
                                            {e.days_shifted} d)
                                        </span>
                                    )}
                                    <span className="text-slate-600"> — {e.reason}</span>
                                </>
                            )}
                            <span className="block text-xs text-slate-500">
                                by {e.actor} ({e.source})
                            </span>
                        </span>
                    </li>
                ))}
            </ol>
        </section>
    );
}
