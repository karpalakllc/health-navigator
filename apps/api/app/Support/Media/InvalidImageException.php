<?php

namespace App\Support\Media;

use RuntimeException;

/**
 * The upload itself is unacceptable (undecodable, or too many pixels) — a
 * client error, as opposed to a storage or encoding failure on our side.
 * Extends RuntimeException so existing callers that catch that still work.
 */
class InvalidImageException extends RuntimeException {}
