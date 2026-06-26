<?php

namespace App\Service;

class VillageCatalog
{
    private string $projectDir;
    /** @var array<string, string>|null */
    private ?array $villages = null;
    /** @var array<string, list<string>>|null */
    private ?array $aliases = null;

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
     * @return list<string>
     */
    public function aliasesFor(string $name): array
    {
        $this->villages();
        $canonical = $this->canonicalName($name);
        $aliases = $this->aliases[$this->normalize($canonical)] ?? [];
        $aliases[] = trim($name);
        $aliases[] = $canonical;

        return array_values(array_unique(array_filter($aliases, static fn (string $alias): bool => $alias !== '')));
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
        $aliases = [];

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

            $canonical = $label ?: $name;
            $canonicalKey = $this->normalize($canonical);
            $aliases[$canonicalKey] ??= [];
            $aliases[$canonicalKey][] = $name;
            $aliases[$canonicalKey][] = $label;
        }

        $this->aliases = array_map(
            static fn (array $items): array => array_values(array_unique(array_filter($items, static fn (string $item): bool => $item !== ''))),
            $aliases
        );

        return $this->villages = $villages;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
