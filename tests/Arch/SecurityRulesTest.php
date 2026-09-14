<?php

declare(strict_types=1);

/*
 * CI gate for the CLAUDE.md security rules. These fail the build — they are not warnings.
 * Each rule maps to a gap in the business guideline, chapter 5.
 */

arch('php preset: no debugging leftovers or unsafe functions')
    ->preset()->php();

arch('security preset: no eval, md5/sha1 passwords, unserialize, shell_exec…')
    ->preset()->security();

arch('strict types everywhere in app/')
    ->expect('App')
    ->toUseStrictTypes();

arch('env() is only read inside config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('controllers never touch the database facade directly')
    ->expect('Illuminate\Support\Facades\DB')
    ->not->toBeUsedIn('App\Http\Controllers');

/**
 * Source-level rules Pest arch can't express. Each entry: [regex, why].
 *
 * @return array<string, array{0: string, 1: string}>
 */
function forbiddenPatterns(): array
{
    return [
        'guarded-empty' => ['/\$guarded\s*=\s*\[\s*\]/', 'Gap 03 mass assignment: use $fillable and $request->validated().'],
        'unguard' => ['/Model::unguard\s*\(|->unguard\s*\(/', 'Gap 03 mass assignment: never unguard models.'],
        'request-all' => ['/\$request->all\s*\(|request\(\)->all\s*\(/', 'Gap 03/09: use $request->validated() (and never log the whole request).'],
        'raw-with-variable' => ['/(DB::raw|selectRaw|whereRaw|orWhereRaw|havingRaw|orderByRaw|groupByRaw)\s*\(\s*["\'][^"\']*\$|(DB::raw|selectRaw|whereRaw|orWhereRaw|havingRaw|orderByRaw|groupByRaw)\s*\(\s*\$/', 'Gap 06 SQL injection: use bound parameters and a column allowlist.'],
        'amount-from-request' => ['/[\'"](amount|price|total)[\'"]\s*=>\s*\$request->/', 'Gap 02: never take prices or amounts from the client — recalculate on the server.'],
        'float-cast-money' => ['/[\'"][a-z_]*(price|amount|total|weight|cents)[a-z_]*[\'"]\s*=>\s*[\'"](float|double|real)[\'"]/', 'Rule 01: money is integer cents (MoneyCast), weight is DECIMAL(10,3) (WeightCast).'],
        'client-original-name' => ['/getClientOriginalName\s*\(/', 'Gap 07 uploads: store with a random filename, never the client’s.'],
        'debug-dump' => ['/\b(dd|dump|ray|var_dump|print_r)\s*\(/', 'No debug output left in application code.'],
    ];
}

/** @return list<string> */
function applicationPhpFiles(): array
{
    $root = dirname(__DIR__, 2);
    $files = [];
    foreach (['app', 'routes', 'database'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('keeps forbidden patterns out of app code', function (string $pattern, string $why) {
    $root = dirname(__DIR__, 2);
    $hits = [];
    foreach (applicationPhpFiles() as $file) {
        foreach (file($file) as $n => $line) {
            if (str_contains($line, 'security-rules: allow')) {
                continue;   // must be justified in a code review comment on the same line
            }
            if (preg_match($pattern, $line)) {
                $hits[] = substr($file, strlen($root) + 1).':'.($n + 1).'  '.trim($line);
            }
        }
    }

    expect($hits)->toBeEmpty($why."\n".implode("\n", $hits));
})->with(forbiddenPatterns());

it('declares $fillable on every Eloquent model', function () {
    $missing = [];
    foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') as $file) {
        $src = file_get_contents($file);
        if (preg_match('/extends\s+(Model|Authenticatable|Pivot)\b/', $src) && ! str_contains($src, 'protected $fillable')) {
            $missing[] = basename($file);
        }
    }

    expect($missing)->toBeEmpty('Every model needs an explicit $fillable list: '.implode(', ', $missing));
});
