<?php

namespace App\Exceptions;

/**
 * P6 — Code commercial saisi à la souscription d'un forfait inconnu, désactivé ou
 * appartenant au vendeur lui-même. Le message est affichable tel quel.
 */
class InvalidSalesCodeException extends \RuntimeException {}
