import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { show } from '@/routes/user-management-access';

const INACTIVITY_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'] as const;

/**
 * Monitors user activity and redirects to the access code page
 * after the specified period of inactivity.
 */
export function useInactivityLock(timeoutMs: number = INACTIVITY_TIMEOUT_MS): void {
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const resetTimer = () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }

            timerRef.current = setTimeout(() => {
                router.visit(show().url);
            }, timeoutMs);
        };

        // Start the timer immediately
        resetTimer();

        // Reset on any user activity
        for (const event of ACTIVITY_EVENTS) {
            window.addEventListener(event, resetTimer, { passive: true });
        }

        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }

            for (const event of ACTIVITY_EVENTS) {
                window.removeEventListener(event, resetTimer);
            }
        };
    }, [timeoutMs]);
}
