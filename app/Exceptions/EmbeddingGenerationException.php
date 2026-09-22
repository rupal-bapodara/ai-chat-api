<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an EmbeddingProviderInterface implementation fails to
 * produce a vector for a given piece of text (upstream API failure,
 * malformed response, etc).
 */
class EmbeddingGenerationException extends RuntimeException {}
