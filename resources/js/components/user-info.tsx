import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';
import Typography from '@mui/material/Typography';
import Box from '@mui/material/Box';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar
                style={{
                    width: 32,
                    height: 32,
                    overflow: 'hidden',
                    borderRadius: '50%',
                }}
            >
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback
                    style={{
                        borderRadius: 8,
                    }}
                >
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <Box
                sx={{
                    display: 'grid',
                    flex: 1,
                    textAlign: 'left',
                    fontSize: '0.875rem',
                    lineHeight: 1.25,
                }}
            >
                <Box
                    component="span"
                    sx={{
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        fontWeight: 500,
                    }}
                >
                    {user.name}
                </Box>
                {showEmail && (
                    <Typography variant="caption" color="text.secondary" noWrap>
                        {user.email}
                    </Typography>
                )}
            </Box>
        </>
    );
}
