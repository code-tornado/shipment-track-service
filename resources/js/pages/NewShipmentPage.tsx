import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { ErrorBox, Field } from '../components/ui';
import type { Shipment } from '../types';

interface ContainerDraft {
    container_no: string;
    container_type: string;
    seal_no: string;
}

export default function NewShipmentPage() {
    const navigate = useNavigate();
    const [form, setForm] = useState({
        shipping_method: 'sea',
        shipping_line: 'TRANSSEA AS',
        origin_port: 'Nhava Sheva (Mumbai)',
        destination_port: '',
        incoterm: 'CIF',
        etd: '',
        eta: '',
        status: 'planned',
        notes: '',
    });
    const [containers, setContainers] = useState<ContainerDraft[]>([{ container_no: '', container_type: '45UT', seal_no: '' }]);
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);

    const set = (key: keyof typeof form) => (e: { target: { value: string } }) => setForm({ ...form, [key]: e.target.value });

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const result = await api<{ data: Shipment }>('/shipments', {
                method: 'POST',
                body: {
                    ...form,
                    notes: form.notes || null,
                    containers: containers
                        .filter((c) => c.container_no.trim())
                        .map((c) => ({ ...c, seal_no: c.seal_no || null, container_type: c.container_type || null })),
                },
            });
            navigate(`/shipments/${result.data.id}`);
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={submit} className="mx-auto max-w-3xl space-y-4">
            <h1 className="text-xl font-semibold">New shipment</h1>
            <div className="card grid grid-cols-2 gap-3 md:grid-cols-3">
                <Field label="Shipping method">
                    <select className="input" value={form.shipping_method} onChange={set('shipping_method')}>
                        <option value="sea">Sea</option>
                        <option value="air">Air</option>
                        <option value="road">Road</option>
                    </select>
                </Field>
                <Field label="Shipping line">
                    <input className="input" value={form.shipping_line} onChange={set('shipping_line')} />
                </Field>
                <Field label="Incoterm">
                    <input className="input" value={form.incoterm} onChange={set('incoterm')} />
                </Field>
                <Field label="Origin port">
                    <input className="input" value={form.origin_port} onChange={set('origin_port')} />
                </Field>
                <Field label="Destination port">
                    <input className="input" value={form.destination_port} onChange={set('destination_port')} required placeholder="BERGEN" />
                </Field>
                <Field label="Initial status">
                    <select className="input" value={form.status} onChange={set('status')}>
                        <option value="planned">Planned</option>
                        <option value="in_transit">In transit</option>
                    </select>
                </Field>
                <Field label="ETD">
                    <input className="input" type="date" value={form.etd} onChange={set('etd')} required />
                </Field>
                <Field label="Planned ETA">
                    <input className="input" type="date" value={form.eta} onChange={set('eta')} required />
                </Field>
                <Field label="Notes" className="col-span-2 md:col-span-3">
                    <input className="input" value={form.notes} onChange={set('notes')} />
                </Field>
            </div>

            <div className="card space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold">Containers</h2>
                    <button type="button" className="btn" onClick={() => setContainers([...containers, { container_no: '', container_type: '45UT', seal_no: '' }])}>
                        + Add container
                    </button>
                </div>
                {containers.map((c, i) => (
                    <div key={i} className="grid grid-cols-3 gap-3">
                        <Field label={form.shipping_method === 'air' ? 'Air waybill no' : 'Container no (ISO 6346)'}>
                            <input
                                className="input font-mono"
                                value={c.container_no}
                                placeholder={form.shipping_method === 'air' ? '501-20079076' : 'HLBU8324720'}
                                onChange={(e) => setContainers(containers.map((x, j) => (j === i ? { ...x, container_no: e.target.value } : x)))}
                            />
                        </Field>
                        <Field label="Type">
                            <input
                                className="input"
                                value={c.container_type}
                                onChange={(e) => setContainers(containers.map((x, j) => (j === i ? { ...x, container_type: e.target.value } : x)))}
                            />
                        </Field>
                        <Field label="Seal no">
                            <input className="input" value={c.seal_no} onChange={(e) => setContainers(containers.map((x, j) => (j === i ? { ...x, seal_no: e.target.value } : x)))} />
                        </Field>
                    </div>
                ))}
                <p className="text-xs text-slate-500">Cargo items (tags) are added on the shipment page after saving.</p>
            </div>

            <ErrorBox error={error} />
            <div className="flex gap-2">
                <button className="btn btn-primary" disabled={busy}>
                    {busy ? 'Saving…' : 'Create shipment'}
                </button>
                <button type="button" className="btn" onClick={() => navigate('/shipments')}>
                    Cancel
                </button>
            </div>
        </form>
    );
}
