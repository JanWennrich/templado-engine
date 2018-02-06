<?php declare(strict_types = 1);
namespace Templado\Engine;

use DOMAttr;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;

class ViewModelRenderer {

    /** @var array */
    private $stack;

    /** @var string[] */
    private $stackNames;

    /** @var SnapshotDOMNodelist[] */
    private $listStack;

    /**
     * @throws ViewModelRendererException
     */
    public function render(DOMNode $context, $model) {
        $this->stack = [$model];
        $this->stackNames = [];
        $this->listStack = [];
        $this->walk($context);
    }

    /**
     * @throws ViewModelRendererException
     */
    private function walk(DOMNode $context) {
        if (!$context instanceof DOMElement) {
            return;
        }

        $stackAdded = false;
        if ($context->hasAttribute('property')) {
            $this->addToStack($context);
            $stackAdded = true;
            $context = $this->applyCurrent($context);
        }
        if ($context->hasChildNodes()) {
            $list = new SnapshotDOMNodelist($context->childNodes);
            $this->listStack[] = $list;

            while($list->hasNext()) {
                $childNode = $list->getNext();
                /** @var \DOMNode $childNode */
                $this->walk($childNode);
            }
            array_pop($this->listStack);
        }

        if ($stackAdded) {
            $this->dropFromStack();
        }
    }

    /**
     * @throws ViewModelRendererException
     */
    private function addToStack(DOMElement $context) {
        $model = $this->current();
        $property = $context->getAttribute('property');

        $this->ensureIsObject($model, $property);

        $this->stackNames[] = $property;

        foreach([$property, 'get' . ucfirst($property)] as $method) {
            if (method_exists($model, $method)) {
                $this->stack[] = $model->{$method}($context->nodeValue);

                return;
            }
        }

        if (method_exists($model, '__call')) {
            $this->stack[] = $model->{$property}($context->nodeValue);

            return;
        }

        throw new ViewModelRendererException(
            sprintf('Viewmodel method missing: $model->%s', implode('()->', $this->stackNames) . '()')
        );
    }

    /**
     * @return mixed
     */
    private function current() {
        return end($this->stack);
    }

    private function dropFromStack() {
        array_pop($this->stack);
        array_pop($this->stackNames);
    }

    /**
     * @throws ViewModelRendererException
     */
    private function applyCurrent(DOMElement $context): DOMNode {
        $model = $this->current();
        switch (\gettype($model)) {
            case 'boolean': {
                return $this->processBoolean($context, $model);
            }
            case 'string': {
                $this->processString($context, $model);

                return $context;
            }

            case 'object': {
                return $this->processObject($context, $model);
            }

            case 'array': {
                return $this->processArray($context, $model);
            }

            default: {
                throw new ViewModelRendererException(
                    sprintf('Unsupported type %s', \gettype($model))
                );
            }
        }
    }

    /**
     * @return DOMDocumentFragment|DOMElement
     *
     * @throws ViewModelRendererException
     */
    private function processBoolean(DOMElement $context, bool $model) {
        if ($model === true) {
            return $context;
        }

        if ($context->isSameNode($context->ownerDocument->documentElement)) {
            throw new ViewModelRendererException('Cannot remove root element');
        }

        $this->removeNodeFromCurrentSnapshotList($context);
        $context->parentNode->removeChild($context);

        return $context->ownerDocument->createDocumentFragment();
    }

    private function processString(DOMElement $context, string $model) {
        $context->nodeValue = $model;
    }

    /**
     * @throws ViewModelRendererException
     */
    private function processObject(DOMElement $context, $model) {
        if ($model instanceOf \Iterator) {
            return $this->processArray($context, $model);
        }

        return $this->processObjectAsModel($context, $model);
    }

    /**
     * @throws ViewModelRendererException
     */
    private function processObjectAsModel(DOMElement $context, $model): DOMElement {
        $container = $this->moveToContainer($context);
        $workContext = $this->selectMatchingWorkContext($container->firstChild, $model);

        if (method_exists($model, 'asString') ||
            method_exists($model, '__call')
        ) {
            $value = $model->asString($workContext->nodeValue);
            if ($value !== null) {
                $workContext->nodeValue = $value;
            }
        }

        foreach($workContext->attributes as $attribute) {
            $this->processAttribute($attribute, $model);
        }

        $container->parentNode->insertBefore($workContext, $container);
        $container->parentNode->removeChild($container);

        return $workContext;
    }

    /**
     * @throws ViewModelRendererException
     */
    private function processArray(DOMElement $context, $model): DOMDocumentFragment {
        $count = $this->getElementCount($model);
        if ($count > 1 && $context->isSameNode($context->ownerDocument->documentElement)) {
            throw new ViewModelRendererException(
                'Cannot render multiple copies of root element'
            );
        }
        if ($count === 0) {
            return $this->processBoolean($context, false);
        }

        $container = $this->moveToContainer($context);

        foreach($model as $pos => $entry) {

            $subcontext = $container->cloneNode(true);
            $container->parentNode->insertBefore($subcontext, $container);

            $result = $this->processArrayEntry($subcontext->firstChild, $entry, $pos);

            $container->parentNode->insertBefore($result, $subcontext);
            $container->parentNode->removeChild($subcontext);

        }

        $fragment = $container->ownerDocument->createDocumentFragment();
        $container->parentNode->removeChild($container);

        return $fragment;
    }

    /**
     * @throws ViewModelRendererException
     */
    private function processArrayEntry(DOMElement $context, $entry, int $pos): DOMElement {
        $workContext = $this->selectMatchingWorkContext($context, $entry);
        /** @var DOMElement $clone */
        $this->stack[] = $entry;
        $this->stackNames[] = (string)$pos;

        $this->applyCurrent($workContext);

        if ($workContext->hasChildNodes()) {

            $list = new SnapshotDOMNodelist($workContext->childNodes);
            $this->listStack[] = $list;
            foreach($list as $cpos => $childNode) {
                /** @var \DOMNode $childNode */
                $this->walk($childNode);
            }
            array_pop($this->listStack);

        }
        $this->dropFromStack();

        return $workContext;
    }

    /**
     * @throws ViewModelRendererException
     */
    private function processAttribute(DOMAttr $attribute, $model) {
        $attributeName = $attribute->nodeName;
        if (strpos($attributeName,'-') !== false) {
            $parts = explode('-', $attributeName);
            array_walk(
                $parts,
                function (&$value, $pos) { $value = ucfirst($value); }
            );
            $attributeName = implode('', $parts);
        }
        foreach([$attributeName, 'get' . ucfirst($attributeName), '__call'] as $method) {

            if (!method_exists($model, $method)) {
                continue;
            }

            if ($method === '__call') {
                $method = $attribute->name;
            }

            $value = $model->{$method}($attribute->value);
            if ($value === null) {
                return;
            }

            if ($value === false) {
                /** @var DOMElement $parent */
                $parent = $attribute->parentNode;
                $parent->removeAttribute($attribute->name);

                return;
            }

            if (!\is_string($value)) {
                throw new ViewModelRendererException(
                    sprintf('Attribute value must be string or boolean false - type %s received from $model->%s',
                        \gettype($value),
                        implode('()->', $this->stackNames) . '()'
                    )
                );
            }

            $attribute->value = $value;

            return;
        }
    }

    /**
     * @throws ViewModelRendererException
     */
    private function ensureIsObject($model, string $property) {
        if (!\is_object($model)) {
            throw new ViewModelRendererException(
                sprintf(
                    'Trying to add "%s" failed - Non object (%s) on stack: $%s',
                    $property,
                    \gettype($model),
                    implode('()->', $this->stackNames) . '() '
                )
            );
        }
    }

    /**
     * @throws ViewModelRendererException
     */
    private function selectMatchingWorkContext(DOMElement $context, $entry): DOMElement {
        if (!$context->hasAttribute('typeof')) {
            return $context;
        }

        if (!method_exists($entry, 'typeOf')) {
            throw new ViewModelRendererException(
                'No typeOf method in model but current context is conditional'
            );
        }

        $requestedTypeOf = $entry->typeOf();

        if ($context->getAttribute('typeof') === $requestedTypeOf) {
            return $context;
        }

        $xp = new \DOMXPath($context->ownerDocument);
        $list = $xp->query(
            sprintf(
                '(following-sibling::*)[@property="%s" and @typeof="%s"]',
                $context->getAttribute('property'),
                $requestedTypeOf
            ),
            $context
        );

        $newContext = $list->item(0);

        if (!$newContext instanceof DOMElement) {

            throw new ViewModelRendererException(
                sprintf(
                    "Context for type '%s' not found.",
                    $requestedTypeOf
                )
            );
        }

        return $newContext;
    }

    private function moveToContainer(DOMElement $context): DOMElement {
        $container = $context->ownerDocument->createElement('container');
        $context->parentNode->insertBefore($container, $context);

        $xp = new \DOMXPath($container->ownerDocument);
        $list = $xp->query(
            sprintf('*[@property="%s"]', $context->getAttribute('property')),
            $context->parentNode
        );

        foreach($list as $node) {
            $container->appendChild($node);
            $this->removeNodeFromCurrentSnapshotList($node);
            }
        return $container;
        }

    private function removeNodeFromCurrentSnapshotList(DOMElement $context) {
        $stackList = end($this->listStack);
        if ((!$stackList instanceof SnapshotDOMNodelist) || !$stackList->hasNode($context)) {
            return;
        }
        $stackList->removeNode($context);
    }

    private function getElementCount($model): int {
        if (is_array($model) || $model instanceof \Countable) {
            return \count($model);
        }

        throw new ViewModelRendererException(
            sprintf(
                'Class %s must implement \Countable to be used as array',
                \get_class($model)
            )
        );
    }

}
