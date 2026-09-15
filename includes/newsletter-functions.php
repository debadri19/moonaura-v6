<?php
/* ===================================================================
   NEWSLETTER SUBSCRIPTION (Brevo Contacts API)
   -------------------------------------------------------------------
   Homepage signup only. Creates or updates a contact on the pending
   list. Confirmation mail and the move to the confirmed list are
   handled by a Brevo Automation. No local subscriber table. Does not
   send mail through includes/mailer.php.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function newsletter_is_configured(): bool
{
    return BREVO_API_KEY !== ''
        && (int) BREVO_NEWSLETTER_PENDING_LIST_ID > 0
        && (int) BREVO_NEWSLETTER_LIST_ID > 0;
}

function newsletter_subscribe(string $email): string
{
    $email = normalize_email($email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'invalid';
    }

    if (!newsletter_is_configured()) {
        error_log('newsletter_subscribe: Brevo Contacts API is not configured');
        return 'error';
    }

    $existing = newsletter_get_contact($email);
    $httpStatus = (int) ($existing['status'] ?? 0);

    if ($httpStatus === 200) {
        return newsletter_subscribe_existing($email, $existing['body'] ?? []);
    }

    if ($httpStatus === 404) {
        return newsletter_create_pending_contact($email);
    }

    newsletter_log_brevo_failure($httpStatus, $existing);
    return 'error';
}

function newsletter_subscribe_existing(string $email, array $contact): string
{
    $listIds = newsletter_contact_list_ids($contact);

    if (in_array((int) BREVO_NEWSLETTER_LIST_ID, $listIds, true)) {
        return 'already';
    }

    if (in_array((int) BREVO_NEWSLETTER_PENDING_LIST_ID, $listIds, true)) {
        return 'pending';
    }

    $result = newsletter_brevo_request(
        'PUT',
        '/contacts/' . rawurlencode($email),
        ['listIds' => [(int) BREVO_NEWSLETTER_PENDING_LIST_ID]]
    );

    $httpStatus = (int) ($result['status'] ?? 0);

    if (in_array($httpStatus, [200, 204], true)) {
        return 'pending';
    }

    newsletter_log_brevo_failure($httpStatus, $result);
    return 'error';
}

function newsletter_create_pending_contact(string $email): string
{
    $result = newsletter_brevo_request(
        'POST',
        '/contacts',
        [
            'email'         => $email,
            'listIds'       => [(int) BREVO_NEWSLETTER_PENDING_LIST_ID],
            'updateEnabled' => false,
        ]
    );

    $httpStatus = (int) ($result['status'] ?? 0);

    if ($httpStatus === 201) {
        return 'pending';
    }

    if ($httpStatus === 400 && newsletter_is_duplicate_response($result)) {
        $existing = newsletter_get_contact($email);

        if ((int) ($existing['status'] ?? 0) === 200) {
            return newsletter_subscribe_existing($email, $existing['body'] ?? []);
        }

        newsletter_log_brevo_failure((int) ($existing['status'] ?? 0), $existing);
        return 'error';
    }

    newsletter_log_brevo_failure($httpStatus, $result);
    return 'error';
}

function newsletter_get_contact(string $email): array
{
    return newsletter_brevo_request(
        'GET',
        '/contacts/' . rawurlencode($email)
    );
}

function newsletter_contact_list_ids(array $contact): array
{
    $listIds = $contact['listIds'] ?? [];

    if (!is_array($listIds)) {
        return [];
    }

    return array_map('intval', $listIds);
}

function newsletter_is_duplicate_response(array $result): bool
{
    $body = $result['body'] ?? [];
    if (!is_array($body)) {
        return false;
    }

    $code    = strtolower(trim((string) ($body['code'] ?? '')));
    $message = strtolower(trim((string) ($body['message'] ?? '')));

    if ($code === 'duplicate_parameter') {
        return true;
    }

    if ($message === '') {
        return false;
    }

    if (str_contains($message, 'does not exist')) {
        return false;
    }

    return str_contains($message, 'contact already')
        || str_contains($message, 'already exist');
}

function newsletter_log_brevo_failure(int $httpStatus, array $result): void
{
    $body    = is_array($result['body'] ?? null) ? $result['body'] : [];
    $code    = (string) ($body['code'] ?? '');
    $message = (string) ($body['message'] ?? '');

    error_log(
        'newsletter_subscribe: Brevo request failed'
        . ' HTTP=' . $httpStatus
        . ' code=' . $code
        . ' message=' . $message
        . ' pendingListId=' . (int) BREVO_NEWSLETTER_PENDING_LIST_ID
        . ' listId=' . (int) BREVO_NEWSLETTER_LIST_ID
    );
}

function newsletter_brevo_request(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.brevo.com/v3' . $path);

    $headers = [
        'accept: application/json',
        'api-key: ' . BREVO_API_KEY,
    ];

    $options = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ];

    if ($body !== null) {
        $headers[] = 'content-type: application/json';
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $options);

    $responseBody = curl_exec($ch);
    $httpStatus   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        error_log('newsletter_brevo_request: network error');
        return ['status' => 0, 'body' => []];
    }

    $decoded = json_decode((string) $responseBody, true);

    return [
        'status' => $httpStatus,
        'body'   => is_array($decoded) ? $decoded : [],
    ];
}
