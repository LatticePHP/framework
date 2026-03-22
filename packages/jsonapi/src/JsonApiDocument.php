<?php

declare(strict_types=1);

namespace Lattice\JsonApi;

final class JsonApiDocument
{
    /** @var array|null Single resource data array */
    private ?array $data = null;

    /** @var bool Whether data is a collection */
    private bool $isCollection = false;

    /** @var JsonApiResource[] */
    private array $included = [];

    private array $meta = [];

    private array $links = [];

    /** @var array[] */
    private array $errors = [];

    private function __construct() {}

    public static function fromResource(JsonApiResource $resource): self
    {
        $doc = new self();
        $doc->data = $resource->toArray();
        $doc->isCollection = false;

        return $doc;
    }

    /**
     * @param JsonApiResource[] $resources
     */
    public static function fromCollection(array $resources): self
    {
        $doc = new self();
        $doc->data = array_map(fn(JsonApiResource $r) => $r->toArray(), $resources);
        $doc->isCollection = true;

        return $doc;
    }

    /**
     * @param array[] $errors
     */
    public static function fromErrors(array $errors): self
    {
        $doc = new self();
        $doc->errors = $errors;

        return $doc;
    }

    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    public function setLinks(array $links): void
    {
        $this->links = $links;
    }

    public function addIncluded(JsonApiResource $resource): void
    {
        $this->included[] = $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->errors !== []) {
            $result['errors'] = $this->errors;
        } elseif ($this->data !== null || $this->isCollection) {
            $result['data'] = $this->data;
        }

        if ($this->included !== []) {
            $result['included'] = array_map(fn(JsonApiResource $r) => $r->toArray(), $this->included);
        }

        if ($this->meta !== []) {
            $result['meta'] = $this->meta;
        }

        if ($this->links !== []) {
            $result['links'] = $this->links;
        }

        return $result;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
