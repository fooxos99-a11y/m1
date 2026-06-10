import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

const hasRealtimeConfig = () => Boolean(process.env.VUE_APP_PUSHER_APP_KEY);

const buildPusherOptions = () => ({
  broadcaster: 'pusher',
  key: process.env.VUE_APP_PUSHER_APP_KEY,
  cluster: process.env.VUE_APP_PUSHER_APP_CLUSTER || 'mt1',
  wsHost: process.env.VUE_APP_PUSHER_HOST || undefined,
  wsPort: Number(process.env.VUE_APP_PUSHER_PORT || 443),
  wssPort: Number(process.env.VUE_APP_PUSHER_PORT || 443),
  forceTLS: (process.env.VUE_APP_PUSHER_SCHEME || 'https') === 'https',
  enabledTransports: ['ws', 'wss'],
});

const ensureEcho = () => {
  if (!hasRealtimeConfig()) {
    return null;
  }

  if (!echoInstance) {
    window.Pusher = Pusher;
    echoInstance = new Echo(buildPusherOptions());
  }

  return echoInstance;
};

export const subscribeDashboardRealtime = (handlers = {}) => {
  const echo = ensureEcho();

  if (!echo) {
    return () => {};
  }

  const notificationsChannel = echo.channel('dashboard.notifications');
  const activityChannel = echo.channel('dashboard.activity');

  notificationsChannel.listen('.dashboard.notification.created', ({ notification }) => {
    handlers.onNotificationCreated?.(notification);
  });

  notificationsChannel.listen('.dashboard.notification.deleted', ({ notificationId }) => {
    handlers.onNotificationDeleted?.(notificationId);
  });

  activityChannel.listen('.dashboard.activity.logged', ({ activityLog }) => {
    handlers.onActivityLogged?.(activityLog);
  });

  return () => {
    echo.leave('dashboard.notifications');
    echo.leave('dashboard.activity');
  };
};

export const disconnectDashboardRealtime = () => {
  if (!echoInstance) {
    return;
  }

  echoInstance.disconnect();
  echoInstance = null;
};