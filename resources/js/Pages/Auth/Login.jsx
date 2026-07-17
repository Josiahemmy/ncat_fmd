import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Loader2, LogIn } from 'lucide-react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title="Sign in to your workspace"
            subtitle="Enter your NCAT FMD credentials to access the stores console."
        >
            <Head title="Sign in" />

            {status && (
                <div className="mb-6 flex items-center gap-2 rounded-md border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                    <CheckCircle2 className="size-4 shrink-0" />
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-1.5">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        placeholder="you@ncatfmd.com.ng"
                        aria-invalid={!!errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && <p className="text-sm font-medium text-destructive">{errors.email}</p>}
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm font-medium text-primary hover:underline"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        placeholder="••••••••"
                        aria-invalid={!!errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && (
                        <p className="text-sm font-medium text-destructive">{errors.password}</p>
                    )}
                </div>

                <label className="flex cursor-pointer select-none items-center gap-2.5">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="size-4 rounded border-input text-primary shadow-sm focus:ring-2 focus:ring-ring/40"
                    />
                    <span className="text-sm text-muted-foreground">Keep me signed in on this device</span>
                </label>

                <Button type="submit" size="lg" className="w-full" disabled={processing}>
                    {processing ? (
                        <>
                            <Loader2 className="size-4 animate-spin" />
                            Signing in…
                        </>
                    ) : (
                        <>
                            <LogIn className="size-4" />
                            Sign in
                        </>
                    )}
                </Button>
            </form>

            <p className="mt-8 rounded-md bg-muted/60 px-4 py-3 text-center text-xs text-muted-foreground">
                Access is provisioned by the FMD administrator. Contact stores administration
                if you need an account or a password reset.
            </p>
        </AuthLayout>
    );
}
