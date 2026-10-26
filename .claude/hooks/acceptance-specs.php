<?php

declare(strict_types=1);

/**
 * Keeps approved acceptance scenarios out of the agents' reach during an hcf run.
 *
 *   guard                       PreToolUse hook: exit 2 (block) on edits to files locked by an active plan.
 *   lock <plan-dir> <scenarios> Record hashes of a plan's scenario files and the shared expectations.
 *   verify                      Exit 1 when a locked file of an active plan changed or disappeared.
 *
 * A plan is active until its _plan.md status is "completed". Paths are relative to the project root.
 */
const PLANS_DIR = '.claude/plans'; // ponytail: hcf's default; read .claude/hcf.json if plansDir is ever configured
const ACCEPTANCE_DIR = 'tests/Acceptance';
const EXPECTATIONS = 'tests/Acceptance/Support/Expectations.php';

$root = rtrim(getenv('CLAUDE_PROJECT_DIR') ?: getcwd(), '/');

/**
 * @return list<array{plan: string, scenarios: string, files: array<string, string>}>
 */
function activeLocks(string $root): array
{
    $locks = [];

    foreach (glob($root.'/'.PLANS_DIR.'/*/.acceptance-lock') ?: [] as $lockFile) {
        $plan = (string) @file_get_contents(dirname($lockFile).'/_plan.md');

        if (preg_match('/^## Status\s*\n\s*completed\b/m', $plan) === 1) {
            continue;
        }

        $lock = json_decode((string) file_get_contents($lockFile), true);
        $locks[] = ['plan' => basename(dirname($lockFile)), 'scenarios' => $lock['scenarios'], 'files' => $lock['files']];
    }

    return $locks;
}

function relativePath(string $root, string $path): string
{
    $path = str_starts_with($path, $root.'/') ? substr($path, strlen($root) + 1) : $path;

    return preg_replace('#^\./#', '', $path);
}

function guard(string $root): int
{
    $input = json_decode((string) stream_get_contents(STDIN), true);
    $path = $input['tool_input']['file_path'] ?? $input['tool_input']['notebook_path'] ?? '';
    $target = relativePath($root, (string) $path);

    foreach (activeLocks($root) as $lock) {
        if (isset($lock['files'][$target]) || str_starts_with($target, $lock['scenarios'].'/')) {
            fwrite(STDERR, "{$target} is locked by the acceptance scenarios of plan \"{$lock['plan']}\". "
                .'They are the human-approved spec: do not change them. If a scenario looks wrong, stop and report '
                ."`TASK_FAILED: spec conflict` with the scenario and the reason.\n");

            return 2;
        }
    }

    return 0;
}

function lock(string $root, string $planDir, string $scenarios): int
{
    $scenarios = rtrim(relativePath($root, $scenarios), '/');

    if (! str_starts_with($scenarios, ACCEPTANCE_DIR.'/') || ! is_dir($root.'/'.$scenarios)) {
        fwrite(STDERR, 'Scenario directory must be an existing directory under '.ACCEPTANCE_DIR."/: {$scenarios}\n");

        return 1;
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$scenarios, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $paths[] = relativePath($root, $file->getPathname());
    }

    sort($paths);

    if (is_file($root.'/'.EXPECTATIONS)) {
        $paths[] = EXPECTATIONS;
    }

    $files = [];

    foreach ($paths as $path) {
        $files[$path] = hash_file('sha256', $root.'/'.$path);
    }

    $lock = json_encode(['scenarios' => $scenarios, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($root.'/'.relativePath($root, $planDir).'/.acceptance-lock', $lock."\n");
    echo 'Locked '.count($files)." file(s):\n  ".implode("\n  ", array_keys($files))."\n";

    return 0;
}

function verify(string $root): int
{
    $problems = [];

    foreach (activeLocks($root) as $lock) {
        foreach ($lock['files'] as $path => $hash) {
            if (! is_file($root.'/'.$path)) {
                $problems[] = "missing: {$path} (plan {$lock['plan']})";
            } elseif (hash_file('sha256', $root.'/'.$path) !== $hash) {
                $problems[] = "changed: {$path} (plan {$lock['plan']})";
            }
        }
    }

    if ($problems !== []) {
        fwrite(STDERR, "Locked acceptance scenarios were modified:\n  ".implode("\n  ", $problems)."\n");

        return 1;
    }

    return 0;
}

exit(match ($argv[1] ?? '') {
    'guard' => guard($root),
    'lock' => isset($argv[2], $argv[3]) ? lock($root, $argv[2], $argv[3]) : 1,
    'verify' => verify($root),
    default => (function (): int {
        fwrite(STDERR, "Usage: acceptance-specs.php guard | lock <plan-dir> <scenario-dir> | verify\n");

        return 1;
    })(),
});
