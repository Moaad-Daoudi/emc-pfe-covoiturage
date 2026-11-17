<?php

declare(strict_types=1);

namespace App\Exception;

final class SeatsNotAvailableException extends DomainException
{
    public function __construct(
        int $requested = 0,
        int $available = 0,
        ?string $segmentDetails = null,
        ?string $customMessage = null
    ) {
        if ($customMessage !== null) {
            parent::__construct($customMessage);
            return;
        }

        $message = sprintf('Places insuffisantes : %d demandée(s), mais seulement %d disponible(s).', $requested, $available);
        if ($segmentDetails) {
            $message .= ' (Tronçon concerné : ' . $segmentDetails . ')';
        }

        parent::__construct($message);
    }
}
