<?php

use App\Notifications\BookingCreated;
use App\Notifications\ContractExecuted;
use App\Notifications\GalleryArchiveReady;
use App\Notifications\GalleryExpirationReminder;
use App\Notifications\GuestPhotoCommented;
use App\Notifications\SelectionLimitReached;
use App\Notifications\StorageAlmostFull;
use App\Notifications\SubscriptionExpiredWarning;
use App\Notifications\SubscriptionExpiringSoon;
use App\Notifications\VerifyEmail;

/**
 * Catalogue of transactional emails shown in the admin panel.
 *
 * Labels, descriptions and trigger metadata are admin-only and therefore live
 * here in both locales. The `fields` arrays reference the actual translation
 * keys used by the notifications, so the translations panel can edit them.
 *
 * @return array<string, array{
 *     notification: class-string,
 *     label: array{en: string, es: string},
 *     description: array{en: string, es: string},
 *     trigger: array{en: string, es: string},
 *     when: array{en: string, es: string},
 *     recipients: array{en: string, es: string},
 *     fields: list<array{key: string, label: string, type: 'text'|'textarea'}>,
 *     banner_fields?: list<array{key: string, label: string, type: 'text'|'textarea'}>
 * }>
 */
return [

    'verify_email' => [
        'notification' => VerifyEmail::class,
        'label' => [
            'en' => 'Email verification',
            'es' => 'Verificación de email',
        ],
        'description' => [
            'en' => 'Sends the link to verify the account email address.',
            'es' => 'Envía el enlace para verificar el email de la cuenta.',
        ],
        'trigger' => [
            'en' => 'Registration, the resend button or the email:resend-verification command.',
            'es' => 'Registro, botón de reenvío o comando email:resend-verification.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'The user',
            'es' => 'El usuario',
        ],
        'fields' => [
            ['key' => 'Verify Email Address', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Please click the button below to verify your email address.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Verify Email Address', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'If you did not create an account, no further action is required.', 'label' => 'Body', 'type' => 'textarea'],
        ],
    ],

    'booking_created' => [
        'notification' => BookingCreated::class,
        'label' => [
            'en' => 'Booking confirmed',
            'es' => 'Reserva confirmada',
        ],
        'description' => [
            'en' => 'Notifies the studio and the payer after a booking payment.',
            'es' => 'Avisa al estudio y al pagador tras el pago de una reserva.',
        ],
        'trigger' => [
            'en' => 'Stripe checkout completed for a booking without a photoshoot.',
            'es' => 'Pago de Stripe completado en una reserva sin sesión.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'Team owner and payer email',
            'es' => 'Dueño del equipo y email del pagador',
        ],
        'fields' => [
            ['key' => 'Booking Confirmed: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello!', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'A new booking has been made.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Session: :name', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Date: :date', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Start Time: :start', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'End Time: :end', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Customer: :customer', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Amount: :amount', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Add to Google Calendar', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Photoshoot with :customer', 'label' => 'Calendar event', 'type' => 'text'],
        ],
    ],

    'guest_photo_commented' => [
        'notification' => GuestPhotoCommented::class,
        'label' => [
            'en' => 'Guest photo comment',
            'es' => 'Comentario de invitado',
        ],
        'description' => [
            'en' => 'Notifies the studio when a guest comments on a shared gallery photo.',
            'es' => 'Avisa al estudio cuando un invitado comenta una foto de una galería compartida.',
        ],
        'trigger' => [
            'en' => 'A guest comments on a photo in a shared gallery.',
            'es' => 'Un invitado comenta una foto en una galería compartida.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'New comment on your photo', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello!', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'A new comment has been left on a photo in your gallery:', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Gallery: :name', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Photo: :name', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Comment: ":comment"', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'View Photo', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'You are receiving this because someone commented on your shared gallery.', 'label' => 'Body', 'type' => 'textarea'],
        ],
    ],

    'gallery_expiration_reminder' => [
        'notification' => GalleryExpirationReminder::class,
        'label' => [
            'en' => 'Gallery expiration reminder',
            'es' => 'Recordatorio de expiración de galería',
        ],
        'description' => [
            'en' => 'Reminds the studio before a gallery expires.',
            'es' => 'Recuerda al estudio que una galería va a expirar.',
        ],
        'trigger' => [
            'en' => 'Daily command for galleries expiring within 3 days without a previous reminder.',
            'es' => 'Comando diario para galerías que expiran en 3 días sin recordatorio previo.',
        ],
        'when' => [
            'en' => 'Daily at 09:00 UTC',
            'es' => 'Diario a las 09:00 UTC',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Gallery Expiration Reminder', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Your gallery ":name" will expire on :date.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'View Gallery', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'gallery_archive_ready' => [
        'notification' => GalleryArchiveReady::class,
        'label' => [
            'en' => 'Gallery ZIP ready',
            'es' => 'ZIP de galería listo',
        ],
        'description' => [
            'en' => 'Notifies the studio when a gallery ZIP has been generated.',
            'es' => 'Avisa al estudio cuando se ha generado el ZIP de una galería.',
        ],
        'trigger' => [
            'en' => 'A gallery ZIP build completes successfully.',
            'es' => 'Un ZIP de galería se genera correctamente.',
        ],
        'when' => [
            'en' => 'After the archive upload, queued',
            'es' => 'Tras subir el archivo, en cola',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Your gallery ZIP is ready: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello :name,', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'The ZIP archive for the gallery ":name" is ready to download.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'You can notify your client that the photos are ready to download.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'The ZIP will be available for :days days.', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Open Download tab', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Best regards, Picstome Team', 'label' => 'Salutation', 'type' => 'text'],
        ],
    ],

    'selection_limit_reached' => [
        'notification' => SelectionLimitReached::class,
        'label' => [
            'en' => 'Selection limit reached',
            'es' => 'Límite de selección alcanzado',
        ],
        'description' => [
            'en' => 'Notifies the studio when the customer reaches the gallery selection limit.',
            'es' => 'Avisa al estudio cuando el cliente alcanza el límite de selección de la galería.',
        ],
        'trigger' => [
            'en' => 'Favorites count reaches the gallery selection limit; sent once per gallery.',
            'es' => 'El número de favoritos alcanza el límite de la galería; una vez por galería.',
        ],
        'when' => [
            'en' => 'Immediately, queued',
            'es' => 'Inmediato, en cola',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Selection Limit Reached for Gallery: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello :name,', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Your customer has reached the selection limit of :limit photos for the gallery ":name".', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'You can now start working on the selected photos.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Please note that the customer may have changed pictures in the meantime, so be sure to confirm with the customer before starting.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'View Gallery', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Best regards, Picstome Team', 'label' => 'Salutation', 'type' => 'text'],
        ],
    ],

    'contract_executed' => [
        'notification' => ContractExecuted::class,
        'label' => [
            'en' => 'Contract signed',
            'es' => 'Contrato firmado',
        ],
        'description' => [
            'en' => 'Sends the signed contract to every signer.',
            'es' => 'Envía el contrato firmado a cada firmante.',
        ],
        'trigger' => [
            'en' => 'The last signature is completed and the PDF is generated.',
            'es' => 'Se completa la última firma y se genera el PDF.',
        ],
        'when' => [
            'en' => 'After the PDF job, queued',
            'es' => 'Tras el trabajo del PDF, en cola',
        ],
        'recipients' => [
            'en' => 'Every signer email',
            'es' => 'Cada email firmante',
        ],
        'fields' => [
            ['key' => 'Signed: :title', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Contract signed', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Download the signed contract for your records. All relevant parties have been notified by email.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Download contract', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'subscription_expiring_soon' => [
        'notification' => SubscriptionExpiringSoon::class,
        'label' => [
            'en' => 'Subscription expiring soon',
            'es' => 'Suscripción por expirar',
        ],
        'description' => [
            'en' => 'Warns the owner before the subscription ends.',
            'es' => 'Avisa al dueño antes de que termine la suscripción.',
        ],
        'trigger' => [
            'en' => 'Daily cleanup when an active subscription ends in 15, 7 or 1 days.',
            'es' => 'Limpieza diaria cuando una suscripción activa termina en 15, 7 o 1 días.',
        ],
        'when' => [
            'en' => 'Daily at 00:00 UTC',
            'es' => 'Diario a las 00:00 UTC',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Your subscription expires soon', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Your subscription expires in :days days.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Renew Subscription', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Thank you for using our application!', 'label' => 'Body', 'type' => 'textarea'],
        ],
    ],

    'subscription_expired' => [
        'notification' => SubscriptionExpiredWarning::class,
        'label' => [
            'en' => 'Subscription expired',
            'es' => 'Suscripción expirada',
        ],
        'description' => [
            'en' => 'Warns the owner after the subscription expires and before data is deleted.',
            'es' => 'Avisa al dueño tras expirar la suscripción y antes de borrar los datos.',
        ],
        'trigger' => [
            'en' => 'Daily cleanup one day after a canceled subscription ends.',
            'es' => 'Limpieza diaria un día después de que termine una suscripción cancelada.',
        ],
        'when' => [
            'en' => 'Daily at 00:00 UTC',
            'es' => 'Diario a las 00:00 UTC',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Your subscription has expired', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Your subscription has expired.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Renew Subscription', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Your data will be deleted in :days days if not renewed.', 'label' => 'Body', 'type' => 'textarea'],
        ],
    ],

    'storage_almost_full' => [
        'notification' => StorageAlmostFull::class,
        'label' => [
            'en' => 'Storage almost full',
            'es' => 'Almacenamiento casi lleno',
        ],
        'description' => [
            'en' => 'Warns the owner before uploads start failing because the storage is full. Includes the dashboard banner copy.',
            'es' => 'Avisa al dueño antes de que fallen las subidas por falta de espacio. Incluye el texto del banner del dashboard.',
        ],
        'trigger' => [
            'en' => 'A photo upload leaves usage at or above 95%; sent once per plan.',
            'es' => 'Una subida deja el uso en 95% o más; una vez por plan.',
        ],
        'when' => [
            'en' => 'After the upload, queued',
            'es' => 'Tras la subida, en cola',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Your storage is almost full', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello :name,', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'You have used :used of :total storage.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Choose a plan with more storage to keep uploading photos and videos.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'See plans', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Best regards, Picstome Team', 'label' => 'Salutation', 'type' => 'text'],
        ],
        'banner_fields' => [
            ['key' => 'Your storage is almost full', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'You have used :percent% of your storage. When it fills up, you won\'t be able to upload more photos or videos.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'See plans', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Dismiss', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

];
