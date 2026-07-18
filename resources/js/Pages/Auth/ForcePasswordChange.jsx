import { Head, useForm } from '@inertiajs/react';
import { KeyRound, Loader2, ShieldCheck } from 'lucide-react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';

export default function ForcePasswordChange() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.change.update'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title="Set a new password"
            subtitle="For security, choose a new password before continuing."
        >
            <Head title="Set a new password" />

            <div className="mb-6 flex items-start gap-3 rounded-md border border-info/20 bg-info/10 px-4 py-3 text-sm text-info">
                <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                <span>
                    Your account was created with a temporary password. It must be changed
                    before you can access the console.
                </span>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-1.5">
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        autoFocus
                        aria-invalid={!!errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password ? (
                        <p className="text-sm font-medium text-destructive">{errors.password}</p>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            At least 10 characters, with upper &amp; lower case and a number.
                        </p>
                    )}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="password_confirmation">Confirm new password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </div>

                <Button type="submit" size="lg" className="w-full" disabled={processing}>
                    {processing ? <Loader2 className="size-4 animate-spin" /> : <KeyRound className="size-4" />}
                    Set password &amp; continue
                </Button>
            </form>
        </AuthLayout>
    );
}
