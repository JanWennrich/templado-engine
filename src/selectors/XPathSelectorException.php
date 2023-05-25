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

use Exception;

class XPathSelectorException extends Exception {
    public const InvalidExpression    = 1207;
    public const UnregisteredFunction = 1209;
    public const UndefinedNamespace   = 1219;
}
