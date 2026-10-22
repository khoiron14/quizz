import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Auth } from '@/types/auth';

/**
 * Composable for role and permission authorization checks in Vue components.
 */
export function useCan() {
    const page = usePage<{ auth: Auth }>();

    const permissions = computed<string[]>(() => page.props.auth?.permissions ?? []);
    const roles = computed<string[]>(() => page.props.auth?.roles ?? []);

    const can = (permission: string): boolean => {
        return permissions.value.includes(permission);
    };

    const hasRole = (role: string): boolean => {
        return roles.value.includes(role);
    };

    const hasAnyRole = (...checkRoles: string[]): boolean => {
        return checkRoles.some((r) => roles.value.includes(r));
    };

    return {
        permissions,
        roles,
        can,
        hasRole,
        hasAnyRole,
    };
}
