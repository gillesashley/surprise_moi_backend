export interface Notification {
    id: string;
    type: string;
    title: string;
    message: string;
    action_url: string | null;
    actor: {
        id: number;
        name: string;
        avatar: string | null;
    } | null;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface NotificationResponse {
    success: boolean;
    data: {
        notifications: Notification[];
        meta?: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
    message?: string;
}

export interface UnreadCountResponse {
    success: boolean;
    data: {
        unread_count: number;
    };
}

const API_BASE_URL = '/api/v1/notifications';

export const notificationApi = {
    async getAll(page = 1, perPage = 20): Promise<NotificationResponse> {
        const response = await fetch(
            `${API_BASE_URL}?page=${page}&per_page=${perPage}`,
            {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'include',
            },
        );

        if (!response.ok) {
            throw new Error('Failed to fetch notifications');
        }

        return response.json();
    },

    async getUnread(): Promise<NotificationResponse> {
        const response = await fetch(`${API_BASE_URL}/unread`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error('Failed to fetch unread notifications');
        }

        return response.json();
    },

    async getUnreadCount(): Promise<UnreadCountResponse> {
        const response = await fetch(`${API_BASE_URL}/unread-count`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error('Failed to fetch unread count');
        }

        return response.json();
    },

    async markAsRead(notificationId: string): Promise<{ success: boolean; message: string }> {
        const response = await fetch(`${API_BASE_URL}/${notificationId}/read`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error('Failed to mark notification as read');
        }

        return response.json();
    },

    async markAsUnread(notificationId: string): Promise<{ success: boolean; message: string }> {
        const response = await fetch(`${API_BASE_URL}/${notificationId}/unread`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error('Failed to mark notification as unread');
        }

        return response.json();
    },

    async markAllAsRead(): Promise<{ success: boolean; message: string; data: { marked_count: number } }> {
        const response = await fetch(`${API_BASE_URL}/read-all`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error('Failed to mark all as read');
        }

        return response.json();
    },

    async delete(notificationId: string): Promise<{ success: boolean; message: string }> {
        const response = await fetch(`${API_BASE_URL}/${notificationId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error('Failed to delete notification');
        }

        return response.json();
    },
};
