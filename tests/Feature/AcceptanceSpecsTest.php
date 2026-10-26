<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * @return ProcessResult
 */
function runAcceptanceSpecs(string $root, array $arguments, string $input = '')
{
    return Process::path($root)
        ->env(['CLAUDE_PROJECT_DIR' => $root])
        ->input($input)
        ->run(['php', base_path('.claude/hooks/acceptance-specs.php'), ...$arguments]);
}

function guardInput(string $filePath): string
{
    return json_encode(['tool_name' => 'Edit', 'tool_input' => ['file_path' => $filePath]]);
}

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/acceptance-specs-'.uniqid();
    File::ensureDirectoryExists($this->root.'/.claude/plans/upload');
    File::ensureDirectoryExists($this->root.'/tests/Acceptance/Upload');
    File::ensureDirectoryExists($this->root.'/tests/Acceptance/Support');
    File::put($this->root.'/.claude/plans/upload/_plan.md', "# Plan: Upload\n\n## Status\nin_progress\n");
    File::put($this->root.'/tests/Acceptance/Upload/UploadTest.php', '<?php // scenario');
    File::put($this->root.'/tests/Acceptance/Support/Expectations.php', '<?php // expectations');
    File::put($this->root.'/tests/Acceptance/Support/Dsl.php', '<?php // helpers');
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

test('locked acceptance scenarios of active plans are unchanged', function () {
    $result = runAcceptanceSpecs(base_path(), ['verify']);

    expect($result->exitCode())->toBe(0, $result->errorOutput());
});

test('it locks the scenarios and shared expectations of a plan', function () {
    $result = runAcceptanceSpecs($this->root, ['lock', '.claude/plans/upload', 'tests/Acceptance/Upload']);

    $lock = json_decode(File::get($this->root.'/.claude/plans/upload/.acceptance-lock'), true);

    expect($result->exitCode())->toBe(0)
        ->and($lock['scenarios'])->toBe('tests/Acceptance/Upload')
        ->and(array_keys($lock['files']))->toBe([
            'tests/Acceptance/Upload/UploadTest.php',
            'tests/Acceptance/Support/Expectations.php',
        ]);
});

test('it refuses to lock a directory outside tests/Acceptance', function () {
    $result = runAcceptanceSpecs($this->root, ['lock', '.claude/plans/upload', 'tests/Feature']);

    expect($result->exitCode())->toBe(1)
        ->and(File::exists($this->root.'/.claude/plans/upload/.acceptance-lock'))->toBeFalse();
});

test('it blocks edits to locked files', function (string $path) {
    runAcceptanceSpecs($this->root, ['lock', '.claude/plans/upload', 'tests/Acceptance/Upload']);

    $result = runAcceptanceSpecs($this->root, ['guard'], guardInput($this->root.'/'.$path));

    expect($result->exitCode())->toBe(2)
        ->and($result->errorOutput())->toContain('locked');
})->with([
    'scenario' => 'tests/Acceptance/Upload/UploadTest.php',
    'new file in scenario dir' => 'tests/Acceptance/Upload/AnotherTest.php',
    'shared expectations' => 'tests/Acceptance/Support/Expectations.php',
]);

test('it allows edits to helpers and application code', function (string $path) {
    runAcceptanceSpecs($this->root, ['lock', '.claude/plans/upload', 'tests/Acceptance/Upload']);

    $result = runAcceptanceSpecs($this->root, ['guard'], guardInput($this->root.'/'.$path));

    expect($result->exitCode())->toBe(0);
})->with([
    'dsl helpers' => 'tests/Acceptance/Support/Dsl.php',
    'app code' => 'app/Models/Recipe.php',
    'similarly named dir' => 'tests/Acceptance/UploadExtra/UploadTest.php',
]);

test('it stops guarding once the plan is completed', function () {
    runAcceptanceSpecs($this->root, ['lock', '.claude/plans/upload', 'tests/Acceptance/Upload']);
    File::put($this->root.'/.claude/plans/upload/_plan.md', "# Plan: Upload\n\n## Status\ncompleted\n");

    $result = runAcceptanceSpecs($this->root, ['guard'], guardInput($this->root.'/tests/Acceptance/Upload/UploadTest.php'));

    expect($result->exitCode())->toBe(0);
});

test('verify fails when a locked file changes or disappears', function () {
    runAcceptanceSpecs($this->root, ['lock', '.claude/plans/upload', 'tests/Acceptance/Upload']);
    File::put($this->root.'/tests/Acceptance/Upload/UploadTest.php', '<?php // weakened');
    File::delete($this->root.'/tests/Acceptance/Support/Expectations.php');

    $result = runAcceptanceSpecs($this->root, ['verify']);

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('changed: tests/Acceptance/Upload/UploadTest.php')
        ->and($result->errorOutput())->toContain('missing: tests/Acceptance/Support/Expectations.php');
});

test('verify passes when locked files are untouched', function () {
    runAcceptanceSpecs($this->root, ['lock', '.claude/plans/upload', 'tests/Acceptance/Upload']);

    expect(runAcceptanceSpecs($this->root, ['verify'])->exitCode())->toBe(0);
});
