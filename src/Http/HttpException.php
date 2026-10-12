<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Interrompt le traitement d'une requête avec un code HTTP.
 * Le message est une clé de traduction affichée à l'utilisateur.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $messageKey = '',
        public readonly ?string $location = null,
    ) {
        parent::__construct($messageKey);
    }

    public static function redirect(string $location): self
    {
        return new self(303, '', $location);
    }
}
