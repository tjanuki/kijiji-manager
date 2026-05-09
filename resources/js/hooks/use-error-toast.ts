import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export function useErrorToast(): void {
    useEffect(() => {
        const offHttp = router.on('httpException', (event) => {
            const status = (event as CustomEvent).detail?.response?.status;

            if (status === 422) {
return;
}

            const messageByStatus: Record<number, string> = {
                403: "You don't have permission to do that.",
                404: 'That item no longer exists.',
                419: "Your session expired. Please refresh and try again.",
                429: 'Too many requests. Please wait a moment.',
            };
            toast.error(messageByStatus[status] ?? 'Something went wrong. Please try again.');
        });

        const offNetwork = router.on('networkError', () => {
            toast.error('Network error. Check your connection and try again.');
        });

        return () => {
            offHttp();
            offNetwork();
        };
    }, []);
}
