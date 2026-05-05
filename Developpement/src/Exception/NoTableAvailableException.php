<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Erreur métier « indisponibilité » : plus de table libre pour le créneau, ou conflit concurrent
 * traduit en message utilisateur homogène (SPECS_FONCTIONNELLES §6.5–6.6, SPECS_TECHNIQUES §5).
 *
 * À attraper dans les contrôleurs pour afficher un flash ou une erreur de formulaire.
 */
final class NoTableAvailableException extends \RuntimeException
{
    /** Message affichable tel quel côté interface (éviter de divulguer le détail SQL). */
    public const DEFAULT_MESSAGE = 'Il n\'y a plus de table disponible pour ce créneau.';

    /**
     * @param string         $message  Texte utilisateur (par défaut {@see self::DEFAULT_MESSAGE}).
     * @param int            $code     Code d’erreur PHP (souvent 0).
     * @param \Throwable|null $previous Cause interne (ex. violation d’unicité) pour les logs.
     */
    public function __construct(string $message = self::DEFAULT_MESSAGE, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
