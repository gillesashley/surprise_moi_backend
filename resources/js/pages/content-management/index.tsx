import { BespokeServicesTab } from '@/components/content-management/bespoke-services-tab';
import { CategoriesTab } from '@/components/content-management/categories-tab';
import { CategoryDetailsModal } from '@/components/content-management/category-details-modal';
import { InterestsTab } from '@/components/content-management/interests-tab';
import { MusicGenresTab } from '@/components/content-management/music-genres-tab';
import { PersonalityTraitsTab } from '@/components/content-management/personality-traits-tab';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import Box from '@mui/material/Box';
import InputAdornment from '@mui/material/InputAdornment';
import { Head, router, usePage } from '@inertiajs/react';
import { FolderKanban, Heart, Music, Search, Sparkles } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

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

interface Interest {
    id: number;
    name: string;
    icon: string | null;
    users_count: number;
    created_at: string;
}

interface PersonalityTrait {
    id: number;
    name: string;
    icon: string | null;
    users_count: number;
    created_at: string;
}

interface MusicGenre {
    id: number;
    name: string;
    icon: string | null;
    users_count: number;
    created_at: string;
}

interface BespokeService {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
    vendor_applications_count: number;
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    categories: PaginatedData<Category>;
    interests: PaginatedData<Interest>;
    personalityTraits: PaginatedData<PersonalityTrait>;
    musicGenres: PaginatedData<MusicGenre>;
    bespokeServices: PaginatedData<BespokeService>;
    canCreate: boolean;
    canDelete: boolean;
    activeTab?: string;
    search?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Content Management',
        href: '/dashboard/content-management',
    },
];

export default function ContentManagementIndex({
    categories,
    interests,
    personalityTraits,
    musicGenres,
    bespokeServices,
    canCreate,
    canDelete,
    activeTab = 'categories',
    search: initialSearch = '',
}: Props) {
    const [viewingCategory, setViewingCategory] =
        React.useState<Category | null>(null);
    const [currentTab, setCurrentTab] = React.useState(activeTab);
    const [searchTerm, setSearchTerm] = React.useState(initialSearch);
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    React.useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    React.useEffect(() => {
        const delay = setTimeout(() => {
            if (searchTerm !== initialSearch) {
                router.get('/dashboard/content-management', {
                    tab: currentTab,
                    ...(searchTerm ? { search: searchTerm } : {}),
                }, {
                    preserveState: true,
                    preserveScroll: true,
                });
            }
        }, 300);
        return () => clearTimeout(delay);
    }, [searchTerm]);

    const handleTabChange = (value: string) => {
        setCurrentTab(value);
        const params = new URLSearchParams();
        params.set('tab', value);
        if (searchTerm) {
            params.set('search', searchTerm);
        }
        window.history.replaceState({}, '', `/dashboard/content-management?${params.toString()}`);
    };

    const iconStyle = { width: 16, height: 16 };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content Management" />
            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 2, flexWrap: 'wrap' }}>
                            <Box>
                                <CardTitle>Content Management</CardTitle>
                                <CardDescription>
                                    Manage categories, interests, personality traits,
                                    music genres, and bespoke services
                                </CardDescription>
                            </Box>
                            <Input
                                placeholder="Search by name..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm((e.target as HTMLInputElement).value)}
                                startAdornment={
                                    <InputAdornment position="start">
                                        <Search style={{ width: 16, height: 16, color: '#9e9e9e' }} />
                                    </InputAdornment>
                                }
                                style={{ maxWidth: 300 }}
                            />
                        </Box>
                    </CardHeader>
                    <CardContent>
                        <Tabs
                            value={currentTab}
                            onValueChange={handleTabChange}
                        >
                            <TabsList style={{ display: 'grid', width: '100%', gridTemplateColumns: 'repeat(5, 1fr)' }}>
                                <TabsTrigger
                                    value="categories"
                                    style={{ display: 'flex', alignItems: 'center', gap: 8 }}
                                >
                                    <FolderKanban style={iconStyle} />
                                    <Box component="span" sx={{ display: { xs: 'none', sm: 'inline' } }}>
                                        Categories
                                    </Box>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="interests"
                                    style={{ display: 'flex', alignItems: 'center', gap: 8 }}
                                >
                                    <Heart style={iconStyle} />
                                    <Box component="span" sx={{ display: { xs: 'none', sm: 'inline' } }}>
                                        Interests
                                    </Box>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="traits"
                                    style={{ display: 'flex', alignItems: 'center', gap: 8 }}
                                >
                                    <Sparkles style={iconStyle} />
                                    <Box component="span" sx={{ display: { xs: 'none', sm: 'inline' } }}>
                                        Traits
                                    </Box>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="music"
                                    style={{ display: 'flex', alignItems: 'center', gap: 8 }}
                                >
                                    <Music style={iconStyle} />
                                    <Box component="span" sx={{ display: { xs: 'none', sm: 'inline' } }}>
                                        Music
                                    </Box>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="bespoke"
                                    style={{ display: 'flex', alignItems: 'center', gap: 8 }}
                                >
                                    <Sparkles style={iconStyle} />
                                    <Box component="span" sx={{ display: { xs: 'none', sm: 'inline' } }}>
                                        Bespoke
                                    </Box>
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="categories">
                                <CategoriesTab
                                    categories={categories}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                    onViewCategory={setViewingCategory}
                                    search={searchTerm}
                                />
                            </TabsContent>

                            <TabsContent value="interests">
                                <InterestsTab
                                    interests={interests}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                    search={searchTerm}
                                />
                            </TabsContent>

                            <TabsContent value="traits">
                                <PersonalityTraitsTab
                                    personalityTraits={personalityTraits}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                    search={searchTerm}
                                />
                            </TabsContent>

                            <TabsContent value="music">
                                <MusicGenresTab
                                    musicGenres={musicGenres}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                    search={searchTerm}
                                />
                            </TabsContent>

                            <TabsContent value="bespoke">
                                <BespokeServicesTab
                                    bespokeServices={bespokeServices}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                    search={searchTerm}
                                />
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </Box>

            <CategoryDetailsModal
                category={viewingCategory}
                onClose={() => setViewingCategory(null)}
            />
        </AppLayout>
    );
}
