import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { useToast } from '@/Components/ui/Toast';

/**
 * Surfaces server flash messages (`status`/`success`/`error`) as toasts.
 * Mount once inside a ToastProvider (AppLayout does this).
 */
export function useFlashToasts() {
    const { flash } = usePage().props;
    const { toast } = useToast();

    useEffect(() => {
        if (flash?.success) toast({ variant: 'success', title: flash.success });
        if (flash?.status) toast({ variant: 'success', title: flash.status });
        if (flash?.error) toast({ variant: 'error', title: flash.error });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.success, flash?.status, flash?.error]);
}
