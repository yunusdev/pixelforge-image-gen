<?php

namespace App\Pixelforge;

/**
 * Pixelforge refused the request outright (content policy, validation, daily limit, ...).
 * Sending it again won't help.
 */
final class PermanentError extends PixelforgeError {}
