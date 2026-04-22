import echo from '@/lib/echo';
import { useEffect } from 'react';

interface VendorApprovalEvent {
    vendor_application_id: number;
    user_id: number;
    user_name: string;
    user_email: string;
    submitted_at: string;
    message: string;
}

const isDevelopment = import.meta.env.DEV;

/**
 * Hook to listen for vendor approval events on the admin channel
 * Calls the provided callback when a new vendor application is submitted
 *
 * @param onNewApplication - Callback fired when new vendor submits application
 * @returns Cleanup function to unsubscribe from channel
 */
export function useVendorApprovalEvents(
    onNewApplication?: (event: VendorApprovalEvent) => void,
) {
    useEffect(() => {
        if (isDevelopment) {
            console.log(
                '📡 [useVendorApprovalEvents] Hook mounted, subscribing to admin channel...',
            );
        }

        // Listen on private admin channel for new submissions
        const channel = echo.private('admin');

        if (isDevelopment) {
            console.log(
                '📡 [useVendorApprovalEvents] Subscribed to channel:',
                'private-admin',
            );
            console.log(
                '📡 [useVendorApprovalEvents] Listening for event:',
                'vendor.approval.submitted',
            );
        }

        const listener = channel.listen(
            'vendor.approval.submitted',
            (event: VendorApprovalEvent) => {
                if (isDevelopment) {
                    console.log(
                        '✅ [useVendorApprovalEvents] Event received!',
                        {
                            timestamp: new Date().toISOString(),
                            event: 'vendor.approval.submitted',
                            data: event,
                        },
                    );
                }

                onNewApplication?.(event);
            },
        );

        // Add error handler
        channel.error((error: any) => {
            if (isDevelopment) {
                console.error(
                    '❌ [useVendorApprovalEvents] Channel error:',
                    error,
                );
            }
        });

        if (isDevelopment) {
            console.log(
                '📡 [useVendorApprovalEvents] Event listeners configured',
            );
        }

        // Cleanup: unsubscribe when component unmounts
        return () => {
            if (isDevelopment) {
                console.log(
                    '📡 [useVendorApprovalEvents] Cleaning up - leaving admin channel',
                );
            }
            echo.leaveChannel('admin');
        };
    }, [onNewApplication]);
}
