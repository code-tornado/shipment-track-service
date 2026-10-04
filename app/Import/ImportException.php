<?php

namespace App\Import;

use RuntimeException;

/** The file as a whole cannot be imported (unreadable, header row missing…). */
class ImportException extends RuntimeException {}
