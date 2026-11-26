<?php

use App\Notifications\BookingCancelled;
use App\Notifications\BookingCancelledForPhotographer;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingCreated;
use App\Notifications\BookingPaid;
use App\Notifications\BookingPendingBizum;
use App\Notifications\BookingPendingBizumForPhotographer;
use App\Notifications\BookingReminder;
use App\Notifications\CameraUploadsPaused;
use App\Notifications\ContractExecuted;
use App\Notifications\ContractPdfFailed;
use App\Notifications\GalleryArchiveFailed;
use App\Notifications\GalleryArchiveReady;
use App\Notifications\GalleryExpirationReminder;
use App\Notifications\GuestPhotoCommented;
use App\Notifications\LoginLink;
use App\Notifications\PaymentLinkBizumConfirmed;
use App\Notifications\PaymentLinkBizumPending;
use App\Notifications\PaymentLinkBizumPendingForPhotographer;
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
            ['key' => 'Session', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Date', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Start Time: :start', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'End Time: :end', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Customer', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Amount', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Add to Google Calendar', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Photoshoot with :customer', 'label' => 'Calendar event', 'type' => 'text'],
        ],
    ],

    'booking_confirmed' => [
        'notification' => BookingConfirmed::class,
        'label' => [
            'en' => 'Booking paid',
            'es' => 'Reserva pagada',
        ],
        'description' => [
            'en' => 'Confirms the session to the client and the studio after a card payment.',
            'es' => 'Confirma la sesión al cliente y al estudio tras el pago con tarjeta.',
        ],
        'trigger' => [
            'en' => 'Stripe checkout completed or a card payment fulfilled.',
            'es' => 'Pago de Stripe completado o pago con tarjeta confirmado.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'Client and team owner',
            'es' => 'Cliente y dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Booking confirmed: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello!', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Your session is booked.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Time', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Total', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Paid now', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Pending', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'View booking', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Add it to your calendar:', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Google Calendar', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Outlook Calendar', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'iCloud Calendar', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'booking_paid' => [
        'notification' => BookingPaid::class,
        'label' => [
            'en' => 'New booking paid',
            'es' => 'Nueva reserva pagada',
        ],
        'description' => [
            'en' => 'Tells the photographer that a booking has been paid.',
            'es' => 'Avisa al fotógrafo de que una reserva se ha pagado.',
        ],
        'trigger' => [
            'en' => 'A card payment is fulfilled or the photographer confirms a Bizum.',
            'es' => 'Se completa un pago con tarjeta o el fotógrafo confirma un Bizum.',
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
            ['key' => 'New booking paid: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'New booking', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Customer', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Email', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Phone', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Notes', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Open the agenda', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'booking_pending_bizum_photographer' => [
        'notification' => BookingPendingBizumForPhotographer::class,
        'label' => [
            'en' => 'New booking pending payment (photographer)',
            'es' => 'Nueva reserva pendiente de pago (fotógrafo)',
        ],
        'description' => [
            'en' => 'Tells the photographer that a new booking is waiting for a Bizum.',
            'es' => 'Avisa al fotógrafo de que una reserva nueva espera un Bizum.',
        ],
        'trigger' => [
            'en' => 'The client chooses Bizum on the public booking page.',
            'es' => 'El cliente elige Bizum en la página pública de reserva.',
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
            ['key' => 'New booking pending payment: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'New booking', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Customer', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Email', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Phone', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Expected', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Reference', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Payment method: :method', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Confirm it from the agenda when the payment arrives.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Open the agenda', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'booking_reminder' => [
        'notification' => BookingReminder::class,
        'label' => [
            'en' => 'Booking reminder',
            'es' => 'Recordatorio de reserva',
        ],
        'description' => [
            'en' => 'Reminds the client before the session.',
            'es' => 'Recuerda al cliente la sesión.',
        ],
        'trigger' => [
            'en' => 'The bookings:send-reminders command.',
            'es' => 'El comando bookings:send-reminders.',
        ],
        'when' => [
            'en' => 'At the configured hours before the session',
            'es' => 'A las horas configuradas antes de la sesión',
        ],
        'recipients' => [
            'en' => 'Client',
            'es' => 'Cliente',
        ],
        'fields' => [
            ['key' => 'Reminder: your session is on :date', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'This is a reminder of your session.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Location', 'label' => 'Body', 'type' => 'text'],
        ],
    ],

    'booking_cancelled' => ['notification' => BookingCancelled::class,        'label' => [
        'en' => 'Booking cancelled (client)',
        'es' => 'Reserva cancelada (cliente)',
    ],
        'description' => [
            'en' => 'Tells the client that the session has been cancelled.',
            'es' => 'Avisa al cliente de que la sesión se ha cancelado.',
        ],
        'trigger' => [
            'en' => 'The photographer cancels a booking from the agenda.',
            'es' => 'El fotógrafo cancela una reserva desde la agenda.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'Client',
            'es' => 'Cliente',
        ],
        'fields' => [
            ['key' => 'Booking cancelled: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Your session has been cancelled.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Reason', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Cancellation policy: :policy', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'If a refund applies, the photographer will handle it separately.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Book another session', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'booking_cancelled_photographer' => [
        'notification' => BookingCancelledForPhotographer::class,
        'label' => [
            'en' => 'Booking cancelled (photographer)',
            'es' => 'Reserva cancelada (fotógrafo)',
        ],
        'description' => [
            'en' => 'Sends the photographer a record of the cancellation with the client contact.',
            'es' => 'Envía al fotógrafo un registro de la cancelación con el contacto del cliente.',
        ],
        'trigger' => [
            'en' => 'The photographer cancels a booking from the agenda.',
            'es' => 'El fotógrafo cancela una reserva desde la agenda.',
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
            ['key' => 'Booking cancelled by you: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Booking cancelled', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Reason', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Open the agenda', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'booking_pending_bizum' => [
        'notification' => BookingPendingBizum::class,
        'label' => [
            'en' => 'Booking pending Bizum',
            'es' => 'Reserva pendiente de Bizum',
        ],
        'description' => [
            'en' => 'Sends the Bizum instructions and the reference to the client.',
            'es' => 'Envía al cliente las instrucciones de Bizum y la referencia.',
        ],
        'trigger' => [
            'en' => 'The client chooses Bizum on the public booking page.',
            'es' => 'El cliente elige Bizum en la página pública de reserva.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'Client',
            'es' => 'Cliente',
        ],
        'fields' => [
            ['key' => 'Complete your booking with Bizum: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Your slot is reserved. Send the payment to confirm it:', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Send the Bizum to this phone:', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Reference', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'The reservation is confirmed as soon as the photographer checks the payment.', 'label' => 'Body', 'type' => 'textarea'],
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

    'gallery_archive_failed' => [
        'notification' => GalleryArchiveFailed::class,
        'label' => [
            'en' => 'Gallery ZIP failed',
            'es' => 'Error al generar el ZIP de galería',
        ],
        'description' => [
            'en' => 'Notifies the studio when a gallery ZIP could not be generated.',
            'es' => 'Avisa al estudio cuando no se pudo generar el ZIP de una galería.',
        ],
        'trigger' => [
            'en' => 'A gallery ZIP build fails, for example because there is not enough free disk space.',
            'es' => 'Falla la generación de un ZIP de galería, por ejemplo por falta de espacio libre.',
        ],
        'when' => [
            'en' => 'After the failure, queued',
            'es' => 'Tras el fallo, en cola',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'The gallery could not be generated: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello :name,', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'The gallery could not be generated. Please contact the administrator.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Gallery: :name', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'View Gallery', 'label' => 'Button', 'type' => 'text'],
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

    'contract_pdf_failed' => [
        'notification' => ContractPdfFailed::class,
        'label' => [
            'en' => 'Contract PDF failed',
            'es' => 'Error al generar el PDF del contrato',
        ],
        'description' => [
            'en' => 'Warns the owner when the signed contract PDF could not be generated.',
            'es' => 'Avisa al dueño cuando no se pudo generar el PDF del contrato firmado.',
        ],
        'trigger' => [
            'en' => 'PDF generation fails after every retry. The signatures stay valid and the PDF can be requested again.',
            'es' => 'La generación del PDF falla tras todos los reintentos. Las firmas siguen siendo válidas y el PDF se puede volver a solicitar.',
        ],
        'when' => [
            'en' => 'After the last retry fails, queued',
            'es' => 'Tras fallar el último reintento, en cola',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'The contract PDF could not be generated: :name', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello :name,', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'We could not generate the PDF for contract :name. Open the contract and request it again.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'View contract', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Best regards, Picstome Team', 'label' => 'Salutation', 'type' => 'text'],
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

    'camera_uploads_paused' => [
        'notification' => CameraUploadsPaused::class,
        'label' => [
            'en' => 'Camera uploads paused',
            'es' => 'Subidas de cámara en pausa',
        ],
        'description' => [
            'en' => 'Tells the owner that shots sent from the camera are being discarded because the plan has no storage left.',
            'es' => 'Avisa al dueño de que las fotos enviadas desde la cámara se descartan porque el plan se ha quedado sin espacio.',
        ],
        'trigger' => [
            'en' => 'A camera upload arrives and the team has no storage left; sent once per pause.',
            'es' => 'Llega una subida de cámara y el equipo no tiene espacio; una vez por pausa.',
        ],
        'when' => [
            'en' => 'When the upload is rejected, queued',
            'es' => 'Cuando se rechaza la subida, en cola',
        ],
        'recipients' => [
            'en' => 'Team owner',
            'es' => 'Dueño del equipo',
        ],
        'fields' => [
            ['key' => 'Camera uploads paused: no storage left', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello :name,', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Your studio has used all of its storage, so photos sent from the camera are being discarded.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Used: :used of :total.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Free up space or upgrade your plan to resume camera uploads.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'See plans', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'Best regards, Picstome Team', 'label' => 'Salutation', 'type' => 'text'],
        ],
    ],

    'login_link' => [
        'notification' => LoginLink::class,
        'label' => [
            'en' => 'Passwordless login link',
            'es' => 'Enlace de acceso sin contraseña',
        ],
        'description' => [
            'en' => 'Sends the single-use link used to log in without a password. Transactional emails also include a login link.',
            'es' => 'Envía el enlace de un solo uso para entrar sin contraseña. Los emails transaccionales también incluyen un enlace de acceso.',
        ],
        'trigger' => [
            'en' => 'The login screen requests an email access link.',
            'es' => 'La pantalla de login solicita un enlace de acceso por email.',
        ],
        'when' => [
            'en' => 'Immediately, queued',
            'es' => 'Inmediato, en cola',
        ],
        'recipients' => [
            'en' => 'The user',
            'es' => 'El usuario',
        ],
        'fields' => [
            ['key' => 'Your Picstome login link', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello :name,', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Click the button below to log in to your Picstome account.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Log in', 'label' => 'Button', 'type' => 'text'],
            ['key' => 'This link expires in 15 minutes. If you did not request it, you can ignore this email.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Best regards, Picstome Team', 'label' => 'Salutation', 'type' => 'text'],
            ['key' => 'You can also [log in directly](:url) without a password.', 'label' => 'Body', 'type' => 'textarea'],
        ],
    ],

    'payment_link_bizum_pending' => [
        'notification' => PaymentLinkBizumPendingForPhotographer::class,
        'label' => [
            'en' => 'Bizum payment pending',
            'es' => 'Pago por Bizum pendiente',
        ],
        'description' => [
            'en' => 'Tells the photographer that a client chose Bizum on a payment link and is waiting for confirmation.',
            'es' => 'Avisa al fotógrafo de que un cliente eligió Bizum en un enlace de pago y espera confirmación.',
        ],
        'trigger' => [
            'en' => 'A client submits the Bizum form of a payment link.',
            'es' => 'Un cliente envía el formulario de Bizum de un enlace de pago.',
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
            ['key' => 'New Bizum payment pending: :reference', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'New payment', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Customer', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Email', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Phone', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Description', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Amount', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Reference', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Confirm it from the payments page when the transfer arrives.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Open payments', 'label' => 'Button', 'type' => 'text'],
        ],
    ],

    'payment_link_bizum_pending_client' => [
        'notification' => PaymentLinkBizumPending::class,
        'label' => [
            'en' => 'Bizum instructions',
            'es' => 'Instrucciones de Bizum',
        ],
        'description' => [
            'en' => 'Sends the Bizum phone and reference to the client of a payment link.',
            'es' => 'Envía el teléfono y la referencia de Bizum al cliente de un enlace de pago.',
        ],
        'trigger' => [
            'en' => 'A client chooses Bizum on a payment link.',
            'es' => 'Un cliente elige Bizum en un enlace de pago.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'The client',
            'es' => 'El cliente',
        ],
        'fields' => [
            ['key' => 'Complete your payment with Bizum: :reference', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello!', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'Send the Bizum to complete your payment.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Description', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Amount', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Send the Bizum to this phone:', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Reference', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'The payment is confirmed as soon as the photographer checks the transfer.', 'label' => 'Body', 'type' => 'textarea'],
        ],
    ],

    'payment_link_bizum_confirmed' => [
        'notification' => PaymentLinkBizumConfirmed::class,
        'label' => [
            'en' => 'Bizum payment confirmed',
            'es' => 'Pago por Bizum confirmado',
        ],
        'description' => [
            'en' => 'Confirms to the client that the photographer checked the Bizum transfer of a payment link.',
            'es' => 'Confirma al cliente que el fotógrafo comprobó la transferencia Bizum de un enlace de pago.',
        ],
        'trigger' => [
            'en' => 'The photographer confirms a pending Bizum payment.',
            'es' => 'El fotógrafo confirma un pago por Bizum pendiente.',
        ],
        'when' => [
            'en' => 'Immediately',
            'es' => 'Inmediato',
        ],
        'recipients' => [
            'en' => 'The client',
            'es' => 'El cliente',
        ],
        'fields' => [
            ['key' => 'Payment confirmed: :reference', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'Hello!', 'label' => 'Greeting', 'type' => 'text'],
            ['key' => 'We have received your payment.', 'label' => 'Body', 'type' => 'textarea'],
            ['key' => 'Description', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Amount', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Reference', 'label' => 'Body', 'type' => 'text'],
            ['key' => 'Thank you!', 'label' => 'Body', 'type' => 'text'],
        ],
    ],

];
