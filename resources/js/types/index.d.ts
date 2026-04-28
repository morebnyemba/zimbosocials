export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    balance?: number;
    role?: string;
    api_key?: string;
    phone?: string;
    currency?: string;
    locale?: string;
    profile_image_url?: string;
    account_type?: 'individual' | 'business' | 'marketer';
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash?: {
        success?: string;
        error?: string;
        info?: string;
    };
};
