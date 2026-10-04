import { useEffect, useState, type FormEvent } from 'react';
import { api, fmtDateTime } from '../api';
import { Empty, ErrorBox, Loading, Notice } from '../components/ui';
import type { ImportRun } from '../types';

export default function ImportPage() {
    const [runs, setRuns] = useState<ImportRun[] | null>(null);
    const [selected, setSelected] = useState<ImportRun | null>(null);
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);
    const [level, setLevel] = useState<'all' | 'error' | 'warning'>('all');

    const loadRuns = () => api<{ data: ImportRun[] }>('/imports').then((r) => setRuns(r.data)).catch(setError);

    useEffect(() => {
        void loadRuns();
    }, []);

    const select = (run: ImportRun) => api<{ data: ImportRun }>(`/imports/${run.id}`).then((r) => setSelected(r.data)).catch(setError);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (!file) return;
        setBusy(true);
        setError(null);
        const formData = new FormData();
        formData.append('file', file);
        try {
            const result = await api<{ data: ImportRun }>('/imports', { method: 'POST', formData });
            setSelected(result.data);
            await loadRuns();
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    const issues = (selected?.issues ?? []).filter((i) => level === 'all' || i.level === level);

    return (
        <div className="space-y-4">
            <h1 className="text-xl font-semibold">Import delivery plan</h1>
            <form onSubmit={submit} className="card flex flex-wrap items-center gap-3">
                <input type="file" accept=".xlsx,.xlsm,.xls" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="text-sm" />
                <button className="btn btn-primary" disabled={!file || busy}>
                    {busy ? 'Importing…' : 'Import'}
                </button>
                <p className="w-full text-xs text-slate-500">
                    Expects the “Delivery Plan” sheet layout (one row per tag). Rows are grouped into containers and sailings; rows that cannot be imported are listed
                    with the reason, rows already imported (same package/batch number) are skipped.
                </p>
            </form>
            <ErrorBox error={error} />

            {selected && (
                <section className="card space-y-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="font-semibold">
                            {selected.filename} <span className="text-sm font-normal text-slate-500">#{selected.id}</span>
                        </h2>
                        <span className={`rounded px-2 py-0.5 text-xs font-semibold ${selected.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
                            {selected.status}
                        </span>
                        <span className="text-xs text-slate-500">{fmtDateTime(selected.finished_at)}</span>
                    </div>
                    {selected.error ? (
                        <ErrorBox error={new Error(selected.error)} />
                    ) : (
                        <Notice>
                            {selected.rows_imported} of {selected.rows_total} rows imported, {selected.rows_skipped} skipped, {selected.warnings_count} warnings →{' '}
                            {selected.shipments_created} shipments, {selected.containers_created} containers, {selected.cargo_items_created} cargo items created.
                        </Notice>
                    )}
                    <div className="flex items-center gap-3 text-sm">
                        <span className="font-medium">Issues ({selected.issues?.length ?? 0})</span>
                        {(['all', 'error', 'warning'] as const).map((l) => (
                            <label key={l} className="flex items-center gap-1">
                                <input type="radio" checked={level === l} onChange={() => setLevel(l)} /> {l}
                            </label>
                        ))}
                    </div>
                    {issues.length === 0 ? (
                        <Empty>No issues.</Empty>
                    ) : (
                        <div className="max-h-[32rem] overflow-auto">
                            <table className="table">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Level</th>
                                        <th>Column</th>
                                        <th>Message</th>
                                        <th>Invoice</th>
                                        <th>Container</th>
                                        <th>Tag</th>
                                        <th>Customer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {issues.map((i) => (
                                        <tr key={i.id} className={i.level === 'error' ? 'bg-red-50/40' : ''}>
                                            <td>{i.row_number}</td>
                                            <td className={i.level === 'error' ? 'font-semibold text-red-700' : 'text-amber-700'}>{i.level}</td>
                                            <td className="font-mono text-xs">{i.column}</td>
                                            <td>{i.message}</td>
                                            <td className="font-mono text-xs">{i.raw?.invoice}</td>
                                            <td className="font-mono text-xs">{i.raw?.container}</td>
                                            <td className="font-mono text-xs">{i.raw?.tag}</td>
                                            <td className="text-xs">{i.raw?.customer}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}

            <section className="card p-0">
                <h2 className="border-b border-slate-100 px-4 py-2 font-semibold">Previous imports</h2>
                {!runs ? (
                    <Loading />
                ) : runs.length === 0 ? (
                    <Empty>No imports yet.</Empty>
                ) : (
                    <table className="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>File</th>
                                <th>When</th>
                                <th>By</th>
                                <th>Status</th>
                                <th>Rows</th>
                                <th>Imported</th>
                                <th>Skipped</th>
                                <th>Warnings</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {runs.map((r) => (
                                <tr key={r.id}>
                                    <td>{r.id}</td>
                                    <td>{r.filename}</td>
                                    <td className="text-xs">{fmtDateTime(r.finished_at ?? r.started_at)}</td>
                                    <td className="text-xs">{r.user ?? '–'}</td>
                                    <td>{r.status}</td>
                                    <td>{r.rows_total}</td>
                                    <td>{r.rows_imported}</td>
                                    <td>{r.rows_skipped}</td>
                                    <td>{r.warnings_count}</td>
                                    <td>
                                        <button type="button" className="btn" onClick={() => void select(r)}>
                                            Details
                                        </button>
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
