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

const iconSmall = { width: 16, height: 16 };
const iconMedium = { width: 20, height: 20 };

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
                                    style={{ marginRight: 8, width: 34, height: 34 }}
                                >
                                    <Menu style={iconMedium} />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                style={{
                                    display: 'flex',
                                    height: '100%',
                                    width: 256,
                                    flexDirection: 'column',
                                    alignItems: 'stretch',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <SheetTitle style={{
                                    position: 'absolute',
                                    width: 1,
                                    height: 1,
                                    padding: 0,
                                    margin: -1,
                                    overflow: 'hidden',
                                    clip: 'rect(0,0,0,0)',
                                    whiteSpace: 'nowrap',
                                    border: 0,
                                }}>
                                    Navigation Menu
                                </SheetTitle>
                                <SheetHeader style={{ display: 'flex', justifyContent: 'flex-start', textAlign: 'left' }}>
                                    <AppLogoIcon style={{ width: 24, height: 24 }} />
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
                                                        gap: 8,
                                                        fontWeight: 500,
                                                        textDecoration: 'none',
                                                        color: 'inherit',
                                                    }}
                                                >
                                                    {item.icon && (
                                                        <Icon
                                                            iconNode={item.icon}
                                                            style={iconMedium}
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
                                                        gap: 8,
                                                        fontWeight: 500,
                                                        textDecoration: 'none',
                                                        color: 'inherit',
                                                    }}
                                                >
                                                    {item.icon && (
                                                        <Icon
                                                            iconNode={item.icon}
                                                            style={iconMedium}
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
                            gap: 8,
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
                        <NavigationMenu style={{ display: 'flex', height: '100%', alignItems: 'stretch' }}>
                            <NavigationMenuList style={{ display: 'flex', height: '100%', alignItems: 'stretch', gap: 8 }}>
                                {mainNavItems.map((item, index) => (
                                    <NavigationMenuItem
                                        key={index}
                                        style={{ position: 'relative', display: 'flex', height: '100%', alignItems: 'center' }}
                                    >
                                        <Link
                                            href={item.href!}
                                            style={{
                                                ...(navigationMenuTriggerStyle() ? {} : {}),
                                                display: 'inline-flex',
                                                alignItems: 'center',
                                                height: 36,
                                                cursor: 'pointer',
                                                paddingLeft: 12,
                                                paddingRight: 12,
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                                textDecoration: 'none',
                                                color: isSameUrl(page.url, item.href!)
                                                    ? 'var(--mui-palette-text-primary)'
                                                    : 'inherit',
                                            }}
                                        >
                                            {item.icon && (
                                                <Icon
                                                    iconNode={item.icon}
                                                    style={{ ...iconSmall, marginRight: 8 }}
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
                                style={{ width: 36, height: 36, cursor: 'pointer' }}
                            >
                                <Search style={{ width: 20, height: 20, opacity: 0.8 }} />
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
                                                            style={{ width: 20, height: 20, opacity: 0.8 }}
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
                                    style={{ width: 40, height: 40, borderRadius: '50%', padding: 4 }}
                                >
                                    <Avatar style={{ width: 32, height: 32, overflow: 'hidden', borderRadius: '50%' }}>
                                        <AvatarImage
                                            src={auth.user.avatar}
                                            alt={auth.user.name}
                                        />
                                        <AvatarFallback style={{ borderRadius: 8 }}>
                                            {getInitials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent style={{ width: 224 }} align="end">
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
