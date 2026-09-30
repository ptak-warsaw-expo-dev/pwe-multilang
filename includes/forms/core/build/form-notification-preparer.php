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

        return self::protectMessageMetaTags(self::prepareQrAttachmentFlags($notifications));
    }

    private static function protectMessageMetaTags(array $notifications): array
    {
        foreach ($notifications as &$notification) {
            if (!isset($notification['message']) || !is_string($notification['message'])) {
                continue;
            }

            // GF 2.8's DOM parser can mistake email meta tags for the admin page head.
            // Keep the comment on the same line so its insertion-point check rejects them.
            $notification['message'] = preg_replace_callback(
                '~(<meta\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>)(<!-- pwe-mail-meta -->)?~i',
                static function (array $match): string {
                    $attributes = preg_replace('~"[^"]*"|\'[^\']*\'~', '""', $match[1]);
                    if (!preg_match('~\s(?:charset|http-equiv)\s*=~i', $attributes)) {
                        return $match[0];
                    }

                    return $match[1] . '<!-- pwe-mail-meta -->';
                },
                $notification['message']
            );
        }

        unset($notification);
        return $notifications;
    }

    private static function normalize(array $notifications): array
    {
        $normalized = [];

        foreach ($notifications as $notification) {
            if (!empty($notification['pwe_notification_key'])) {
                $notification['pwe_notification_key'] = sanitize_key(
                    (string) $notification['pwe_notification_key']
                );
            }

            if (empty($notification['id'])) {
                $notification['id'] = uniqid();
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
