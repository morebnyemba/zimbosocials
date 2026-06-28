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
    can_use_monetizer?: boolean;
    monetizer_unlocked_at?: string;
    youtube_channel_id?: string;
    facebook_page_id?: string;
    tiktok_username?: string;
    instagram_username?: string;
    x_username?: string;
    manager_role?: string;
    account_manager_id?: number;
    support_manager_id?: number;
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
