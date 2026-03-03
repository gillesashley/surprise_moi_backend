import { Bell, Check, Trash2 } from 'lucide-react';
import { useNotifications } from '@/hooks/useNotifications';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';
import MuiButton from '@mui/material/Button';
import { alpha } from '@mui/material/styles';

interface NotificationDropdownProps {
    onClose: () => void;
}

export function NotificationDropdown({ onClose }: NotificationDropdownProps) {
    const {
        notifications,
        unreadCount,
        isLoading,
        markAsRead,
        markAllAsRead,
        deleteNotification,
    } = useNotifications();

    const handleNotificationClick = async (notificationId: string) => {
        await markAsRead(notificationId);
    };

    const handleMarkAllRead = async () => {
        await markAllAsRead();
    };

    const handleDelete = async (e: React.MouseEvent, notificationId: string) => {
        e.stopPropagation();
        await deleteNotification(notificationId);
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column' }}>
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    borderBottom: 1,
                    borderColor: 'divider',
                    px: 2,
                    py: 1.5,
                }}
            >
                <Typography sx={{ fontWeight: 600 }}>Notifications</Typography>
                {unreadCount > 0 && (
                    <MuiButton
                        variant="text"
                        size="small"
                        onClick={handleMarkAllRead}
                        sx={{ fontSize: '0.75rem', textTransform: 'none' }}
                    >
                        <Check style={{ width: 12, height: 12, marginRight: 4 }} />
                        Mark all read
                    </MuiButton>
                )}
            </Box>

            <Box sx={{ height: 320, overflowY: 'auto' }}>
                {isLoading ? (
                    <Box
                        sx={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            p: 2,
                        }}
                    >
                        <Typography
                            variant="body2"
                            sx={{ color: 'text.secondary' }}
                        >
                            Loading...
                        </Typography>
                    </Box>
                ) : notifications.length === 0 ? (
                    <Box
                        sx={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            justifyContent: 'center',
                            p: 2,
                            textAlign: 'center',
                        }}
                    >
                        <Bell
                            style={{
                                width: 32,
                                height: 32,
                                marginBottom: 8,
                                color: 'var(--mui-palette-text-secondary)',
                            }}
                        />
                        <Typography
                            variant="body2"
                            sx={{ color: 'text.secondary' }}
                        >
                            No notifications yet
                        </Typography>
                    </Box>
                ) : (
                    <Box
                        sx={{
                            '& > * + *': {
                                borderTop: 1,
                                borderColor: 'divider',
                            },
                        }}
                    >
                        {notifications.map((notification) => (
                            <Box
                                key={notification.id}
                                onClick={() =>
                                    handleNotificationClick(notification.id)
                                }
                                sx={{
                                    display: 'flex',
                                    cursor: 'pointer',
                                    alignItems: 'flex-start',
                                    gap: 1.5,
                                    px: 2,
                                    py: 1.5,
                                    transition: 'background-color 0.15s',
                                    '&:hover': {
                                        bgcolor: 'action.hover',
                                    },
                                    ...(!notification.read_at && {
                                        bgcolor: (theme) =>
                                            alpha(
                                                theme.palette.primary.main,
                                                0.05,
                                            ),
                                    }),
                                }}
                            >
                                <Box
                                    sx={{
                                        flex: 1,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 0.5,
                                    }}
                                >
                                    <Typography
                                        variant="body2"
                                        sx={{
                                            ...(!notification.read_at && {
                                                fontWeight: 500,
                                            }),
                                        }}
                                    >
                                        {notification.title}
                                    </Typography>
                                    <Typography
                                        variant="caption"
                                        sx={{
                                            color: 'text.secondary',
                                            display: '-webkit-box',
                                            WebkitLineClamp: 2,
                                            WebkitBoxOrient: 'vertical',
                                            overflow: 'hidden',
                                        }}
                                    >
                                        {notification.message}
                                    </Typography>
                                    <Typography
                                        variant="caption"
                                        sx={{ color: 'text.secondary' }}
                                    >
                                        {formatTimeAgo(notification.created_at)}
                                    </Typography>
                                </Box>
                                <Box
                                    sx={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 0.5,
                                    }}
                                >
                                    {!notification.read_at && (
                                        <Box
                                            sx={{
                                                width: 8,
                                                height: 8,
                                                borderRadius: '50%',
                                                bgcolor: 'primary.main',
                                            }}
                                        />
                                    )}
                                    <IconButton
                                        size="small"
                                        onClick={(e) =>
                                            handleDelete(e, notification.id)
                                        }
                                        sx={{
                                            width: 24,
                                            height: 24,
                                            opacity: 0.5,
                                            '&:hover': {
                                                opacity: 1,
                                            },
                                        }}
                                    >
                                        <Trash2
                                            style={{ width: 12, height: 12 }}
                                        />
                                    </IconButton>
                                </Box>
                            </Box>
                        ))}
                    </Box>
                )}
            </Box>
        </Box>
    );
}

function formatTimeAgo(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor(
        (now.getTime() - date.getTime()) / 1000,
    );

    if (diffInSeconds < 60) {
        return 'Just now';
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
        return `${diffInMinutes}m ago`;
    }

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
        return `${diffInHours}h ago`;
    }

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
        return `${diffInDays}d ago`;
    }

    return date.toLocaleDateString();
}
