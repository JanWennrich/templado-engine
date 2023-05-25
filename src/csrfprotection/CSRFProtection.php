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

class CSRFProtection {
    /** @var string */
    private $fieldName;

    /** @var string */
    private $tokenValue;

    public function __construct(string $fieldName, string $tokenValue) {
        $this->fieldName  = $fieldName;
        $this->tokenValue = $tokenValue;
    }

    public function getFieldName(): string {
        return $this->fieldName;
    }

    public function getTokenValue(): string {
        return $this->tokenValue;
    }
}
