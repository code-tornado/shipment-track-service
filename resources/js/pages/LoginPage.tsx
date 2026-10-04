import { useState, type FormEvent } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth';
import { ErrorBox, Field } from '../components/ui';

export default function LoginPage() {
    const { user, login, loginWithToken } = useAuth();
    const [mode, setMode] = useState<'password' | 'token'>('password');
    const [email, setEmail] = useState('demo@garware.example');
    const [password, setPassword] = useState('password');
    const [apiToken, setApiToken] = useState('');
    const [error, setError] = useState<unknown>(null);
    const [busy, setBusy] = useState(false);

    if (user) return <Navigate to="/shipments" replace />;

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            mode === 'password' ? await login(email, password) : await loginWithToken(apiToken);
        } catch (err) {
            setError(err);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="mx-auto mt-20 max-w-sm">
            <h1 className="mb-1 text-xl font-semibold">Shipment Tracking</h1>
            <p className="mb-6 text-sm text-slate-500">Sign in to your company's shipping plan.</p>
            <form onSubmit={submit} className="card space-y-4">
                <div className="flex gap-4 text-sm">
                    <label className="flex items-center gap-1">
                        <input type="radio" checked={mode === 'password'} onChange={() => setMode('password')} /> Email &amp; password
                    </label>
                    <label className="flex items-center gap-1">
                        <input type="radio" checked={mode === 'token'} onChange={() => setMode('token')} /> API token
                    </label>
                </div>
                {mode === 'password' ? (
                    <>
                        <Field label="Email">
                            <input className="input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                        </Field>
                        <Field label="Password">
                            <input className="input" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
                        </Field>
                    </>
                ) : (
                    <Field label="Bearer token">
                        <input className="input font-mono" value={apiToken} onChange={(e) => setApiToken(e.target.value)} placeholder="1|…" required />
                    </Field>
                )}
                <ErrorBox error={error} />
                <button className="btn btn-primary w-full justify-center" disabled={busy}>
                    {busy ? 'Signing in…' : 'Sign in'}
                </button>
                <p className="text-xs text-slate-500">
                    Seeded demo: <code>demo@garware.example</code> / <code>password</code> (token <code>1|demo-token-garware</code>).
                </p>
            </form>
        </div>
    );
}
