import { Breadcrumbs } from '@/components/breadcrumbs';
import { Icon } from '@/components/icon';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { isSameUrl, resolveUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { BookOpen, Folder, LayoutGrid, Menu, Search } from 'lucide-react';
import AppLogo from './app-logo';
import AppLogoIcon from './app-logo-icon';
import { NotificationBell } from '@/components/notifications';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const rightNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

const activeItemSx = {
    color: 'text.primary',
} as const;

const srOnlySx = {
    position: 'absolute',
    width: 1,
    height: 1,
    p: 0,
    m: -1,
    overflow: 'hidden',
    clip: 'rect(0,0,0,0)',
    whiteSpace: 'nowrap',
    border: 0,
} as const;

interface AppHeaderProps {
    breadcrumbs?: BreadcrumbItem[];
}

export function AppHeader({ breadcrumbs = [] }: AppHeaderProps) {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const getInitials = useInitials();
    return (
        <>
            <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                <Box
                    sx={{
                        mx: 'auto',
                        display: 'flex',
                        height: 64,
                        alignItems: 'center',
                        px: 2,
                        maxWidth: { md: '80rem' },
                    }}
                >
                    {/* Mobile Menu */}
                    <Box sx={{ display: { xs: 'block', lg: 'none' } }}>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 h-[34px] w-[34px]"
                                >
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex h-full w-64 flex-col items-stretch justify-between bg-sidebar"
                            >
                                <SheetTitle className="sr-only">
                                    Navigation Menu
                                </SheetTitle>
                                <SheetHeader className="flex justify-start text-left">
                                    <AppLogoIcon className="h-6 w-6 fill-current text-black dark:text-white" />
                                </SheetHeader>
                                <Box
                                    sx={{
                                        display: 'flex',
                                        height: '100%',
                                        flex: 1,
                                        flexDirection: 'column',
                                        gap: 2,
                                        p: 2,
                                    }}
                                >
                                    <Box
                                        sx={{
                                            display: 'flex',
                                            height: '100%',
                                            flexDirection: 'column',
                                            justifyContent: 'space-between',
                                            fontSize: '0.875rem',
                                        }}
                                    >
                                        <Box
                                            sx={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 2,
                                            }}
                                        >
                                            {mainNavItems.map((item) => (
                                                <Link
                                                    key={item.title}
                                                    href={item.href!}
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: '8px',
                                                        fontWeight: 500,
                                                        textDecoration: 'none',
                                                        color: 'inherit',
                                                    }}
                                                >
                                                    {item.icon && (
                                                        <Icon
                                                            iconNode={item.icon}
                                                            className="h-5 w-5"
                                                        />
                                                    )}
                                                    <span>{item.title}</span>
                                                </Link>
                                            ))}
                                        </Box>

                                        <Box
                                            sx={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 2,
                                            }}
                                        >
                                            {rightNavItems.map((item) => (
                                                <a
                                                    key={item.title}
                                                    href={resolveUrl(item.href!)}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    style={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: '8px',
                                                        fontWeight: 500,
                                                        textDecoration: 'none',
                                                        color: 'inherit',
                                                    }}
                                                >
                                                    {item.icon && (
                                                        <Icon
                                                            iconNode={item.icon}
                                                            className="h-5 w-5"
                                                        />
                                                    )}
                                                    <span>{item.title}</span>
                                                </a>
                                            ))}
                                        </Box>
                                    </Box>
                                </Box>
                            </SheetContent>
                        </Sheet>
                    </Box>

                    <Link
                        href={dashboard()}
                        prefetch
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            textDecoration: 'none',
                            color: 'inherit',
                        }}
                    >
                        <AppLogo />
                    </Link>

                    {/* Desktop Navigation */}
                    <Box
                        sx={{
                            ml: 3,
                            display: { xs: 'none', lg: 'flex' },
                            height: '100%',
                            alignItems: 'center',
                            gap: 3,
                        }}
                    >
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                                {mainNavItems.map((item, index) => (
                                    <NavigationMenuItem
                                        key={index}
                                        className="relative flex h-full items-center"
                                    >
                                        <Link
                                            href={item.href!}
                                            className={[
                                                navigationMenuTriggerStyle(),
                                                'h-9 cursor-pointer px-3',
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                            style={
                                                isSameUrl(
                                                    page.url,
                                                    item.href!,
                                                )
                                                    ? {
                                                          color: 'var(--color-neutral-900)',
                                                      }
                                                    : undefined
                                            }
                                        >
                                            {item.icon && (
                                                <Icon
                                                    iconNode={item.icon}
                                                    className="mr-2 h-4 w-4"
                                                />
                                            )}
                                            {item.title}
                                        </Link>
                                        {isSameUrl(page.url, item.href!) && (
                                            <Box
                                                sx={{
                                                    position: 'absolute',
                                                    bottom: 0,
                                                    left: 0,
                                                    height: '2px',
                                                    width: '100%',
                                                    transform:
                                                        'translateY(1px)',
                                                    bgcolor: 'text.primary',
                                                }}
                                            />
                                        )}
                                    </NavigationMenuItem>
                                ))}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </Box>

                    <Box
                        sx={{
                            ml: 'auto',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 1,
                        }}
                    >
                        <Box
                            sx={{
                                position: 'relative',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 0.5,
                            }}
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                className="group h-9 w-9 cursor-pointer"
                            >
                                <Search className="!size-5 opacity-80 group-hover:opacity-100" />
                            </Button>
                            <NotificationBell />
                            <Box
                                sx={{
                                    display: { xs: 'none', lg: 'flex' },
                                }}
                            >
                                {rightNavItems.map((item) => (
                                    <TooltipProvider
                                        key={item.title}
                                        delayDuration={0}
                                    >
                                        <Tooltip>
                                            <TooltipTrigger>
                                                <Box
                                                    component="a"
                                                    href={resolveUrl(item.href!)}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    sx={{
                                                        ml: 0.5,
                                                        display:
                                                            'inline-flex',
                                                        height: 36,
                                                        width: 36,
                                                        alignItems: 'center',
                                                        justifyContent:
                                                            'center',
                                                        borderRadius: 1,
                                                        bgcolor:
                                                            'transparent',
                                                        p: 0,
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                        color: 'text.secondary',
                                                        transition:
                                                            'background-color 0.15s, color 0.15s',
                                                        textDecoration: 'none',
                                                        '&:hover': {
                                                            bgcolor:
                                                                'action.hover',
                                                            color: 'text.primary',
                                                        },
                                                        '&:focus-visible': {
                                                            outline: 'none',
                                                            ring: 2,
                                                        },
                                                    }}
                                                >
                                                    <Typography
                                                        component="span"
                                                        sx={srOnlySx}
                                                    >
                                                        {item.title}
                                                    </Typography>
                                                    {item.icon && (
                                                        <Icon
                                                            iconNode={item.icon}
                                                            className="size-5 opacity-80 group-hover:opacity-100"
                                                        />
                                                    )}
                                                </Box>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{item.title}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                ))}
                            </Box>
                        </Box>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="size-10 rounded-full p-1"
                                >
                                    <Avatar className="size-8 overflow-hidden rounded-full">
                                        <AvatarImage
                                            src={auth.user.avatar}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </Box>
                </Box>
            </Box>
            {breadcrumbs.length > 1 && (
                <Box
                    sx={{
                        display: 'flex',
                        width: '100%',
                        borderBottom: 1,
                        borderColor: 'divider',
                    }}
                >
                    <Box
                        sx={{
                            mx: 'auto',
                            display: 'flex',
                            height: 48,
                            width: '100%',
                            alignItems: 'center',
                            justifyContent: 'flex-start',
                            px: 2,
                            color: 'text.secondary',
                            maxWidth: { md: '80rem' },
                        }}
                    >
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </Box>
                </Box>
            )}
        </>
    );
}
