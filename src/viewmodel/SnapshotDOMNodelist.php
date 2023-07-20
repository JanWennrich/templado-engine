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

use function array_splice;
use function count;
use ArrayIterator;
use DOMNode;
use DOMNodeList;
use IteratorAggregate;
use Traversable;

/**
 * Iterating over a DOMNodeList in PHP does not work when the list
 * changes during the iteration process. This Wrapper around NodeList
 * takes a snapshot of the list first and then allows iterating over it.
 */
class SnapshotDOMNodelist implements IteratorAggregate {
    /** @var DOMNode[] */
    private $items = [];

    /** @var int */
    private $pos = 0;

    public function __construct(DOMNodeList $list) {
        $this->extractItemsFromNodeList($list);
    }

    public function hasNode(DOMNode $node): bool {
        foreach ($this->items as $pos => $item) {
            if ($item->isSameNode($node)) {
                return true;
            }
        }

        return false;
    }

    public function hasNext(): bool {
        $count = count($this->items);

        return $count > 0 && $this->pos < $count;
    }

    public function getNext(): DOMNode {
        $node = $this->current();
        $this->pos++;

        return $node;
    }

    public function removeNode(DOMNode $node): void {
        /** @psalm-var int $pos */
        foreach ($this->items as $pos => $item) {
            if ($item->isSameNode($node)) {
                array_splice($this->items, $pos, 1);

                if ($this->pos > 0 && $pos <= $this->pos) {
                    $this->pos--;
                }

                return;
            }
        }

        throw new SnapshotDOMNodelistException('Node not found in list');
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->items);
    }

    private function current(): DOMNode {
        if (!$this->valid()) {
            throw new SnapshotDOMNodelistException('No current node available');
        }

        return $this->items[$this->pos];
    }

    private function valid(): bool {
        $count = count($this->items);

        return $count > 0 && $count > $this->pos;
    }

    private function extractItemsFromNodeList(DOMNodeList $list): void {
        foreach ($list as $item) {
            $this->items[] = $item;
        }
    }
}
