import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import type React from 'react';

interface Category {
    id: number;
    name: string;
    slug: string;
    type: 'product' | 'service';
    description: string | null;
    icon: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
    created_at: string;
}

interface Props {
    category: Category | null;
    onClose: () => void;
}

export function CategoryDetailsModal({ category, onClose }: Props) {
    return (
        <Dialog open={!!category} onOpenChange={(open) => !open && onClose()}>
            <DialogContent style={{ maxWidth: 672 }} aria-describedby={undefined}>
                <DialogHeader>
                    <DialogTitle>Category Details</DialogTitle>
                </DialogHeader>
                {category && (
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box
                            sx={{
                                display: 'grid',
                                gap: 2,
                                gridTemplateColumns: { md: 'repeat(2, 1fr)' },
                            }}
                        >
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 0.5,
                                        display: 'block',
                                    }}
                                >
                                    Name
                                </Typography>
                                <Typography variant="body1" fontWeight={500}>
                                    {category.name}
                                </Typography>
                            </Box>
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 0.5,
                                        display: 'block',
                                    }}
                                >
                                    Type
                                </Typography>
                                <Chip
                                    label={
                                        category.type === 'service'
                                            ? 'Service'
                                            : 'Product'
                                    }
                                    size="small"
                                    color={
                                        category.type === 'service'
                                            ? 'info'
                                            : 'secondary'
                                    }
                                    variant="outlined"
                                />
                            </Box>
                        </Box>

                        <Box
                            sx={{
                                display: 'grid',
                                gap: 2,
                                gridTemplateColumns: { md: 'repeat(2, 1fr)' },
                            }}
                        >
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 0.5,
                                        display: 'block',
                                    }}
                                >
                                    Slug
                                </Typography>
                                <Typography
                                    variant="body1"
                                    color="text.secondary"
                                >
                                    {category.slug}
                                </Typography>
                            </Box>
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 0.5,
                                        display: 'block',
                                    }}
                                >
                                    Products
                                </Typography>
                                <Typography variant="body1">
                                    {category.products_count}
                                </Typography>
                            </Box>
                        </Box>

                        {category.description && (
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 0.5,
                                        display: 'block',
                                    }}
                                >
                                    Description
                                </Typography>
                                <Typography variant="body1">
                                    {category.description}
                                </Typography>
                            </Box>
                        )}

                        <Box
                            sx={{
                                display: 'grid',
                                gap: 2,
                                gridTemplateColumns: { md: 'repeat(2, 1fr)' },
                            }}
                        >
                            {category.icon && (
                                <Box>
                                    <Typography
                                        variant="caption"
                                        color="text.secondary"
                                        sx={{
                                            fontWeight: 500,
                                            mb: 0.5,
                                            display: 'block',
                                        }}
                                    >
                                        Icon
                                    </Typography>
                                    <Box
                                        sx={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 1,
                                        }}
                                    >
                                        <Box
                                            component="img"
                                            src={
                                                category.icon.startsWith('http')
                                                    ? category.icon
                                                    : category.icon.startsWith(
                                                            '/',
                                                        )
                                                      ? category.icon
                                                      : `/storage/${category.icon}`
                                            }
                                            alt="Category icon"
                                            sx={{
                                                height: 48,
                                                width: 48,
                                                objectFit: 'contain',
                                            }}
                                            onError={(e: React.SyntheticEvent<HTMLImageElement>) => {
                                                e.currentTarget.style.display =
                                                    'none';
                                            }}
                                        />
                                    </Box>
                                </Box>
                            )}
                        </Box>

                        {category.image && (
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 1,
                                        display: 'block',
                                    }}
                                >
                                    Image
                                </Typography>
                                <Box
                                    component="img"
                                    src={
                                        category.image.startsWith('http')
                                            ? category.image
                                            : category.image.startsWith('/')
                                              ? category.image
                                              : `/storage/${category.image}`
                                    }
                                    alt={category.name}
                                    sx={{
                                        width: '100%',
                                        maxWidth: 448,
                                        borderRadius: 2,
                                        border: 1,
                                        borderColor: 'divider',
                                        objectFit: 'cover',
                                    }}
                                    onError={(e: React.SyntheticEvent<HTMLImageElement>) => {
                                        e.currentTarget.src =
                                            '/placeholder-image.png';
                                    }}
                                />
                            </Box>
                        )}

                        <Box
                            sx={{
                                display: 'grid',
                                gap: 2,
                                gridTemplateColumns: { md: 'repeat(2, 1fr)' },
                            }}
                        >
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 0.5,
                                        display: 'block',
                                    }}
                                >
                                    Sort Order
                                </Typography>
                                <Typography variant="body1">
                                    {category.sort_order}
                                </Typography>
                            </Box>
                            <Box>
                                <Typography
                                    variant="caption"
                                    color="text.secondary"
                                    sx={{
                                        fontWeight: 500,
                                        mb: 0.5,
                                        display: 'block',
                                    }}
                                >
                                    Products
                                </Typography>
                                <Typography variant="body1">
                                    {category.products_count}
                                </Typography>
                            </Box>
                        </Box>
                    </Box>
                )}
            </DialogContent>
        </Dialog>
    );
}
