<?php declare(strict_types=1);
/*
 * This file is part of Templado\Engine.
 *
 * Copyright (c) Arne Blankerts <arne@blankerts.de> and contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Templado\Engine;

final class MergerException extends Exception {
    public const EmptyDocument = 1;
    public const EmptyList     = 2;
    public const DuplicateId   = 3;
}
