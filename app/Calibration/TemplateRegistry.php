<?php

namespace App\Calibration;

use InvalidArgumentException;

class TemplateRegistry
{
    /**
     * @var array<string, class-string<WorksheetTemplate>>
     */
    protected array $templates = [
        'autoclave' => AutoclaveTemplate::class,
    ];

    public function register(string $key, string $templateClass): void
    {
        $this->templates[$key] = $templateClass;
    }

    public function resolve(string $key): WorksheetTemplate
    {
        $class = $this->templates[$key] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No calibration template registered for [{$key}].");
        }

        return app($class);
    }

    public function has(string $key): bool
    {
        return isset($this->templates[$key]);
    }

    /**
     * @return array<string, string> key => label
     */
    public function options(): array
    {
        $options = [];

        foreach (array_keys($this->templates) as $key) {
            $options[$key] = $this->resolve($key)->label();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->templates);
    }
}
