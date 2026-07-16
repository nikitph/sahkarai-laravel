import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { productEcho } from '@/lib/echo';

type RealtimeNotice = {
    id: string;
    title: string;
    body: string;
};

export function RealtimeNotifications() {
    const { auth, realtime } = usePage().props;
    const userId = auth.user.id;

    useEffect(() => {
        const echo = productEcho(realtime);

        if (!echo) {
            return;
        }

        const channelName = `App.Models.User.${userId}`;
        const channel = echo
            .private(channelName)
            .listen(
                '.product.notification.created',
                (notice: RealtimeNotice) => {
                    toast.info(notice.title, { description: notice.body });
                    router.reload({
                        only:
                            window.location.pathname === '/notifications'
                                ? ['product', 'notifications']
                                : ['product'],
                    });
                },
            );

        return () => {
            channel.stopListening('.product.notification.created');
            echo.leave(channelName);
        };
    }, [realtime, userId]);

    return null;
}
