<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Blade;

const BOOST_PATH = __DIR__.'/../../resources/boost';

it('has exactly one boost guideline', function () {
    expect(glob(BOOST_PATH.'/guidelines/*'))->toBe([BOOST_PATH.'/guidelines/core.blade.php']);
});

it('renders the guideline with Blade', function () {
    $source = (string) file_get_contents(BOOST_PATH.'/guidelines/core.blade.php');

    expect(Blade::render($source))->toBe($source);
});

it('has valid skill frontmatter', function (string $skillFile) {
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($skillFile), $matches);

    expect($matches)->not->toBeEmpty('Missing frontmatter in '.$skillFile);

    // Boost parses the frontmatter with symfony/yaml
    $frontmatter = Yaml::parse($matches[1]);

    expect($frontmatter['name'] ?? null)->toBe(basename(dirname($skillFile)))
        ->and(strlen($frontmatter['description'] ?? ''))->toBeGreaterThan(0)->toBeLessThanOrEqual(1024);
})->with(fn () => glob(BOOST_PATH.'/skills/*/SKILL.md'));

it('only links to existing files in skills', function (string $file) {
    preg_match_all('/\]\((?!https?:)([^)#]+)(?:#[^)]*)?\)/', (string) file_get_contents($file), $links);

    expect($file)->toBeReadableFile();

    foreach ($links[1] as $link) {
        expect(dirname($file).'/'.$link)->toBeReadableFile();
    }
})->with(fn () => [
    ...glob(BOOST_PATH.'/skills/*/SKILL.md'),
    ...glob(BOOST_PATH.'/skills/*/references/*.md'),
]);

it('links to the main branch on GitHub', function (string $file) {
    preg_match_all('#https://(?:raw\.githubusercontent\.com|github\.com)/TobyMaxham/laravel-dkim/(?:blob/)?([^/]+)/#', (string) file_get_contents($file), $branches);

    expect(array_unique($branches[1]))->toBe(['main']);
})->with([
    __DIR__.'/../../UPGRADING.md',
    __DIR__.'/../../CHANGELOG.md',
    BOOST_PATH.'/skills/laravel-dkim-upgrade/SKILL.md',
    BOOST_PATH.'/skills/laravel-dkim-upgrade/references/3.x-to-4.0.md',
    BOOST_PATH.'/skills/laravel-dkim-setup/SKILL.md',
]);
