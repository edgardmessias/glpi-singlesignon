<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2026 Edgard
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2026 Edgard
 * @copyright Copyright (C) 2006-2022 by Example plugin team.
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * @link      https://github.com/pluginsGLPI/example
 * ---------------------------------------------------------------------
 */

require_once __DIR__ . '/../../src/Plugin.php';

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector as CodeQuality;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector as DeadCode;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/front',
        __DIR__ . '/src',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withCache(
        cacheClass: FileCacheStorage::class,
        cacheDirectory: sys_get_temp_dir() . '/singlesignon-rector',
    )
    ->withRootFiles()
    ->withParallel(timeoutSeconds: 300)
    ->withImportNames(removeUnusedImports: true)
    ->withRules([
        CodeQuality\Assign\CombinedAssignRector::class,
        CodeQuality\BooleanAnd\RemoveUselessIsObjectCheckRector::class,
        CodeQuality\BooleanAnd\SimplifyEmptyArrayCheckRector::class,
        CodeQuality\BooleanNot\ReplaceMultipleBooleanNotRector::class,
        CodeQuality\Catch_\ThrowWithPreviousExceptionRector::class,
        CodeQuality\Empty_\SimplifyEmptyCheckOnEmptyArrayRector::class,
        CodeQuality\Expression\InlineIfToExplicitIfRector::class,
        CodeQuality\Expression\TernaryFalseExpressionToIfRector::class,
        CodeQuality\For_\ForRepeatedCountToOwnVariableRector::class,
        CodeQuality\Foreach_\ForeachItemsAssignToEmptyArrayToAssignRector::class,
        CodeQuality\Foreach_\ForeachToInArrayRector::class,
        CodeQuality\Foreach_\SimplifyForeachToCoalescingRector::class,
        CodeQuality\Foreach_\UnusedForeachValueToArrayKeysRector::class,
        CodeQuality\FuncCall\ChangeArrayPushToArrayAssignRector::class,
        CodeQuality\FuncCall\CompactToVariablesRector::class,
        CodeQuality\FuncCall\InlineIsAInstanceOfRector::class,
        CodeQuality\FuncCall\IsAWithStringWithThirdArgumentRector::class,
        CodeQuality\FuncCall\RemoveSoleValueSprintfRector::class,
        CodeQuality\FuncCall\SetTypeToCastRector::class,
        CodeQuality\FuncCall\SimplifyFuncGetArgsCountRector::class,
        CodeQuality\FuncCall\SimplifyInArrayValuesRector::class,
        CodeQuality\FuncCall\SimplifyStrposLowerRector::class,
        CodeQuality\FuncCall\UnwrapSprintfOneArgumentRector::class,
        CodeQuality\Identical\BooleanNotIdenticalToNotIdenticalRector::class,
        CodeQuality\Identical\SimplifyArraySearchRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\Identical\StrlenZeroToIdenticalEmptyStringRector::class,
        CodeQuality\If_\CombineIfRector::class,
        CodeQuality\If_\CompleteMissingIfElseBracketRector::class,
        CodeQuality\If_\ConsecutiveNullCompareReturnsToNullCoalesceQueueRector::class,
        CodeQuality\If_\ExplicitBoolCompareRector::class,
        CodeQuality\If_\ShortenElseIfRector::class,
        CodeQuality\If_\SimplifyIfElseToTernaryRector::class,
        CodeQuality\If_\SimplifyIfNotNullReturnRector::class,
        CodeQuality\If_\SimplifyIfNullableReturnRector::class,
        CodeQuality\If_\SimplifyIfReturnBoolRector::class,
        CodeQuality\Include_\AbsolutizeRequireAndIncludePathRector::class,
        CodeQuality\LogicalAnd\AndAssignsToSeparateLinesRector::class,
        CodeQuality\LogicalAnd\LogicalToBooleanRector::class,
        CodeQuality\NotEqual\CommonNotEqualRector::class,
        CodeQuality\Ternary\UnnecessaryTernaryExpressionRector::class,
        DeadCode\Assign\RemoveUnusedVariableAssignRector::class,
    ])
    ->withPhpSets(php74: true) // apply PHP sets up to PHP 7.4
;
