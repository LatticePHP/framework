<?php

declare(strict_types=1);

namespace Lattice\DevTools;

final class GeneratorManager
{
    /** @var array<string, GeneratorInterface> */
    private array $generators = [];

    public function register(string $name, GeneratorInterface $generator): void
    {
        $this->generators[$name] = $generator;
    }

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(string $name, array $options): array
    {
        if (!isset($this->generators[$name])) {
            throw new \InvalidArgumentException(
                sprintf('Generator "%s" is not registered. Available: %s', $name, implode(', ', array_keys($this->generators)))
            );
        }

        return $this->generators[$name]->generate($options);
    }

    /**
     * @return array<string, string> name => description
     */
    public function list(): array
    {
        $list = [];
        foreach ($this->generators as $name => $generator) {
            $list[$name] = $generator->getDescription();
        }
        return $list;
    }
}
