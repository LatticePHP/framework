<?php

declare(strict_types=1);

namespace Lattice\Validation\Illuminate;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;

final class IlluminateValidator
{
    private ValidatorFactory $factory;

    public function __construct(?string $langPath = null)
    {
        $loader = new FileLoader(new Filesystem(), $langPath ?? __DIR__ . '/lang');
        $translator = new Translator($loader, 'en');
        $this->factory = new ValidatorFactory($translator);
    }

    public function validate(array $data, array $rules, array $messages = []): array
    {
        $validator = $this->factory->make($data, $rules, $messages);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
    }

    public function getFactory(): ValidatorFactory
    {
        return $this->factory;
    }
}
