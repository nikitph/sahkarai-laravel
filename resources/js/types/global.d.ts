import type { Auth } from '@/types/auth';

export type Organization = { id: number; name: string; slug: string };

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            organization: {
                current: Organization | null;
                all: Organization[];
                permissions: string[];
            } | null;
            flash: { success?: string; error?: string };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
