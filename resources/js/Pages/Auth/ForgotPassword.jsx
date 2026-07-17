import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Loader2, Mail } from 'lucide-react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout
            title="Reset your password"
            subtitle="Enter your email and we'll send a secure link to set a new password."
        >
            <Head title="Forgot password" />

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
                        autoFocus
                        placeholder="you@ncatfmd.com.ng"
                        aria-invalid={!!errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && <p className="text-sm font-medium text-destructive">{errors.email}</p>}
                </div>

                <Button type="submit" size="lg" className="w-full" disabled={processing}>
                    {processing ? <Loader2 className="size-4 animate-spin" /> : <Mail className="size-4" />}
                    Email reset link
                </Button>
            </form>

            <Link
                href={route('login')}
                className="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-primary"
            >
                <ArrowLeft className="size-4" />
                Back to sign in
            </Link>
        </AuthLayout>
    );
}
