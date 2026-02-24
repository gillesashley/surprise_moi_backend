'use client';

import { Label } from '@/components/ui/label';
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
        <div className="space-y-2">
            <Label htmlFor={name}>{label}</Label>
            <div className="flex flex-col gap-4">
                <input
                    id={name}
                    name={name}
                    type="file"
                    accept={acceptedFormats.join(',')}
                    onChange={handleFileChange}
                    className="hidden"
                />
                <label
                    htmlFor={name}
                    className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-input px-6 py-8 transition-colors hover:border-primary hover:bg-accent"
                >
                    <Upload className="size-6 text-muted-foreground" />
                    <div className="text-center">
                        <p className="text-sm font-medium text-foreground">
                            Click to upload or drag and drop
                        </p>
                        <p className="text-xs text-muted-foreground">
                            PNG, JPG, JPEG or WebP up to {maxSizeMB}MB
                        </p>
                    </div>
                </label>

                {preview && (
                    <div className="relative">
                        <img
                            src={preview}
                            alt="Preview"
                            className="h-auto w-full max-w-xs rounded-lg border border-input object-cover"
                        />
                        {fileName && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                {fileName}
                            </p>
                        )}
                        <button
                            type="button"
                            onClick={clearImage}
                            className="absolute top-2 right-2 rounded-full bg-destructive p-1 text-destructive-foreground transition-colors hover:bg-destructive/90"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                )}

                {helperText && (
                    <p className="text-xs text-muted-foreground">
                        {helperText}
                    </p>
                )}

                {error && <p className="text-sm text-destructive">{error}</p>}
            </div>
        </div>
    );
}
