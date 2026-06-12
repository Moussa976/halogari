<?php

namespace App\Service;

class VillageCatalog
{
    private string $projectDir;
    /** @var array<string, string>|null */
    private ?array $villages = null;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function isValid(string $name): bool
    {
        return isset($this->villages()[$this->normalize($name)]);
    }

    public function canonicalName(string $name): string
    {
        return $this->villages()[$this->normalize($name)] ?? trim($name);
    }

    /**
     * @return array<string, string>
     */
    private function villages(): array
    {
        if ($this->villages !== null) {
            return $this->villages;
        }

        $path = $this->projectDir . '/public/cities.json';
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $villages = [];

        foreach (is_array($decoded) ? $decoded : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $label = trim((string) ($item['name_2'] ?? $name));
            if ($name === '') {
                continue;
            }

            $villages[$this->normalize($name)] = $label ?: $name;
            if ($label !== '') {
                $villages[$this->normalize($label)] = $label;
            }
        }

        return $this->villages = $villages;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
