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
            locale: 'en' | 'hi' | 'gu' | 'mr';
            auth: Auth;
            organization: {
                current: Organization | null;
                all: Organization[];
                permissions: string[];
            } | null;
            product: {
                tier: 'free' | 'tier_1' | 'tier_2';
                role: 'individual_member' | 'saas_admin';
                locale: 'en' | 'hi' | 'gu' | 'mr';
                credits: number;
                unread_notifications: number;
            } | null;
            realtime: {
                key: string | null;
                host: string | null;
                port: number;
                scheme: string;
            } | null;
            flash: { success?: string; error?: string };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
