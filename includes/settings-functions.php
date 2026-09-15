<?php
/* ===================================================================
   SETTINGS FUNCTIONS
   -------------------------------------------------------------------
   Generic key-value config, backed by the "settings" table. Used by
   PaymentManager to decide which gateway is active (instead of a
   hardcoded constant), and by whether Cash on Delivery is offered -
   but this file itself knows nothing about payments; it's a general
   read/write pair any future feature can reuse.
=================================================================== */

require_once __DIR__ . '/db.php';


/* ==========================================
   GET A SETTING
   Returns $default if the key doesn't exist -
   and ALSO if the settings table itself can't
   be read (e.g. an older database that hasn't
   had migration_phase3a_payments.sql applied
   yet). A missing/misconfigured settings store
   should degrade to safe defaults, not take
   checkout down with a fatal PDO exception.
========================================== */

function get_setting(string $key, ?string $default = null): ?string
{
    try {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);

        $value = $stmt->fetchColumn();

        return ($value !== false) ? $value : $default;

    } catch (PDOException $e) {
        // Log for the admin/developer, but never let a settings read
        // fail the page - callers always get a usable default back.
        error_log('get_setting(\'' . $key . '\') failed, falling back to default: ' . $e->getMessage());

        return $default;
    }
}


/* ==========================================
   SET A SETTING
   Creates the key if it doesn't exist yet,
   updates it if it does. This is the function
   a future Admin Settings page would call -
   nothing else needs to change when that page
   is built.
========================================== */

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}
