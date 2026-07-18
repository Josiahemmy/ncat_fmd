import { usePage } from '@inertiajs/react';

/**
 * Permission/role helpers driven by the props shared in HandleInertiaRequests.
 * Mirrors server-side policy checks so the UI can hide what the server enforces.
 * (Server policies remain the source of truth — this only affects visibility.)
 */
export function usePermissions() {
    const { auth } = usePage().props;
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];

    const can = (permission) => permissions.includes(permission);
    const canAny = (list = []) => list.some((p) => permissions.includes(p));
    const hasRole = (role) => roles.includes(role);

    return { can, canAny, hasRole, permissions, roles };
}
