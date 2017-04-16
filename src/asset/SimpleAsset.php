<?php declare(strict_types = 1);
namespace Templado\Engine;

use DOMElement;
use DOMNode;

class SimpleAsset implements Asset {

    /** @var DOMNode */
    private $content;

    /**
     * @var string
     */
    private $targetId;

    /**
     * @param string  $targetId
     * @param DOMNode $content
     *
     * @internal param bool $replace
     */
    public function __construct(string $targetId, DOMNode $content) {
        $this->targetId = $targetId;
        $this->content  = $content;
    }

    /**
     * @return string
     */
    public function getTargetId(): string {
        return $this->targetId;
    }

    /**
     * @param DOMElement $node
     */
    public function applyTo(DOMElement $node) {
        $content = $node->ownerDocument->importNode($this->content, true);

        if ($this->shouldReplace($node, $content)) {
            $node->parentNode->replaceChild($content, $node);

            return;
        }

        $node->appendChild($content);
    }

    /**
     * @param DOMElement $node
     * @param DOMNode    $content
     *
     * @return bool
     */
    private function shouldReplace(DOMElement $node, DOMNode $content): bool {
        if (!$content instanceof DOMElement) {
            return false;
        }

        if (!$node->hasAttribute('id') || !$content->hasAttribute('id')) {
            return false;
        }

        return $node->getAttribute('id') === $content->getAttribute('id');
    }

}
