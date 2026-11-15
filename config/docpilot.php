<?php

/*
|--------------------------------------------------------------------------
| Paramètres métier DocPilot
|--------------------------------------------------------------------------
| Valeurs par défaut de la plateforme. Chaque entreprise pourra surcharger
| certaines d'entre elles (délais d'alerte, relances) via ses paramètres.
*/

return [

    'uploads' => [
        'disk' => env('DOCUMENTS_DISK', 'documents'),
        'max_kb' => (int) env('DOCPILOT_MAX_UPLOAD_KB', 10240), // 10 Mo
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],

    'max_upload_mb' => (int) env('DOCPILOT_MAX_UPLOAD_MB', 10),

    'branding' => [
        // Disque des logos d'entreprise : s3 en production, local en développement sans S3
        'disk' => env('BRANDING_DISK', 's3'),
    ],

    'documents' => [
        // Un document valide expirant dans moins de N jours passe en "bientôt expiré"
        'expiring_soon_days' => (int) env('DOCPILOT_EXPIRING_SOON_DAYS', 30),
        // Jalons d'alerte avant expiration (le 0 = le jour même)
        'expiration_alert_days' => [60, 30, 15, 7, 0],
        // Durée de validité d'une URL de téléchargement temporaire
        'download_url_minutes' => (int) env('DOCPILOT_DOWNLOAD_URL_MINUTES', 5),
    ],

    'portal' => [
        // Durée de validité par défaut d'un lien de dépôt sous-traitant
        'link_days' => (int) env('DOCPILOT_PORTAL_LINK_DAYS', 30),
        // Longueur du jeton (difficile à deviner)
        'token_length' => 48,
    ],

    'reminders' => [
        // Relances après la demande initiale (en jours)
        'after_request_days' => [7, 14],
        // Relance avant la date limite (en jours)
        'before_due_days' => [2],
    ],

    'completeness' => [
        // Seuils d'affichage du score de complétude (en %)
        'complete' => 100,
        'almost_complete' => 75,
        'incomplete' => 50,
    ],

    'plans' => [
        'mini' => ['label' => 'Mini', 'price_cents' => 590, 'max_users' => 1, 'max_subcontractors' => 5],
        'essentiel' => ['label' => 'Essentiel', 'price_cents' => 1490, 'max_users' => 5, 'max_subcontractors' => 25],
        'business' => ['label' => 'Business', 'price_cents' => 2990, 'max_users' => 15, 'max_subcontractors' => 100],
    ],

];
