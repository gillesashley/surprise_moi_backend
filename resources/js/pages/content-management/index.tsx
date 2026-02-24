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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { FolderKanban, Heart, Music, Sparkles } from 'lucide-react';
import * as React from 'react';

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
}: Props) {
    const [viewingCategory, setViewingCategory] =
        React.useState<Category | null>(null);
    const [currentTab, setCurrentTab] = React.useState(activeTab);

    const handleTabChange = (value: string) => {
        // Update local state immediately for responsive UI
        setCurrentTab(value);

        // Update URL without making a full page request
        window.history.replaceState({}, '', `/content-management?tab=${value}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content Management" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Content Management</CardTitle>
                        <CardDescription>
                            Manage categories, interests, personality traits,
                            music genres, and bespoke services
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Tabs
                            value={currentTab}
                            onValueChange={handleTabChange}
                        >
                            <TabsList className="grid w-full grid-cols-5">
                                <TabsTrigger
                                    value="categories"
                                    className="flex items-center gap-2"
                                >
                                    <FolderKanban className="size-4" />
                                    <span className="hidden sm:inline">
                                        Categories
                                    </span>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="interests"
                                    className="flex items-center gap-2"
                                >
                                    <Heart className="size-4" />
                                    <span className="hidden sm:inline">
                                        Interests
                                    </span>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="traits"
                                    className="flex items-center gap-2"
                                >
                                    <Sparkles className="size-4" />
                                    <span className="hidden sm:inline">
                                        Traits
                                    </span>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="music"
                                    className="flex items-center gap-2"
                                >
                                    <Music className="size-4" />
                                    <span className="hidden sm:inline">
                                        Music
                                    </span>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="bespoke"
                                    className="flex items-center gap-2"
                                >
                                    <Sparkles className="size-4" />
                                    <span className="hidden sm:inline">
                                        Bespoke
                                    </span>
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="categories">
                                <CategoriesTab
                                    categories={categories}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                    onViewCategory={setViewingCategory}
                                />
                            </TabsContent>

                            <TabsContent value="interests">
                                <InterestsTab
                                    interests={interests}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                />
                            </TabsContent>

                            <TabsContent value="traits">
                                <PersonalityTraitsTab
                                    personalityTraits={personalityTraits}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                />
                            </TabsContent>

                            <TabsContent value="music">
                                <MusicGenresTab
                                    musicGenres={musicGenres}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                />
                            </TabsContent>

                            <TabsContent value="bespoke">
                                <BespokeServicesTab
                                    bespokeServices={bespokeServices}
                                    canCreate={canCreate}
                                    canDelete={canDelete}
                                />
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </div>

            <CategoryDetailsModal
                category={viewingCategory}
                onClose={() => setViewingCategory(null)}
            />
        </AppLayout>
    );
}
