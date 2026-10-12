<?php
declare(strict_types=1);

namespace App\Service;

use App\Security;

/**
 * Qui consulte le sondage : un gestionnaire (créateur via son lien admin ou administrateur du site)
 * ou un visiteur, avec les jetons de vote mémorisés dans son navigateur.
 */
final class AccessContext
{
    /** @param array<int, string> $ownedTokens vote_id => jeton de modification */
    public function __construct(
        public readonly bool $manager = false,
        public readonly array $ownedTokens = [],
    ) {
    }

    public function owns(array $vote): bool
    {
        $token = $this->ownedTokens[(int) $vote['id']] ?? null;
        return is_string($token) && hash_equals((string) $vote['edit_token_hash'], Security::hash($token));
    }
}
