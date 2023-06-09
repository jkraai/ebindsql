<?php

// declare(strict_types=1);
//
// use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
// use Rector\Config\RectorConfig;
// use Rector\Set\ValueObject\LevelSetList;
//
// return static function (RectorConfig $rectorConfig): void {
//     $rectorConfig->paths([
//         __DIR__ . '/DocGenerator.1.1_[RTF_PDF]',
//         __DIR__ . '/FCKeditor',
//         __DIR__ . '/MP',
//         __DIR__ . '/Rector',
//         __DIR__ . '/admin',
//         __DIR__ . '/autosave',
//         __DIR__ . '/document_generator',
//         __DIR__ . '/excel',
//         __DIR__ . '/input_filter',
//         __DIR__ . '/mac_install',
//         __DIR__ . '/mobile',
//         __DIR__ . '/queries',
//         __DIR__ . '/queues',
//         __DIR__ . '/rtf',
//         __DIR__ . '/server-scripts',
//         __DIR__ . '/speller',
//         __DIR__ . '/text',
//     ]);
//
//     // register a single rule
//     $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
//
//     // define sets of rules
//     //    $rectorConfig->sets([
//     //        LevelSetList::UP_TO_PHP_74
//     //    ]);
// };


/*
php ../rector/bin/rector \
    --config rector.php \
    --clear-cache process \
    --dry-run \
    tmp/test_RemoveAlwaysElseRector.php

*/

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\SetList;
use Rector\Set\ValueObject\LevelSetList;

use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\CodingStyle\Rector\FuncCall\ConsistentPregDelimiterRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

// for rule skipping
use Rector\CodingStyle\Encapsed\EncapsedStringsToSprintfRector;

return static function (RectorConfig $rectorConfig): void {
    // use composer-installed rector
    //     $ php vendor/bin/rector --config ./rector.php process . --dry-run > tmp/rector.diff
    // use externally installed rector
    //     $ php ../rector/bin/rector --config ./rector.php process . --dry-run > tmp/rector.diff


    // register single rule
//    $rectorConfig->rule(TypedPropertyFromStrictConstructorRector::class);

    // current version
//    $rectorConfig->phpVersion(PhpVersion::PHP_53);

    // disable parallel processing due to bug that acts like a memory leak
    https://getrector.com/documentation/troubleshooting-parallel
    $rectorConfig->disableParallel();

    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets with your IDE
    $rectorConfig->sets([
//        SetList::PHP_70,
//        SetList::PHP_56,
//        SetList::PHP_55,
//        SetList::PHP_54,
//        SetList::PHP_53,
        LevelSetList::UP_TO_PHP_74,
        // there are bnorrell@(telligen & ifmc) email addresses.  most need to go away.  a couple need to go to dpowers
        // __soapCall method needs to become soapCall
        // __getLastResponseHeaders
//        SetList::PHP_72,
//        SetList::PHP_74,
//        SetList::PHP_80,
//        SetList::TYPE_DECLARATION,  // save this for 8.0 or NEVER if it's not required
//        SetList::PHP_82,

        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,

        SetList::EARLY_RETURN,
        SetList::DEAD_CODE,
    ]);

//    $rectorConfig->fileExtensions(['php', 'phtml']);

//    $rectorConfig->paths([
//        __DIR__,
//    ]);

    // how to skip files &/or folders
    $rectorConfig->skip([
//        __DIR__ . '/src/SingleFile.php',
//        getcwd() . '/vendor',

        // or use fnmatch
//        __DIR__  . '/vendor',
//        getcwd() . '/vendor',
//        __DIR__  . '/vendor/*',
//        getcwd() . '/vendor/*',
//        __DIR__  . '/vendor/**',
//        getcwd() . '/vendor/**',
//        __DIR__  . '/vendor/**/*',
//        getcwd() . '/vendor/**/*',
        // how to skip rules
        EncapsedStringsToSprintfRector::class,
        LongArrayToShortArrayRector::class,
        NewlineAfterStatementRector::class,
        PostIncDecToPreIncDecRector::class,
        RemoveDeadIfForeachForRector::class,
//        RemoveDeadIfForeachForRector::class => [
//            __DIR__ . '/**/test_RemoveAlwaysElseRector.php',
//        ],
        RemoveDeadLoopRector::class,
//        RemoveDeadLoopRector::class => [
//            __DIR__ . '/tmp/test_RemoveAlwaysElseRector.php',
//        ],
        RemoveDeadZeroAndOneOperationRector::class,
        RemoveSoleValueSprintfRector::class,
        RemoveUnusedVariableAssignRector::class,
        SimplifyUselessVariableRector::class,
        UnwrapSprintfOneArgumentRector::class,
        VarConstantCommentRector::class,
    ]);

    // default target delimiter is '#', which i don't like at all
    // changing it to "normal" '/'
    $rectorConfig->ruleWithConfiguration(ConsistentPregDelimiterRector::class, [
        ConsistentPregDelimiterRector::DELIMITER => '/',
    ]);

};
