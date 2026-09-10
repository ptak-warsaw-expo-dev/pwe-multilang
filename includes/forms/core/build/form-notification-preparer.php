<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PWE_Multilang_Form_Notification_Preparer
{
    public static function prepare(array $notifications, array $fields, string $formDir = ''): array
    {
        $notifications = self::normalize($notifications);
        $notifications = PWE_Multilang_Form_Notification_Templates::hydrate($notifications, $formDir);
        $notifications = PWE_Multilang_Form_Notification_Templates::replaceLangShortcodes($notifications);
        $notifications = PWE_Multilang_Form_Conditional_Logic_Resolver::notifications($notifications, $fields);
        $notifications = PWE_Multilang_Form_Conditional_Logic_Resolver::notificationRecipients($notifications, $fields);
        $notifications = PWE_Multilang_Form_Merge_Tag_Resolver::notifications($notifications, $fields);

        return self::prepareQrAttachmentFlags($notifications);
    }

    private static function normalize(array $notifications): array
    {
        $normalized = [];

        foreach ($notifications as $notification) {
            if (empty($notification['id'])) {
                $notification['id'] = md5($notification['name'] ?? uniqid('', true));
            }

            $normalized[$notification['id']] = $notification;
        }

        return $normalized;
    }

    private static function prepareQrAttachmentFlags(array $notifications): array
    {
        foreach ($notifications as &$notification) {
            if (empty($notification['attachQr'])) {
                unset($notification['attachQr']);
                continue;
            }

            $notification['pwe_attach_qr_image'] = 1;
            unset($notification['attachQr']);
        }

        unset($notification);
        return $notifications;
    }
}
