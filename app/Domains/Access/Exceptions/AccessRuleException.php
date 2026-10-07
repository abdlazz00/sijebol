<?php

namespace App\Domains\Access\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis akses; pesannya ditampilkan ke pengguna. */
class AccessRuleException extends RuntimeException {}
