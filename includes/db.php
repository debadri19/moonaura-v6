<?php
/* ===================================================================
   DATABASE CONNECTION (PDO)
   -------------------------------------------------------------------
   Provides one shared PDO connection for the whole project.

   Usage in any page:

       require_once __DIR__ . '/../includes/db.php';
       $pdo = db();

       $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
       $stmt->execute([$productId]);
       $product = $stmt->fetch();

   PDO + prepared statements are used everywhere in this project to
   protect against SQL Injection (see README security requirements).
=================================================================== */

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    // A static variable keeps the SAME connection for the whole
    // request instead of opening a new one every time db() is called.
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        // Throw exceptions on errors instead of failing silently.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        // Return rows as associative arrays by default, e.g. $row['name'].
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Use real prepared statements (safer against SQL injection
        // than PHP emulating them).
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Never show raw database errors (or credentials) to visitors.
        if (ENVIRONMENT === 'development') {
            die('Database connection failed: ' . $e->getMessage());
        }
        die('Something went wrong. Please try again later.');
    }

    return $pdo;
}
