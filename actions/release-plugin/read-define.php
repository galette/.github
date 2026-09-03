<?php

/**
 * Reads the metadata a Galette plugin declares in its _define.php, and prints it
 * as JSON: {"version": "2.2.1", "compver": "1.3.0"}.
 *
 * Usage: php read-define.php <path to a _define.php>
 *
 * Why PHP and not a regex: _define.php is a PHP file included in the context of
 * Galette's Plugins object, and the plugins in the wild do not agree on shape.
 * They pass named arguments — except plugin-openidc, which passes them
 * positionally, with no name to match — and their trailing comments differ:
 * objectslend writes "//Galette version compatibility" where every other plugin
 * writes "//Galette compatible version". Replaying the real signature reads all
 * of them, and keeps working whatever a plugin author writes in a comment.
 *
 * The signature below mirrors Galette\Core\Plugins::register(). Should it ever
 * gain a parameter, an older _define.php still loads: the caller passes what it
 * knows, and PHP fills the rest from the defaults.
 *
 * SPDX-FileCopyrightText: Copyright © 2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

final class DefineReader
{
    /** @var array<string,?string> */
    private array $meta = [];

    /**
     * The subset of Plugins::register() this needs. Named exactly as the real
     * one, because most plugins call it with named arguments.
     *
     * @param ?array<string,string> $acls
     */
    public function register(
        string $name,
        string $desc,
        string $author,
        string $version,
        ?string $compver = null,
        ?string $route = null,
        ?string $date = null,
        ?array $acls = null,
        ?int $priority = 1000,
        ?float $dbver = null
    ): void {
        $this->meta = ['version' => $version, 'compver' => $compver];
    }

    /**
     * Absorbs whatever else a _define.php calls on $this — paypal and openidc
     * call setCsrfExclusions(), and nothing says another plugin will not reach
     * for a different method of Plugins.
     *
     * @param array<mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        return null;
    }

    /** @return array<string,?string> */
    public function read(string $file): array
    {
        include $file;
        return $this->meta;
    }
}

if ($argc !== 2) {
    fwrite(STDERR, "usage: php read-define.php <_define.php>\n");
    exit(2);
}

$file = $argv[1];
if (!is_file($file)) {
    fwrite(STDERR, sprintf("%s does not exist\n", $file));
    exit(2);
}

// A _define.php that throws, or one whose register() call no longer matches the
// signature above, must not read as "this plugin declares nothing" — the caller
// has to be able to tell the two apart.
//
// Galette's own constants are deliberately NOT defined here. A plugin writing
// `compver: GALETTE_COMPAT_VERSION` — as two test fixtures of the core do, and no
// published plugin does — would claim compatibility with whatever Galette it
// happens to run on, which is the opposite of what the value is for. Inventing a
// value for the constant would turn that mistake into a number published as fact,
// so such a file is reported as unreadable instead.
try {
    $meta = (new DefineReader())->read($file);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("%s could not be read: %s\n", $file, $e->getMessage()));
    exit(1);
}

if (!isset($meta['version'])) {
    fwrite(STDERR, sprintf("%s calls no register()\n", $file));
    exit(1);
}

echo json_encode($meta), "\n";
