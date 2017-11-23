<?php declare(strict_types = 1);
namespace Templado\Engine;

use DOMNode;
use DOMNodeList;
use Iterator;

/**
 * Iterating over a DOMNodeList in PHP does not work when the list
 * changes during the iteration process. This Wrapper around NodeList
 * takes a snapshot of the list first and then turns that into an
 * iterator.
 */
class SnapshotDOMNodelist implements Iterator {

    /**
     * @var DOMNode[]
     */
    private $items = [];

    private $pos = 0;

    public function __construct(DOMNodeList $list) {
        $this->extractItemsFromNodeList($list);
    }

    public function hasNode(DOMNode $node) {
        foreach($this->items as $pos => $item) {
            if ($item->isSameNode($node)) {
                return true;
            }
        }
        return false;
    }

    public function removeNode(DOMNode $node) {
        foreach($this->items as $pos => $item) {
            if ($item->isSameNode($node)) {
                array_splice($this->items, $pos, 1);
                return;
            }
        }
        throw new SnapshotDOMNodelistException('Node not found in list');
    }

    public function current() {
        return $this->items[$this->pos];
    }

    public function next() {
        $this->pos++;
    }

    public function key() {
        return $this->pos;
    }

    public function valid() {
        return count($this->items) > $this->pos;
    }

    public function rewind() {
        $this->pos = 0;
    }

    private function extractItemsFromNodeList(DOMNodeList $list) {
        foreach($list as $item) {
            $this->items[] = $item;
        }
    }
}