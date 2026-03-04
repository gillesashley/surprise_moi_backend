import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { type InertiaLinkProps, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useCallback, useState } from 'react';

const STORAGE_KEY = 'sidebar-open-items';
const ICON_SIZE = 18;
const SUB_ICON_SIZE = 16;

function getStoredOpenItems(): string[] {
    try {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch {
        return [];
    }
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const [openItems, setOpenItems] = useState<string[]>(getStoredOpenItems);

    const toggleItem = useCallback((title: string) => {
        setOpenItems((prev) => {
            const next = prev.includes(title)
                ? prev.filter((item) => item !== title)
                : [...prev, title];
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(next));
            return next;
        });
    }, []);

    const isActive = (href?: InertiaLinkProps['href']): boolean => {
        if (!href) return false;
        const resolved = resolveUrl(href);

        // Exact match for dashboard
        if (resolved === '/dashboard') {
            return page.url === resolved;
        }

        // For URLs with query params, compare pathname and check all params exist
        const [resolvedPath, resolvedQuery] = resolved.split('?');
        const [pagePath, pageQuery] = page.url.split('?');

        if (!pagePath.startsWith(resolvedPath)) {
            return false;
        }

        // If no query params in href, path prefix match is enough
        if (!resolvedQuery) {
            return true;
        }

        // Check that all query params from the nav href exist in the current URL
        const pageParams = new URLSearchParams(pageQuery || '');
        const resolvedParams = new URLSearchParams(resolvedQuery);
        for (const [key, value] of resolvedParams) {
            if (pageParams.get(key) !== value) {
                return false;
            }
        }
        return true;
    };

    const hasActiveChild = (item: NavItem): boolean => {
        if (!item.items) {
            return false;
        }
        return item.items.some((child) => isActive(child.href));
    };

    return (
        <SidebarGroup style={{ paddingLeft: 12, paddingRight: 12, paddingTop: 4, paddingBottom: 4 }}>
            <SidebarGroupLabel style={{ fontSize: '0.6875rem', textTransform: 'uppercase', letterSpacing: '0.05em', color: 'var(--muted-foreground, #94a3b8)' }}>
                Navigation
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    if (item.items && item.items.length > 0) {
                        const isOpen =
                            openItems.includes(item.title) ||
                            hasActiveChild(item);

                        return (
                            <Collapsible
                                key={item.title}
                                asChild
                                open={isOpen}
                                onOpenChange={() => toggleItem(item.title)}
                            >
                                <SidebarMenuItem>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            tooltip={{
                                                children: item.title,
                                            }}
                                        >
                                            {item.icon && (
                                                <item.icon size={ICON_SIZE} strokeWidth={1.75} style={{ flexShrink: 0 }} />
                                            )}
                                            <span>{item.title}</span>
                                            <ChevronRight
                                                size={14}
                                                strokeWidth={2}
                                                style={{
                                                    marginLeft: 'auto',
                                                    transition: 'transform 0.2s ease',
                                                    transform: isOpen
                                                        ? 'rotate(90deg)'
                                                        : 'none',
                                                    opacity: 0.5,
                                                }}
                                            />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {item.items.map((subItem) => (
                                                <SidebarMenuSubItem
                                                    key={subItem.href ? resolveUrl(subItem.href) : subItem.title}
                                                >
                                                    <SidebarMenuSubButton
                                                        asChild
                                                        isActive={isActive(subItem.href)}
                                                    >
                                                        <Link
                                                            href={subItem.href!}
                                                            prefetch
                                                        >
                                                            {subItem.icon && (
                                                                <subItem.icon size={SUB_ICON_SIZE} strokeWidth={1.75} style={{ flexShrink: 0 }} />
                                                            )}
                                                            <span>
                                                                {subItem.title}
                                                            </span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        );
                    }

                    return (
                        <SidebarMenuItem key={item.href ? resolveUrl(item.href) : item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href!} prefetch>
                                    {item.icon && (
                                        <item.icon size={ICON_SIZE} strokeWidth={1.75} style={{ flexShrink: 0 }} />
                                    )}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
