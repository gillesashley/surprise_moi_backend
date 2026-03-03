'use client';

import { Label } from '@/components/ui/label';
import Box from '@mui/material/Box';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import { Upload, X } from 'lucide-react';
import { useState } from 'react';

interface ImageUploadProps {
    name: string;
    label?: string;
    helperText?: string;
    error?: string;
    existingImage?: string | null;
    maxSizeMB?: number;
    acceptedFormats?: string[];
}

export default function ImageUpload({
    name,
    label = 'Image',
    helperText,
    error,
    existingImage,
    maxSizeMB = 5,
    acceptedFormats = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'],
}: ImageUploadProps) {
    const normalizeExisting = (value?: string | null) => {
        if (!value) return null;
        // If already a data URL, absolute URL, or root-relative, return as-is
        if (
            value.startsWith('data:') ||
            value.startsWith('http://') ||
            value.startsWith('https://') ||
            value.startsWith('/')
        ) {
            return value;
        }

        // Otherwise assume it's a storage path like "bespoke-services/abc.jpg"
        return `/storage/${value}`;
    };

    const [preview, setPreview] = useState<string | null>(
        normalizeExisting(existingImage),
    );
    const [fileName, setFileName] = useState<string | null>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) return;

        // Validate file type
        if (!acceptedFormats.includes(file.type)) {
            e.target.value = '';
            setPreview(null);
            setFileName(null);
            return;
        }

        // Validate file size
        const sizeMB = file.size / (1024 * 1024);
        if (sizeMB > maxSizeMB) {
            e.target.value = '';
            setPreview(null);
            setFileName(null);
            return;
        }

        // Create preview
        const reader = new FileReader();
        reader.onloadend = () => {
            setPreview(reader.result as string);
            setFileName(file.name);
        };
        reader.readAsDataURL(file);
    };

    const clearImage = () => {
        const input = document.getElementById(name) as HTMLInputElement | null;
        if (input) {
            input.value = '';
        }
        setPreview(null);
        setFileName(null);
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
            <Label htmlFor={name}>{label}</Label>
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                <input
                    id={name}
                    name={name}
                    type="file"
                    accept={acceptedFormats.join(',')}
                    onChange={handleFileChange}
                    style={{ display: 'none' }}
                />
                <Box
                    component="label"
                    htmlFor={name}
                    sx={{
                        display: 'flex',
                        cursor: 'pointer',
                        flexDirection: 'column',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: 1,
                        borderRadius: 2,
                        border: '2px dashed',
                        borderColor: 'divider',
                        px: 3,
                        py: 4,
                        transition: 'all 0.2s',
                        '&:hover': {
                            borderColor: 'primary.main',
                            bgcolor: 'action.hover',
                        },
                    }}
                >
                    <Box sx={{ color: 'text.secondary' }}>
                        <Upload style={{ width: 24, height: 24, color: 'inherit' }} />
                    </Box>
                    <Box sx={{ textAlign: 'center' }}>
                        <Typography
                            variant="body2"
                            sx={{ fontWeight: 500, color: 'text.primary' }}
                        >
                            Click to upload or drag and drop
                        </Typography>
                        <Typography variant="caption" color="text.secondary">
                            PNG, JPG, JPEG or WebP up to {maxSizeMB}MB
                        </Typography>
                    </Box>
                </Box>

                {preview && (
                    <Box sx={{ position: 'relative' }}>
                        <Box
                            component="img"
                            src={preview}
                            alt="Preview"
                            sx={{
                                height: 'auto',
                                width: '100%',
                                maxWidth: 320,
                                borderRadius: 2,
                                border: 1,
                                borderColor: 'divider',
                                objectFit: 'cover',
                            }}
                        />
                        {fileName && (
                            <Typography
                                variant="caption"
                                color="text.secondary"
                                sx={{ mt: 1, display: 'block' }}
                            >
                                {fileName}
                            </Typography>
                        )}
                        <IconButton
                            onClick={clearImage}
                            sx={{
                                position: 'absolute',
                                top: 8,
                                right: 8,
                                bgcolor: 'error.main',
                                color: 'error.contrastText',
                                p: 0.5,
                                '&:hover': {
                                    bgcolor: 'error.dark',
                                },
                            }}
                        >
                            <X style={{ width: 16, height: 16 }} />
                        </IconButton>
                    </Box>
                )}

                {helperText && (
                    <Typography variant="caption" color="text.secondary">
                        {helperText}
                    </Typography>
                )}

                {error && (
                    <Typography variant="body2" color="error">
                        {error}
                    </Typography>
                )}
            </Box>
        </Box>
    );
}
